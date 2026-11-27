<?php

namespace Tests\Feature;

use App\Models\CertificateTemplateVersion;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CertificateFixtures;
use Tests\Support\UsesIsolatedForwardMigrations;
use Tests\TestCase;

class CertificateTemplatesTest extends TestCase
{
    use CertificateFixtures, UsesIsolatedForwardMigrations;

    private const URI = '/api/v1/certificates/templates';

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('T', 32)), 'jwt.secret' => str_repeat('test-secret-', 8)]);
        Storage::fake('private');
    }

    public function test_template_operations_preserve_immutable_versions_and_private_backgrounds(): void
    {
        $actor = $this->member('HR');
        $this->actingAs($actor, 'api');
        $upload = $this->upload($this->pdf())->assertCreated()->json('data');
        $this->assertEqualsWithDelta(210, $upload['page']['width'], .01);
        $this->assertEqualsWithDelta(297, $upload['page']['height'], .01);
        $configuration = $this->configuration();
        $configuration['boxes'][0]['paragraphs'] = [[
            ['text' => 'This is to certify that '], ['token' => 'trainee_name', 'bold' => true],
            ['text' => ' attended '], ['token' => 'program_name', 'italic' => true],
            ['text' => ' in '], ['token' => 'department_name'], ['text' => '.'],
        ]];
        $payload = ['name' => 'Program Excellence', 'background_token' => $upload['token'], 'configuration' => $configuration];
        $saved = $this->postJson(self::URI, $payload)->assertCreated()->assertJsonMissingPath('data.background_path')->json('data');
        $this->getJson(self::URI.'?search=Excellence')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson(self::URI.'/'.$saved['id'])->assertOk()
            ->assertJsonPath('data.configuration.boxes.0.paragraphs.0.0.text', 'This is to certify that ')
            ->assertJsonPath('data.configuration.boxes.0.paragraphs.0.2.text', ' attended ')
            ->assertJsonPath('data.configuration.boxes.0.paragraphs.0.3.italic', true);
        $proof = $this->postJson(self::URI.'/proof', [
            'version_id' => $saved['version_id'], 'configuration' => $configuration, 'sample' => 'long',
        ])->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $proof->getContent());

        $edit = ['name' => 'Program Excellence Revised', 'version_id' => $saved['version_id'],
            'base_version' => $saved['version_id'], 'configuration' => $configuration];
        $edit['configuration']['boxes'][0]['x'] = 23;
        $second = $this->putJson(self::URI.'/'.$saved['id'], $edit)->assertOk()->assertJsonPath('data.version', 2)->json('data');
        $this->putJson(self::URI.'/'.$saved['id'], $edit)->assertUnprocessable();
        $old = CertificateTemplateVersion::findOrFail($saved['version_id']);
        $this->assertEquals(10, $old->configuration['boxes'][0]['x']);
        $this->assertSame($old->background_path, CertificateTemplateVersion::findOrFail($second['version_id'])->background_path);

        $this->postJson(self::URI.'/'.$saved['id'].'/duplicate', ['name' => 'Copied template'])
            ->assertCreated()->assertJsonPath('data.version', 1)->assertJsonPath('data.configuration.boxes.0.x', 23);
        $this->postJson(self::URI.'/'.$saved['id'].'/archive')->assertNoContent();
        $this->getJson(self::URI.'?search=Excellence')->assertJsonCount(0, 'data');
        $this->getJson(self::URI.'?archived=1')->assertJsonCount(1, 'data');
        $this->get(self::URI.'/versions/'.$old->id.'/background')->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')->assertHeader('X-Content-Type-Options', 'nosniff');
        Storage::disk('private')->assertExists($old->background_path);
    }

    public function test_template_access_and_draft_ownership_are_enforced(): void
    {
        $this->getJson(self::URI.'/assets')->assertUnauthorized();
        $this->actingAs($this->member('TRAINEE'), 'api')->getJson(self::URI)->assertForbidden();
        $this->actingAs($this->member('HR'), 'api');
        $token = $this->upload($this->pdf())->assertCreated()->json('data.token');
        $this->actingAs($this->member('HR'), 'api')->postJson(self::URI.'/draft-background', [
            'background_token' => $token,
        ])->assertUnprocessable();
    }

    public function test_unsafe_inputs_and_unsupported_configuration_are_rejected(): void
    {
        $this->actingAs($this->member('HR'), 'api');
        $this->upload($this->pdf(true))->assertUnprocessable();
        $this->upload($this->pdf(false, true))->assertUnprocessable();
        $this->upload($this->pdf(false).str_repeat('x', 5 * 1024 * 1024))->assertUnprocessable();
        $this->upload('%PDF-1.4 invalid body')->assertUnprocessable();
        $token = $this->upload($this->pdf())->assertCreated()->json('data.token');
        $request = ['background_token' => $token, 'name' => 'Invalid', 'configuration' => $this->configuration(['salutation'])];
        $this->postJson(self::URI, $request)->assertUnprocessable();
        $request['configuration'] = $this->configuration();
        $request['configuration']['boxes'][0]['paragraphs'][0][0] = ['html' => '<img src="https://example.invalid/x">'];
        $this->postJson(self::URI, $request)->assertUnprocessable();
        $this->assertDatabaseCount('certificate_templates', 0);
    }

    public function test_raster_dimensions_and_proof_overflow(): void
    {
        $this->actingAs($this->member('HR'), 'api');
        $image = imagecreatetruecolor(880, 440);
        ob_start();
        imagepng($image);
        $imageBytes = ob_get_clean();
        $upload = $this->upload($imageBytes, 'landscape.png')->assertCreated()->json('data');
        $this->assertEqualsWithDelta(2, $upload['page']['width'] / $upload['page']['height'], .00001);
        ob_start();
        imagejpeg($image);
        $jpg = $this->upload(ob_get_clean(), 'landscape.jpg')->assertCreated()->json('data');
        $this->assertSame('image/jpeg', $jpg['mime']);
        $this->assertEqualsWithDelta(2, $jpg['page']['width'] / $jpg['page']['height'], .00001);
        $this->postJson(self::URI.'/draft-background', ['background_token' => $jpg['token']])
            ->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $config = $this->configuration();
        $config['boxes'][0]['h'] = 1;
        $this->postJson(self::URI.'/proof', ['background_token' => $upload['token'], 'configuration' => $config, 'sample' => 'long'])
            ->assertUnprocessable()->assertJsonValidationErrors('configuration');
    }

    private function upload(string $bytes, string $name = 'background.pdf')
    {
        return $this->post(self::URI.'/backgrounds', ['file' => UploadedFile::fake()->createWithContent($name, $bytes)], [
            'Accept' => 'application/json',
        ]);
    }

    private function pdf(bool $active = false, bool $multiple = false): string
    {
        $action = $active ? '/OpenAction << /S /JavaScript /JS (void 0) >>' : '';
        $count = $multiple ? 2 : 1;
        $pages = $multiple ? '3 0 R 5 0 R' : '3 0 R';
        $stream = '0 .5 .5 rg 10 10 50 50 re f';
        $leaf = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595.276 841.89] /Contents 4 0 R >>';
        $objects = [
            "<< /Type /Catalog /Pages 2 0 R $action >>",
            "<< /Type /Pages /Count $count /Kids [$pages] >>",
            $leaf,
            '<< /Length '.strlen($stream)." >>\nstream\n$stream\nendstream",
        ];
        if ($multiple) {
            $objects[] = $leaf;
        }
        $pdf = "%PDF-1.4\n";
        $xref = "0000000000 65535 f \n";
        foreach ($objects as $id => $object) {
            $xref .= sprintf("%010d 00000 n \n", strlen($pdf));
            $pdf .= ($id + 1)." 0 obj\n$object\nendobj\n";
        }

        return $pdf."xref\n0 ".(count($objects) + 1)."\n$xref"."trailer\n<< /Root 1 0 R /Size ".(count($objects) + 1)." >>\nstartxref\n".strlen($pdf)."\n%%EOF\n";
    }
}
