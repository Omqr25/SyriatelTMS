<?php

namespace Tests\Feature;

use App\Models\GalleryPhoto;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GalleryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['jwt.secret' => str_repeat('gallery-test-', 4)]);
    }

    private function user(string $role): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::firstOrCreate(['role' => $role]));

        return $user;
    }

    private function photo(): UploadedFile
    {
        return UploadedFile::fake()->image('class.jpg', 2300, 1200);
    }

    public function test_hr_can_publish_edit_replace_and_remove_a_public_photo(): void
    {
        $disk = Storage::fake('private');
        $hr = $this->user('HR');

        $this->getJson('/api/v1/gallery')->assertOk()->assertJsonCount(0, 'data');
        $created = $this->actingAs($hr, 'api')->postJson('/api/v1/gallery', [
            'title' => 'ختام دورة التدريب',
            'description' => 'The cohort celebrating together.',
            'image' => $this->photo(),
        ])->assertCreated()->assertJsonPath('data.title', 'ختام دورة التدريب')->json('data');

        $photo = GalleryPhoto::findOrFail($created['id']);
        $original = $photo->image_path;
        $disk->assertExists($original);
        $size = getimagesizefromstring($disk->get($original));
        $this->assertSame(1920, $size[0]);
        $this->assertSame(IMAGETYPE_WEBP, $size[2]);
        $endpoint = '/api/v1/gallery/'.$photo->id;

        auth('api')->forgetUser();
        $this->getJson('/api/v1/gallery')->assertOk()
            ->assertJsonPath('data.0.description', 'The cohort celebrating together.')
            ->assertJsonMissingPath('data.0.image_path');
        $response = $this->get($endpoint.'/image')->assertOk()->assertHeader('Content-Type', 'image/webp')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertSame($disk->get($original), $response->streamedContent());

        $this->actingAs($hr, 'api')->patchJson($endpoint, ['title' => 'Training graduation'])
            ->assertOk()->assertJsonPath('data.image_version', $created['image_version']);
        $this->assertSame($original, $photo->fresh()->image_path);

        $updated = $this->post($endpoint, [
            '_method' => 'PATCH',
            'image' => UploadedFile::fake()->image('replacement.png', 60, 40),
            'description' => null,
        ], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('data.title', 'Training graduation')->json('data');

        $this->assertNotSame($created['image_version'], $updated['image_version']);
        $newPath = $photo->fresh()->image_path;
        $disk->assertMissing($original);
        $disk->assertExists($newPath);

        $this->deleteJson($endpoint)->assertNoContent();
        $disk->assertMissing($newPath);
        auth('api')->forgetUser();
        $this->getJson($endpoint.'/image')->assertNotFound();
        $this->getJson('/api/v1/gallery')->assertJsonCount(0, 'data');
    }

    public function test_only_hr_can_change_the_gallery_and_uploads_require_safe_image_formats(): void
    {
        $disk = Storage::fake('private');
        $photo = GalleryPhoto::create(['title' => 'Public photo', 'image_path' => 'gallery/previous.webp']);
        $endpoint = '/api/v1/gallery/'.$photo->id;

        $this->postJson('/api/v1/gallery')->assertUnauthorized();
        $this->patchJson($endpoint, ['title' => 'Changed'])->assertUnauthorized();
        $this->deleteJson($endpoint)->assertUnauthorized();

        foreach (['TRAINEE', 'SUPERVISOR'] as $role) {
            $this->actingAs($this->user($role), 'api')->postJson('/api/v1/gallery')->assertForbidden();
            $this->patchJson($endpoint, ['title' => 'Changed'])->assertForbidden();
            $this->deleteJson($endpoint)->assertForbidden();
        }

        $this->actingAs($this->user('HR'), 'api')->postJson('/api/v1/gallery', [
            'title' => 'Bad image',
            'image' => UploadedFile::fake()->createWithContent('photo.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>'),
        ])->assertUnprocessable()->assertJsonValidationErrors('image');
        $this->postJson('/api/v1/gallery', [
            'title' => 'Large image',
            'image' => UploadedFile::fake()->image('large.png')->size(8193),
        ])->assertUnprocessable()->assertJsonValidationErrors('image');
        $this->assertSame('Public photo', $photo->fresh()->title);
        $this->assertSame([], $disk->allFiles());
    }
}
