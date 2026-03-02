<?php

namespace App\Http\Controllers;

use App\Models\ForumDiscussion;
use App\Models\ForumReply;
use App\Models\ResearchSubmission;
use App\Services\AnonymizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;

class ForumController extends Controller
{
    public function __construct(
        private readonly AnonymizationService $anonymization
    ) {}

    // ── GET /api/v1/research/{research}/forums/annotations ──────────
    public function listAnnotationDiscussions(Request $request, ResearchSubmission $research): JsonResponse
    {
        $viewer = JWTAuth::user();

        $request->validate([
            'document_id' => 'required|integer|exists:document_versions,document_id',
        ]);

        $documentId = $request->query('document_id');

        $query = ForumDiscussion::where('research_id', $research->research_id)
            ->where('discussion_type', 'annotation')
            ->whereHas('annotation', fn ($q) => $q->where('document_id', $documentId))
            ->with(['creator', 'annotation', 'replies' => fn ($q) => $q->whereNull('deleted_at')]);

        $sort = $request->query('sort', 'chronological');
        if ($sort === 'document_order') {
            $query->join('annotations', 'forum_discussions.referenced_annotation_id', '=', 'annotations.annotation_id')
                ->orderBy('annotations.text_range_start')
                ->select('forum_discussions.*');
        } else {
            $query->orderBy('forum_discussions.created_at');
        }

        $discussions = $query->paginate(15);

        $discussions->getCollection()->transform(function ($d) use ($research, $viewer) {
            return [
                'discussion_id' => $d->discussion_id,
                'title' => $d->title,
                'annotation' => $d->annotation ? [
                    'annotation_id' => $d->annotation->annotation_id,
                    'text_range_start' => $d->annotation->text_range_start,
                    'text_range_end' => $d->annotation->text_range_end,
                ] : null,
                'created_by' => $this->anonymization->reviewerArray($d->creator, $research, $viewer),
                'created_at' => $d->created_at,
                'reply_count' => $d->replies->count(),
            ];
        });

        return response()->json($discussions);
    }

    // ── GET /api/v1/research/{research}/forums/reports ──────────────
    public function listReportDiscussions(Request $request, ResearchSubmission $research): JsonResponse
    {
        $viewer = JWTAuth::user();

        // Authors can only access after Interactive Phase (REQ-066)
        if ($viewer->isAuthor() && ! $research->isInteractive()) {
            return response()->json([
                'message' => 'Forbidden. The Review Reports Forum is not accessible to authors until the Interactive Phase begins.',
            ], 403);
        }

        $discussions = ForumDiscussion::where('research_id', $research->research_id)
            ->where('discussion_type', 'review_report')
            ->with(['creator', 'report', 'replies' => fn ($q) => $q->whereNull('deleted_at')])
            ->orderBy('created_at')
            ->paginate(15);

        $discussions->getCollection()->transform(function ($d) use ($research, $viewer) {
            return [
                'discussion_id' => $d->discussion_id,
                'title' => $d->title,
                'report' => $d->report ? [
                    'report_id' => $d->report->report_id,
                    'recommendation' => $d->report->recommendation,
                ] : null,
                'created_by' => $this->anonymization->reviewerArray($d->creator, $research, $viewer),
                'created_at' => $d->created_at,
                'reply_count' => $d->replies->count(),
            ];
        });

        return response()->json($discussions);
    }

    // ── GET /api/v1/research/{research}/forums/{discussion} ─────────
    public function show(ResearchSubmission $research, ForumDiscussion $discussion): JsonResponse
    {
        $viewer = JWTAuth::user();

        if ($discussion->research_id !== $research->research_id) {
            return response()->json(['message' => 'Discussion not found for this research.'], 404);
        }

        // Author gate for review_report discussions
        if ($discussion->discussion_type === 'review_report' && $viewer->isAuthor()) {
            if (! $research->isInteractive()) {
                return response()->json([
                    'message' => 'Forbidden. This forum is not accessible until the Interactive Phase begins.',
                ], 403);
            }
        }

        // Load all replies (including soft-deleted for thread continuity)
        $replies = ForumReply::where('discussion_id', $discussion->discussion_id)
            ->with('user')
            ->orderBy('created_at')
            ->get();

        $repliesData = $replies->map(function ($reply) use ($research, $viewer) {
            $isDeleted = $reply->deleted_at !== null;

            return [
                'reply_id' => $reply->reply_id,
                'user' => $isDeleted ? null : [
                    'user_id' => $reply->user->user_id,
                    'display_name' => $this->anonymization->resolveDisplayName($reply->user, $research, $viewer),
                ],
                'content' => $isDeleted ? null : $reply->content,
                'created_at' => $reply->created_at,
                'deleted_at' => $reply->deleted_at,
            ];
        });

        return response()->json([
            'data' => [
                'discussion_id' => $discussion->discussion_id,
                'discussion_type' => $discussion->discussion_type,
                'title' => $discussion->title,
                'referenced_annotation_id' => $discussion->referenced_annotation_id,
                'referenced_report_id' => $discussion->referenced_report_id,
                'created_at' => $discussion->created_at,
                'replies' => $repliesData,
            ],
        ]);
    }

    // ── POST /api/v1/research/{research}/forums/{discussion}/replies ─
    public function storeReply(Request $request, ResearchSubmission $research, ForumDiscussion $discussion): JsonResponse
    {
        $user = JWTAuth::user();

        if ($discussion->research_id !== $research->research_id) {
            return response()->json(['message' => 'Discussion not found for this research.'], 404);
        }

        // Authors cannot reply to review_report discussions before Interactive Phase
        if ($viewer = $user) {
            if ($discussion->discussion_type === 'review_report' && $viewer->isAuthor()) {
                if (! $research->isInteractive()) {
                    return response()->json(['message' => 'Forbidden.'], 403);
                }
            }
        }

        $data = $request->validate([
            'content' => 'required|string',
        ]);

        $reply = ForumReply::create([
            'discussion_id' => $discussion->discussion_id,
            'user_id' => $user->user_id,
            'content' => $data['content'],
            'created_at' => now(),
        ]);

        return response()->json([
            'message' => 'Reply posted.',
            'data' => [
                'reply_id' => $reply->reply_id,
                'discussion_id' => $discussion->discussion_id,
                'user_id' => $user->user_id,
                'content' => $reply->content,
                'created_at' => $reply->created_at,
            ],
        ], 201);
    }

    // ── DELETE /api/v1/research/{research}/forums/{discussion}/replies/{reply}
    public function deleteReply(ResearchSubmission $research, ForumDiscussion $discussion, ForumReply $reply): JsonResponse
    {
        $user = JWTAuth::user();

        if ($discussion->research_id !== $research->research_id) {
            return response()->json(['message' => 'Discussion not found for this research.'], 404);
        }

        if ($reply->discussion_id !== $discussion->discussion_id) {
            return response()->json(['message' => 'Reply not found for this discussion.'], 404);
        }

        // Only the reply author can delete their own reply (REQ-083)
        if ($reply->user_id !== $user->user_id) {
            return response()->json(['message' => 'Forbidden. You can only delete your own replies.'], 403);
        }

        if ($reply->deleted_at !== null) {
            return response()->json(['message' => 'Reply is already deleted.'], 422);
        }

        $reply->deleted_at = now();
        $reply->save();

        return response()->json(['message' => 'Reply deleted.']);
    }
}
