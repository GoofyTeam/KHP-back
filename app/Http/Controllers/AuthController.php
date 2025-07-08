<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

class AuthController extends Controller
{
    public function authenticate(Request $request): JsonResponse
    {
        if (Auth::check()) {
            return new JsonResponse([
                'message' => 'Already authenticated',
                'user' => Auth::user(),
                'remember' => false,
            ], 200);
        }

        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
            'remember' => ['sometimes', 'boolean'],
        ]);

        $remember = $credentials['remember'] ?? false;

        if (Auth::attempt(credentials: $credentials, remember: $remember)) {

            $request->session()->regenerate();

            return new JsonResponse([
                'message' => 'Authentication successful',
                'user' => Auth::user(),
                'remember' => $remember,
            ], 200);
        }

        return new JsonResponse([
            'message' => 'Authentication failed',
        ], 401);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return new JsonResponse([
            'message' => 'Logout successful',
        ], 200);
    }

    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        Auth::login($user);

        return new JsonResponse([
            'message' => 'Registration successful',
            'user' => $user,
        ], 201);
    }

    public function send_password_reset_email(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $status = Password::sendResetLink(
            $request->only('email')
        );

        return $status === Password::RESET_LINK_SENT
            ? back()->with(['status' => __($status)])
            : back()->withErrors(['email' => __($status)]);
    }
}
