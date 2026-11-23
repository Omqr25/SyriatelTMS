<?php

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Models\Application;
use App\Models\Department;
use App\Models\Role;
use App\Models\Trainee;
use App\Models\Trainer;
use App\Models\TrainingProgram;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class DismissedTraineeSessionTest extends TestCase
{
    use RefreshDatabase;

    private const SESSION_CHECK = '/api/v1/notification/unread-count';

    public function test_dismissal_revokes_existing_sessions_on_every_device(): void
    {
        config(['jwt.secret' => str_repeat('test-secret-', 8)]);
        Storage::fake('private');

        $user = $this->userWithRole('TRAINEE');
        $department = Department::factory()->create();
        $trainer = Trainer::factory()->create([
            'user_id' => $this->userWithRole('SUPERVISOR')->id,
            'department_id' => $department->id,
        ]);
        $application = Application::factory()->create([
            'training_program_id' => TrainingProgram::factory()->create()->id,
            'cv' => 'application/cv.pdf',
        ]);
        Storage::disk('private')->put($application->cv, 'CV contents');
        $trainee = Trainee::factory()->create([
            'user_id' => $user->id,
            'department_id' => $department->id,
            'trainer_id' => $trainer->id,
            'application_id' => $application->id,
        ]);
        $user->deviceTokens()->create(['token' => 'browser-one', 'platform' => 'web']);
        $user->deviceTokens()->create(['token' => 'browser-two', 'platform' => 'web']);
        $hr = $this->userWithRole('HR');
        $attachment = "/api/v1/application/{$application->id}/attachments/cv?format=json";
        $loginBody = ['email' => $user->email, 'password' => '12345678'];

        $firstToken = $this->postJson('/api/v1/auth/login', $loginBody)
            ->assertOk()->json('data.token');
        $this->travel(1)->seconds();
        $secondToken = $this->postJson('/api/v1/auth/login', $loginBody)
            ->assertOk()->json('data.token');
        $this->assertNotSame($firstToken, $secondToken);
        $hrToken = JWTAuth::fromUser($hr);

        $this->bearer($firstToken)->getJson(self::SESSION_CHECK)->assertOk();
        $this->bearer($secondToken)->getJson($attachment)->assertOk()
            ->assertJsonPath('content', base64_encode('CV contents'));
        $peerToken = JWTAuth::fromUser($this->userWithRole('TRAINEE'));
        $this->bearer($peerToken)->getJson($attachment)->assertForbidden();

        $resetToken = Password::createToken($user);
        $this->bearer($hrToken)->postJson("/api/v1/trainee/fire/{$trainee->id}", [
            'reason' => 'Dismissed from the program',
        ])->assertSuccessful();

        $this->assertSame(UserStatus::FIRED, $user->fresh()->status);
        $this->assertSame(1, $user->fresh()->session_version);
        $this->assertSame(0, $user->deviceTokens()->count());
        foreach ([$firstToken, $secondToken] as $token) {
            $this->bearer($token)->getJson(self::SESSION_CHECK)->assertUnauthorized();
            $this->bearer($token)->getJson($attachment)->assertUnauthorized();
            $this->bearer($token)->postJson('/api/v1/notification/device-tokens', [
                'token' => 'new-device',
                'platform' => 'web',
            ])->assertUnauthorized();
        }
        $this->bearer($hrToken)->getJson($attachment)->assertOk();
        $this->flushHeaders()->postJson('/api/v1/auth/login', $loginBody)->assertUnauthorized();
        $this->postJson('/api/v1/auth/reset-password', [
            'email' => $user->email,
            'token' => $resetToken,
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertStatus(400);

        // Restoring account status must never restore a revoked token.
        $user->fresh()->update(['status' => UserStatus::ACTIVE]);
        $this->bearer($firstToken)->getJson(self::SESSION_CHECK)->assertUnauthorized();
        $newToken = $this->flushHeaders()->postJson('/api/v1/auth/login', $loginBody)
            ->assertOk()->json('data.token');
        $this->bearer($newToken)->getJson(self::SESSION_CHECK)->assertOk();
    }

    private function bearer(string $token): static
    {
        Auth::guard('api')->unsetToken();
        Auth::forgetGuards();

        return $this->withToken($token);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::firstOrCreate(['role' => $role]));

        return $user;
    }
}
