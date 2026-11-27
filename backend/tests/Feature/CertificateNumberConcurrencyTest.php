<?php

namespace Tests\Feature;

use App\Models\Certificate;
use App\Services\Certificate\CertificateReservations;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Support\CertificateFixtures;
use Tests\Support\IsolatedTestDatabase;
use Tests\TestCase;
use Throwable;

class CertificateNumberConcurrencyTest extends TestCase
{
    use CertificateFixtures;

    public function test_mysql_transactions_serialize_duplicate_reservations_and_allocate_distinct_numbers(): void
    {
        if (config('database.connections.'.config('database.default').'.driver') !== 'mysql' || ! function_exists('pcntl_fork')) {
            $this->markTestSkipped('Requires explicitly isolated MySQL and CLI PCNTL.');
        }
        IsolatedTestDatabase::verify($this->app);
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
        $this->travelTo(CarbonImmutable::parse('2026-12-01 00:00:00', 'Asia/Damascus'));
        Storage::fake('private');
        $hr = $this->member('HR');
        $work = [];
        for ($i = 0; $i < 2; $i++) {
            $program = $this->program();
            $work[] = [$program, $this->trainee($program), $this->version($hr)];
        }
        $jobsBefore = DB::table('certificate_jobs')->count();
        DB::disconnect();
        $start = microtime(true) + 0.5;
        $children = [];
        foreach ([...$work, $work[0]] as [$program, $trainee, $version]) {
            $pid = pcntl_fork();
            $this->assertGreaterThanOrEqual(0, $pid);
            if ($pid === 0) {
                usleep((int) max(0, ($start - microtime(true)) * 1_000_000));
                try {
                    app(CertificateReservations::class)->confirm(
                        $hr, $program, $version, [$trainee->id], (string) Str::uuid(), true,
                    );
                    exit(0);
                } catch (ValidationException $exception) {
                    exit(array_key_exists('issuance', $exception->errors()) ? 2 : 3);
                } catch (Throwable $exception) {
                    fwrite(STDERR, $exception->getMessage().PHP_EOL);
                    exit(1);
                }
            }
            $children[] = $pid;
        }
        $exits = [];
        foreach ($children as $pid) {
            pcntl_waitpid($pid, $status);
            $this->assertTrue(pcntl_wifexited($status));
            $exits[] = pcntl_wexitstatus($status);
        }
        sort($exits);
        $this->assertSame([0, 0, 2], $exits);
        $this->assertSame($jobsBefore + 2, DB::table('certificate_jobs')->count());
        $numbers = Certificate::whereIn('training_program_id', array_map(fn ($job) => $job[0]->id, $work))
            ->orderBy('number')->pluck('number');
        $this->assertCount(2, $numbers);
        $this->assertNotSame($numbers[0], $numbers[1]);
        $this->assertSame((int) substr($numbers[0], 10) + 1, (int) substr($numbers[1], 10));
    }
}
