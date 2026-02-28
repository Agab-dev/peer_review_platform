<?php

namespace Database\Seeders;

use App\Models\Annotation;
use App\Models\DocumentVersion;
use App\Models\EditorAssignment;
use App\Models\ForumDiscussion;
use App\Models\ForumReply;
use App\Models\ResearchSubmission;
use App\Models\ReviewReport;
use App\Models\ReviewerAssignment;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DevelopmentSeeder extends Seeder
{
    public function run(): void
    {
        // ── USERS ───────────────────────────────────────────────────

        $authors = collect([
            ['full_name' => 'Sara Ahmed',       'email' => 'sara@university.edu',   'institution' => 'University of Salahaddin'],
            ['full_name' => 'Omar Khalid',      'email' => 'omar@tech.edu',         'institution' => 'Erbil Polytechnic University'],
            ['full_name' => 'Layla Hassan',     'email' => 'layla@research.edu',    'institution' => 'University of Duhok'],
            ['full_name' => 'Dilan Mahmoud',    'email' => 'dilan@uni.edu',         'institution' => 'University of Sulaimani'],
            ['full_name' => 'Ravan Aziz',       'email' => 'ravan@college.edu',     'institution' => 'Koya University'],
        ])->map(fn ($data) => User::firstOrCreate(['email' => $data['email']], array_merge($data, [
            'password' => Hash::make('Password123'),
            'role'     => 'author',
        ])));

        $reviewers = collect([
            ['full_name' => 'Dr. Karwan Hassan',  'email' => 'karwan@uni.edu',    'institution' => 'University of Salahaddin', 'expertise_areas' => 'Machine Learning, NLP'],
            ['full_name' => 'Dr. Nadia Omar',     'email' => 'nadia@tech.edu',    'institution' => 'Erbil Polytechnic University', 'expertise_areas' => 'Computer Vision, Deep Learning'],
            ['full_name' => 'Dr. Soran Ali',      'email' => 'soran@research.edu','institution' => 'University of Duhok', 'expertise_areas' => 'Software Engineering, Agile'],
            ['full_name' => 'Dr. Hana Rashid',    'email' => 'hana@uni.edu',      'institution' => 'University of Sulaimani', 'expertise_areas' => 'Cybersecurity, Networks'],
        ])->map(fn ($data) => User::firstOrCreate(['email' => $data['email']], array_merge($data, [
            'password'            => Hash::make('Password123'),
            'role'                => 'reviewer',
            'must_change_password' => false,
        ])));

        $editors = collect([
            ['full_name' => 'Prof. Layla Mahmoud', 'email' => 'prof.layla@uni.edu',   'institution' => 'University of Salahaddin', 'expertise_areas' => 'Software Engineering, AI'],
            ['full_name' => 'Prof. Aram Salih',    'email' => 'prof.aram@tech.edu',   'institution' => 'Erbil Polytechnic University', 'expertise_areas' => 'Database Systems, Cloud'],
            ['full_name' => 'Prof. Shilan Karim',  'email' => 'prof.shilan@uni.edu',  'institution' => 'University of Duhok', 'expertise_areas' => 'Networks, Security'],
        ])->map(fn ($data) => User::firstOrCreate(['email' => $data['email']], array_merge($data, [
            'password'            => Hash::make('Password123'),
            'role'                => 'editor',
            'must_change_password' => false,
        ])));

        // ── RESEARCH 1: Independent Phase ───────────────────────────

        $r1 = ResearchSubmission::firstOrCreate(['title' => 'Deep Learning in Medical Imaging'], [
            'author_id'           => $authors[0]->user_id,
            'research_field'      => 'Computer Science',
            'status'              => 'pending',
            'review_phase'        => 'independent',
            'anonymization_model' => 'double',
            'deadline'            => now()->addDays(14)->toDateString(),
            'submitted_at'        => now()->subDays(10),
        ]);

        $doc1 = DocumentVersion::firstOrCreate(['research_id' => $r1->research_id, 'version_number' => 1], [
            'pdf_file_path' => 'sample_doc1_v1.pdf',
            'html_content'  => '<html><body><h1>Deep Learning in Medical Imaging</h1><p>This paper presents a novel CNN architecture for tumor detection in MRI scans. The proposed model achieves 94.3% accuracy on the benchmark dataset. Further improvements can be made by incorporating attention mechanisms.</p><p>The methodology section describes the dataset collection process and annotation procedure used for training the model.</p></body></html>',
            'html_ready'    => true,
            'uploaded_at'   => now()->subDays(10),
        ]);

        EditorAssignment::firstOrCreate(
            ['research_id' => $r1->research_id, 'editor_id' => $editors[0]->user_id, 'deleted_at' => null],
            ['is_primary' => true, 'assigned_at' => now()->subDays(9)]
        );
        EditorAssignment::firstOrCreate(
            ['research_id' => $r1->research_id, 'editor_id' => $editors[1]->user_id, 'deleted_at' => null],
            ['is_primary' => false, 'assigned_at' => now()->subDays(9)]
        );

        ReviewerAssignment::firstOrCreate(
            ['research_id' => $r1->research_id, 'reviewer_id' => $reviewers[0]->user_id],
            ['assigned_at' => now()->subDays(8), 'deleted_at' => null]
        );
        ReviewerAssignment::firstOrCreate(
            ['research_id' => $r1->research_id, 'reviewer_id' => $reviewers[1]->user_id],
            ['assigned_at' => now()->subDays(8), 'deleted_at' => null]
        );

        $report1 = ReviewReport::firstOrCreate(
            ['research_id' => $r1->research_id, 'reviewer_id' => $reviewers[0]->user_id],
            [
                'summary'        => 'This paper presents a well-structured CNN architecture for tumor detection. The approach is novel and the results are promising.',
                'major_issues'   => 'The evaluation dataset is not publicly available, making reproducibility difficult. The comparison with state-of-the-art methods is limited.',
                'minor_issues'   => 'Some references are outdated. Figure 3 labels are too small.',
                'recommendation' => 'revisions_required',
                'submitted_at'   => now()->subDays(5),
            ]
        );

        ForumDiscussion::firstOrCreate(
            ['referenced_report_id' => $report1->report_id],
            [
                'research_id'          => $r1->research_id,
                'discussion_type'      => 'review_report',
                'title'                => 'Review Report — Reviewer Note',
                'created_at'           => now()->subDays(5),
                'created_by'           => $reviewers[0]->user_id,
            ]
        );

        // ── RESEARCH 2: Interactive Phase ───────────────────────────

        $r2 = ResearchSubmission::firstOrCreate(['title' => 'Blockchain-Based Supply Chain Transparency'], [
            'author_id'           => $authors[1]->user_id,
            'research_field'      => 'Information Systems',
            'status'              => 'pending',
            'review_phase'        => 'interactive',
            'anonymization_model' => 'single',
            'submitted_at'        => now()->subDays(30),
        ]);

        $doc2 = DocumentVersion::firstOrCreate(['research_id' => $r2->research_id, 'version_number' => 1], [
            'pdf_file_path' => 'sample_doc2_v1.pdf',
            'html_content'  => '<html><body><h1>Blockchain-Based Supply Chain Transparency</h1><p>We propose a decentralized ledger system to improve traceability in food supply chains. Smart contracts are used to automate compliance verification. The system was piloted with three regional distributors over six months.</p><p>Results indicate a 40% reduction in traceability time and improved stakeholder trust. Security analysis confirms resistance to common attack vectors.</p></body></html>',
            'html_ready'    => true,
            'uploaded_at'   => now()->subDays(30),
        ]);

        EditorAssignment::firstOrCreate(
            ['research_id' => $r2->research_id, 'editor_id' => $editors[0]->user_id, 'deleted_at' => null],
            ['is_primary' => true, 'assigned_at' => now()->subDays(28)]
        );

        ReviewerAssignment::firstOrCreate(
            ['research_id' => $r2->research_id, 'reviewer_id' => $reviewers[2]->user_id],
            ['assigned_at' => now()->subDays(27), 'deleted_at' => null]
        );
        ReviewerAssignment::firstOrCreate(
            ['research_id' => $r2->research_id, 'reviewer_id' => $reviewers[3]->user_id],
            ['assigned_at' => now()->subDays(27), 'deleted_at' => null]
        );

        $report2 = ReviewReport::firstOrCreate(
            ['research_id' => $r2->research_id, 'reviewer_id' => $reviewers[2]->user_id],
            [
                'summary'        => 'A solid contribution to blockchain applications in logistics.',
                'major_issues'   => 'The threat model does not address 51% attacks adequately.',
                'minor_issues'   => 'The pilot study sample size is small.',
                'recommendation' => 'accept',
                'submitted_at'   => now()->subDays(20),
            ]
        );

        $discussion2 = ForumDiscussion::firstOrCreate(
            ['referenced_report_id' => $report2->report_id],
            [
                'research_id'          => $r2->research_id,
                'discussion_type'      => 'review_report',
                'title'                => 'Review Report — Dr. Soran Ali',
                'created_at'           => now()->subDays(20),
                'created_by'           => $reviewers[2]->user_id,
            ]
        );

        ForumReply::firstOrCreate(
            ['discussion_id' => $discussion2->discussion_id, 'user_id' => $authors[1]->user_id],
            [
                'content'    => 'Thank you for the feedback. We have added a dedicated section addressing 51% attack resistance in the revised version.',
                'created_at' => now()->subDays(15),
            ]
        );

        // Annotation in interactive phase
        $annotation1 = Annotation::firstOrCreate(
            ['document_id' => $doc2->document_id, 'reviewer_id' => $reviewers[2]->user_id, 'text_range_start' => 245, 'text_range_end' => 312],
            [
                'comment'    => 'This claim about 51% attack resistance requires a formal proof or reference.',
                'created_at' => now()->subDays(18),
            ]
        );

        $annDiscussion = ForumDiscussion::firstOrCreate(
            ['referenced_annotation_id' => $annotation1->annotation_id],
            [
                'research_id'              => $r2->research_id,
                'discussion_type'          => 'annotation',
                'title'                    => 'This claim about 51% attack resistance...',
                'created_at'               => now()->subDays(18),
                'created_by'               => $reviewers[2]->user_id,
            ]
        );

        ForumReply::firstOrCreate(
            ['discussion_id' => $annDiscussion->discussion_id, 'user_id' => $authors[1]->user_id],
            [
                'content'    => 'Acknowledged. We will add a formal proof in Section 5 of the revision.',
                'created_at' => now()->subDays(14),
            ]
        );

        // ── RESEARCH 3: Accepted ────────────────────────────────────

        $r3 = ResearchSubmission::firstOrCreate(['title' => 'Federated Learning for Privacy-Preserving Healthcare Analytics'], [
            'author_id'           => $authors[2]->user_id,
            'research_field'      => 'Computer Science',
            'status'              => 'accepted',
            'review_phase'        => 'interactive',
            'anonymization_model' => 'open',
            'submitted_at'        => now()->subDays(60),
            'accepted_at'         => now()->subDays(5),
        ]);

        DocumentVersion::firstOrCreate(['research_id' => $r3->research_id, 'version_number' => 1], [
            'pdf_file_path' => 'sample_doc3_v1.pdf',
            'html_content'  => '<html><body><h1>Federated Learning for Privacy-Preserving Healthcare Analytics</h1><p>This paper demonstrates a federated learning framework that enables collaborative model training across hospital networks without sharing raw patient data. The approach is validated on three real-world clinical datasets and achieves performance comparable to centralized models.</p></body></html>',
            'html_ready'    => true,
            'uploaded_at'   => now()->subDays(60),
        ]);

        EditorAssignment::firstOrCreate(
            ['research_id' => $r3->research_id, 'editor_id' => $editors[2]->user_id, 'deleted_at' => null],
            ['is_primary' => true, 'assigned_at' => now()->subDays(58)]
        );

        // ── RESEARCH 4: Rejected ────────────────────────────────────

        $r4 = ResearchSubmission::firstOrCreate(['title' => 'A Survey of Password Hashing Algorithms'], [
            'author_id'           => $authors[3]->user_id,
            'research_field'      => 'Cybersecurity',
            'status'              => 'rejected',
            'review_phase'        => 'independent',
            'anonymization_model' => 'double',
            'submitted_at'        => now()->subDays(45),
        ]);

        DocumentVersion::firstOrCreate(['research_id' => $r4->research_id, 'version_number' => 1], [
            'pdf_file_path' => 'sample_doc4_v1.pdf',
            'html_content'  => '<html><body><h1>A Survey of Password Hashing Algorithms</h1><p>This survey reviews bcrypt, Argon2, scrypt, and PBKDF2. Each algorithm is analysed for resistance to GPU attacks.</p></body></html>',
            'html_ready'    => true,
            'uploaded_at'   => now()->subDays(45),
        ]);

        EditorAssignment::firstOrCreate(
            ['research_id' => $r4->research_id, 'editor_id' => $editors[1]->user_id, 'deleted_at' => null],
            ['is_primary' => true, 'assigned_at' => now()->subDays(43)]
        );

        // ── RESEARCH 5: Pending — no reviewers yet ──────────────────

        $r5 = ResearchSubmission::firstOrCreate(['title' => 'Real-Time Object Detection on Edge Devices'], [
            'author_id'      => $authors[4]->user_id,
            'research_field' => 'Computer Science',
            'status'         => 'pending',
            'review_phase'   => null,
            'submitted_at'   => now()->subDays(2),
        ]);

        DocumentVersion::firstOrCreate(['research_id' => $r5->research_id, 'version_number' => 1], [
            'pdf_file_path' => 'sample_doc5_v1.pdf',
            'html_content'  => null,
            'html_ready'    => false,
            'uploaded_at'   => now()->subDays(2),
        ]);

        $this->command->info('Development seed data created successfully.');
        $this->command->info('');
        $this->command->info('Test accounts (password: Password123):');
        $this->command->info('  Authors:    sara@university.edu, omar@tech.edu, layla@research.edu, dilan@uni.edu, ravan@college.edu');
        $this->command->info('  Reviewers:  karwan@uni.edu, nadia@tech.edu, soran@research.edu, hana@uni.edu');
        $this->command->info('  Editors:    prof.layla@uni.edu, prof.aram@tech.edu, prof.shilan@uni.edu');
        $this->command->info('');
        $this->command->info('Research states seeded:');
        $this->command->info('  1. Deep Learning in Medical Imaging        → Independent Phase (pending)');
        $this->command->info('  2. Blockchain-Based Supply Chain           → Interactive Phase (pending, has annotations)');
        $this->command->info('  3. Federated Learning for Healthcare       → Accepted (published)');
        $this->command->info('  4. Survey of Password Hashing              → Rejected');
        $this->command->info('  5. Real-Time Object Detection              → Pending (just submitted, no reviewers yet)');
    }
}
