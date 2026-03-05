<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ChatMessageSendRequest;
use App\Http\Requests\Api\ChatRequestCloseRequest;
use App\Http\Requests\Api\ChatRequestRespondRequest;
use App\Http\Requests\Api\ChatRequestSendRequest;
use App\Http\Resources\ChatMessageResource;
use App\Http\Resources\ChatRequestActionResource;
use App\Services\Chat\HandleChatRequestLifecycleService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

/**
 * Handles chat request lifecycle endpoints and realtime notifications.
 */
class ChatRequestController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @param  HandleChatRequestLifecycleService  $chatRequestLifecycleService  Service that encapsulates chat request business logic.
     * @return void
     */
    public function __construct(private readonly HandleChatRequestLifecycleService $chatRequestLifecycleService) {}

    /**
     * Send a chat request from the authenticated user to another user.
     *
     * Logic:
     * 1) Validate target user ID.
     * 2) Resolve authenticated user and reject unauthenticated requests.
     * 3) Prevent self-targeted requests.
     * 4) Broadcast a `requested` message to the target user's private channel.
     * 5) Return standardized success response.
     *
     * @param  ChatRequestSendRequest  $request
     * @return JsonResponse
     */
    public function send(ChatRequestSendRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $fromUser = $request->user();

        if (is_null($fromUser)) {
            return ApiResponse::error('Unauthenticated.', 401);
        }

        try {
            $toUserId = (int) $validated['to_user_id'];

            $this->chatRequestLifecycleService->send($fromUser, $toUserId);
        } catch (InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422);
        }

        return ApiResponse::success('Chat request sent.', [
            'chat_request' => ChatRequestActionResource::make([
                'action' => 'requested',
                'to_user_id' => $toUserId,
            ])->resolve($request),
        ]);
    }

    /**
     * Respond to an incoming chat request with accept/decline action.
     *
     * Logic:
     * 1) Validate requester user ID and response action.
     * 2) Resolve authenticated responder and reject unauthenticated requests.
     * 3) Map action to broadcast type (`accepted` or `declined`).
     * 4) Broadcast response message to original requester channel.
     * 5) Return standardized success response.
     *
     * @param  ChatRequestRespondRequest  $request
     * @return JsonResponse
     */
    public function respond(ChatRequestRespondRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $fromUser = $request->user();

        if (is_null($fromUser)) {
            return ApiResponse::error('Unauthenticated.', 401);
        }

        $requesterUserId = (int) $validated['requester_user_id'];
        $action = (string) $validated['action'];

        $this->chatRequestLifecycleService->respond(
            $fromUser,
            $requesterUserId,
            $action,
        );

        return ApiResponse::success('Chat request response sent.', [
            'chat_request' => ChatRequestActionResource::make([
                'action' => $action,
                'requester_user_id' => $requesterUserId,
            ])->resolve($request),
        ]);
    }

    /**
     * Notify another user that an active chat has been closed.
     *
     * Logic:
     * 1) Validate target user ID.
     * 2) Resolve authenticated user and reject unauthenticated requests.
     * 3) Prevent self-targeted close messages.
     * 4) Broadcast a `closed` message to the target user's private channel.
     * 5) Return standardized success response.
     *
     * @param  ChatRequestCloseRequest  $request
     * @return JsonResponse
     */
    public function close(ChatRequestCloseRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $fromUser = $request->user();

        if (is_null($fromUser)) {
            return ApiResponse::error('Unauthenticated.', 401);
        }

        try {
            $toUserId = (int) $validated['to_user_id'];

            $this->chatRequestLifecycleService->close($fromUser, $toUserId);
        } catch (InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422);
        }

        return ApiResponse::success('Chat closed message sent.', [
            'chat_request' => ChatRequestActionResource::make([
                'action' => 'closed',
                'to_user_id' => $toUserId,
            ])->resolve($request),
        ]);
    }

    /**
     * Send a direct chat message to the selected user.
     *
     * Logic:
     * 1) Validate target user and message payload via FormRequest.
     * 2) Resolve authenticated sender and reject unauthenticated requests.
     * 3) Delegate online validation and broadcast dispatch to service layer.
     * 4) Return standardized API success envelope using a resource payload.
     *
     * @param  ChatMessageSendRequest  $request
     * @return JsonResponse
     */
    public function sendMessage(ChatMessageSendRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $fromUser = $request->user();

        if (is_null($fromUser)) {
            return ApiResponse::error('Unauthenticated.', 401);
        }

        try {
            $toUserId = (int) $validated['to_user_id'];
            $message = trim((string) $validated['message']);

            $this->chatRequestLifecycleService->sendMessage($fromUser, $toUserId, $message);
        } catch (InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 422);
        }

        return ApiResponse::success('Message sent.', [
            'chat_message' => ChatMessageResource::make([
                'to_user_id' => $toUserId,
                'message' => $message,
            ])->resolve($request),
        ]);
    }
}
