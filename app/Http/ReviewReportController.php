<?php

namespace App\Http\Controllers;

use App\Models\ForumDiscussion;
use App\Models\ResearchSubmission;
use App\Models\ReviewReport;
use App\Services\AnonymizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;

class ReviewReportController extends Controller
{
    public function __construct(
        private readonly AnonymizationService $anonymization
    ) {}

    // ── POST /api/v1/research/{research}/reports ────────────────────
    public function store(Request $request, ResearchSubmission $research): JsonResponse
    {
        $user = JWTAuth::user();

        // Only during Independent Phase
        if (!$research->isIndependent()) {
            return response()->json([
                'message' => 'Unprocessable. Review reports can only be submitted during the Independent Review Phase.',
            ], 422);
        }

        $data = $request->validate([
            'summary'        => 'required|string',
            'major_issues'   => 'required|string',
            'minor_issues'   => 'nullable|string',
            'recommendation' => 'required|in:accept,revisions_required,reject',
        ]);

        $report = ReviewReport::create([
            'research_id'    => $research->research_id,
            'reviewer_id'    => $user->user_id,
            'summary'        => $data['summary'],
            'major_issues'   => $data['major_issues'],
            'minor_issues'   => $data['minor_issues'] ?? null,
            'recommendation' => $data['recommendation'],
            'submitted_at'   => now(),
        ]);

        // Auto-create Review Reports Forum thread (REQ-054)
        $discussion = ForumDiscussion::create([
            'research_id'          => $research->research_id,
            'discussion_type'      => 'review_report',
            'referenced_report_id' => $report->report_id,
            'title'                => 'Review Report — ' . $this->anonymization->resolveDisplayName(
                $user, $research, $user
            ),
            'created_at'           => now(),
            'created_by'           => $user->user_id,
        ]);

        return response()->json([
            'message' => 'Review report submitted.',
            'data'    => [
                'report_id'      => $report->report_id,
                'research_id'    => $research->research_id,
                'reviewer_id'    => $user->user_id,
                'recommendation' => $report->recommendation,
                'submitted_at'   => $report->submitted_at,
                'discussion'     => [
                    'discussion_id' => $discussion->discussion_id,
                    'title'         => $discussion->title,
                ],
            ],
        ], 201);
    }

    // ── GET /api/v1/research/{research}/reports ─────────────────────
    public function index(ResearchSubmission $research): JsonResponse
    {
        $viewer = JWTAuth::user();

        // Authors can only see reports after Interactive Phase begins (REQ-056)
        if ($viewer->isAuthor() && !$research->isInteractive() && !$research->isAccepted()) {
            return response()->json([
                'message' => 'Forbidden. Review reports are not visible to authors until the Interactive Phase begins.',
            ], 403);
        }

        $reports = ReviewReport::where('research_id', $research->research_id)
            ->with(['reviewer', 'forumDiscussion'])
            ->get();

        $data = $reports->map(function ($report) use ($research, $viewer) {
            return [
                'report_id'      => $report->report_id,
                'reviewer'       => $this->anonymization->reviewerArray($report->reviewer, $research, $viewer),
                'summary'        => $report->summary,
                'major_issues'   => $report->major_issues,
                'minor_issues'   => $report->minor_issues,
                'recommendation' => $report->recommendation,
                'submitted_at'   => $report->submitted_at,
                'discussion_id'  => $report->forumDiscussion?->discussion_id,
            ];
        });

        return response()->json(['data' => $data]);
    }
}
