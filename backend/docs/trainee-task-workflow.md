# Trainee task workflow (UC-TE#03, #04, #05, #06, #16)

## Where to find it

In **frontend/main**, sign in as a trainee and open **Training Program**. The page defaults to **My tasks**; **Team tasks** includes tasks from the same supervisor, department, and training cycle. Expand a phase and select a task.

The task workspace shows instructions, resources, deadline, priority, assignee, progress, and optional subtasks. The **Next step** panel offers the action permitted by the backend. **Submissions & feedback** keeps each of the trainee's submissions and the corresponding supervisor feedback, newest first. Teammates may view task details but cannot see private submissions/feedback or change the task.

Supervisors can define optional subtasks in the existing create/edit task dialog. Subtasks are a supervisor-maintained checklist, read-only for trainees; their checkmarks do not approve the overall task.

## State rules

- Existing database status `pending` represents **Assigned**. Only the active assignee can start it. Starting records `started_at` once and changes the status to `in progress`.
- An in-progress task requires one to three files per submission. PDFs, JPEGs, PNGs, WebP images, and GIFs are accepted, up to 10 MB each. Submission creates a new evidence record with its submission timestamp and changes the task to `for review`.
- Only the owning supervisor can review the latest evidence. **Request Revision** changes the task to `need fix`; **Evaluate Task** approves it as `completed` and records `completed_at`.
- `need fix` permits revised evidence only when the latest evidence has a revision-request feedback. Resubmission preserves earlier evidence and feedback and returns the task to `for review`.
- A passed deadline blocks starting/uploading even if the scheduler has not yet updated the displayed database status. Already submitted work remains reviewable. Completed, cancelled, and for-review tasks are not overwritten by overdue jobs.
- Submission time is shown on each evidence entry; approved completion is separate. A trainee's upload never marks the task approved.
- Each submission creates an in-app notification for the supervisor. FCM push is queued after commit; a push outage cannot remove evidence or roll back the recorded submission.

## Deployment

Deploy backend and frontend together: starting a task now uses `POST /api/v1/task/tasks/{id}/start` (formerly GET).

From `backend`:

```sh
php artisan migrate
php artisan optimize:clear
php artisan queue:restart
```

The migration adds nullable `tasks.subtasks` JSON; existing tasks need no backfill. Continue running the configured queue worker and Laravel scheduler (`schedule:run` every minute, or `php artisan schedule:work` locally). The scheduler now also updates overdue tasks. Configure Firebase/device tokens for browser push; in-app notifications are stored independently. PHP/web-server upload limits must allow up to 30 MB plus multipart overhead per request.

From `frontend/main`:

```sh
npm ci
npm run build
```

## Manual acceptance test

Use an active trainee assigned to a supervisor and department in the current training cycle. Create that supervisor's program/phase and a task for this trainee with a future deadline. Add a resource URL and two subtasks. Use a second trainee in the same team and one outside it for access checks.

1. **UC-TE#03:** As the assignee, open Training Program → phase → task. Confirm all details, resources, subtasks, and assigned status. Switch to Team tasks and view a teammate's task: it is read-only and has no submissions/feedback section. Another team/cycle must not be able to open the task URL.
2. **UC-TE#04:** Click Start task. Confirm In progress and a recorded start time. Refresh: the state persists and Start is no longer offered.
3. **UC-TE#05:** Try an unsupported file, an oversized file, and four files at once. Each attempt must be rejected. Submit a PDF and a screenshot together. Confirm For review, a timestamped submission, authenticated download, and no duplicate upload action. Refresh and verify persistence.
4. **UC-TE#06:** As the supervisor, open the same task, download the evidence, and choose Request Revision with feedback. As the trainee, refresh and confirm the feedback and Changes requested status.
5. **UC-TE#16:** Upload corrected evidence using Resubmit corrected evidence. Confirm For review, two downloadable submissions, and the original feedback still present. As the supervisor, verify the new in-app notification and review the newest submission.
6. Approve the newest evidence using Evaluate Task. Confirm Completed and approved-completion time in the trainee view, with all history preserved and no start/upload controls.
7. Try another pending/in-progress task after its deadline. Starting and uploading must be blocked. A previously submitted task must remain reviewable after its deadline.
8. Repeat the UI checks in Arabic and English, including a phone-width viewport. Verify My tasks/Team tasks filtering, collapsible phases, error/retry states, and authenticated downloads.

## Automated verification

```sh
# backend (test database is in-memory SQLite per phpunit.xml)
php artisan test --filter=TraineeTaskWorkflowTest
php artisan test

# frontend/main
npm test -- --run
npm run build
```

The task feature tests cover the full revision cycle, duplicate actions, deadline/active-status restrictions, team/cycle access, private evidence access, foreign-evidence review rejection, subtasks, durable notifications during a push failure, and overdue job safety. Browser smoke checks were also performed with mocked API responses for trainee and supervisor flows, downloads, refresh persistence, validation, and Arabic mobile rendering. Real FCM delivery requires testing with the deployment's configured credentials and registered devices.
