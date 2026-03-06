<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ChatConversationIndexRequest;
use App\Http\Requests\Api\ChatConversationMarkReadRequest;
use App\Http\Resources\ChatMessageResource;
use App\Http\Resources\ChatUnreadCountResource;
use App\Models\User;
use App\Services\Auth\ResolveAuthenticatedUserService;
use App\Services\Chat\ManageChatMessagesService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Exposes API endpoints for persisted direct chat messages.
 */
class ChatMessageController extends Controller
{
    /**
     * Create a new controller instance.
     *
        * Logic:
        * 1) Inject message management service for persistence and query operations.
        * 2) Inject authenticated-user resolver for API request user context.
        *
     * @param  ManageChatMessagesService  $manageChatMessagesService
     * @param  ResolveAuthenticatedUserService  $resolveAuthenticatedUserService
     * @return void
     */
    public function __construct(
        private readonly ManageChatMessagesService $manageChatMessagesService,
        private readonly ResolveAuthenticatedUserService $resolveAuthenticatedUserService,
    ) {}

    /**
     * Return persisted conversation history for authenticated user and target user.
     *
        * Logic:
        * 1) Resolve authenticated user from the request context.
        * 2) Read validated query constraints such as `limit`.
        * 3) Delegate conversation retrieval to the service layer.
        * 4) Transform results using API resource collection.
        *
     * @param  ChatConversationIndexRequest  $request
     * @param  User  $user
     * @return JsonResponse
     */
    public function conversation(ChatConversationIndexRequest $request, User $user): JsonResponse
    {
        $authenticatedUser = $this->resolveAuthenticatedUserService->handle($request);
        $limit = (int) ($request->validated()['limit'] ?? 200);

        $messages = $this->manageChatMessagesService->conversation($authenticatedUser, $user, $limit);

        return ApiResponse::success('Conversation retrieved successfully.', [
            'messages' => ChatMessageResource::collection($messages)->resolve($request),
        ]);
    }

    /**
     * Return unread incoming message counts grouped by sender user.
     *
        * Logic:
        * 1) Resolve authenticated user from request context.
        * 2) Query grouped unread counters via service layer.
        * 3) Transform and return standardized API success envelope.
        *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function unreadCounts(Request $request): JsonResponse
    {
        $authenticatedUser = $this->resolveAuthenticatedUserService->handle($request);

        $counts = $this->manageChatMessagesService->unreadCounts($authenticatedUser);

        return ApiResponse::success('Unread message counts retrieved successfully.', [
            'counts' => ChatUnreadCountResource::collection($counts)->resolve($request),
        ]);
    }

    /**
     * Mark unread incoming messages from a specific user as read.
     *
        * Logic:
        * 1) Resolve authenticated user from request context.
        * 2) Mark unread rows for target conversation as read.
        * 3) Return updated-row count in API success envelope.
        *
     * @param  ChatConversationMarkReadRequest  $request
     * @param  User  $user
     * @return JsonResponse
     */
    public function markRead(ChatConversationMarkReadRequest $request, User $user): JsonResponse
    {
        $authenticatedUser = $this->resolveAuthenticatedUserService->handle($request);

        $updatedCount = $this->manageChatMessagesService->markConversationAsRead($authenticatedUser, $user);

        return ApiResponse::success('Conversation marked as read.', [
            'updated_count' => $updatedCount,
        ]);
    }
}
