<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class HrAccountTest extends TestCase
{
    use RefreshDatabase;

    private const PROFILE = '/api/v1/hr/profile';

    protected function setUp(): void
    {
        parent::setUp();

        config(['jwt.secret' => str_repeat('test-secret-', 8)]);
    }

    public function test_hr_profile_returns_only_the_signed_in_users_account_details(): void
    {
        $hr = $this->userWithRole('HR');
        $otherHr = $this->userWithRole('HR');

        $this->getJson(self::PROFILE)->assertUnauthorized();

        $response = $this->withToken(JWTAuth::fromUser($hr))
            ->getJson(self::PROFILE.'?user_id='.$otherHr->id)
            ->assertOk()
            ->assertJsonPath('data', [
                'personal_information' => ['name' => $hr->name],
                'contact_information' => [
                    'email' => $hr->email,
                    'phone_number' => $hr->phone_number,
                ],
            ]);

        $this->assertTrue($response->baseResponse->headers->hasCacheControlDirective('no-store'));
    }

    #[TestWith(['TRAINEE'])]
    #[TestWith(['SUPERVISOR'])]
    public function test_other_roles_cannot_read_the_hr_profile(string $role): void
    {
        $user = $this->userWithRole($role);

        $this->withToken(JWTAuth::fromUser($user))->getJson(self::PROFILE)->assertForbidden();
    }

    public function test_hr_can_change_their_password_with_the_current_password(): void
    {
        $currentPassword = 'existing-hr-password';
        $hr = $this->userWithRole('HR', ['password' => $currentPassword]);
        $this->withToken(JWTAuth::fromUser($hr));

        $change = [
            'current_password' => $currentPassword,
            'new_password' => 'revised-hr-password',
            'new_password_confirmation' => 'revised-hr-password',
        ];

        $this->postJson('/api/v1/auth/changePassword', ['current_password' => 'incorrect'] + $change)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('current_password');
        $this->assertTrue(Hash::check($currentPassword, $hr->fresh()->password));

        $this->postJson('/api/v1/auth/changePassword', $change)->assertOk();
        $this->assertTrue(Hash::check($change['new_password'], $hr->fresh()->password));
    }

    private function userWithRole(string $role, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->roles()->attach(Role::firstOrCreate(['role' => $role]));

        return $user;
    }
}
