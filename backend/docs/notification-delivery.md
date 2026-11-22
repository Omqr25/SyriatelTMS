# Notification delivery

Laravel stores inbox items and per-recipient read state before sending push. The
inbox and its 30-second visible-tab refresh can operate independently of Firebase.

## Configuration

- Set frontend build-time `VITE_API_URL` to the Laravel API URL ending in
  `/api/v1`. If omitted, route the frontend origin's `/api/v1/*` requests to
  Laravel. Serve these routes ahead of the application's HTML fallback.
- For browser push, set `VITE_API_KEY`, `VITE_PROJECT_ID`,
  `VITE_MESSAGING_SENDER_ID`, `VITE_APP_ID`, and `VITE_VAPID_KEY` before the
  frontend build. Deploy `firebase-messaging-sw.js` and the generated
  `firebase-worker-config.js` and Firebase worker libraries from the same
  build at the root of the frontend HTTPS origin.
- Set `FIREBASE_CREDENTIALS` or `GOOGLE_APPLICATION_CREDENTIALS` to a readable
  service account file, or set `FIREBASE_SERVICE_ACCOUNT_JSON` to its JSON
  contents. Use credentials for the same Firebase project as the frontend.
  These values belong in the backend and background service environments.

## Release and health checks

1. Publish the frontend and its worker before switching the backend to the new
   data-only push format. Reload the frontend to register the new worker before
   checking push. Apply the Laravel migration that adds delivery outcomes.
2. Start separate services with `sh start-worker.sh queue` and
   `sh start-worker.sh scheduler` from the backend directory. Compose defines
   both services. Configure them separately from the Railway web process and
   use the same database, queue, credentials, and application release.
3. Check `notification_deliveries` and the Laravel queue backlog. Count due
   entries and monitor how long the oldest one has been due. The scheduler
   runs recovery every minute and checks HR registration reminders hourly.
   Monitor the scheduler process and its command logs.
4. Interpret `accepted` as Firebase submission accepted for at least one
   device. `accepted_count` and `rejected_count` reflect partial outcomes.
   Review `retryable_failure` and `retry_exhausted` with `last_error`, and
   `permanent_rejection` separately from `no_devices`. Legacy delivered and
   failed rows retain their timestamps and have no outcome classification.

Verify an inbox item and unread count with push turned off, then enable push and
test a task or announcement while the browser is visible and while it is in
the background. Verify the click opens the role's inbox and that signing out
prevents further display for that account on the browser.
