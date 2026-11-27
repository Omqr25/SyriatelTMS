<?php

namespace Tests\Support;

use App\Models\Application;
use App\Models\CertificateTemplateVersion;
use App\Models\Department;
use App\Models\Role;
use App\Models\Trainee;
use App\Models\Trainer;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Services\Certificate\CertificateStorage;
use App\Services\Certificate\CertificateTemplates;
use Illuminate\Support\Facades\Storage;

trait CertificateFixtures
{
    private function member(string $role, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->roles()->attach(Role::firstOrCreate(['role' => $role]));

        return $user;
    }

    private function program(): TrainingProgram
    {
        return TrainingProgram::factory()->create(['start_date' => '2026-10-01', 'end_date' => '2026-11-30']);
    }

    private function trainee(TrainingProgram $program, array $options = []): Trainee
    {
        $department = Department::factory()->create(['name' => $options['department'] ?? 'Network Operations']);
        $trainer = Trainer::factory()->create(['user_id' => $this->member('SUPERVISOR')->id, 'department_id' => $department->id]);
        $application = Application::factory()->create([
            'training_program_id' => $program->id, 'full_name' => $options['application'] ?? 'Original North',
        ]);

        return Trainee::factory()->create([
            'user_id' => $this->member('TRAINEE', ['name' => $options['account'] ?? 'Account North'])->id,
            'status' => $options['status'] ?? 'active', 'application_id' => $application->id,
            'department_id' => $department->id, 'trainer_id' => $trainer->id,
        ]);
    }

    private function configuration(array $tokens = ['trainee_name', 'department_name', 'issue_date', 'certificate_number']): array
    {
        return ['page' => ['width' => 210, 'height' => 297], 'boxes' => [[
            'x' => 10, 'y' => 25, 'w' => 100, 'h' => 45, 'size' => 22, 'min' => 12, 'fit' => true,
            'font' => 'sans', 'align' => 'left', 'color' => '#234567', 'leading' => 1.32,
            'paragraphs' => [[['text' => 'Training for ', 'italic' => true], ...array_map(fn ($token) => ['token' => $token], $tokens)]],
        ]]];
    }

    private function version(User $hr, ?array $tokens = null): CertificateTemplateVersion
    {
        $path = app(CertificateStorage::class)->backgroundPath('pdf');
        Storage::disk('private')->put($path, "%PDF-1.4\n% synthetic fixture");
        $configuration = $tokens === null ? $this->configuration() : $this->configuration($tokens);

        return app(CertificateTemplates::class)->create($hr, 'Training template', $path, $configuration);
    }
}
