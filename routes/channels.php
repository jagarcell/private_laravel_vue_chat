<?php

use Illuminate\Support\Facades\Broadcast;

/**
 * Authorize private model channel subscriptions for the owning user only.
 *
 * Logic:
 * 1) Compare authenticated user ID with channel `{id}` parameter.
 * 2) Grant access only when both IDs match.
 *
 * @param  mixed  $user
 * @param  mixed  $id
 * @return bool
 */
Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

/**
 * Authorize global users status channel for any authenticated user.
 *
 * Logic:
 * 1) Ensure request has an authenticated user.
 * 2) Allow subscription when user is present.
 *
 * @param  mixed  $user
 * @return bool
 */
Broadcast::channel('users.status', function ($user) {
    return ! is_null($user);
});

/**
 * Authorize chat-request channel subscriptions for the intended recipient.
 *
 * Logic:
 * 1) Compare authenticated user ID with channel `{id}` parameter.
 * 2) Grant access only to the matching user.
 *
 * @param  mixed  $user
 * @param  mixed  $id
 * @return bool
 */
Broadcast::channel('users.chat-request.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

/**
 * Authorize private channel subscriptions for direct chat message events.
 *
 * Rules:
 * 1) Only the authenticated user can subscribe to their own user-specific channel.
 * 2) Deny all cross-user channel access.
 *
 * @param  User  $user
 * @param  int  $userId
 * @return bool
 */
Broadcast::channel('users.chat-message.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});
