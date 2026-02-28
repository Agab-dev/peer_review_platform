<?php

namespace App\Http\Controllers;

use App\Models\ConflictOfInterestDeclaration;
use App\Models\EditorAssignment;
use App\Models\ResearchSubmission;
use App\Models\ReviewerAssignment;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Facades\JWTAuth;

class EditorialController extends Controller
{
    // ── POST /api/v1/research/{research}/editors ────────────────────
    public function assignEditor(Request $request, ResearchSubmission $research): JsonResponse
    {
        $data = $request->validate([
            'editor_id'  => 'required|integer|exists:users,user_id',
            'is_primary' => 'required|boolean',
        ]);

        $editor = User::findOrFail($data['editor_id']);

        if ($editor->role !== 'editor') {
            return response()->json([
                'message' => 'Validation failed.',
                'errors'  => ['editor_id' => ['The specified user is not a handling editor.']],
            ], 422);
        }

        DB::transaction(function () use ($research, $data) {
            // If assigning a primary editor, soft-delete the existing primary first
            if ($data['is_primary']) {
                EditorAssignment::where('research_id', $research->research_id)
                    ->whereNull('deleted_at')
                    ->where('is_primary', true)
                    ->update(['deleted_at' => now()]);
            }

            EditorAssignment::create([
                'research_id' => $research->research_id,
                'editor_id'   => $data['editor_id'],
                'is_primary'  => $data['is_primary'],
                'assigned_at' => now(),
            ]);
        });

        $assignment = EditorAssignment::where('research_id', $research->research_id)
            ->where('editor_id', $data['editor_id'])
            ->whereNull('deleted_at')
            ->latest('assigned_at')
            ->first();

        return response()->json([
            'message' => 'Handling editor assigned successfully.',
            'data'    => [
                'assignment_id' => $assignment->assignment_id,
                'research_id'   => $research->research_id,
                'editor_id'     => $assignment->editor_id,
                'is_primary'    => $assignment->is_primary,
                'assigned_at'   => $assignment->assigned_at,
            ],
        ], 201);
    }

    // ── DELETE /api/v1/research/{research}/editors/{assignment} ─────
    public function removeEditor(ResearchSubmission $research, EditorAssignment $assignment): JsonResponse
    {
        if ($assignment->research_id !== $research->research_id) {
            return response()->json(['message' => 'Assignment not found for this research.'], 404);
        }

        if ($assignment->deleted_at !== null) {
            return response()->json(['message' => 'Assignment already removed.'], 422);
        }

        $assignment->deleted_at = now();
        $assignment->save();

        return response()->json(['message' => 'Editor assignment removed.']);
    }

    // ── PATCH /api/v1/research/{research}/anonymization ─────────────
    public function setAnonymization(Request $request, ResearchSubmission $research): JsonResponse
    {
        $this->requirePrimaryEditor($research);

        $data = $request->validate([
            'anonymization_model' => 'required|in:single,double,open',
        ]);

        $research->anonymization_model = $data['anonymization_model'];
        $research->save();

        return response()->json([
            'message' => 'Anonymization model updated.',
            'data'    => [
                'research_id'         => $research->research_id,
                'anonymization_model' => $research->anonymization_model,
            ],
        ]);
    }

    // ── PATCH /api/v1/research/{research}/deadline ──────────────────
    public function setDeadline(Request $request, ResearchSubmission $research): JsonResponse
    {
        $this->requirePrimaryEditor($research);

        $data = $request->validate([
            'deadline' => 'required|date|after:today',
        ]);

        $research->deadline = $data['deadline'];
        $research->save();

        return response()->json([
            'message' => 'Deadline updated.',
            'data'    => [
                'research_id' => $research->research_id,
                'deadline'    => $research->deadline,
            ],
        ]);
    }

    // ── POST /api/v1/research/{research}/reviewers ──────────────────
    public function assignReviewer(Request $request, ResearchSubmission $research): JsonResponse
    {
        $this->requirePrimaryEditor($research);

        $data = $request->validate([
            'reviewer_id' => 'required|integer|exists:users,user_id',
        ]);

        $reviewer = User::findOrFail($data['reviewer_id']);

        if ($reviewer->role !== 'reviewer') {
            return response()->json([
                'message' => 'Validation failed.',
                'errors'  => ['reviewer_id' => ['The specified user is not a reviewer.']],
            ], 422);
        }

        // Check for active duplicate assignment
        $existing = ReviewerAssignment::where('research_id', $research->research_id)
            ->where('reviewer_id', $data['reviewer_id'])
            ->whereNull('deleted_at')
            ->exists();

        if ($existing) {
            return response()->json(['message' => 'This reviewer is already assigned to the submission.'], 409);
        }

        $assignment = ReviewerAssignment::create([
            'research_id' => $research->research_id,
            'reviewer_id' => $data['reviewer_id'],
            'assigned_at' => now(),
        ]);

        // Count active reviewers after this assignment
        $totalActive = ReviewerAssignment::where('research_id', $research->research_id)
            ->whereNull('deleted_at')
            ->count();

        $phaseStarted = false;

        // Auto-start Independent Phase when 2nd reviewer is assigned (REQ-041)
        if ($totalActive >= 2 && $research->review_phase === null) {
            $research->review_phase = 'independent';
            $research->save();
            $phaseStarted = true;
        }

        return response()->json([
            'message' => 'Reviewer assigned successfully.',
            'data'    => [
                'assignment_id' => $assignment->assignment_id,
                'research_id'   => $research->research_id,
                'reviewer_id'   => $assignment->reviewer_id,
                'assigned_at'   => $assignment->assigned_at,
            ],
            'meta' => [
                'total_active_reviewers' => $totalActive,
                'phase_started'          => $phaseStarted,
            ],
        ], 201);
    }

    // ── DELETE /api/v1/research/{research}/reviewers/{assignment} ───
    public function revokeReviewer(ResearchSubmission $research, ReviewerAssignment $assignment): JsonResponse
    {
        $this->requirePrimaryEditor($research);

        if ($assignment->research_id !== $research->research_id) {
            return response()->json(['message' => 'Assignment not found for this research.'], 404);
        }

        if ($assignment->deleted_at !== null) {
            return response()->json(['message' => 'Assignment already revoked.'], 422);
        }

        $assignment->deleted_at = now();
        $assignment->save();

        $totalActive = ReviewerAssignment::where('research_id', $research->research_id)
            ->whereNull('deleted_at')
            ->count();

        return response()->json([
            'message' => 'Reviewer assignment revoked.',
            'meta'    => [
                'total_active_reviewers' => $totalActive,
                'minimum_met'            => $totalActive >= 2,
            ],
        ]);
    }

    // ── POST /api/v1/research/{research}/conflicts ──────────────────
    public function declareConflict(Request $request, ResearchSubmission $research): JsonResponse
    {
        $user = JWTAuth::user();

        $data = $request->validate([
            'description' => 'required|string',
        ]);

        $declaration = ConflictOfInterestDeclaration::create([
            'research_id' => $research->research_id,
            'declared_by' => $user->user_id,
            'description' => $data['description'],
            'declared_at' => now(),
        ]);

        return response()->json([
            'message' => 'Conflict of interest recorded.',
            'data'    => [
                'coi_id'      => $declaration->coi_id,
                'research_id' => $research->research_id,
                'declared_by' => $user->user_id,
                'description' => $declaration->description,
                'declared_at' => $declaration->declared_at,
            ],
        ], 201);
    }

    // ── Private helper ───────────────────────────────────────────────
    private function requirePrimaryEditor(ResearchSubmission $research): void
    {
        $user              = JWTAuth::user();
        $primaryAssignment = $research->primaryEditorAssignment()->first();

        if (!$primaryAssignment || $primaryAssignment->editor_id !== $user->user_id) {
            abort(403, 'Forbidden. Only the primary handling editor may perform this action.');
        }
    }
}
