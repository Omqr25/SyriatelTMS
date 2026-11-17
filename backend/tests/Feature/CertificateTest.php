<?php

namespace Tests\Feature;

use App\Models\{User, Trainee, Trainer, Department, Application, TrainingProgram, Certificate, Role};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CertificateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['jwt.secret' => str_repeat('test-secret-', 8)]);
        Storage::fake('local');
    }

    private function user(string $role): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::firstOrCreate(['role' => $role]));
        return $user;
    }

    private function trainee(string $status = 'completed'): Trainee
    {
        $department = Department::factory()->create();
        $trainer = Trainer::factory()->create(['user_id' => $this->user('SUPERVISOR')->id, 'department_id' => $department->id]);
        $application = Application::factory()->create(['training_program_id' => TrainingProgram::factory()->create()->id]);
        return Trainee::factory()->create(['user_id' => $this->user('TRAINEE')->id, 'status' => $status,
            'department_id' => $department->id, 'trainer_id' => $trainer->id, 'application_id' => $application->id]);
    }

    private function issue(Trainee $trainee): int
    {
        return $this->actingAs($this->user('HR'), 'api')->postJson('/api/v1/certificates/trainees/' . $trainee->id, [
            'file' => UploadedFile::fake()->create('official.pdf', 10, 'application/pdf'),
        ])->assertCreated()->assertJsonMissingPath('data.path')->json('data.id');
    }

    public function test_hr_can_issue_and_trainee_can_list_preview_and_download(): void
    {
        $trainee = $this->trainee();
        $id = $this->issue($trainee);
        $certificate = Certificate::findOrFail($id);
        Storage::disk('local')->assertExists($certificate->path);
        $this->actingAs($trainee->user, 'api')->getJson('/api/v1/certificates')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $id);
        $this->get('/api/v1/certificates/' . $id . '/preview')->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'inline; filename=certificate-' . $id . '.pdf');
        $this->get('/api/v1/certificates/' . $id . '/download')->assertOk()->assertDownload('certificate-' . $id . '.pdf');
    }

    public function test_other_trainees_cannot_list_or_open_someone_elses_certificate(): void
    {
        $id = $this->issue($this->trainee());
        $other = $this->trainee();
        $this->actingAs($other->user, 'api')->getJson('/api/v1/certificates')->assertOk()->assertJsonCount(0, 'data');
        foreach (['preview', 'download'] as $action) {
            $this->getJson('/api/v1/certificates/' . $id . '/' . $action)->assertForbidden();
        }
        $this->getJson('/api/v1/certificates/candidates')->assertForbidden();
        $this->postJson('/api/v1/certificates/trainees/' . $other->id)->assertForbidden();
    }

    public function test_only_completed_trainees_are_candidates_and_duplicates_are_rejected(): void
    {
        $completed = $this->trainee();
        $active = $this->trainee('active');
        $id = $this->issue($completed);
        $this->getJson('/api/v1/certificates/candidates')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.issued', true);
        foreach ([[$completed, 409], [$active, 422]] as [$trainee, $status]) {
            $this->postJson('/api/v1/certificates/trainees/' . $trainee->id, [
                'file' => UploadedFile::fake()->create('certificate.pdf', 10, 'application/pdf'),
            ])->assertStatus($status);
        }
        $this->assertDatabaseCount('certificates', 1);
        $this->assertCount(1, Storage::disk('local')->files('certificates'));
    }

    public function test_file_validation_and_missing_file_are_handled(): void
    {
        $trainee = $this->trainee();
        $this->actingAs($this->user('HR'), 'api');
        foreach ([UploadedFile::fake()->create('bad.txt', 1, 'text/plain'), UploadedFile::fake()->create('large.pdf', 10241, 'application/pdf')] as $file) {
            $this->postJson('/api/v1/certificates/trainees/' . $trainee->id, ['file' => $file])
                ->assertUnprocessable()->assertJsonValidationErrors('file');
        }
        $id = $this->issue($trainee);
        Storage::disk('local')->delete(Certificate::find($id)->path);
        $this->actingAs($trainee->user, 'api')->getJson('/api/v1/certificates/' . $id . '/preview')->assertNotFound();
    }

    public function test_training_completion_enables_issuance_but_does_not_issue_a_pdf_automatically(): void
    {
        $trainee = $this->trainee('active');
        $trainee->application->update(['status' => 'active']);
        $trainee->application->trainingProgram->update(['end_date' => today()->subDay()]);
        $this->actingAs($this->user('HR'), 'api')->getJson('/api/v1/certificates/candidates')
            ->assertOk()->assertJsonCount(0, 'data');
        $this->artisan('training-programs:update-status')->assertSuccessful();
        $this->getJson('/api/v1/certificates/candidates')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.issued', false);
        $this->actingAs($trainee->user, 'api')->getJson('/api/v1/certificates')
            ->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_guests_cannot_access_certificates(): void
    {
        $this->getJson('/api/v1/certificates')->assertUnauthorized();
    }

    public function test_supervisors_cannot_access_certificate_endpoints(): void
    {
        $this->actingAs($this->user('SUPERVISOR'), 'api')->getJson('/api/v1/certificates')->assertForbidden();
    }
}
