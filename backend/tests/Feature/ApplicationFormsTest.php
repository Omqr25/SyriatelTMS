<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\ApplicationAnswer;
use App\Models\ApplicationForm;
use App\Models\Role;
use App\Models\TrainingProgram;
use App\Models\User;
use App\Services\File\FileStorageService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class ApplicationFormsTest extends TestCase
{
    use RefreshDatabase;

    private const APPLY = '/api/v1/application';

    private const OPEN_PROGRAM = '/api/v1/training_program/openProgram';

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2030, 1, 1)->startOfDay());
        Storage::fake('private');
    }

    private function program(int $daysUntilOpen = 5): TrainingProgram
    {
        return TrainingProgram::factory()->create([
            'application_start_date' => today()->addDays($daysUntilOpen),
            'application_end_date' => today()->addDays($daysUntilOpen + 5),
            'start_date' => today()->addDays($daysUntilOpen + 6),
            'end_date' => today()->addDays($daysUntilOpen + 25),
            'status' => 'Closed',
        ]);
    }

    private function login(string $role = 'HR'): void
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::firstOrCreate(['role' => $role]));
        $this->actingAs($user, 'api');
    }

    private function url(TrainingProgram $program): string
    {
        return "/api/v1/training_program/{$program->id}/application-form";
    }

    private function saveForm(TrainingProgram $program, array $questions, int $revision = 0): array
    {
        return $this->putJson($this->url($program), [
            'expected_revision' => $revision,
            'questions' => $questions,
        ])->assertOk()->json('data');
    }

    private function publishedForm(TrainingProgram $program, array $questions): array
    {
        $this->login();
        $draft = $this->saveForm($program, $questions);

        return $this->postJson($this->url($program).'/publish', [
            'expected_revision' => $draft['revision'],
        ])->assertOk()->assertJsonPath('data.can_edit', false)->json('data.form');
    }

    private static function question(string $type, array $properties = []): array
    {
        return $properties + ['prompt' => "Answer for $type", 'type' => $type, 'required' => true];
    }

    private function fixedFields(TrainingProgram $program): array
    {
        $pdf = fn () => UploadedFile::fake()->create('document.pdf', 1, 'application/pdf');

        return [
            'training_program_id' => $program->id,
            'full_name' => 'Test Applicant',
            'email' => 'applicant@example.net',
            'phone_number' => '0945123456',
            'birth_date' => '2000-01-01',
            'city' => 'damascus',
            'year_of_study' => 'graduated',
            'gpa' => 90,
            'commitment' => true,
            'previous_training' => false,
            'how_heard' => 'social media',
            'home_address' => 'Damascus',
            'university' => 'Damascus University',
            'field_of_study' => 'IT & Software Engineering',
            'academic_major' => 'IT',
            'technical_skills' => 'Programming',
            'national_id_number' => '12345678901',
            'cv' => $pdf(),
            'national_id' => $pdf(),
            'academic_qualification' => $pdf(),
            'personal_photo' => $pdf(),
        ];
    }

    private function submit(TrainingProgram $program, array $data = [])
    {
        return $this->post(self::APPLY, $data + $this->fixedFields($program), ['Accept' => 'application/json']);
    }

    public function test_guest_submission_and_hr_history_use_the_original_form_and_all_four_value_shapes(): void
    {
        $program = $this->program();
        $form = $this->publishedForm($program, [
            self::question('number', ['min_value' => '0']),
            self::question('text'),
            self::question('single_choice', ['options' => ['One', 'Two']]),
            self::question('multiple_choice', ['options' => ['Alpha', 'Beta', 'Gamma']]),
            self::question('text', ['prompt' => 'Optional', 'required' => false]),
        ]);
        [$number, $text, $single, $multiple] = $form['questions'];
        $this->getJson(self::OPEN_PROGRAM.'?include=application_form')->assertNotFound();
        $this->travelTo($program->application_start_date);
        $this->getJson(self::OPEN_PROGRAM)->assertOk()->assertJsonMissingPath('data.application_form');
        $this->getJson(self::OPEN_PROGRAM.'?include=application_form')->assertOk()
            ->assertJsonPath('data.application_form', $form);

        $data = [
            'application_form_id' => $form['id'],
            'answers' => json_encode([
                ['question_id' => $number['id'], 'numeric_value' => '0'],
                ['question_id' => $text['id'], 'text_value' => '  A meaningful answer  '],
                ['question_id' => $single['id'], 'option_ids' => [$single['options'][1]['id']]],
                ['question_id' => $multiple['id'], 'option_ids' => [$multiple['options'][2]['id'], $multiple['options'][0]['id']]],
            ], JSON_THROW_ON_ERROR),
        ];
        $response = $this->submit($program, $data + [
            'training_program_id' => (string) $program->id,
            'gpa' => '90.50',
            'commitment' => '1',
            'previous_training' => '0',
        ])->assertCreated()->assertJsonMissingPath('data.answers');
        $application = Application::sole();
        $this->assertSame($application->training_program_id, $response->json('data.training_program_id'));
        $this->assertSame($application->gpa, $response->json('data.gpa'));
        $this->assertSame($application->commitment, $response->json('data.commitment'));
        $this->assertSame($application->previous_training, $response->json('data.previous_training'));
        $this->assertSame($form['id'], $application->application_form_id);
        $this->assertSame($program->id, $application->training_program_id);
        $this->assertSame('Test Applicant', $application->full_name);
        $this->assertEquals(90.50, $application->gpa);
        $this->assertSame('pending', $application->status->value);
        Storage::disk('private')->assertExists($application->cv);
        $this->get(self::APPLY."/{$application->id}/attachments/cv")->assertOk();
        $answers = $application->answers()->get()->keyBy('form_question_id');
        $this->assertCount(4, $answers);
        $this->assertSame('0.0000', $answers[$number['id']]->numeric_value);
        $this->assertNull($answers[$number['id']]->text_value);
        $this->assertSame('A meaningful answer', $answers[$text['id']]->text_value);
        $this->assertNull($answers[$text['id']]->numeric_value);
        foreach ([$single, $multiple] as $choice) {
            $this->assertNull($answers[$choice['id']]->text_value);
            $this->assertNull($answers[$choice['id']]->numeric_value);
        }
        $this->assertEquals([$single['options'][1]['id']], $answers[$single['id']]->options->modelKeys());
        $this->assertEquals([$multiple['options'][0]['id'], $multiple['options'][2]['id']], $answers[$multiple['id']]->options->modelKeys());
        $this->assertDatabaseCount('answer_options', 3);

        $history = self::APPLY."/{$application->id}/answers";
        $this->getJson($history)->assertOk()
            ->assertJsonPath('data.answers.0.prompt', $number['prompt'])
            ->assertJsonPath('data.answers.2.options.0.label', 'Two')
            ->assertJsonPath('data.answers.3.options.1.label', 'Gamma');
        $this->login('SUPERVISOR');
        $this->getJson($history)->assertForbidden();
        $this->getJson(self::APPLY."/{$application->id}")->assertOk()->assertJsonMissingPath('data.answers');
        $this->submit($program, $data)->assertUnprocessable()->assertJsonValidationErrors(['national_id_number', 'email']);

        $this->login();
        $this->deleteJson(self::APPLY."/{$application->id}")->assertOk();
        $this->assertDatabaseCount('application_answers', 0);
        $this->assertDatabaseCount('answer_options', 0);
        $this->assertDatabaseHas('application_forms', ['id' => $form['id']]);
    }

    public function test_hr_permissions_draft_revision_publication_and_intake_history(): void
    {
        $program = $this->program();
        $url = $this->url($program);
        $questions = [self::question('single_choice')];

        $this->getJson($url)->assertUnauthorized();
        $this->getJson(self::APPLY.'/1/answers')->assertUnauthorized();
        $this->login('TRAINEE');
        $this->getJson($url)->assertForbidden();
        $this->putJson($url, ['expected_revision' => 0, 'questions' => $questions])->assertForbidden();
        $this->postJson($url.'/publish', ['expected_revision' => 1])->assertForbidden();

        $this->login();
        $program->forceFill(['archived_at' => now(), 'registration_has_opened' => null])->save();
        $this->getJson($url)->assertOk()->assertJsonPath('data.requires_history_confirmation', true);
        $this->saveForm($program, $questions);
        $this->postJson($url.'/publish', ['expected_revision' => 1])->assertUnprocessable()
            ->assertJsonValidationErrors('confirm_unopened');
        $this->postJson($url.'/publish', ['expected_revision' => 1, 'confirm_unopened' => true])->assertUnprocessable()
            ->assertJsonValidationErrors('questions.0.options');

        $questions[0]['options'] = ['A', 'B'];
        $this->saveForm($program, $questions, 1);
        $this->postJson($url.'/publish', ['expected_revision' => 1, 'confirm_unopened' => true])->assertConflict()
            ->assertJsonPath('data.reason', 'application_form_stale');
        $this->postJson($url.'/publish', ['expected_revision' => 2, 'confirm_unopened' => true])->assertOk();
        $this->putJson($url, ['expected_revision' => 2, 'questions' => []])->assertConflict();
        $this->postJson($url.'/publish', ['expected_revision' => 2, 'confirm_unopened' => true])->assertConflict();
        $this->getJson($url)->assertOk()->assertJsonPath('data.form.questions.0.options.1.label', 'B');
        $this->travelTo($program->application_start_date);
        $this->getJson(self::OPEN_PROGRAM.'?include=application_form')->assertNotFound();
        $this->postJson("/api/v1/training_program/{$program->id}/restore")->assertOk();
        $this->getJson(self::OPEN_PROGRAM.'?include=application_form')->assertOk()
            ->assertJsonPath('data.application_form.questions.0.options.1.label', 'B');
    }

    public function test_open_intake_with_unpublished_draft_accepts_old_clients_and_keeps_legacy_reads_working(): void
    {
        $program = $this->program();
        $this->login();
        $draft = $this->saveForm($program, [self::question('text')]);
        $this->travelTo($program->application_start_date);
        $this->getJson(self::OPEN_PROGRAM.'?include=application_form')->assertOk()->assertJsonPath('data.application_form', null);
        $this->submit($program, ['application_form_id' => $draft['form']['id'], 'answers' => '[]'])
            ->assertConflict()->assertJsonPath('data.reason', 'application_form_changed');
        $this->postJson($this->url($program).'/publish', ['expected_revision' => 1])->assertConflict();
        $id = $this->submit($program)->assertCreated()->json('data.id');
        $this->getJson(self::APPLY."/$id/answers")->assertOk()->assertJsonPath('data.application_form_id', null)
            ->assertJsonPath('data.answers', []);
        $this->getJson(self::APPLY."/$id")->assertOk()->assertJsonPath('data.id', $id);
        $this->getJson(self::APPLY)->assertOk()->assertJsonPath('data.0.id', $id);
        $this->assertDatabaseCount('application_answers', 0);
    }

    public function test_explicit_program_deletion_cleans_only_unpublished_drafts(): void
    {
        $program = $this->program();
        $other = $this->program(18);
        $form = $this->publishedForm($other, [self::question('text')]);
        $draft = $this->saveForm($program, [self::question('single_choice', ['options' => ['A']])]);
        $this->deleteJson("/api/v1/training_program/{$program->id}")->assertOk();
        $this->assertDatabaseMissing('application_forms', ['id' => $draft['form']['id']]);
        $this->assertDatabaseCount('question_options', 0);
        $this->deleteJson("/api/v1/training_program/{$other->id}")->assertConflict()
            ->assertJsonPath('data.reason', 'published_form');
        $this->assertDatabaseHas('application_forms', ['id' => $form['id']]);
    }

    public function test_wrong_forms_types_questions_and_selections_are_rejected(): void
    {
        $program = $this->program();
        $other = $this->program(30);
        $otherForm = $this->publishedForm($other, [self::question('text')]);
        $form = $this->publishedForm($program, [
            self::question('text'),
            self::question('single_choice', ['required' => false, 'options' => ['A']]),
            self::question('multiple_choice', ['required' => false, 'options' => ['B', 'C']]),
        ]);
        [$text, $single, $multiple] = $form['questions'];
        $textRow = ['question_id' => $text['id'], 'text_value' => 'A valid answer'];
        $this->travelTo($program->application_start_date);

        $this->submit($program)->assertConflict()->assertJsonPath('data.reason', 'application_form_changed');
        $this->submit($program, ['application_form_id' => $otherForm['id']])->assertConflict();
        $this->submit($program, ['application_form_id' => true])->assertUnprocessable()
            ->assertJsonValidationErrors('application_form_id');
        $this->submit($program, ['application_form_id' => $form['id'], 'answers' => '{"0":{}}'])
            ->assertUnprocessable()->assertJsonValidationErrors('answers');
        foreach ([
            [[], "questions.{$text['id']}"],
            [[['question_id' => $text['id'], 'text_value' => '   ']], "questions.{$text['id']}"],
            [[['question_id' => $text['id'], 'text_value' => str_repeat('a', 101)]], 'answers.0.text_value'],
            [[['question_id' => $text['id'], 'option_ids' => [$single['options'][0]['id']]]], 'answers.0.text_value'],
            [[$textRow, $textRow], 'answers.1.question_id'],
            [[['question_id' => (float) $text['id'], 'text_value' => 'Float ID']], 'answers.0.question_id'],
            [[$textRow, ['question_id' => $otherForm['questions'][0]['id'], 'text_value' => 'Other']], 'answers.1.question_id'],
            [[$textRow, ['question_id' => 999999, 'text_value' => 'Unknown']], 'answers.1.question_id'],
            [[$textRow, ['question_id' => $single['id'], 'option_ids' => [$single['options'][0]['id'], $multiple['options'][0]['id']]]], 'answers.1.option_ids'],
            [[$textRow, ['question_id' => $single['id'], 'option_ids' => [$multiple['options'][0]['id']]]], 'answers.1.option_ids'],
            [[$textRow, ['question_id' => $multiple['id'], 'option_ids' => [$multiple['options'][0]['id'], $multiple['options'][0]['id']]]], 'answers.1.option_ids'],
            [[$textRow, ['question_id' => $multiple['id'], 'option_ids' => []]], 'answers.1.option_ids'],
            [[$textRow, ['question_id' => $multiple['id'], 'option_ids' => ' ']], 'answers.1.option_ids'],
            [[$textRow, ['question_id' => $multiple['id'], 'option_ids' => [true]]], 'answers.1.option_ids.0'],
        ] as [$entries, $error]) {
            $this->submit($program, ['application_form_id' => $form['id'], 'answers' => json_encode($entries, JSON_PRESERVE_ZERO_FRACTION)])
                ->assertUnprocessable()->assertJsonValidationErrors($error);
        }
        $this->assertDatabaseCount('applications', 0);
        $this->assertSame([], Storage::disk('private')->allFiles('applications'));
    }

    public static function numbers(): array
    {
        return [
            'unbounded zero' => [null, null, 0, true],
            'unbounded negative extreme' => [null, null, '-99999999.9999', true],
            'unbounded positive extreme' => [null, null, '99999999.9999', true],
            'minimum only exact' => ['0', null, '0.0000', true],
            'minimum only below' => ['0', null, '-0.0001', false],
            'maximum only exact' => [null, '0.50', '.5000', true],
            'maximum only above' => [null, '0.50', '0.5001', false],
            'two bounds minimum' => ['-12.01', '22', '-12.01', true],
            'two bounds maximum' => ['-12.01', '22', '22', true],
            'two bounds above' => ['-12.01', '22', '22.0001', false],
            'too large' => [null, null, '100000000', false],
            'too precise' => [null, null, '0.00001', false],
            'exponent' => [null, null, '1e3', false],
            'bad token' => [null, null, 'abc', false],
            'boolean' => [null, null, true, false],
        ];
    }

    #[DataProvider('numbers')]
    public function test_numeric_answers_use_exact_precision_and_optional_inclusive_bounds(?string $min, ?string $max, mixed $value, bool $valid): void
    {
        $program = $this->program();
        $form = $this->publishedForm($program, [self::question('number', ['min_value' => $min, 'max_value' => $max])]);
        $this->travelTo($program->application_start_date);
        $response = $this->submit($program, [
            'application_form_id' => $form['id'],
            'answers' => json_encode([['question_id' => $form['questions'][0]['id'], 'numeric_value' => $value]]),
        ]);
        if ($valid) {
            $response->assertCreated();
            $this->assertNotNull(ApplicationAnswer::sole()->numeric_value);
        } else {
            $response->assertUnprocessable()->assertJsonValidationErrors('answers.0.numeric_value');
            $this->assertDatabaseCount('applications', 0);
        }
    }

    public function test_hr_rejects_inverted_bounds_and_invalid_definition_values(): void
    {
        $this->login();
        $url = $this->url($this->program());
        $this->putJson($url, ['expected_revision' => 0, 'questions' => [
            self::question('number', ['min_value' => '2', 'max_value' => '1']),
            self::question('number', ['min_value' => '1.23456', 'options' => ['A']]),
            self::question('text', ['max_value' => 0]),
        ]])->assertUnprocessable()->assertJsonValidationErrors([
            'questions.0.max_value', 'questions.1.min_value', 'questions.1.options', 'questions.2.max_value',
        ]);
        $this->putJson($url, [
            'expected_revision' => 0,
            'questions' => [self::question('number', ['min_value' => '1.00001'])],
        ], ['Accept-Language' => 'ar'])->assertUnprocessable()->assertJsonFragment([
            'questions.0.min_value' => [__('application_form.number_format', [], 'ar')],
        ]);
        $this->assertSame('en', app()->getLocale());
        $this->assertDatabaseCount('application_forms', 0);
    }

    public function test_failed_answer_write_rolls_back_application_prior_selection_and_uploads_and_allows_retry(): void
    {
        $program = $this->program();
        $form = $this->publishedForm($program, [
            self::question('single_choice', ['options' => ['A']]),
            self::question('text'),
        ]);
        [$choice, $text] = $form['questions'];
        $this->travelTo($program->application_start_date);
        $data = ['application_form_id' => $form['id'], 'answers' => json_encode([
            ['question_id' => $choice['id'], 'option_ids' => [$choice['options'][0]['id']]],
            ['question_id' => $text['id'], 'text_value' => 'Second answer'],
        ])];
        $fail = true;
        Event::listen('eloquent.creating: '.ApplicationAnswer::class, function (ApplicationAnswer $answer) use (&$fail, $text) {
            if ($fail && $answer->form_question_id === $text['id']) {
                throw new RuntimeException('Simulated answer insert failure');
            }
        });

        $this->submit($program, $data)->assertServerError();
        $this->assertDatabaseCount('applications', 0);
        $this->assertDatabaseCount('application_answers', 0);
        $this->assertDatabaseCount('answer_options', 0);
        $this->assertFalse($program->fresh()->registration_has_opened);
        $this->assertSame([], Storage::disk('private')->allFiles('applications'));

        $fail = false;
        $this->submit($program, $data)->assertCreated();
        $this->assertDatabaseCount('applications', 1);
        $this->assertDatabaseCount('application_answers', 2);
        $this->assertDatabaseCount('answer_options', 1);
        $this->assertCount(4, Storage::disk('private')->allFiles('applications'));
    }

    public function test_failed_upload_removes_previously_staged_files(): void
    {
        $program = $this->program(0);
        $attempt = 0;
        $this->partialMock(FileStorageService::class, function (MockInterface $storage) use (&$attempt) {
            $storage->shouldReceive('store')->twice()->andReturnUsing(function (UploadedFile $file, string $directory) use (&$attempt) {
                return ++$attempt === 2 ? '' : $file->store($directory, 'private');
            });
        });

        $this->submit($program)->assertServerError();
        $this->assertDatabaseCount('applications', 0);
        $this->assertSame([], Storage::disk('private')->allFiles('applications'));
    }

    public function test_database_membership_constraints_guard_both_composite_joins(): void
    {
        $program = $this->program();
        $other = $this->program(20);
        $form = $this->publishedForm($program, [
            self::question('single_choice', ['options' => ['A']]),
            self::question('single_choice', ['options' => ['B']]),
        ]);
        $otherForm = $this->publishedForm($other, [self::question('text')]);
        $first = $form['questions'][0];
        $second = $form['questions'][1];
        $application = Application::factory()->forProgram($program)->create(['application_form_id' => $form['id']]);
        if (DB::getDriverName() === 'mysql') {
            $this->assertThrows(fn () => $application->update(['application_form_id' => $otherForm['id']]), QueryException::class);
        }
        $this->assertThrows(fn () => $application->answers()->create([
            'form_question_id' => $otherForm['questions'][0]['id'], 'application_form_id' => $form['id'],
        ]), QueryException::class);
        $this->assertThrows(fn () => $application->answers()->create([
            'form_question_id' => $otherForm['questions'][0]['id'], 'application_form_id' => $otherForm['id'],
        ]), QueryException::class);

        $answer = $application->answers()->create(['form_question_id' => $first['id'], 'application_form_id' => $form['id']]);
        $this->assertThrows(fn () => $application->answers()->create([
            'form_question_id' => $first['id'], 'application_form_id' => $form['id'],
        ]), QueryException::class);
        $this->assertThrows(fn () => $answer->options()->attach($second['options'][0]['id'], [
            'form_question_id' => $first['id'],
        ]), QueryException::class);
        $answer->options()->attach($first['options'][0]['id'], ['form_question_id' => $first['id']]);
        $this->assertThrows(fn () => $answer->options()->attach($first['options'][0]['id'], [
            'form_question_id' => $first['id'],
        ]), QueryException::class);
        $this->assertThrows(fn () => ApplicationForm::findOrFail($form['id'])->delete(), QueryException::class);
    }
}
