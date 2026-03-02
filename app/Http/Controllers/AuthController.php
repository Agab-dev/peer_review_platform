<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    // ── POST /api/v1/auth/register ──────────────────────────────────
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'full_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'institution' => 'required|string|max:255',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::create([
            'full_name' => $data['full_name'],
            'email' => $data['email'],
            'institution' => $data['institution'],
            'password' => Hash::make($data['password']),
            'role' => 'author',
        ]);

        $token = JWTAuth::fromUser($user);

        return response()->json([
            'message' => 'Registration successful.',
            'data' => [
                'token' => $token,
                'token_type' => 'Bearer',
                'user' => [
                    'user_id' => $user->user_id,
                    'full_name' => $user->full_name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'institution' => $user->institution,
                    'created_at' => $user->created_at,
                ],
            ],
        ], 201);
    }

    // ── POST /api/v1/auth/login ─────────────────────────────────────
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if (! $token = JWTAuth::attempt($credentials)) {
            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        $user = JWTAuth::user();

        return response()->json([
            'message' => 'Login successful.',
            'data' => [
                'token' => $token,
                'token_type' => 'Bearer',
                'must_change_password' => $user->must_change_password,
                'user' => [
                    'user_id' => $user->user_id,
                    'full_name' => $user->full_name,
                    'role' => $user->role,
                    'email' => $user->email,
                ],
            ],
        ]);
    }

    // ── POST /api/v1/auth/logout ────────────────────────────────────
    public function logout(): JsonResponse
    {
        JWTAuth::invalidate(JWTAuth::getToken());

        return response()->json(['message' => 'Logged out successfully.']);
    }

    // ── POST /api/v1/auth/change-password ──────────────────────────
    public function changePassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        $user = JWTAuth::user();

        if (! Hash::check($data['current_password'], $user->password)) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => ['current_password' => ['Current password is incorrect.']],
            ], 422);
        }

        $user->password = Hash::make($data['new_password']);
        $user->must_change_password = false;
        $user->save();

        return response()->json(['message' => 'Password changed successfully.']);
    }
}
