<?php

namespace App\Http\Controllers;

use App\Jobs\ConvertPdfToHtml;
use App\Models\DocumentVersion;
use App\Models\ResearchSubmission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;

class DocumentVersionController extends Controller
{
    // ── GET /api/v1/research/{research}/versions ────────────────────
    public function index(ResearchSubmission $research): JsonResponse
    {
        $versions = DocumentVersion::where('research_id', $research->research_id)
            ->orderByDesc('version_number')
            ->get();

        $latestVersionNumber = $versions->first()?->version_number;

        $data = $versions->map(fn ($v) => [
            'document_id'    => $v->document_id,
            'version_number' => $v->version_number,
            'html_ready'     => $v->html_ready,
            'uploaded_at'    => $v->uploaded_at,
            'is_latest'      => $v->version_number === $latestVersionNumber,
        ]);

        return response()->json(['data' => $data]);
    }

    // ── GET /api/v1/research/{research}/versions/{document} ─────────
    public function show(ResearchSubmission $research, DocumentVersion $document): JsonResponse
    {
        // Ensure document belongs to this research
        if ($document->research_id !== $research->research_id) {
            return response()->json(['message' => 'Document not found for this research.'], 404);
        }

        $latestVersion = DocumentVersion::where('research_id', $research->research_id)
            ->max('version_number');

        $isLatest = $document->version_number === $latestVersion;

        return response()->json([
            'data' => [
                'document_id'       => $document->document_id,
                'version_number'    => $document->version_number,
                'html_content'      => $document->html_ready ? $document->html_content : null,
                'html_ready'        => $document->html_ready,
                'is_latest'         => $isLatest,
                'latest_document_id' => $isLatest ? null : DocumentVersion::where('research_id', $research->research_id)
                    ->orderByDesc('version_number')
                    ->value('document_id'),
                'uploaded_at'       => $document->uploaded_at,
            ],
        ]);
    }

    // ── POST /api/v1/research/{research}/versions ───────────────────
    public function store(Request $request, ResearchSubmission $research): JsonResponse
    {
        $user = JWTAuth::user();

        // Only authors of this submission can upload
        if ($research->author_id !== $user->user_id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        // Only during Interactive Phase
        if (!$research->isInteractive()) {
            return response()->json([
                'message' => 'Unprocessable. Revised documents can only be uploaded during the Interactive Phase.',
            ], 422);
        }

        $request->validate([
            'pdf_file' => 'required|file|mimes:pdf|max:51200',
        ]);

        $file     = $request->file('pdf_file');
        $filename = uniqid('pdf_', true) . '.pdf';
        $file->storeAs('', $filename, 'pdfs');

        $nextVersion = DocumentVersion::where('research_id', $research->research_id)->max('version_number') + 1;

        $document = DocumentVersion::create([
            'research_id'    => $research->research_id,
            'version_number' => $nextVersion,
            'pdf_file_path'  => $filename,
            'html_ready'     => false,
            'uploaded_at'    => now(),
        ]);

        ConvertPdfToHtml::dispatch($document->document_id);

        return response()->json([
            'message' => 'Revised document uploaded successfully.',
            'data'    => [
                'document_id'    => $document->document_id,
                'research_id'    => $research->research_id,
                'version_number' => $document->version_number,
                'html_ready'     => $document->html_ready,
                'uploaded_at'    => $document->uploaded_at,
                'is_latest'      => true,
            ],
        ], 201);
    }
}
