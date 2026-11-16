<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_request_a_link_pointing_to_the_main_frontend(): void
    {
        Notification::fake();
        config(['app.frontend_url' => 'https://main.example.test/']);
        $user = User::factory()->create(['email' => 'person+training@example.test']);

        $this->postJson('/api/v1/auth/forgotPassword', ['email' => $user->email])
            ->assertOk()
            ->assertJsonPath('message', __('auth.passwordResetLinkSent'))
            ->assertJsonPath('data', null);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            $url = $notification->toMail($user)->actionUrl;
            $this->assertStringStartsWith('https://main.example.test/reset-password?', $url);
            parse_str(parse_url($url, PHP_URL_QUERY), $query);
            $this->assertSame($user->email, $query['email']);
            $this->assertTrue(Password::tokenExists($user, $query['token']));
            return true;
        });
    }

    public function test_link_requests_are_throttled(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $this->postJson('/api/v1/auth/forgotPassword', ['email' => $user->email])->assertOk();
        $this->postJson('/api/v1/auth/forgotPassword', ['email' => $user->email])
            ->assertStatus(400)->assertJsonPath('message', __('passwords.throttled'));
        Notification::assertSentToTimes($user, ResetPassword::class, 1);
    }

    public function test_reset_links_keep_the_backend_form_as_a_fallback(): void
    {
        config(['app.frontend_url' => null]);
        $user = User::factory()->create();
        $notification = new ResetPassword(Password::createToken($user));
        $this->assertStringStartsWith(
            rtrim(config('app.url'), '/') . '/reset-password?',
            $notification->toMail($user)->actionUrl,
        );
    }

    public function test_valid_token_changes_the_password_and_cannot_be_reused(): void
    {
        $user = User::factory()->create();
        $body = $this->resetBody($user);
        $this->postJson('/api/v1/auth/reset-password', $body)->assertOk()
            ->assertJsonPath('message', __('auth.passwordResetSuccessfully'));
        $this->assertTrue(Hash::check($body['password'], $user->fresh()->password));
        $this->assertFalse(Hash::check('12345678', $user->fresh()->password));
        $this->postJson('/api/v1/auth/reset-password', $body)->assertStatus(400);

        config(['jwt.secret' => str_repeat('test-secret-', 8)]);
        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => $body['password']])
            ->assertOk()->assertJsonStructure(['data' => ['token']]);
        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => '12345678'])
            ->assertUnauthorized();
    }

    public function test_invalid_expired_and_wrong_account_tokens_do_not_change_passwords(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $original = $user->password;
        $body = $this->resetBody($user);
        $this->postJson('/api/v1/auth/reset-password', [...$body, 'token' => 'invalid'])->assertStatus(400);
        $this->postJson('/api/v1/auth/reset-password', [...$body, 'email' => $other->email])->assertStatus(400);
        DB::table('password_reset_tokens')->where('email', $user->email)->update([
            'created_at' => now()->subMinutes(config('auth.passwords.users.expire') + 1),
        ]);
        $this->postJson('/api/v1/auth/reset-password', $body)->assertStatus(400)
            ->assertJsonPath('message', __('passwords.token'));
        $this->assertSame($original, $user->fresh()->password);
    }

    public function test_reset_requires_matching_passwords_of_at_least_eight_characters(): void
    {
        $user = User::factory()->create();
        $body = $this->resetBody($user);
        $this->postJson('/api/v1/auth/reset-password', [...$body, 'password_confirmation' => 'different'])
            ->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->postJson('/api/v1/auth/reset-password', [...$body, 'password' => 'short', 'password_confirmation' => 'short'])
            ->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->postJson('/api/v1/auth/reset-password', [...$body, 'token' => ''])
            ->assertUnprocessable()->assertJsonValidationErrors('token');
        $this->assertTrue(Password::tokenExists($user, $body['token']));
    }

    public function test_link_request_validates_email(): void
    {
        Notification::fake();
        foreach (['invalid', 'missing@example.test'] as $email) {
            $this->postJson('/api/v1/auth/forgotPassword', ['email' => $email])
                ->assertUnprocessable()->assertJsonValidationErrors('email');
        }
        Notification::assertNothingSent();
    }

    private function resetBody(User $user): array
    {
        return [
            'email' => $user->email,
            'token' => Password::createToken($user),
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ];
    }
}
