<?php

namespace App\Services;

use App\Models\ResearchSubmission;
use App\Models\User;

class AnonymizationService
{
    /**
     * Resolve the display name of a participant based on the anonymization
     * model in effect, who is viewing, and whether the submission is accepted.
     *
     * Rules:
     * - If submission is accepted → anonymization ends, always show full name (REQ-047)
     * - EIC, primary editor, and co-editors always see full names (REQ-046)
     * - open model → everyone sees full names
     * - single model → reviewer names hidden from authors; author visible to reviewers
     * - double model → both hidden from each other
     */
    public function resolveDisplayName(
        User $participant,
        ResearchSubmission $research,
        ?User $viewer
    ): string {
        // Accepted — anonymization ends for everyone
        if ($research->isAccepted()) {
            return $participant->full_name;
        }

        // No viewer (public access) — should not reach here for non-accepted
        if ($viewer === null) {
            return $participant->full_name;
        }

        // EIC and editors always see full names
        if ($viewer->isEic() || $viewer->isEditor()) {
            return $participant->full_name;
        }

        $model = $research->anonymization_model;

        // Open review — everyone sees full names
        if ($model === 'open') {
            return $participant->full_name;
        }

        // Single-anonymized: reviewer names hidden from authors
        if ($model === 'single') {
            if ($viewer->isAuthor() && $participant->isReviewer()) {
                return 'Reviewer Note';
            }

            return $participant->full_name;
        }

        // Double-anonymized: reviewer hidden from author, author hidden from reviewer
        if ($model === 'double') {
            if ($viewer->isAuthor() && $participant->isReviewer()) {
                return 'Reviewer Note';
            }
            if ($viewer->isReviewer() && $participant->isAuthor()) {
                return 'Anonymous Author';
            }

            return $participant->full_name;
        }

        return $participant->full_name;
    }

    /**
     * Build a reviewer array respecting anonymization for API responses.
     */
    public function reviewerArray(User $reviewer, ResearchSubmission $research, ?User $viewer): array
    {
        return [
            'user_id' => $reviewer->user_id,
            'display_name' => $this->resolveDisplayName($reviewer, $research, $viewer),
        ];
    }

    /**
     * Generate the discussion thread title for an annotation:
     * first 7 words of highlighted text + ellipsis (REQ-071).
     */
    public function generateAnnotationThreadTitle(string $highlightedText): string
    {
        $words = preg_split('/\s+/', trim($highlightedText));
        $first7 = array_slice($words, 0, 7);
        $title = implode(' ', $first7);

        if (count($words) > 7) {
            $title .= '...';
        }

        return $title;
    }
}
