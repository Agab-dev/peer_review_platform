# Collaborative Platform for Academic Research and Peer Review
## Backend — Laravel 12

---

## Requirements

- PHP 8.2+
- Composer
- PostgreSQL 14+
- `poppler-utils` (for PDF-to-HTML conversion)

Install poppler-utils:
```bash
# Ubuntu / Debian
sudo apt-get install poppler-utils

# macOS
brew install poppler
```

---

## Setup

### 1. Install dependencies
```bash
composer install
```

### 2. Configure environment
```bash
cp .env.example .env
php artisan key:generate
php artisan jwt:secret
```

Edit `.env` and set your PostgreSQL credentials:
```
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=peer_review_platform
DB_USERNAME=postgres
DB_PASSWORD=your_password
```

### 3. Create the database
```sql
CREATE DATABASE peer_review_platform;
```

### 4. Run migrations and seed
```bash
php artisan migrate
php artisan db:seed
```

This creates:
- The EIC account: `eic@peerreview.edu` / `EicPassword123`
- 5 test authors, 4 reviewers, 3 editors
- 5 research submissions in various states (independent phase, interactive phase, accepted, rejected, pending)

### 5. Start the development server
```bash
php artisan serve
```

### 6. Start the queue worker (required for PDF-to-HTML conversion)
In a **separate terminal**:
```bash
php artisan queue:work
```

The queue worker processes uploaded PDFs asynchronously.
`html_ready` on the document version will flip to `true` once conversion completes.

---

## API Base URL

```
http://localhost:8000/api/v1/
```

All endpoints are documented in `API_Spec_v3.docx`.

---

## Authentication

All protected endpoints require:
```
Authorization: Bearer {token}
```

Get a token via `POST /api/v1/auth/login` or `POST /api/v1/auth/register`.

---

## Test Accounts

All test accounts use password: `Password123`

| Role     | Email                    |
|----------|--------------------------|
| EIC      | eic@peerreview.edu       |
| Author   | sara@university.edu      |
| Author   | omar@tech.edu            |
| Author   | layla@research.edu       |
| Author   | dilan@uni.edu            |
| Author   | ravan@college.edu        |
| Reviewer | karwan@uni.edu           |
| Reviewer | nadia@tech.edu           |
| Reviewer | soran@research.edu       |
| Reviewer | hana@uni.edu             |
| Editor   | prof.layla@uni.edu       |
| Editor   | prof.aram@tech.edu       |
| Editor   | prof.shilan@uni.edu      |

EIC password: `EicPassword123`

---

## Seeded Research States

| # | Title                                        | Status   | Phase       |
|---|----------------------------------------------|----------|-------------|
| 1 | Deep Learning in Medical Imaging             | pending  | independent |
| 2 | Blockchain-Based Supply Chain Transparency   | pending  | interactive |
| 3 | Federated Learning for Healthcare Analytics  | accepted | interactive |
| 4 | A Survey of Password Hashing Algorithms      | rejected | independent |
| 5 | Real-Time Object Detection on Edge Devices   | pending  | null        |

---

## Key Design Notes

- **PDF storage**: `storage/app/pdfs/` (private, not web-accessible)
- **HTML storage**: Stored in the `html_content` database column after conversion
- **Queue driver**: `database` — jobs stored in the `jobs` PostgreSQL table
- **JWT**: Tokens expire after 1440 minutes (24 hours) by default (`JWT_TTL` in `.env`)
- **Partial unique indexes**: `editor_assignments` and `reviewer_assignments` use PostgreSQL partial indexes (`WHERE deleted_at IS NULL`) to allow reassignment after soft-deletion
- **Anonymization**: Resolved per-request in `AnonymizationService` — never stored in the database

---

## Useful Artisan Commands

```bash
# Re-run migrations from scratch (development only)
php artisan migrate:fresh --seed

# Monitor queue jobs
php artisan queue:monitor

# Clear failed jobs
php artisan queue:flush
```
