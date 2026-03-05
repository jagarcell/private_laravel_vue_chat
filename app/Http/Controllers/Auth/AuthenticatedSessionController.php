<?php

namespace App\Http\Controllers\Auth;

use App\Events\UserOnlineStatusChanged;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Support\OnlineUsersStore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Handles authentication session lifecycle and realtime presence updates.
 *
 * This controller authenticates users, maintains the online-user tracking store,
 * and broadcasts online/offline status changes used by the chat UI.
 */
class AuthenticatedSessionController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @param  OnlineUsersStore  $onlineUsersStore  Tracks users currently considered online.
        * @return void
     */
    public function __construct(private readonly OnlineUsersStore $onlineUsersStore) {}

    /**
     * Render the login page.
     *
     * The response includes frontend flags for password reset availability and
     * any status message from prior auth-related actions.
     *
     * @param  Request  $request
     * @return Response
     */
    public function create(Request $request): Response
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
        ]);
    }

    /**
     * Authenticate a user and initialize authenticated session state.
     *
     * Flow:
     * 1) Validate and authenticate credentials via LoginRequest.
     * 2) If authentication succeeds, mark the user as online in the presence store.
     * 3) Broadcast an online presence event for realtime UI updates.
     * 4) Regenerate session ID to prevent session fixation.
     * 5) Redirect to the intended URL, defaulting to chat home.
     *
     * @param  LoginRequest  $request
     * @return RedirectResponse
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $user = $request->user();

        if (! is_null($user)) {
            $this->onlineUsersStore->markOnline($user->id);
            event(new UserOnlineStatusChanged($user->id, true));
        }

        $request->session()->regenerate();

        return redirect()->intended('/');
    }

    /**
     * Log out the current user and tear down session state.
     *
     * Flow:
     * 1) Capture current user before logout.
     * 2) Log out from the web guard.
     * 3) Mark user offline and broadcast offline presence event.
     * 4) Invalidate session and regenerate CSRF token.
     * 5) Redirect to home.
     *
     * @param  Request  $request
     * @return RedirectResponse
     */
    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();

        Auth::guard('web')->logout();

        if (! is_null($user)) {
            $this->onlineUsersStore->markOffline($user->id);
            event(new UserOnlineStatusChanged($user->id, false));
        }

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
