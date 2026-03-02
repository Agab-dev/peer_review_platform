<?php

namespace App\Http\Controllers;

use App\Models\Annotation;
use App\Models\DocumentVersion;
use App\Models\ForumDiscussion;
use App\Models\ResearchSubmission;
use App\Services\AnonymizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;

class AnnotationController extends Controller
{
    public function __construct(
        private readonly AnonymizationService $anonymization
    ) {}

    // ── POST /api/v1/research/{research}/versions/{document}/annotations
    public function store(Request $request, ResearchSubmission $research, DocumentVersion $document): JsonResponse
    {
        $user = JWTAuth::user();

        if ($document->research_id !== $research->research_id) {
            return response()->json(['message' => 'Document not found for this research.'], 404);
        }

        // Only during Interactive Phase
        if (! $research->isInteractive()) {
            return response()->json([
                'message' => 'Unprocessable. Annotations can only be created during the Interactive Review Phase.',
            ], 422);
        }

        // html_ready must be true before annotations can be made
        if (! $document->html_ready) {
            return response()->json([
                'message' => 'Unprocessable. Document is still being processed.',
            ], 422);
        }

        $data = $request->validate([
            'text_range_start' => 'required|integer|min:0',
            'text_range_end' => 'required|integer|gt:text_range_start',
            'comment' => 'required|string',
        ]);

        // Check for duplicate annotation by same reviewer on same range (REQ-074)
        $duplicate = Annotation::where('document_id', $document->document_id)
            ->where('reviewer_id', $user->user_id)
            ->where('text_range_start', $data['text_range_start'])
            ->where('text_range_end', $data['text_range_end'])
            ->exists();

        if ($duplicate) {
            return response()->json([
                'message' => 'Conflict. You have already annotated this exact text range on this document version.',
            ], 409);
        }

        $annotation = Annotation::create([
            'document_id' => $document->document_id,
            'reviewer_id' => $user->user_id,
            'text_range_start' => $data['text_range_start'],
            'text_range_end' => $data['text_range_end'],
            'comment' => $data['comment'],
            'created_at' => now(),
        ]);

        // Auto-generate thread title from first 7 words of comment (REQ-071)
        $title = $this->anonymization->generateAnnotationThreadTitle($data['comment']);

        // Auto-create Annotations Forum thread (REQ-071)
        $discussion = ForumDiscussion::create([
            'research_id' => $research->research_id,
            'discussion_type' => 'annotation',
            'referenced_annotation_id' => $annotation->annotation_id,
            'title' => $title,
            'created_at' => now(),
            'created_by' => $user->user_id,
        ]);

        return response()->json([
            'message' => 'Annotation created.',
            'data' => [
                'annotation_id' => $annotation->annotation_id,
                'document_id' => $document->document_id,
                'reviewer_id' => $user->user_id,
                'text_range_start' => $annotation->text_range_start,
                'text_range_end' => $annotation->text_range_end,
                'comment' => $annotation->comment,
                'created_at' => $annotation->created_at,
                'discussion' => [
                    'discussion_id' => $discussion->discussion_id,
                    'title' => $discussion->title,
                ],
            ],
        ], 201);
    }

    // ── GET /api/v1/research/{research}/versions/{document}/annotations
    public function index(Request $request, ResearchSubmission $research, DocumentVersion $document): JsonResponse
    {
        $viewer = JWTAuth::user();

        if ($document->research_id !== $research->research_id) {
            return response()->json(['message' => 'Document not found for this research.'], 404);
        }

        $query = Annotation::where('document_id', $document->document_id)
            ->with(['reviewer', 'forumDiscussion']);

        $sort = $request->query('sort', 'chronological');

        if ($sort === 'document_order') {
            $query->orderBy('text_range_start');
        } else {
            $query->orderBy('created_at');
        }

        $annotations = $query->get();

        $data = $annotations->map(function ($annotation) use ($research, $viewer) {
            return [
                'annotation_id' => $annotation->annotation_id,
                'text_range_start' => $annotation->text_range_start,
                'text_range_end' => $annotation->text_range_end,
                'comment' => $annotation->comment,
                'reviewer' => $this->anonymization->reviewerArray($annotation->reviewer, $research, $viewer),
                'created_at' => $annotation->created_at,
                'discussion_id' => $annotation->forumDiscussion?->discussion_id,
            ];
        });

        return response()->json(['data' => $data]);
    }
}
