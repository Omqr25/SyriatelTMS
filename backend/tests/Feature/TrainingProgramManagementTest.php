<?php

namespace Tests\Feature;

use App\Jobs\CloseTrainingApplicationsJob;
use App\Jobs\OpenTrainingApplicationsJob;
use App\Mail\ProgramDatesChangedMail;
use App\Models\Application;
use App\Models\Certificate;
use App\Models\Department;
use App\Models\Notification;
use App\Models\Program;
use App\Models\Role;
use App\Models\Trainee;
use App\Models\Trainer;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Services\Training\TrainingProgramService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TrainingProgramManagementTest extends TestCase
{
    use RefreshDatabase;

    private const API = '/api/v1/training_program';

    private const FIELDS = [
        'name' => 'Winter Training', 'application_start_date' => '2030-01-10', 'application_end_date' => '2030-01-20',
        'start_date' => '2030-02-01', 'end_date' => '2030-02-28',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        config(['jwt.secret' => str_repeat('test-secret-', 8)]);
        $this->travelTo(Carbon::parse('2030-01-01'));
        Queue::fake();
        Mail::fake();
    }

    private function login(string $role = 'HR', bool $admin = false): User
    {
        $user = User::factory()->create(['training_program_admin' => $admin]);
        $user->roles()->attach(Role::firstOrCreate(['role' => $role]));
        $this->actingAs($user, 'api');

        return $user;
    }

    private function createProgram(array $overrides = []): TrainingProgram
    {
        $id = $this->postJson(self::API, array_replace(self::FIELDS, $overrides))->assertCreated()->json('data.0.id');

        return TrainingProgram::findOrFail($id);
    }

    public function test_hr_can_edit_and_conditionally_delete_without_a_separate_grant(): void
    {
        $this->postJson(self::API, self::FIELDS)->assertUnauthorized();
        $this->putJson(self::API.'/1', ['name' => 'Different'])->assertUnauthorized();
        $this->deleteJson(self::API.'/1')->assertUnauthorized();

        $this->login();
        $this->postJson(self::API, self::FIELDS)->assertCreated()
            ->assertJsonPath('data.0.has_applicants', false)
            ->assertJsonPath('data.0.delete_block', null);
        $program = TrainingProgram::sole();
        $url = self::API.'/'.$program->id;
        $this->getJson(self::API.'/management/list')->assertOk()
            ->assertJsonPath('data.can_manage', true)
            ->assertJsonPath('data.can_archive', true)
            ->assertJsonPath('data.programs.0.delete_block', null);
        $this->getJson(self::API)->assertOk()->assertJsonMissingPath('data.0.delete_block');
        $this->putJson($url, ['name' => 'Different'])->assertOk()->assertJsonPath('data.name', 'Different');

        foreach (['SUPERVISOR', 'TRAINEE'] as $role) {
            $this->login($role, admin: true);
            $this->getJson(self::API.'/management/list')->assertForbidden();
            $this->postJson(self::API, self::FIELDS)->assertForbidden();
            $this->putJson($url, ['name' => 'Other'])->assertForbidden();
            $this->deleteJson($url)->assertForbidden();
            $this->postJson($url.'/archive')->assertForbidden();
            $this->postJson($url.'/restore')->assertForbidden();
        }

        $this->login();
        $this->deleteJson($url)->assertOk();
    }

    public function test_create_and_partial_update_enforce_dates_normalized_duplicates_and_both_overlap_rules(): void
    {
        $this->login();
        $this->postJson(self::API, [])->assertUnprocessable()->assertJsonValidationErrors(array_keys(self::FIELDS));
        $program = $this->createProgram(['name' => '  Winter   Training ']);
        $this->assertSame('Winter Training', $program->name);
        $url = self::API.'/'.$program->id;
        $this->postJson(self::API, array_replace(self::FIELDS, ['name' => 'WINTER  training']))->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->postJson(self::API, array_replace(self::FIELDS, ['name' => 'Other']))->assertUnprocessable()->assertJsonValidationErrors('application_start_date');
        $this->putJson($url, ['start_date' => '2030-01-20'])->assertUnprocessable()->assertJsonValidationErrors('start_date');
        $this->putJson($url, ['end_date' => '2030-01-31'])->assertUnprocessable()->assertJsonValidationErrors('end_date');
        $this->putJson($url, ['application_start_date' => '2030-01-21'])->assertUnprocessable()->assertJsonValidationErrors('application_end_date');
        $next = $this->createProgram([
            'application_start_date' => '2030-01-21', 'application_end_date' => '2030-01-31',
            'start_date' => '2030-03-01', 'end_date' => '2030-03-31',
        ]);
        $this->putJson(self::API.'/'.$next->id, ['start_date' => '2030-02-28'])->assertUnprocessable()->assertJsonValidationErrors('start_date');
        $this->assertDatabaseCount('training_program_audits', 2);
    }

    public function test_audited_writes_check_revision_and_allow_deletion_one_second_before_opening(): void
    {
        $actor = $this->login();
        $program = $this->createProgram();
        $url = self::API.'/'.$program->id;
        $this->putJson($url, ['name' => 'Renamed', 'expected_revision' => 1])->assertOk()->assertJsonPath('data.revision', 2);
        $this->putJson($url, ['name' => 'Stale', 'expected_revision' => 1])->assertConflict();
        $this->deleteJson($url, ['expected_revision' => 1])->assertConflict();
        $this->travelTo(Carbon::parse('2030-01-09 23:59:59'));
        $this->deleteJson($url, ['expected_revision' => 2])->assertOk();
        $this->deleteJson($url)->assertNotFound();
        $this->assertDatabaseMissing('training_programs', ['id' => $program->id]);
        $audit = DB::table('training_program_audits')->orderBy('id')->get();
        $this->assertSame(['create', 'update', 'delete'], $audit->pluck('action')->all());
        $this->assertSame([$actor->id], $audit->pluck('actor_id')->unique()->values()->all());
        $this->assertSame('Winter Training', json_decode($audit[1]->before_values, true)['name']);
        $this->assertSame('Renamed', json_decode($audit[2]->before_values, true)['name']);
    }

    public static function phases(): array
    {
        return [
            ['2030-01-09', 'upcoming'], ['2030-01-10', 'registration_open'], ['2030-01-20 23:59:59', 'registration_open'],
            ['2030-01-21', 'registration_closed'], ['2030-02-01', 'in_progress'], ['2030-02-28', 'in_progress'], ['2030-03-01', 'completed'],
        ];
    }

    #[DataProvider('phases')]
    public function test_hr_can_edit_dates_in_every_phase_and_postponing_registration_never_unlocks_deletion(string $at, string $phase): void
    {
        $this->login();
        $program = $this->createProgram();
        $url = self::API.'/'.$program->id;
        $this->travelTo(Carbon::parse($at));
        $this->getJson($url)->assertJsonPath('data.phase', $phase);
        $this->getJson($url.'/checkProgramStatus')->assertJsonPath('data', $phase === 'registration_open' ? 'Open' : 'Closed');
        if ($phase !== 'upcoming') {
            $this->deleteJson($url)->assertConflict()->assertJsonPath('data.reason', 'opened');
        }
        $this->putJson($url, [
            'application_start_date' => '2031-01-01', 'application_end_date' => '2031-01-31',
            'start_date' => '2031-02-01', 'end_date' => '2031-03-31',
            'registration_has_opened' => false,
        ])->assertOk()->assertJsonPath('data.phase', 'upcoming');
        if ($phase === 'upcoming') {
            $this->deleteJson($url)->assertOk();
        } else {
            $this->deleteJson($url)->assertConflict()->assertJsonPath('data.reason', 'opened');
        }
    }

    public function test_applicants_require_confirmation_for_date_changes_and_other_linked_records_block_deletion(): void
    {
        $this->login();
        $program = $this->createProgram();
        $url = self::API.'/'.$program->id;
        Application::factory()->forProgram($program)->create();
        $this->getJson(self::API.'/management/list')->assertJsonPath('data.programs.0.has_applicants', true);
        $this->deleteJson($url)->assertConflict()->assertJsonPath('data.reason', 'linked');
        $this->putJson($url, ['name' => 'Renamed'])->assertOk();
        $dates = ['end_date' => '2030-03-01'];
        $this->putJson($url, $dates)->assertConflict()->assertJsonPath('data.reason', 'confirm_dates');
        $this->assertSame('2030-02-28', $program->fresh()->end_date->toDateString());
        $this->putJson($url, $dates + ['confirm_date_change' => true])->assertOk()->assertJsonPath('data.end_date', '2030-03-01');

        $second = $this->createProgram([
            'application_start_date' => '2031-01-01', 'application_end_date' => '2031-01-31',
            'start_date' => '2031-02-01', 'end_date' => '2031-03-31',
        ]);
        Program::factory()->create(['training_program_id' => $second->id, 'department_id' => Department::factory()->create()->id]);
        $this->deleteJson(self::API.'/'.$second->id)->assertConflict()->assertJsonPath('data.reason', 'linked');
    }

    public function test_legacy_unknown_history_cannot_be_overwritten_and_deleted(): void
    {
        $this->login();
        $program = $this->createProgram();
        DB::table('training_programs')->where('id', $program->id)->update(['registration_has_opened' => null]);
        $url = self::API.'/'.$program->id;
        $this->putJson($url, ['name' => 'Updated', 'registration_has_opened' => false])->assertOk();
        $this->deleteJson($url)->assertConflict()->assertJsonPath('data.reason', 'unknown_history');
    }

    public function test_program_selection_falls_back_to_ongoing_training_and_handles_no_eligible_cycle(): void
    {
        $hr = $this->login();
        $program = $this->createProgram();
        $this->createProgram([
            'draft' => true,
            'name' => 'Future draft',
            'application_start_date' => '2030-03-02',
            'application_end_date' => '2030-03-05',
            'start_date' => '2030-03-06',
            'end_date' => '2030-03-31',
        ]);
        $supervisor = $this->login('SUPERVISOR');
        Trainer::factory()->create([
            'user_id' => $supervisor->id,
            'department_id' => Department::factory()->create()->id,
        ]);
        $service = app(TrainingProgramService::class);

        foreach (['2030-02-28 15:00:00' => $program->id, '2030-03-01' => null] as $time => $expectedProgramId) {
            $this->travelTo(Carbon::parse($time));
            $this->assertSame($expectedProgramId, $service->upcomingTrainingProgram()?->id);

            $this->actingAs($hr, 'api');
            $this->getJson('/api/v1/application/assignedAndQualifiedApplications')->assertOk()->assertJsonPath('data', []);
            $this->getJson('/api/v1/application/interviewApplications')->assertOk()->assertJsonPath('data', []);

            $this->actingAs($supervisor, 'api');
            $this->getJson('/api/v1/application/trainer/assigned')->assertOk()->assertJsonPath('data', []);
            $this->getJson('/api/v1/application/trainer/interview')->assertOk()->assertJsonPath('data', []);
        }
    }

    public function test_hr_can_save_a_draft_and_publish_it_when_ready(): void
    {
        $this->login();
        $this->travelTo(Carbon::parse('2030-01-10'));
        $draft = $this->createProgram(['draft' => true]);
        $this->assertSame('archived', $draft->phase());
        $this->assertFalse($draft->registration_has_opened);
        $this->getJson(self::API.'/openProgram')->assertNotFound();
        $this->getJson(self::API.'/management/list')->assertJsonPath('data.programs.0.phase', 'archived');
        $this->postJson(self::API.'/'.$draft->id.'/restore')->assertOk()->assertJsonPath('data.phase', 'registration_open');
        $this->getJson(self::API.'/openProgram')->assertJsonPath('data.id', $draft->id);
        $this->deleteJson(self::API.'/'.$draft->id)->assertConflict()->assertJsonPath('data.reason', 'opened');
    }

    public function test_registration_reminders_reach_only_active_hr_once_per_program_date(): void
    {
        $hr = $this->login();
        $draft = $this->createProgram(['draft' => true]);
        $this->login('SUPERVISOR');
        $inactiveHr = $this->login();
        $inactiveHr->update(['status' => 'inactive']);

        $this->travelTo(Carbon::parse('2030-01-02'));
        $this->artisan('training-programs:remind-hr')->assertSuccessful();
        $this->assertDatabaseCount('notifications', 0);

        $this->travelTo(Carbon::parse('2030-01-03'));
        $this->artisan('training-programs:remind-hr')->assertSuccessful();
        $this->artisan('training-programs:remind-hr')->assertSuccessful();
        $notice = Notification::where('type', 'training_program_registration_reminder')->sole();
        $this->assertNull($notice->notifier_id);
        $this->assertSame($draft->id, $notice->metadata['training_program_id']);
        $this->assertStringContainsString('publish', $notice->description);
        $this->assertSame([$hr->id], $notice->notified()->pluck('notified_id')->all());

        $this->actingAs($hr, 'api');
        $this->putJson(self::API.'/'.$draft->id, ['application_start_date' => '2030-01-11'])->assertOk();
        $this->travelTo(Carbon::parse('2030-01-04'));
        $this->artisan('training-programs:remind-hr')->assertSuccessful();
        $this->assertDatabaseCount('notifications', 2);
    }

    public function test_archiving_preserves_records_stops_intake_and_restores_the_current_schedule(): void
    {
        $this->login();
        $program = $this->createProgram();
        $url = self::API.'/'.$program->id;
        $this->travelTo(Carbon::parse('2030-01-11'));
        $applicant = Application::factory()->forProgram($program)->create();
        $this->getJson(self::API.'/openProgram')->assertOk();
        $this->postJson($url.'/archive', ['expected_revision' => 1])->assertOk()
            ->assertJsonPath('data.phase', 'archived')->assertJsonPath('data.delete_block', 'archived');
        (new OpenTrainingApplicationsJob($program))->handle(app(TrainingProgramService::class));
        $this->getJson(self::API.'/openProgram')->assertNotFound();
        $this->getJson($url)->assertNotFound();
        $this->getJson(self::API)->assertJsonCount(0, 'data');
        $this->getJson(self::API.'/management/list')->assertJsonPath('data.programs.0.phase', 'archived');
        $this->assertDatabaseHas('applications', ['id' => $applicant->id, 'training_program_id' => $program->id]);
        $this->deleteJson($url)->assertConflict();
        $this->postJson($url.'/restore', ['expected_revision' => 1])->assertConflict();
        $this->putJson($url, ['end_date' => '2030-03-01', 'confirm_date_change' => true])->assertOk()->assertJsonPath('data.phase', 'archived');
        $this->postJson($url.'/restore', ['expected_revision' => 3])->assertOk()->assertJsonPath('data.phase', 'registration_open');
        $this->getJson(self::API.'/openProgram')->assertOk();
        $this->deleteJson($url)->assertConflict()->assertJsonPath('data.reason', 'opened');
        $this->assertDatabaseHas('training_program_audits', ['action' => 'restore', 'training_program_id' => $program->id]);
    }

    public function test_archived_program_rejects_direct_application_and_restore_checks_for_overlaps(): void
    {
        $this->login();
        $program = $this->createProgram();
        $url = self::API.'/'.$program->id;
        $this->postJson($url.'/archive')->assertOk();
        $this->travelTo(Carbon::parse('2030-01-12'));
        Storage::fake('private');
        $file = fn () => UploadedFile::fake()->create('document.pdf', 1, 'application/pdf');
        $application = [
            'training_program_id' => $program->id,
            'full_name' => 'Test Applicant',
            'email' => 'applicant@example.net',
            'phone_number' => '0945123456',
            'birth_date' => '2000-01-01',
            'city' => 'damascus',
            'year_of_study' => 'graduated',
            'gpa' => 90,
            'commitment' => true,
            'previous_training' => false,
            'how_heard' => 'social media',
            'home_address' => 'Damascus',
            'university' => 'Damascus University',
            'field_of_study' => 'IT & Software Engineering',
            'academic_major' => 'IT',
            'technical_skills' => 'Programming',
            'national_id_number' => '12345678901',
            'cv' => $file(), 'national_id' => $file(), 'academic_qualification' => $file(), 'personal_photo' => $file(),
        ];
        $this->postJson('/api/v1/application', $application)->assertConflict();
        $this->createProgram(['name' => 'Another program']);
        $this->postJson($url.'/restore')->assertUnprocessable()->assertJsonValidationErrors('application_start_date');
        $this->assertNotNull($program->fresh()->archived_at);
        $this->assertDatabaseCount('applications', 0);
        $this->assertDatabaseMissing('training_program_audits', ['action' => 'restore']);
    }

    public function test_registration_does_not_open_while_archived_through_the_saved_window(): void
    {
        $this->login();
        $program = $this->createProgram();
        $url = self::API.'/'.$program->id;
        $this->postJson($url.'/archive')->assertOk();
        $this->travelTo(Carbon::parse('2030-01-12'));
        $this->artisan('training-programs:update-status')->assertSuccessful();
        $this->putJson($url, ['application_start_date' => '2030-01-15'])->assertOk();
        $this->assertFalse($program->fresh()->registration_has_opened);
        $this->postJson($url.'/restore')->assertOk()->assertJsonPath('data.phase', 'upcoming');
        $this->deleteJson($url)->assertOk();
    }

    public function test_date_changes_notify_only_affected_people_once_with_the_committed_dates(): void
    {
        $this->login();
        $program = $this->createProgram();
        $department = Department::factory()->create();
        $supervisor = User::factory()->create();
        $trainer = Trainer::factory()->create(['user_id' => $supervisor->id, 'department_id' => $department->id]);
        $applicant = Application::factory()->forProgram($program)->create(['status' => 'qualified']);
        $applicant->assigns()->create(['trainer_id' => $trainer->id, 'status' => 'assigned']);
        $active = Application::factory()->forProgram($program)->create(['status' => 'active']);
        $trainee = Trainee::factory()->create([
            'application_id' => $active->id, 'user_id' => User::factory()->create()->id,
            'department_id' => $department->id, 'trainer_id' => $trainer->id,
        ]);
        Application::factory()->forProgram($program)->create(['status' => 'rejected']);
        $url = self::API.'/'.$program->id;
        $this->putJson($url, ['name' => 'Spring'])->assertOk();
        $change = ['end_date' => '2030-03-10'];
        $this->putJson($url, $change)->assertConflict();
        Mail::assertNothingQueued();
        $this->assertDatabaseCount('notifications', 0);
        $this->putJson($url, $change + ['confirm_date_change' => true])->assertOk();
        $this->putJson($url, $change)->assertOk();
        Mail::assertQueued(ProgramDatesChangedMail::class, fn ($mail) => $mail->hasTo($applicant->email)
            && $mail->schedule['end_date'] === '2030-03-10' && str_contains($mail->render(), 'Spring'));
        Mail::assertQueuedCount(1);
        $notice = Notification::where('type', 'training_program.dates_changed')->sole();
        $this->assertSame($program->id, $notice->metadata['training_program_id']);
        $this->assertEqualsCanonicalizing([$trainee->user_id, $supervisor->id], $notice->notified()->pluck('notified_id')->all());
        $this->assertDatabaseCount('notification_deliveries', 2);
    }

    public function test_migration_classifies_existing_evidence_and_leaves_uncertain_future_programs_protected(): void
    {
        $migration = require database_path('migrations/2026_11_18_000004_add_training_program_management.php');
        $migration->down();
        $legacy = fn ($overrides) => DB::table('training_programs')->insertGetId(array_replace(
            self::FIELDS + ['description' => '', 'status' => 'Closed'], $overrides
        ));
        $past = $legacy(['application_start_date' => '2029-12-31']);
        $previouslyOpen = $legacy(['status' => 'Open']);
        $linked = $legacy([]);
        Application::factory()->create(['training_program_id' => $linked]);
        $unknown = $legacy([]);
        $migration->up();
        foreach ([$past, $previouslyOpen, $linked] as $id) {
            $this->assertTrue(TrainingProgram::findOrFail($id)->registration_has_opened);
        }
        $this->assertNull(TrainingProgram::findOrFail($unknown)->registration_has_opened);
        $this->login();
        $this->deleteJson(self::API.'/'.$unknown)->assertConflict()->assertJsonPath('data.reason', 'unknown_history');
    }

    public function test_a_certificate_alone_protects_a_program_and_a_trainee_protects_their_application_cycle(): void
    {
        $this->login();
        $program = $this->createProgram();
        $other = TrainingProgram::factory()->create();
        $application = Application::factory()->forProgram($other)->create();
        $department = Department::factory()->create();
        $trainer = Trainer::factory()->create(['user_id' => User::factory()->create()->id, 'department_id' => $department->id]);
        $trainee = Trainee::factory()->create([
            'application_id' => $application->id, 'user_id' => User::factory()->create()->id,
            'department_id' => $department->id, 'trainer_id' => $trainer->id,
        ]);
        Certificate::factory()->create(['trainee_id' => $trainee->id, 'training_program_id' => $program->id]);
        $this->deleteJson(self::API.'/'.$program->id)->assertConflict()->assertJsonPath('data.reason', 'linked');
        $this->deleteJson(self::API.'/'.$other->id)->assertConflict();
        $this->assertDatabaseHas('trainees', ['id' => $trainee->id]);
        $this->assertDatabaseHas('certificates', ['training_program_id' => $program->id]);
    }

    public function test_scheduler_and_old_jobs_use_current_dates_and_keep_the_last_registration_day_open(): void
    {
        $this->login();
        $program = $this->createProgram();
        $this->travelTo(Carbon::parse('2030-01-15'));
        $this->artisan('training-programs:update-status')->assertSuccessful();
        $this->assertTrue($program->fresh()->registration_has_opened);
        $this->travelTo(Carbon::parse('2030-01-20 23:59:59'));
        (new CloseTrainingApplicationsJob($program))->handle(app(TrainingProgramService::class));
        $this->assertSame('Open', $program->fresh()->status->value);
        $this->travelTo(Carbon::parse('2030-01-21'));
        (new OpenTrainingApplicationsJob($program))->handle(app(TrainingProgramService::class));
        $this->assertSame('Closed', $program->fresh()->status->value);
    }
}
