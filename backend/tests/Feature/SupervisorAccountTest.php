<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Role;
use App\Models\Trainer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class SupervisorAccountTest extends TestCase
{
    use RefreshDatabase;

    private const PROFILE = '/api/v1/supervisor/profile';

    protected function setUp(): void
    {
        parent::setUp();

        config(['jwt.secret' => str_repeat('test-secret-', 8)]);
    }

    public function test_profile_returns_the_signed_in_supervisors_own_work_and_contact_details(): void
    {
        $supervisor = $this->userWithRole('SUPERVISOR');
        $department = Department::factory()->create(['name' => 'Network Operations', 'location' => 'damascus']);
        Trainer::factory()->forUser($supervisor)->forDepartment($department)->create();

        $this->getJson(self::PROFILE)->assertUnauthorized();

        $this->withToken(JWTAuth::fromUser($supervisor))->getJson(self::PROFILE)
            ->assertOk()
            ->assertJsonPath('data.personal_information', ['name' => $supervisor->name])
            ->assertJsonPath('data.contact_information', [
                'email' => $supervisor->email,
                'phone_number' => $supervisor->phone_number,
            ])
            ->assertJsonPath('data.work_information', [
                'department' => $department->name,
                'department_location' => $department->location,
            ]);
    }

    public function test_trainee_cannot_access_the_supervisor_profile_endpoint(): void
    {
        $trainee = $this->userWithRole('TRAINEE');

        $this->withToken(JWTAuth::fromUser($trainee))->getJson(self::PROFILE)->assertForbidden();
    }

    public function test_supervisor_can_change_their_password_with_the_current_password(): void
    {
        $supervisor = $this->userWithRole('SUPERVISOR');
        $this->withToken(JWTAuth::fromUser($supervisor));
        $passwordChange = [
            'current_password' => '12345678',
            'new_password' => 'updated-password-2026',
            'new_password_confirmation' => 'updated-password-2026',
        ];

        $this->postJson('/api/v1/auth/changePassword', ['current_password' => 'incorrect'] + $passwordChange)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('current_password');
        $this->assertTrue(Hash::check('12345678', $supervisor->fresh()->password));

        $this->postJson('/api/v1/auth/changePassword', $passwordChange)->assertOk();
        $this->assertTrue(Hash::check($passwordChange['new_password'], $supervisor->fresh()->password));
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::firstOrCreate(['role' => $role]));

        return $user;
    }
}
