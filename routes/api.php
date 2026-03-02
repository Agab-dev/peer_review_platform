<?php

use App\Http\Controllers\AnnotationController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DocumentVersionController;
use App\Http\Controllers\EditorialController;
use App\Http\Controllers\ForumController;
use App\Http\Controllers\PublicationController;
use App\Http\Controllers\ResearchController;
use App\Http\Controllers\ReviewReportController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — Collaborative Platform for Academic Research and Peer Review
| All routes versioned under /api/v1/
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {

    // ── 1. AUTHENTICATION & ACCOUNT MANAGEMENT ─────────────────────

    Route::prefix('auth')->group(function () {
        // Public
        Route::post('register', [AuthController::class, 'register']);
        Route::post('login', [AuthController::class, 'login']);

        // Authenticated
        Route::middleware('jwt.auth')->group(function () {
            Route::post('logout', [AuthController::class, 'logout']);
            Route::post('change-password', [AuthController::class, 'changePassword']);
        });
    });

    // EIC creates reviewer and editor accounts
    Route::middleware(['jwt.auth', 'role:eic'])->group(function () {
        Route::post('reviewers', [UserController::class, 'storeReviewer']);
        Route::post('editors', [UserController::class, 'storeEditor']);
    });

    // Reviewer and editor pool listings
    Route::middleware(['jwt.auth', 'role:editor,eic'])->group(function () {
        Route::get('reviewers', [UserController::class, 'listReviewers']);
    });
    Route::middleware(['jwt.auth', 'role:eic'])->group(function () {
        Route::get('editors', [UserController::class, 'listEditors']);
    });

    // ── 2. RESEARCH SUBMISSIONS ─────────────────────────────────────

    Route::middleware('jwt.auth')->group(function () {
        // Submit new research (author only)
        Route::post('research', [ResearchController::class, 'store'])
            ->middleware('role:author');

        // List research (role-aware)
        Route::get('research', [ResearchController::class, 'index']);

        // Get research details
        Route::get('research/{research}', [ResearchController::class, 'show']);

        // Accept or reject (primary editor only — enforced inside controller)
        Route::patch('research/{research}/status', [ResearchController::class, 'updateStatus'])
            ->middleware('role:editor');

        // ── 3. DOCUMENT VERSIONS ───────────────────────────────────

        Route::get('research/{research}/versions', [DocumentVersionController::class, 'index']);
        Route::get('research/{research}/versions/{document}', [DocumentVersionController::class, 'show']);

        // Upload revised version (author only, interactive phase enforced in controller)
        Route::post('research/{research}/versions', [DocumentVersionController::class, 'store'])
            ->middleware('role:author');

        // ── 4. EDITORIAL WORKFLOW ──────────────────────────────────

        // Editor assignment — EIC only
        Route::post('research/{research}/editors', [EditorialController::class, 'assignEditor'])
            ->middleware('role:eic');
        Route::delete('research/{research}/editors/{assignment}', [EditorialController::class, 'removeEditor'])
            ->middleware('role:eic');

        // Anonymization and deadline — primary editor only (enforced in controller)
        Route::patch('research/{research}/anonymization', [EditorialController::class, 'setAnonymization'])
            ->middleware('role:editor');
        Route::patch('research/{research}/deadline', [EditorialController::class, 'setDeadline'])
            ->middleware('role:editor');

        // Reviewer assignment — primary editor only (enforced in controller)
        Route::post('research/{research}/reviewers', [EditorialController::class, 'assignReviewer'])
            ->middleware('role:editor');
        Route::delete('research/{research}/reviewers/{assignment}', [EditorialController::class, 'revokeReviewer'])
            ->middleware('role:editor');

        // Conflict of interest — reviewer or editor
        Route::post('research/{research}/conflicts', [EditorialController::class, 'declareConflict'])
            ->middleware('role:reviewer,editor');

        // ── 5. INDEPENDENT PHASE ───────────────────────────────────

        Route::post('research/{research}/reports', [ReviewReportController::class, 'store'])
            ->middleware('role:reviewer');
        Route::get('research/{research}/reports', [ReviewReportController::class, 'index']);

        // ── 6. INTERACTIVE PHASE — ANNOTATIONS ────────────────────

        Route::post(
            'research/{research}/versions/{document}/annotations',
            [AnnotationController::class, 'store']
        )->middleware('role:reviewer');

        Route::get(
            'research/{research}/versions/{document}/annotations',
            [AnnotationController::class, 'index']
        );

        // ── 7. FORUM DISCUSSIONS & REPLIES ────────────────────────

        Route::get('research/{research}/forums/annotations', [ForumController::class, 'listAnnotationDiscussions']);
        Route::get('research/{research}/forums/reports', [ForumController::class, 'listReportDiscussions']);
        Route::get('research/{research}/forums/{discussion}', [ForumController::class, 'show']);

        Route::post('research/{research}/forums/{discussion}/replies', [ForumController::class, 'storeReply']);
        Route::delete('research/{research}/forums/{discussion}/replies/{reply}', [ForumController::class, 'deleteReply']);
    });

    // ── 8. PUBLIC RESEARCH ACCESS (no authentication) ──────────────

    Route::get('publications', [PublicationController::class, 'index']);
    Route::get('publications/{research}', [PublicationController::class, 'show']);
});
