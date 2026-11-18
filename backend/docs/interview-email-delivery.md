# UC-7: interview email delivery

After UC-6 saves an appointment, the backend queues one `SendInterviewEmailJob`
for the applicant and another for the scheduled supervisor, after the transaction
commits. Each job retries independently (three attempts, backoff 10/60/300 seconds).
The supervisor's SMTP failure therefore does not block the applicant's job.
Messages include applicant name, supervisor department, date, time and location.
The database queue provides at-least-once delivery, not exactly-once delivery: a
provider timeout after accepting a message can still lead to a duplicate on retry.

## Diagnose on the backend that actually scheduled the interview

Run inside `backend/`, or in the backend app container:

```sh
php artisan interviews:mail-check
```

This reads effective cached configuration and prints no credentials. It does not
send a message or prove that Gmail received anything. `MAIL_MAILER=log` writes
messages into Laravel logs; `array` keeps them in memory. Neither delivers email.
A successful HTTP scheduling response confirms the appointment, not inbox delivery.

## Configure a real provider

Receiving mail in Gmail does not require using Gmail as the sender. Use the
provider already used by this backend. Never commit real credentials to GitHub.

For Gmail SMTP, configure the backend's private environment:

```dotenv
MAIL_MAILER=smtp
MAIL_SCHEME=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-sender@gmail.com
MAIL_PASSWORD=your-google-app-password
MAIL_FROM_ADDRESS=your-sender@gmail.com
MAIL_FROM_NAME="Syriatel Training Team"
MAIL_TIMEOUT=15
QUEUE_CONNECTION=database
```

Use a Google App Password for an account that supports it (with two-step
verification), not the normal Google account password. The SMTP transport uses
STARTTLS when supported on port 587. A corporate SMTP service can instead use its
own host, port and credentials. Do not disable certificate verification.

Alternatively, this project already supports Resend:

```dotenv
MAIL_MAILER=resend
RESEND_API_KEY=your-private-provider-key
MAIL_FROM_ADDRESS=interviews@your-verified-domain.example
MAIL_FROM_NAME="Syriatel Training Team"
QUEUE_CONNECTION=database
```

Use a verified sending domain and a sender authorized by the provider; a test
sender may restrict recipients. Gmail delivery can also depend on provider
suppression lists, SPF/DKIM/DMARC, spam filtering and the destination address.

After changing the backend environment:

```sh
php artisan config:clear
php artisan config:cache
php artisan queue:restart
php artisan interviews:mail-check
```

## Run the worker

With `QUEUE_CONNECTION=database`, saving appointments only inserts pending jobs.
A worker must run using the same code, database and mail configuration as the web
backend. For local XAMPP, keep this running in a separate terminal:

```sh
php artisan queue:work --queue=default --tries=3 --timeout=90
```

If using this repository's Docker Compose setup, start its existing worker:

```sh
docker compose up -d --build syriatel_tms_app syriatel_tms_worker
```

In production, run that worker under the hosting platform's worker service or a
process manager. Render static frontend deployments cannot run Laravel queue
workers or change the mail configuration of the separate backend host.

`QUEUE_CONNECTION=sync` runs delivery inside the request and provides no automatic
background retries; a provider error may return an HTTP error after the appointment
has already been committed. Use a real persistent queue for production.

## Existing failures and verification

```sh
php artisan queue:failed
```

Fix the provider/worker issue before retrying specific failed mail jobs with
`php artisan queue:retry JOB_ID`. Legacy interview jobs without a recipient remain
compatible and fan out to separate applicant/supervisor jobs. Do not retry all jobs
blindly; account-creation or other notifications may also be present. An appointment
whose messages were processed with the log mailer has no failed job to retry.

For a controlled verification, schedule with test accounts you own, check the mail
provider's delivery event for both recipients, and check Gmail Inbox and Spam.
A provider's accepted/delivered status is different from inbox placement. Do not
share passwords, API keys, database credentials or full applicant records in chat.
