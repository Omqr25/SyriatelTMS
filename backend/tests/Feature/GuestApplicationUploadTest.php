<?php

namespace Tests\Feature;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Tests\TestCase;

class GuestApplicationUploadTest extends TestCase
{
    public function test_failed_php_uploads_return_an_error_for_each_attachment(): void
    {
        $file = fn (int $error) => new UploadedFile('', 'attachment.pdf', 'application/pdf', $error);

        $this->call('POST', '/api/v1/application', [], [], [
            'cv' => $file(UPLOAD_ERR_INI_SIZE),
            'personal_photo' => $file(UPLOAD_ERR_PARTIAL),
            'national_id' => $file(UPLOAD_ERR_NO_TMP_DIR),
            'academic_qualification' => $file(UPLOAD_ERR_CANT_WRITE),
        ], [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_ORIGIN' => 'http://localhost:5174',
        ])
            ->assertUnprocessable()
            ->assertHeader('Access-Control-Allow-Origin', '*')
            ->assertJsonPath('errors.cv.0', __('application_upload.size'))
            ->assertJsonPath('errors.personal_photo.0', __('application_upload.partial'))
            ->assertJsonPath('errors.national_id.0', __('application_upload.server'))
            ->assertJsonPath('errors.academic_qualification.0', __('application_upload.server'));
    }
}
