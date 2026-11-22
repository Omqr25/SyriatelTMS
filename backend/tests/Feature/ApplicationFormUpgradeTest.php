<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\ApplicationAssign;
use App\Models\Department;
use App\Models\Interview;
use App\Models\Role;
use App\Models\Trainee;
use App\Models\Trainer;
use App\Models\TrainingProgram;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ApplicationFormUpgradeTest extends TestCase
{
    public function test_additive_upgrade_preserves_populated_pre_feature_schema(): void
    {
        $mysqlDatabase = getenv('APPLICATION_FORM_MYSQL_UPGRADE_DATABASE');
        $source = $mysqlDatabase === false ? 'sqlite' : 'mysql';
        $database = $mysqlDatabase === false ? ':memory:' : $mysqlDatabase;
        $this->assertNotSame('', $database);
        config(['database.connections.form_upgrade' => array_replace(config("database.connections.$source"), [
            'database' => $database,
            'url' => null,
        ])]);
        DB::setDefaultConnection('form_upgrade');
        $this->assertSame([], Schema::getTables($source === 'mysql' ? $database : null), 'This test requires an empty disposable database.');

        $paths = array_map(fn ($file) => 'database/migrations/'.basename($file), glob(database_path('migrations/*.php')));
        [$prior, $feature] = collect($paths)->partition(fn ($path) => basename($path) < '2026_11_23_000001_create_application_form_definitions.php');
        $this->artisan('migrate', ['--database' => 'form_upgrade', '--path' => $prior->values()->all()])->assertSuccessful();
        $this->assertFalse(Schema::hasColumn('applications', 'application_form_id'));

        $program = TrainingProgram::factory()->create();
        $application = Application::factory()->forProgram($program)->accepted()->create([
            'english_test_score' => '47.25',
            'cv' => 'applications/legacy/cv.pdf',
            'commitment' => true,
            'previous_training' => false,
            'email' => 'synthetic-legacy@example.test',
        ]);
        $department = Department::factory()->create();
        $trainer = Trainer::factory()->forUser(User::factory()->create())->forDepartment($department)->create();
        $assignment = ApplicationAssign::factory()->create(['application_id' => $application->id, 'trainer_id' => $trainer->id]);
        $interview = Interview::factory()->create(['application_id' => $application->id, 'trainer_id' => $trainer->id]);
        $trainee = Trainee::factory()->create([
            'application_id' => $application->id,
            'user_id' => User::factory()->create()->id,
            'department_id' => $department->id,
            'trainer_id' => $trainer->id,
        ]);
        $legacy = (array) DB::table('applications')->find($application->id);

        $this->artisan('migrate', ['--database' => 'form_upgrade', '--path' => $feature->values()->all()])->assertSuccessful();
        $this->assertEquals($legacy + ['application_form_id' => null], (array) DB::table('applications')->find($application->id));
        $this->assertDatabaseHas('application_assigns', ['id' => $assignment->id, 'application_id' => $application->id]);
        $this->assertDatabaseHas('interviews', ['id' => $interview->id, 'application_id' => $application->id]);
        $this->assertDatabaseHas('trainees', ['id' => $trainee->id, 'application_id' => $application->id]);
        $this->assertDatabaseCount('application_forms', 0);
        $this->assertDatabaseCount('application_answers', 0);
        $this->assertDatabaseCount('answer_options', 0);

        $hr = User::factory()->create();
        $hr->roles()->attach(Role::firstOrCreate(['role' => 'HR']));
        $this->actingAs($hr, 'api');
        $this->getJson("/api/v1/application/{$application->id}")->assertOk()->assertJsonPath('data.id', $application->id);
        $this->getJson("/api/v1/application/{$application->id}/answers")->assertOk()
            ->assertJsonPath('data.application_form_id', null)->assertJsonPath('data.answers', []);
    }
}
