# Password reset for the main frontend

The login page links to `/forgot-password`. That page posts an email to
`POST /api/v1/auth/forgotPassword`. Laravel emails a single-use token; the
`/reset-password?token=...&email=...` page posts `email`, `token`, `password`, and
`password_confirmation` to `POST /api/v1/auth/reset-password`.

## Deployment configuration

- Set backend `FRONTEND_URL` to the main frontend's base URL (for example,
  `https://main.example.com`, or `http://localhost:5173` locally). It must point
  to the main app, not the guest frontend. Clear/rebuild Laravel's config cache
  after changing it. Without this setting, links retain the existing backend
  Blade reset form as a fallback.
- Keep backend `APP_URL` set to the backend URL.
- Configure a working backend mail transport and sender (`MAIL_MAILER` and the
  corresponding SMTP or Resend settings). The default `log` mailer writes links
  to the application log instead of delivering email.
- Set main frontend `VITE_API_URL` to the backend API base ending in `/api/v1`.
- Configure the frontend host to serve `index.html` for direct visits to
  `/forgot-password` and `/reset-password`, as for the existing React routes.

Passwords require at least eight characters and matching confirmation. Tokens
expire after 60 minutes and are deleted after a successful reset. Laravel's
password broker limits repeat link requests for an account to once per minute.
Successful resets return the user to the login flow; they do not log in automatically.

## Verification

Run `php artisan test --filter=PasswordResetTest` from `backend` with the project's
PHP dependencies and SQLite extension installed. The tests use an in-memory
database and fake notifications; no email is sent. They cover frontend email
URLs, link-request throttling, password hashing, token expiry, wrong-account and
invalid tokens, token reuse, and request validation.

Run `npm ci` and `npm run build` in `frontend/main`. For a deployment smoke test,
request a reset using a test account, open the delivered email link, submit a new
password, then log in using it. Confirm that the previous password and reused
link fail. Real email delivery depends on the deployed mail configuration.
