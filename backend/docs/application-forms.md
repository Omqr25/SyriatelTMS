# Configurable application questions

Each training intake can have one form. The HR Registration page can save and reorder draft questions, set required fields and optional numeric bounds, and publish the saved draft before registration first opens. Publishing the intake to the calendar leaves its question draft editable until registration first opens. HR must confirm the history before publishing a future legacy intake whose registration history is unknown. A scheduled intake saved as a draft can have its questions published before the intake is restored to the calendar.

Publication freezes the prompt, type, bounds, order, and options. Schedule changes, archive, and restore retain the form. Form saves and publication take the intake lock first and then the form lock. Their required draft revision rejects stale writes. Submissions resolve the published form under the same intake lock.

## Submission and reads

With `include=application_form`, `GET /api/v1/training_program/openProgram` includes the open intake's published form or `null`. The standard response keeps its existing shape. During registration, the guest uses the original fixed fields and files plus the questions from the published form, when present.

`POST /api/v1/application` accepts the form ID and an `answers` multipart field containing a JSON array. Entries contain `question_id` and exactly one of `text_value`, `numeric_value`, or `option_ids`. Choice IDs form an array, including for single choice. A missing or different form ID for a configured intake returns a conflict and requires reloading the current form. Extra answers are optional only when the applicable question allows omission. A request with no form and no extra answers still works for an unconfigured intake.

The number and both bounds use signed `DECIMAL(12,4)`. Send fractional values as decimal strings. Whole JSON integers are accepted as well. Decimal strings use a period as the separator, up to eight whole digits and four fractional digits, and may use an explicit sign. Scientific notation and rounding are outside the API contract. A number can have either bound, both, or neither. Text is trimmed and limited to 100 characters. Blank optional text is omitted. A blank required text answer fails validation.

`GET /api/v1/application/{id}/answers` is available to HR and to the supervisor currently assigned to review that application. It returns the pinned form ID, original prompts, and stored answers with the original option labels. Legacy rows return a null form ID and an empty array. General application resources and reports do not include the extra answers. Prompts and labels display as entered by HR.

## Database constraints and lifecycle

- MySQL enforces one form per intake and requires a non-null application form link to belong to that application's intake. A null link is valid for pre-feature rows and intakes without a published form.
- MySQL and the SQLite tests enforce one answer per application/question and one row per selected option. Their composite foreign keys require each answer's application and question to share a form, and each selection's answer and option to share a question. SQLite uses an inline nullable FK on the existing applications table. Laravel also checks the intake/form match on that driver.
- Laravel checks role permissions, publication, stale form identity, allowed field shapes, bounds, required answers, and choice counts. MySQL does not enforce these rules through triggers or cross-table checks.
- An existing permitted application deletion cascades to its answers and their selections. Pinned forms and referenced definitions are restricted. The intake deletion flow may remove an unpublished draft from a deletable intake. It preserves published forms.
- Application rows, final attachment path values, answers, and selections share a database transaction. Uploads are staged in the private disk under a unique submission directory. Failed submissions remove staged files after a confirmed rollback. If the commit outcome cannot be determined, retain staged files and reconcile them with the persisted attachment paths before removing any files.

## Upgrade and recovery

Run the additive migrations in filename order. The first creates the form definitions, the second links the existing applications table, and the third adds answers and selections. The application link is nullable and there is no legacy backfill.

Use one migration runner. The Docker and Railway startup scripts can run migrations automatically. Verify the deployed engine and MySQL version, then rehearse the applications ALTER and its lock behavior on a representative disposable database before scheduling it. If a migration is interrupted, compare the migrations ledger with the actual tables, columns, indexes, and foreign keys before resuming missing operations. MySQL DDL committed before the failure can survive the failed migration.

Deploy the schema and backend before the guest and HR clients. Enable publication after the updated clients are available. If reverting the application after submissions are collected, retain the schema and files. Pause submissions for configured intakes if the rollback removes the form-aware writer. The down migrations drop collected answers, selections, and definitions. Use a separate authorized data-preserving recovery plan before removing schema that contains submissions or drafts.
