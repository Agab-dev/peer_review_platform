<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserController extends Controller
{
    // ── POST /api/v1/reviewers ──────────────────────────────────────
    public function storeReviewer(Request $request): JsonResponse
    {
        $data = $request->validate([
            'full_name'      => 'required|string|max:255',
            'email'          => 'required|email|unique:users,email',
            'institution'    => 'required|string|max:255',
            'expertise_areas' => 'required|string',
        ]);

        $temporaryPassword = Str::password(12, true, true, false);

        $user = User::create([
            'full_name'           => $data['full_name'],
            'email'               => $data['email'],
            'institution'         => $data['institution'],
            'expertise_areas'     => $data['expertise_areas'],
            'password'            => Hash::make($temporaryPassword),
            'role'                => 'reviewer',
            'must_change_password' => true,
        ]);

        return response()->json([
            'message' => 'Reviewer account created.',
            'data'    => [
                'user_id'           => $user->user_id,
                'full_name'         => $user->full_name,
                'email'             => $user->email,
                'role'              => $user->role,
                'institution'       => $user->institution,
                'expertise_areas'   => $user->expertise_areas,
                'temporary_password' => $temporaryPassword,
            ],
        ], 201);
    }

    // ── POST /api/v1/editors ────────────────────────────────────────
    public function storeEditor(Request $request): JsonResponse
    {
        $data = $request->validate([
            'full_name'       => 'required|string|max:255',
            'email'           => 'required|email|unique:users,email',
            'institution'     => 'required|string|max:255',
            'expertise_areas' => 'required|string',
        ]);

        $temporaryPassword = Str::password(12, true, true, false);

        $user = User::create([
            'full_name'            => $data['full_name'],
            'email'                => $data['email'],
            'institution'          => $data['institution'],
            'expertise_areas'      => $data['expertise_areas'],
            'password'             => Hash::make($temporaryPassword),
            'role'                 => 'editor',
            'must_change_password' => true,
        ]);

        return response()->json([
            'message' => 'Handling editor account created.',
            'data'    => [
                'user_id'            => $user->user_id,
                'full_name'          => $user->full_name,
                'email'              => $user->email,
                'role'               => $user->role,
                'institution'        => $user->institution,
                'expertise_areas'    => $user->expertise_areas,
                'temporary_password' => $temporaryPassword,
            ],
        ], 201);
    }

    // ── GET /api/v1/reviewers ───────────────────────────────────────
    public function listReviewers(Request $request): JsonResponse
    {
        $query = User::where('role', 'reviewer')
            ->whereNull('deleted_at');

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'ilike', "%{$search}%")
                  ->orWhere('institution', 'ilike', "%{$search}%")
                  ->orWhere('expertise_areas', 'ilike', "%{$search}%");
            });
        }

        $reviewers = $query->select('user_id', 'full_name', 'institution', 'expertise_areas')
            ->paginate(15);

        return response()->json($reviewers);
    }

    // ── GET /api/v1/editors ─────────────────────────────────────────
    public function listEditors(Request $request): JsonResponse
    {
        $query = User::where('role', 'editor')
            ->whereNull('deleted_at');

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'ilike', "%{$search}%")
                  ->orWhere('institution', 'ilike', "%{$search}%")
                  ->orWhere('expertise_areas', 'ilike', "%{$search}%");
            });
        }

        $editors = $query->select('user_id', 'full_name', 'institution', 'expertise_areas')
            ->paginate(15);

        return response()->json($editors);
    }
}
