<?php

namespace App\Http\Controllers;

use App\Models\DocumentVersion;
use App\Models\ResearchSubmission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicationController extends Controller
{
    // ── GET /api/v1/publications ────────────────────────────────────
    public function index(Request $request): JsonResponse
    {
        $query = ResearchSubmission::where('status', 'accepted')
            ->with('author', 'latestDocument');

        if ($title = $request->query('title')) {
            $query->where('title', 'ilike', "%{$title}%");
        }

        if ($field = $request->query('field')) {
            $query->where('research_field', 'ilike', "%{$field}%");
        }

        $results = $query->orderByDesc('accepted_at')->paginate(15);

        $results->getCollection()->transform(fn ($r) => [
            'research_id'    => $r->research_id,
            'title'          => $r->title,
            'research_field' => $r->research_field,
            'author'         => [
                'full_name'   => $r->author->full_name,
                'institution' => $r->author->institution,
            ],
            'accepted_at'    => $r->accepted_at,
        ]);

        return response()->json($results);
    }

    // ── GET /api/v1/publications/{research} ─────────────────────────
    public function show(ResearchSubmission $research): JsonResponse
    {
        // Return 404 if not accepted (REQ-088)
        if (!$research->isAccepted()) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $research->load(['author', 'latestDocument']);
        $document = $research->latestDocument;

        return response()->json([
            'data' => [
                'research_id'    => $research->research_id,
                'title'          => $research->title,
                'research_field' => $research->research_field,
                'author'         => [
                    'full_name'   => $research->author->full_name,
                    'institution' => $research->author->institution,
                ],
                'html_content'   => $document?->html_ready ? $document->html_content : null,
                'accepted_at'    => $research->accepted_at,
            ],
        ]);
    }
}
