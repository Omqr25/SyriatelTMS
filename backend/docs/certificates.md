# Certificate issuance, preview, and download

## Availability and roles

HR uploads the official PDF; the application does not generate a certificate or
issue one automatically. The existing completion job/scheduled command marks
active applications and their trainees `completed` once their training program
ends. The certificate flow follows that status; it adds no new passing-score rule.

- HR: **Certificates** (`/hr/certificates`) lists completed trainees from all
  cohorts, including closed programs. **Upload certificate** accepts an official
  PDF up to 10 MB. Issuing records today's date and associates the file with the
  trainee and their application’s training program. An issued row replaces the
  upload action; duplicate issuance is rejected. Check the PDF before issuing;
  replacement/revocation is not part of this flow.
- Trainee: **Certificates** (`/trainee/certificates`) is visible in the sidebar.
  Before issuance it explains when a certificate will appear. After HR issues
  one, the page shows its program, issue date, **Preview**, and **Download PDF**.
  Refresh the page after HR issues a certificate. Historical certificates remain
  available; there is no dependency on the current registration window.
- Preview fetches the PDF using the user's bearer token and displays it in an
  in-browser PDF iframe. It does not start a download. Download uses a separate
  action and saves `certificate-{id}.pdf`. Browsers without an embedded PDF viewer
  can use the download option.
- HR can view issued certificates; trainees can access only their own. Supervisors
  and unauthenticated visitors cannot access certificate endpoints. File paths
  and public storage links are not returned by the API.

## Deployment

Deploy backend and frontend together. Run the usual `php artisan migrate` if the
existing certificates table has not yet been created; no new migration is needed.
Files use Laravel's `local` disk (`storage/app/private/certificates` with the
current configuration). Keep that storage writable and persistent across
restarts/deployments, and shared across backend replicas. The existing Docker
Compose app volume covers this directory. No `storage:link` is needed.

Keep the scheduler/queue worker running for the existing completion workflow.
PHP and the reverse proxy must permit requests larger than the 10 MB file limit
(to allow multipart overhead). Keep main frontend `VITE_API_URL` pointing to the
backend `/api/v1` base URL. No mail, PDF renderer, or external service is required.

## Manual test

Use a local/staging database and separate HR and trainee accounts.

1. Choose an active trainee whose application is active and whose training program
   has ended. Let the existing completion workflow run, or run
   `php artisan training-programs:update-status` against the test database. This
   command processes all applicable programs, not just the chosen trainee.
2. Log in as the trainee and open **Certificates**. Expect an empty-state message;
   completing training alone does not create a PDF.
3. Log in as HR, open **Certificates**, find that trainee and program, select
   **Upload certificate**, choose a real PDF under 10 MB, and select
   **Issue certificate**. Expect an issued record and no second upload action.
4. As the trainee, refresh **Certificates**. Select **Preview**. Expect the PDF
   inside a dialog, with no automatic download. Close it, select **Download PDF**,
   and confirm the saved file is the uploaded PDF.
5. Reload both pages. Expect the certificate to remain available. Confirm that a
   second trainee sees only their own certificates, and that manually requesting
   another trainee's preview/download URL returns HTTP 403.
6. Verify that active/fired trainees are not upload candidates. The API rejects
   issuance for an unfinished trainee (422), duplicate issuance (409), non-PDF or
   oversized uploads (422), and missing stored files (404 with a helpful message).
7. Switch English/Arabic and test a narrow browser window. The same actions should
   remain available. Try a browser without embedded PDF support using Download PDF.

## Automated checks

From `backend`, run `php artisan test --filter=CertificateTest` (or the full suite).
The tests use in-memory SQLite, fake private storage and uploaded files. They cover
completion-to-eligibility, issuance, validation, duplicate prevention, ownership,
role restrictions, missing files and inline versus attachment response headers.
They do not modify real records or upload real certificates.

From `frontend/main`, run `npm ci` and `npm run build`. Browser checks during
implementation used mocked API responses with a small valid PDF to exercise HR
upload, preview, byte-verified download, authenticated requests, trainee empty and
missing-file states, and Arabic mobile layout. Confirm your deployed filesystem
and browser PDF support with the manual test above.
