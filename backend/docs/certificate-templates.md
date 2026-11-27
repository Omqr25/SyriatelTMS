# HR certificate templates

Phase 2 adds template management to HR → Certificates. The Issuance and Archive tabs use the existing manual uploader and issued-certificate list. Trainee certificate access is unchanged.

## Local setup

After the Phase 1 migrations have run, install the locked dependencies:

```sh
cd backend
composer install
php artisan migrate
cd ../frontend/main
npm ci
npm run dev
```

Run the backend using the existing local PHP entry point and configure the application's normal API URL and authentication. Uploads and authoritative proofs require a reachable PHP CLI with GD, mbstring and the same Composer vendor directory as the web app. Set `CERTIFICATE_PHP_CLI` to the absolute PHP CLI path if the web runtime cannot discover it. On Windows/Laragon verify `proc_open`, CLI extensions, private-directory access and process timeout behavior. Configure the web request timeout above the 12-second child deadline. No certificate queue worker is needed.

## Manual verification

1. Sign in as HR and open Certificates → Templates. Create a template, enter a name and upload a single-page PDF, PNG or JPG.
2. Add a text box. Drag it, resize it, add a paragraph and insert a placeholder at the caret. Select part of the text or a placeholder to format it. Change font, zoom and sample length. Use Undo and Redo.
3. Generate a PDF proof. Compare it with the interactive canvas. Shrink the box to test the overflow warning and proof rejection. Enable auto-fit and check that it respects the minimum size.
4. Save, reopen, and confirm the same content and positions. Save a change to create another version. Duplicate the template, search by name, and archive it. Check the archived list and confirm the original version still has its private background.
5. Switch the application to Arabic. Repeat preview at a narrow width. The canvas remains English and LTR.

## Bounds and operational notes

Uploads use a private, HR-owned one-hour draft before being promoted to an immutable version. Expired drafts are pruned on later uploads in the draft-only directory. Proofs use private request scratch space and are removed at request end. Deployment cleanup for abandoned request scratch or expired drafts must stay within those two directories. Preserve `certificates/templates`, `certificates/issued`, and legacy certificate paths.

The child uses a 128 MiB PHP memory limit and a 12-second wall-clock limit. Input and processed output are limited to 5 MB. Raster dimensions are checked before decoding and re-encoding. Images use their supported GD resolution or 96 DPI; PDF size is based on the physical crop box after rotation. Only supported passive single-page PDFs are imported. PDF actions, annotations, attached files, unsupported compression, encryption, and external streams are rejected. Import preserves vector artwork. Browser background preview uses PDF.js; it rasterizes only the background for display. The explicit proof keeps positioned text selectable and embedded in the PDF.

The metrics manifest records the hashes of the installed Composer fonts. If a font changes, regenerate metrics with FontTools using the Phase 0.1 fixture generator, rerun parity checks before deployment, and preserve the asset version for older templates.

## Automated verification

From `backend` with Composer dependencies installed:

```sh
APP_ENV=testing DB_CONNECTION=sqlite DB_DATABASE=:memory: DB_URL= \
  php vendor/bin/phpunit --do-not-cache-result \
  tests/Feature/CertificateTemplatesTest.php \
  tests/Feature/CertificateFoundationTest.php \
  tests/Feature/CertificateTest.php
```

From `frontend/main`, run `npm test` and `npm run build`.
