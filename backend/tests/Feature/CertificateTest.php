<?php

namespace Tests\Feature;

use App\Models\{User, Trainee, Trainer, Department, Application, TrainingProgram, Certificate, Role};
use Tests\Support\UsesCertificateTestDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CertificateTest extends TestCase
{
    use UsesCertificateTestDatabase;

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

    private function legacy(Trainee $trainee): int
    {
        $path = 'certificates/legacy-'.$trainee->id.'.pdf';
        Storage::disk('local')->put($path, '%PDF historical certificate');

        return Certificate::create([
            'trainee_id' => $trainee->id, 'training_program_id' => $trainee->application->training_program_id,
            'issue_date' => today(), 'path' => $path,
        ])->id;
    }

    public function test_hr_and_trainee_can_list_preview_and_download_historical_certificate(): void
    {
        $trainee = $this->trainee();
        $id = $this->legacy($trainee);
        $certificate = Certificate::findOrFail($id);
        Storage::disk('local')->assertExists($certificate->path);
        $this->actingAs($this->user('HR'), 'api')->getJson('/api/v1/certificates')
            ->assertOk()->assertJsonPath('data.0.id', $id)->assertJsonMissingPath('data.0.path');
        $this->actingAs($trainee->user, 'api')->getJson('/api/v1/certificates')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $id);
        $this->get('/api/v1/certificates/' . $id . '/preview')->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'inline; filename=certificate-' . $id . '.pdf');
        $this->get('/api/v1/certificates/' . $id . '/download')->assertOk()->assertDownload('certificate-' . $id . '.pdf');
    }

    public function test_other_trainees_cannot_list_or_open_someone_elses_certificate(): void
    {
        $id = $this->legacy($this->trainee());
        $other = $this->trainee();
        $this->actingAs($other->user, 'api')->getJson('/api/v1/certificates')->assertOk()->assertJsonCount(0, 'data');
        foreach (['preview', 'download'] as $action) {
            $this->getJson('/api/v1/certificates/' . $id . '/' . $action)->assertForbidden();
        }
        $this->getJson('/api/v1/certificates/issuance/options')->assertForbidden();
        $this->postJson('/api/v1/certificates/trainees/' . $other->id)->assertForbidden();
    }

    public function test_old_manual_upload_api_cannot_bypass_issuance(): void
    {
        $trainee = $this->trainee();
        $this->actingAs($this->user('HR'), 'api')->postJson('/api/v1/certificates/trainees/'.$trainee->id, [
            'file' => UploadedFile::fake()->create('certificate.pdf', 10, 'application/pdf'),
        ])->assertGone();
        $this->assertDatabaseCount('certificates', 0);
        $this->assertSame([], Storage::disk('local')->allFiles('certificates'));
    }

    public function test_missing_historical_file_is_handled(): void
    {
        $trainee = $this->trainee();
        $id = $this->legacy($trainee);
        Storage::disk('local')->delete(Certificate::find($id)->path);
        $this->actingAs($trainee->user, 'api')->getJson('/api/v1/certificates/' . $id . '/preview')->assertNotFound();
    }

    public function test_training_completion_enables_issuance_but_does_not_issue_a_pdf_automatically(): void
    {
        $trainee = $this->trainee('active');
        $trainee->application->update(['status' => 'active']);
        $trainee->application->trainingProgram->update(['end_date' => today()->subDay()]);
        $this->artisan('training-programs:update-status')->assertSuccessful();
        $this->assertDatabaseCount('certificates', 0);
        $this->assertDatabaseCount('certificate_jobs', 0);
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
