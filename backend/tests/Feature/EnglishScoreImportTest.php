<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Role;
use App\Models\TrainingProgram;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as XlsxReader;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class EnglishScoreImportTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/v1/english-score-imports';

    protected function setUp(): void
    {
        parent::setUp();
        config(['jwt.secret' => str_repeat('test-secret-', 8)]);
    }

    private function user(string $role): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::firstOrCreate(['role' => $role]));
        return $user;
    }

    private function applicant(TrainingProgram $program, string $email, ?string $score = null): Application
    {
        return Application::factory()->forProgram($program)->create([
            'email' => $email,
            'english_test_score' => $score,
            'commitment' => true,
            'previous_training' => false,
            'status' => 'pending',
        ]);
    }

    private function workbook(array $rows, bool $extraSheet = false): UploadedFile
    {
        $book = new Spreadsheet;
        $book->getActiveSheet()->fromArray($rows);
        if ($extraSheet) {
            $book->createSheet();
        }
        ob_start();
        try {
            (new Xlsx($book))->save('php://output');
            $bytes = ob_get_contents();
        } finally {
            ob_end_clean();
            $book->disconnectWorksheets();
        }

        return UploadedFile::fake()->createWithContent('scores.xlsx', $bytes);
    }

    private function preview(TrainingProgram $program, array $rows): TestResponse
    {
        return $this->postJson(self::URL.'/preview', [
            'training_program_id' => $program->id,
            'file' => $this->workbook([['Name', 'Email', 'English test score'], ...$rows]),
        ]);
    }

    public function test_hr_preview_does_not_write_scores_and_confirmation_is_scoped_and_idempotent(): void
    {
        $program = TrainingProgram::factory()->create();
        $other = TrainingProgram::factory()->create();
        $match = $this->applicant($program, ' Person@example.test ');
        $outside = $this->applicant($other, 'person@example.test');
        $unscored = $this->applicant($program, 'absent@example.test');
        $this->actingAs($this->user('HR'), 'api');

        $preview = $this->preview($program, [['Different name', ' PERSON@Example.test ', '0']])
            ->assertCreated()->assertJsonPath('data.summary.fully_matched', true)
            ->assertJsonPath('data.summary.applicants_without_scores', 2)->json('data');
        $this->assertNull($match->fresh()->english_test_score);
        $this->getJson(self::URL)->assertJsonCount(0, 'data');
        $id = $preview['id'];

        $this->getJson(self::URL."/$id/rows")->assertJsonPath('data.0.row_number', 2)
            ->assertJsonPath('data.0.score', '0.00')->assertJsonPath('data.0.status', 'ready');
        for ($attempt = 0; $attempt < 2; $attempt++) {
            $this->postJson(self::URL."/$id/confirm", ['replace_existing' => false])
                ->assertOk()->assertJsonPath('data.summary.counts.applied', 1)
                ->assertJsonPath('data.summary.applicants_without_scores', 1);
        }
        $this->assertSame('0.00', $match->fresh()->english_test_score);
        $this->assertNull($outside->fresh()->english_test_score);
        $this->assertNull($unscored->fresh()->english_test_score);
        $this->getJson(self::URL)->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/application')->assertOk()->assertJsonFragment(['id' => $match->id, 'english_test_score' => '0.00']);
        $this->getJson('/api/v1/application/'.$match->id)->assertJsonMissingPath('data.english_test_score');
    }

    public function test_report_retains_excel_rows_and_distinguishes_conflicts_invalid_values_and_stale_matches(): void
    {
        $program = TrainingProgram::factory()->create();
        $this->applicant($program, 'duplicate@example.test');
        $this->applicant($program, ' DUPLICATE@example.test ');
        $this->applicant($program, 'conflict@example.test');
        $this->applicant($program, 'invalid-score@example.test');
        $staleScore = $this->applicant($program, 'stale@example.test', '20.00');
        $staleMatch = $this->applicant($program, 'new-duplicate@example.test');
        $ready = $this->applicant($program, 'ready@example.test');
        $existing = $this->applicant($program, 'existing@example.test', '70.00');
        $this->actingAs($this->user('HR'), 'api');

        $id = $this->preview($program, [
            ['Missing', null, null],
            [null, null, null],
            ['Invalid', 'bad email', 88],
            ['Unmatched', 'unknown@example.test', 50],
            ['Ambiguous', 'duplicate@example.test', 25],
            ['Conflict', 'conflict@example.test', 25],
            ['Conflict', 'conflict@example.test', 30],
            ['Out of range', 'invalid-score@example.test', 101],
            ['Missing', 'invalid-score@example.test', null],
            ['Formula', 'invalid-score@example.test', '=25+25'],
            ['Stale score', 'stale@example.test', 80],
            ['Stale match', 'new-duplicate@example.test', 80],
            ['Ready', 'ready@example.test', 82.50],
            ['Same score', ' READY@example.test ', 82.50],
            ['Replacement', 'existing@example.test', 90],
        ])->assertCreated()->assertJsonPath('data.summary.fully_matched', false)->json('data.id');

        $staleScore->update(['english_test_score' => '40.00']);
        $this->applicant($program, 'NEW-DUPLICATE@example.test');
        $this->postJson(self::URL."/$id/confirm", ['replace_existing' => false])
            ->assertOk()->assertJsonPath('data.summary.counts.stale', 2)
            ->assertJsonPath('data.summary.counts.duplicate', 1)
            ->assertJsonPath('data.summary.counts.replacement_skipped', 1);
        $this->assertSame('40.00', $staleScore->fresh()->english_test_score);
        $this->assertNull($staleMatch->fresh()->english_test_score);
        $this->assertSame('82.50', $ready->fresh()->english_test_score);
        $this->assertSame('70.00', $existing->fresh()->english_test_score);

        $report = collect($this->getJson(self::URL."/$id/rows?exceptions=1")->assertOk()->json('data'))->keyBy('row_number');
        foreach ([
            2 => 'missing_email', 4 => 'invalid_email', 5 => 'unmatched', 6 => 'ambiguous',
            7 => 'conflict', 8 => 'conflict', 9 => 'out_of_range', 10 => 'missing_score',
            11 => 'invalid_score', 12 => 'stale', 13 => 'stale', 16 => 'replacement_skipped',
        ] as $row => $status) {
            $this->assertSame($status, $report[$row]['status']);
        }
        $this->assertSame('bad email', $report[4]['email']);
        $this->assertSame('101', $report[9]['score_input']);
        $this->assertSame('score_changed', $report[12]['reason']);
        $this->assertSame('match_changed', $report[13]['reason']);

        $download = $this->withHeader('Accept-Language', 'ar')->get(self::URL."/$id/exceptions")
            ->assertOk()->assertDownload("english-score-exceptions-$id.xlsx")->streamedContent();
        $export = UploadedFile::fake()->createWithContent('report.xlsx', $download);
        $spreadsheet = (new XlsxReader)->load($export->getRealPath());
        try {
            $sheet = $spreadsheet->getActiveSheet();
            $this->assertSame('صف Excel', $sheet->getCell('A1')->getValue());
            foreach ($sheet->getRowIterator(2) as $exportedRow) {
                $line = $exportedRow->getRowIndex();
                if ((int) $sheet->getCell('A'.$line)->getValue() === 11) {
                    $this->assertSame(DataType::TYPE_STRING, $sheet->getCell('D'.$line)->getDataType());
                    $this->assertSame('=25+25', $sheet->getCell('D'.$line)->getValue());
                    $foundFormulaRow = true;
                }
            }
            $this->assertTrue($foundFormulaRow ?? false);
        } finally {
            $spreadsheet->disconnectWorksheets();
        }

        $replaceId = $this->preview($program, [['Name', 'existing@example.test', 95]])->assertCreated()->json('data.id');
        $this->postJson(self::URL."/$replaceId/confirm", ['replace_existing' => true])->assertOk();
        $this->assertSame('95.00', $existing->fresh()->english_test_score);
    }

    public function test_authorization_workbook_validation_and_preview_expiry(): void
    {
        $program = TrainingProgram::factory()->create();
        $this->getJson(self::URL)->assertUnauthorized();
        $this->actingAs($this->user('SUPERVISOR'), 'api')->getJson(self::URL)->assertForbidden();
        $this->postJson(self::URL.'/preview')->assertForbidden();
        $this->getJson(self::URL.'/1/exceptions')->assertForbidden();
        $this->getJson(self::URL.'/template')->assertForbidden();
        $this->postJson(self::URL.'/1/confirm')->assertForbidden();
        $this->getJson(self::URL.'/1/rows')->assertForbidden();

        $hr = $this->user('HR');
        $anotherHr = $this->user('HR');
        $this->actingAs($hr, 'api');
        $this->assertStringStartsWith('PK', $this->get(self::URL.'/template')
            ->assertOk()->assertDownload('english-score-template.xlsx')->streamedContent());
        foreach ([
            UploadedFile::fake()->create('scores.csv', 1, 'text/csv'),
            $this->workbook([['Email', 'Wrong header'], ['example@example.test', 60]]),
            $this->workbook([['Email', 'English test score'], ['example@example.test', 60]], true),
        ] as $file) {
            $this->postJson(self::URL.'/preview', ['training_program_id' => $program->id, 'file' => $file])
                ->assertUnprocessable()->assertJsonValidationErrors('file');
        }

        $this->postJson(self::URL.'/preview', [
            'training_program_id' => $program->id,
            'file' => UploadedFile::fake()->create('large.xlsx', 5121, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
        ], ['Accept-Language' => 'ar'])->assertUnprocessable()
            ->assertJsonPath('errors.file.0', 'يجب ألا يتجاوز حجم الملف 5 ميغابايت.');

        $id = $this->preview($program, [['Name', 'unknown@example.test', 60]])->assertCreated()->json('data.id');
        $this->actingAs($anotherHr, 'api')->getJson(self::URL."/$id/rows")->assertNotFound();
        $this->getJson(self::URL."/$id/exceptions")->assertNotFound();
        $this->postJson(self::URL."/$id/confirm", ['replace_existing' => false])->assertNotFound();

        $this->travel(31)->minutes();
        $this->actingAs($hr, 'api')->postJson(self::URL."/$id/confirm", ['replace_existing' => false])
            ->assertUnprocessable();
        $this->getJson(self::URL."/$id/rows")->assertNotFound();
    }
}
