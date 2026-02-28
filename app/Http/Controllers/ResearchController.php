<?php

namespace App\Http\Controllers;

use App\Jobs\ConvertPdfToHtml;
use App\Models\DocumentVersion;
use App\Models\EditorAssignment;
use App\Models\ResearchSubmission;
use App\Models\ReviewerAssignment;
use App\Services\AnonymizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Tymon\JWTAuth\Facades\JWTAuth;

class ResearchController extends Controller
{
    public function __construct(
        private readonly AnonymizationService $anonymization
    ) {}

    // ── POST /api/v1/research ───────────────────────────────────────
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'title'          => 'required|string|max:500',
            'research_field' => 'required|string|max:255',
            'pdf_file'       => 'required|file|mimes:pdf|max:51200', // 50MB
        ]);

        $user = JWTAuth::user();
        $file = $request->file('pdf_file');

        // Store PDF in private storage/app/pdfs
        $filename = uniqid('pdf_', true) . '.pdf';
        $path     = $file->storeAs('', $filename, 'pdfs');

        // Create the research submission
        $research = ResearchSubmission::create([
            'author_id'      => $user->user_id,
            'title'          => $request->title,
            'research_field' => $request->research_field,
            'status'         => 'pending',
            'review_phase'   => null,
            'submitted_at'   => now(),
        ]);

        // Create version 1
        $document = DocumentVersion::create([
            'research_id'    => $research->research_id,
            'version_number' => 1,
            'pdf_file_path'  => $filename,
            'html_ready'     => false,
            'uploaded_at'    => now(),
        ]);

        // Dispatch async conversion job
        ConvertPdfToHtml::dispatch($document->document_id);

        return response()->json([
            'message' => 'Research submitted successfully.',
            'data'    => [
                'research_id'    => $research->research_id,
                'title'          => $research->title,
                'research_field' => $research->research_field,
                'status'         => $research->status,
                'review_phase'   => $research->review_phase,
                'submitted_at'   => $research->submitted_at,
                'document'       => [
                    'document_id'    => $document->document_id,
                    'version_number' => $document->version_number,
                    'html_ready'     => $document->html_ready,
                    'uploaded_at'    => $document->uploaded_at,
                ],
            ],
        ], 201);
    }

    // ── GET /api/v1/research ────────────────────────────────────────
    public function index(Request $request): JsonResponse
    {
        $user  = JWTAuth::user();
        $query = ResearchSubmission::query()->with('latestDocument');

        // Role-aware filtering
        if ($user->isAuthor()) {
            $query->where('author_id', $user->user_id);
        } elseif ($user->isReviewer()) {
            $assignedIds = ReviewerAssignment::where('reviewer_id', $user->user_id)
                ->whereNull('deleted_at')
                ->pluck('research_id');
            $query->whereIn('research_id', $assignedIds);
        } elseif ($user->isEditor()) {
            $assignedIds = EditorAssignment::where('editor_id', $user->user_id)
                ->whereNull('deleted_at')
                ->pluck('research_id');
            $query->whereIn('research_id', $assignedIds);
        }
        // EIC sees all — no filter

        // Optional filters
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($field = $request->query('research_field')) {
            $query->where('research_field', 'ilike', "%{$field}%");
        }

        $results = $query->paginate(15);

        // Load primary editors for each result
        $researchIds = $results->pluck('research_id');
        $primaryEditors = EditorAssignment::with('editor')
            ->whereIn('research_id', $researchIds)
            ->whereNull('deleted_at')
            ->where('is_primary', true)
            ->get()
            ->keyBy('research_id');

        $results->getCollection()->transform(function ($research) use ($primaryEditors) {
            $pe = $primaryEditors->get($research->research_id);
            return [
                'research_id'    => $research->research_id,
                'title'          => $research->title,
                'research_field' => $research->research_field,
                'status'         => $research->status,
                'review_phase'   => $research->review_phase,
                'submitted_at'   => $research->submitted_at,
                'primary_editor' => $pe ? [
                    'user_id'   => $pe->editor->user_id,
                    'full_name' => $pe->editor->full_name,
                ] : null,
            ];
        });

        return response()->json($results);
    }

    // ── GET /api/v1/research/{research} ────────────────────────────
    public function show(ResearchSubmission $research): JsonResponse
    {
        $viewer = JWTAuth::user();

        $research->load([
            'author',
            'activeEditorAssignments.editor',
            'activeReviewerAssignments.reviewer',
            'latestDocument',
        ]);

        // Resolve reviewer names respecting anonymization
        $reviewers = $research->activeReviewerAssignments->map(function ($assignment) use ($research, $viewer) {
            return $this->anonymization->reviewerArray($assignment->reviewer, $research, $viewer);
        });

        // Editors and EIC always see full names
        $editors = $research->activeEditorAssignments->map(fn ($a) => [
            'user_id'    => $a->editor->user_id,
            'full_name'  => $a->editor->full_name,
            'is_primary' => $a->is_primary,
        ]);

        // Author visibility depends on anonymization
        $authorData = null;
        if ($viewer->isEic() || $viewer->isEditor() || $research->isAccepted()) {
            $authorData = [
                'user_id'   => $research->author->user_id,
                'full_name' => $research->author->full_name,
            ];
        } elseif ($viewer->isAuthor() && $viewer->user_id === $research->author_id) {
            $authorData = [
                'user_id'   => $research->author->user_id,
                'full_name' => $research->author->full_name,
            ];
        } elseif ($viewer->isReviewer()) {
            // In double-anonymized, hide author from reviewer
            $authorData = $research->anonymization_model === 'double'
                ? ['display_name' => 'Anonymous Author']
                : ['user_id' => $research->author->user_id, 'full_name' => $research->author->full_name];
        }

        $latest = $research->latestDocument;

        return response()->json([
            'data' => [
                'research_id'         => $research->research_id,
                'title'               => $research->title,
                'research_field'      => $research->research_field,
                'status'              => $research->status,
                'review_phase'        => $research->review_phase,
                'anonymization_model' => $research->anonymization_model,
                'deadline'            => $research->deadline,
                'submitted_at'        => $research->submitted_at,
                'accepted_at'         => $research->accepted_at,
                'author'              => $authorData,
                'editors'             => $editors,
                'reviewers'           => $reviewers,
                'latest_document'     => $latest ? [
                    'document_id'    => $latest->document_id,
                    'version_number' => $latest->version_number,
                    'html_ready'     => $latest->html_ready,
                    'uploaded_at'    => $latest->uploaded_at,
                ] : null,
            ],
        ]);
    }

    // ── PATCH /api/v1/research/{research}/status ────────────────────
    public function updateStatus(Request $request, ResearchSubmission $research): JsonResponse
    {
        $viewer = JWTAuth::user();

        // Verify this user is the primary editor of this submission
        $primaryAssignment = $research->primaryEditorAssignment()->first();

        if (!$primaryAssignment || $primaryAssignment->editor_id !== $viewer->user_id) {
            return response()->json(['message' => 'Forbidden. Only the primary handling editor may make this decision.'], 403);
        }

        // Cannot change status if already finalized
        if ($research->isFinalized()) {
            return response()->json([
                'message' => 'Unprocessable. This submission has already been finalized.',
            ], 422);
        }

        $data = $request->validate([
            'status' => 'required|in:accepted,rejected',
        ]);

        $decision = $data['status'];

        if ($decision === 'rejected') {
            // Reject available during Independent and Interactive phases
            $research->status = 'rejected';
            $research->save();

            return response()->json([
                'message' => 'Research rejected permanently.',
                'data'    => [
                    'research_id'  => $research->research_id,
                    'status'       => $research->status,
                    'review_phase' => $research->review_phase,
                    'accepted_at'  => null,
                ],
            ]);
        }

        // Accept logic depends on current phase
        if ($decision === 'accepted') {

            // Accept during Independent Phase → transition to Interactive Phase
            if ($research->isIndependent()) {
                $research->review_phase = 'interactive';
                $research->save();

                return response()->json([
                    'message' => 'Research accepted. Interactive Review Phase has begun.',
                    'data'    => [
                        'research_id'  => $research->research_id,
                        'status'       => $research->status, // still 'pending'
                        'review_phase' => $research->review_phase,
                        'accepted_at'  => null,
                    ],
                ]);
            }

            // Accept during Interactive Phase → final publication decision
            if ($research->isInteractive()) {
                $research->status      = 'accepted';
                $research->accepted_at = now();
                $research->save();

                return response()->json([
                    'message' => 'Research accepted and published.',
                    'data'    => [
                        'research_id'  => $research->research_id,
                        'status'       => $research->status,
                        'review_phase' => $research->review_phase,
                        'accepted_at'  => $research->accepted_at,
                    ],
                ]);
            }

            // Accept not allowed if review_phase is null (no reviewers assigned yet)
            return response()->json([
                'message' => 'Unprocessable. Cannot accept a submission before the Independent Review Phase has begun.',
            ], 422);
        }
    }
}
