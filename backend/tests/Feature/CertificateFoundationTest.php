<?php

namespace Tests\Feature;

use App\Enums\CertificateEventAction;
use App\Enums\CertificateState;
use App\Models\Certificate;
use App\Models\CertificateEvent;
use App\Services\Certificate\CertificateLifecycle;
use App\Services\Certificate\CertificateNames;
use App\Services\Certificate\CertificateReservations;
use App\Services\Certificate\CertificateRoster;
use App\Services\Certificate\CertificateStorage;
use App\Services\Certificate\CertificateTemplates;
use App\Services\Certificate\CertificateText;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;
use Tests\Support\CertificateFixtures;
use Tests\Support\UsesCertificateTestDatabase;
use Tests\TestCase;

class CertificateFoundationTest extends TestCase
{
    use CertificateFixtures, UsesCertificateTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private');
        $this->at('2026-12-01 00:00:00');
    }

    private function at(string $date): void
    {
        $this->travelTo(CarbonImmutable::parse($date, 'Asia/Damascus'));
    }

    private function rejects(callable $callback, string $field, ?string $message = null): void
    {
        try {
            $callback();
            $this->fail("Expected rejection for $field");
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());
            if ($message) {
                $this->assertStringContainsString($message, $exception->errors()[$field][0]);
            }
        }
    }

    public function test_damascus_boundaries_and_manual_opening_do_not_change_original_names(): void
    {
        $program = $this->program();
        $early = $this->program();
        $trainee = $this->trainee($program);
        $actor = $trainee->user;
        $hr = $this->member('HR');
        $names = app(CertificateNames::class);
        $this->at('2026-11-22 23:59:59');
        $this->assertSame('scheduled', $names->window($program)['state']);
        $this->rejects(fn () => $names->submit($actor, $program, $trainee, 'New North'), 'name');
        $names->open($hr, $early);
        $this->assertSame('manual', $names->window($early)['source']);

        $this->at('2026-11-23 00:00:00');
        $this->assertSame('open', $names->window($program)['state']);
        $this->assertSame('automatic', $names->window($program)['source']);
        $names->submit($actor, $program, $trainee, 'François A. O’Neill');
        $this->at('2026-11-30 23:59:59');
        $names->submit($actor, $program, $trainee, 'New North');
        $this->assertSame(['name' => 'New North', 'source' => 'trainee'], $names->resolve($program, $trainee));
        $this->assertSame('Original North', $trainee->application->fresh()->full_name);
        $this->assertSame('Account North', $actor->fresh()->name);

        $this->at('2026-12-01 00:00:00');
        $this->assertSame('closed', $names->window($program)['state']);
        $this->rejects(fn () => $names->submit($actor, $program, $trainee, 'Too Late'), 'name');
        $this->rejects(fn () => $names->open($hr, $program), 'name');
    }

    public function test_name_priority_and_hr_audit_and_validation(): void
    {
        $program = $this->program();
        $hr = $this->member('HR');
        $names = app(CertificateNames::class);
        $trainee = $this->trainee($program);
        $this->assertSame('application', $names->resolve($program, $trainee)['source']);
        $this->rejects(fn () => $names->saveManual($hr, $program, $trainee, 'HR Default'), 'name');
        $trainee->application->update(['full_name' => 'نور']);
        $this->assertSame('account', $names->resolve($program, $trainee->fresh())['source']);
        $trainee->user->update(['name' => 'حساب']);
        $trainee = $trainee->fresh();
        $this->assertNull($names->resolve($program, $trainee));
        $names->saveManual($hr, $program, $trainee, 'First Name');
        $names->saveManual($hr, $program, $trainee, 'Corrected Name');
        $this->assertSame(['name' => 'Corrected Name', 'source' => 'hr'], $names->resolve($program, $trainee));
        $this->assertDatabaseCount('certificate_names', 1);
        $events = CertificateEvent::orderBy('id')->get();
        $this->assertCount(2, $events);
        $this->assertSame(CertificateEventAction::HrNameSaved, $events[1]->action);
        $this->assertEquals(['previous' => 'First Name', 'name' => 'Corrected Name'], $events[1]->details);
        $this->assertSame($hr->id, $events[1]->actor_id);
        $this->assertNotNull($events[1]->created_at);
        foreach (['', '   ', 'نور Smith', "Ray\nNorth", '<script>', str_repeat('A', 201)] as $invalid) {
            $this->assertNull(CertificateText::name($invalid));
        }
        $this->assertSame('François A. O’Neill', CertificateText::name(' François A. O’Neill '));
    }

    public function test_reservation_is_immutable_and_new_attempt_cannot_publish_the_old_file(): void
    {
        $hr = $this->member('HR');
        $program = $this->program();
        $first = $this->trainee($program);
        $second = $this->trainee($program);
        $version = $this->version($hr);
        $key = (string) Str::uuid();
        $service = app(CertificateReservations::class);
        $batch = $service->confirm($hr, $program, $version, [$first->id, $second->id], $key, true);
        $certs = $batch->certificates()->orderBy('id')->get();
        $certificate = $certs[0];
        $snapshot = $certificate->snapshot;
        $this->assertSame('CERT-2026-000001', $certificate->number);
        $this->assertSame('CERT-2026-000002', $certs[1]->number);
        $this->assertSame('2026-12-01', $certs[1]->issue_date->toDateString());
        $this->assertEquals(['trainee_name' => 'Original North', 'department_name' => 'Network Operations',
            'issue_date' => '2026-12-01', 'certificate_number' => $certificate->number], $snapshot['values']);
        $this->assertSame($batch->id, $service->confirm($hr, $program, $version, [$second->id, $first->id], $key, true)->id);
        $this->rejects(fn () => $service->confirm($hr, $program, $version, [$first->id], $key, true), 'issuance');
        $this->rejects(fn () => $service->confirm($hr, $program, $version, [$first->id], (string) Str::uuid(), true), 'issuance');

        $first->department->update(['name' => 'Changed Department']);
        $version2 = app(CertificateTemplates::class)->addVersion($hr, $version->template, $version->background_path, $this->configuration([]));
        $this->assertSame(2, $version2->version);
        app(CertificateTemplates::class)->archive($hr, $version2->template);

        $process = app(CertificateLifecycle::class);
        $old = $process->start($certificate);
        $failed = $process->fail($old, $old->attempt_token, 'temporary_storage');
        $current = $process->start($process->retry($hr, $failed));
        try {
            $process->publish($current, $old->attempt_token);
            $this->fail('The old attempt must not publish');
        } catch (LogicException) {
            $this->assertSame(CertificateState::Generating, $current->fresh()->state);
        }

        $path = app(CertificateStorage::class)->issuedPath($current->id, $current->attempt_token);
        Storage::disk('private')->put($path, '%PDF synthetic');
        $issued = $process->publish($current, $current->attempt_token);
        $this->assertSame(CertificateState::Issued, $issued->state);
        $this->assertSame($issued->id, $process->publish($current, $current->attempt_token)->id);
        $this->assertSame($snapshot, $issued->snapshot);
        $this->assertSame($version->id, $issued->templateVersion->id);
        $this->assertSame(2, $issued->generation_attempts);
        $this->assertTrue(Gate::forUser($first->user)->allows('view', $issued));
        $this->assertFalse(Gate::forUser($second->user)->allows('view', $issued));
        Storage::disk('private')->assertExists($path);
        $this->expectException(LogicException::class);
        $issued->delete();
    }

    public function test_eligibility_exclusion_and_invalid_sources_do_not_become_failed_issuance(): void
    {
        $hr = $this->member('HR');
        $program = $this->program();
        $trainee = $this->trainee($program, ['application' => 'اسم', 'account' => 'حساب']);
        $version = $this->version($hr);
        $reserve = app(CertificateReservations::class);
        $roster = app(CertificateRoster::class);
        $issue = fn (?string $reason = null) => $reserve->confirm($hr, $program, $version, [$trainee->id], (string) Str::uuid(), true, $reason);
        $this->at('2026-11-30 23:59:59');
        $this->rejects(fn () => $issue('Before cutoff'), 'issuance');
        $this->at('2026-12-01 00:00:00');
        $this->assertSame('ready', $roster->status($program, $trainee));
        $roster->setExcluded($hr, $program, $trainee, true, 'Exclude');
        $this->assertSame('excluded', $roster->status($program, $trainee));
        $this->rejects($issue, 'issuance');
        $roster->setExcluded($hr, $program, $trainee, false, 'Restore');
        $this->assertSame('ready', $roster->status($program, $trainee));
        $trainee->update(['status' => 'fired']);
        $this->assertSame('fired', $roster->status($program, $trainee));
        $this->rejects(fn () => $issue('Exceptional reason'), 'issuance', 'valid English');
        $this->assertDatabaseCount('certificates', 0);
        $this->assertDatabaseCount('certificate_batches', 0);
        app(CertificateNames::class)->saveManual($hr, $program, $trainee, 'Approved Name');
        $certificate = $issue('Exceptional reason')->certificates()->firstOrFail();
        $event = CertificateEvent::where('certificate_id', $certificate->id)->firstOrFail();
        $this->assertSame('fired', $event->details['trainee_status']);
        $this->assertSame($certificate->number, $event->details['number']);
        $this->assertSame('Approved Name', $certificate->snapshot['trainee_name']);
        $this->assertSame('fired', $trainee->fresh()->status->value);
    }

    public function test_missing_english_template_value_blocks_only_when_referenced(): void
    {
        $hr = $this->member('HR');
        $program = $this->program();
        $trainee = $this->trainee($program, ['department' => 'قسم']);
        $version = $this->version($hr);
        $reserve = app(CertificateReservations::class);
        $this->rejects(fn () => $reserve->confirm($hr, $program, $version, [$trainee->id], (string) Str::uuid(), true), 'placeholders');
        $this->assertDatabaseCount('certificate_number_sequences', 0);
        $unused = $this->version($hr, []);
        $batch = $reserve->confirm($hr, $program, $unused, [$trainee->id], (string) Str::uuid(), true);
        $this->assertSame([], $batch->certificates()->firstOrFail()->snapshot['values']);
    }

    public function test_owner_and_current_hr_role_are_required_and_unissued_records_are_hidden(): void
    {
        $program = $this->program();
        $trainee = $this->trainee($program);
        $other = $this->trainee($program);
        $this->assertFalse(Gate::forUser($other->user)->allows('submitName', [Certificate::class, $trainee]));
        $this->assertTrue(Gate::forUser($trainee->user)->allows('submitName', [Certificate::class, $trainee]));
        $hr = $this->member('HR');
        $version = $this->version($hr);
        $batch = app(CertificateReservations::class)->confirm($hr, $program, $version, [$trainee->id], (string) Str::uuid(), true);
        $record = $batch->certificates()->firstOrFail();
        $this->assertFalse(Gate::forUser($trainee->user)->allows('view', $record));
        $this->assertSame(0, Certificate::issued()->count());
        $hr->roles()->detach();
        $this->expectException(AuthorizationException::class);
        app(CertificateNames::class)->open($hr, $program);
    }

    public function test_templates_reject_reserved_placeholders_and_versions_cannot_be_modified(): void
    {
        $hr = $this->member('HR');
        $version = $this->version($hr);
        $this->rejects(fn () => app(CertificateTemplates::class)
            ->addVersion($hr, $version->template, $version->background_path, $this->configuration(['unit_name'])), 'config.boxes.0.paragraphs.0.1.token');
        $this->assertDatabaseCount('certificate_template_versions', 1);
        $this->expectException(LogicException::class);
        $version->update(['configuration' => []]);
    }

    public function test_the_database_rejects_duplicate_certificate_identity(): void
    {
        $trainee = $this->trainee($program = $this->program());
        $attributes = ['trainee_id' => $trainee->id, 'training_program_id' => $program->id,
            'issue_date' => '2026-12-01', 'path' => 'certificates/legacy.pdf'];
        DB::table('certificates')->insert($attributes);
        $this->expectException(QueryException::class);
        DB::table('certificates')->insert($attributes);
    }
}
