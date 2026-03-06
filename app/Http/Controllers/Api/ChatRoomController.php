<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ChatRoomInviteRespondRequest;
use App\Http\Requests\Api\ChatRoomStoreRequest;
use App\Http\Resources\ChatRoomInviteResource;
use App\Http\Resources\ChatRoomResource;
use App\Services\Auth\ResolveAuthenticatedUserService;
use App\Services\Chat\ManageChatRoomsService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * Exposes API endpoints for chat-room listing, creation, and room-request lifecycles.
 */
class ChatRoomController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * Logic:
     * 1) Inject chat-room management service for list/create/request operations.
     * 2) Inject authenticated-user resolver for request user context.
     *
     * @param  ManageChatRoomsService  $manageChatRoomsService
     * @param  ResolveAuthenticatedUserService  $resolveAuthenticatedUserService
     * @return void
     */
    public function __construct(
        private readonly ManageChatRoomsService $manageChatRoomsService,
        private readonly ResolveAuthenticatedUserService $resolveAuthenticatedUserService,
    ) {}

    /**
     * Return chat rooms for the authenticated user.
     *
     * Logic:
     * 1) Resolve authenticated user from request context.
     * 2) Query rooms where that user is a participant.
     * 3) Transform room collection with API resource projection.
     * 4) Return standardized API success envelope.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $authenticatedUser = $this->resolveAuthenticatedUserService->handle($request);
        $rooms = $this->manageChatRoomsService->listForUser($authenticatedUser);

        return ApiResponse::success('Chat rooms retrieved successfully.', [
            'rooms' => ChatRoomResource::collection($rooms)->resolve($request),
        ]);
    }

    /**
     * Create a new chat room with selected participants.
     *
     * Logic:
     * 1) Resolve authenticated user from request context.
     * 2) Read validated payload (`name`, `user_ids`) from form request.
     * 3) Delegate room creation and participant assignment to service layer.
     * 4) Transform created room with API resource projection.
     * 5) Return standardized API success envelope with 201 status.
     *
     * @param  ChatRoomStoreRequest  $request
     * @return JsonResponse
     */
    public function store(ChatRoomStoreRequest $request): JsonResponse
    {
        $authenticatedUser = $this->resolveAuthenticatedUserService->handle($request);
        $validated = $request->validated();

        $chatRoom = $this->manageChatRoomsService->create(
            authenticatedUser: $authenticatedUser,
            name: (string) $validated['name'],
            participantUserIds: $validated['user_ids'] ?? [],
        );

        return ApiResponse::success('Chat room created successfully.', [
            'chat_room' => ChatRoomResource::make($chatRoom)->resolve($request),
        ], 201);
    }

    /**
     * Return pending room invites for the authenticated user.
     *
     * Logic:
     * 1) Resolve authenticated user from request context.
     * 2) Query pending invites addressed to that user.
     * 3) Transform invite collection with API resource projection.
     * 4) Return standardized API success envelope.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function invites(Request $request): JsonResponse
    {
        $authenticatedUser = $this->resolveAuthenticatedUserService->handle($request);
        $invites = $this->manageChatRoomsService->pendingInvitesForUser($authenticatedUser);

        return ApiResponse::success('Chat room invites retrieved successfully.', [
            'invites' => ChatRoomInviteResource::collection($invites)->resolve($request),
        ]);
    }

    /**
     * Accept or decline a pending room invite.
     *
     * Logic:
     * 1) Resolve authenticated user from request context.
     * 2) Read validated `action` (`accept` or `decline`) from request payload.
     * 3) Delegate invite response handling to service layer.
     * 4) Convert domain validation failures into 422 API error responses.
     * 5) Return standardized API success envelope with invite and optional room data.
     *
     * @param  ChatRoomInviteRespondRequest  $request
     * @param  int  $inviteId
     * @return JsonResponse
     */
    public function respondInvite(ChatRoomInviteRespondRequest $request, int $inviteId): JsonResponse
    {
        $authenticatedUser = $this->resolveAuthenticatedUserService->handle($request);

        try {
            $result = $this->manageChatRoomsService->respondToInvite(
                authenticatedUser: $authenticatedUser,
                inviteId: $inviteId,
                action: (string) $request->validated()['action'],
            );
        } catch (InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422);
        }

        return ApiResponse::success('Chat room invite response recorded.', [
            'invite' => ChatRoomInviteResource::make($result['invite'])->resolve($request),
            'chat_room' => is_null($result['chat_room'])
                ? null
                : ChatRoomResource::make($result['chat_room'])->resolve($request),
        ]);
    }

    /**
     * Close a chat room as creator.
     *
     * Logic:
     * 1) Resolve authenticated user from request context.
     * 2) Delegate close-room operation to service layer.
     * 3) Convert domain validation failures into 422 API error responses.
     * 4) Return standardized API success envelope.
     *
     * @param  Request  $request
     * @param  int  $chatRoomId
     * @return JsonResponse
     */
    public function close(Request $request, int $chatRoomId): JsonResponse
    {
        $authenticatedUser = $this->resolveAuthenticatedUserService->handle($request);

        try {
            $this->manageChatRoomsService->closeRoom($authenticatedUser, $chatRoomId);
        } catch (InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422);
        }

        return ApiResponse::success('Chat room closed successfully.');
    }

    /**
     * Leave a chat room as non-creator participant.
     *
     * Logic:
     * 1) Resolve authenticated user from request context.
     * 2) Delegate leave-room operation to service layer.
     * 3) Convert domain validation failures into 422 API error responses.
     * 4) Return standardized API success envelope.
     *
     * @param  Request  $request
     * @param  int  $chatRoomId
     * @return JsonResponse
     */
    public function leave(Request $request, int $chatRoomId): JsonResponse
    {
        $authenticatedUser = $this->resolveAuthenticatedUserService->handle($request);

        try {
            $this->manageChatRoomsService->leaveRoom($authenticatedUser, $chatRoomId);
        } catch (InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422);
        }

        return ApiResponse::success('You left the chat room successfully.');
    }
}
