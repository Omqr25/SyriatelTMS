<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class DevelopmentActionsTest extends TestCase
{
    public function test_test_actions_are_not_available_over_http(): void
    {
        Bus::fake();
        Mail::shouldReceive('raw')->never();

        foreach (['/api/test-email', '/api/test-queue'] as $url) {
            $this->getJson($url)->assertNotFound();
            $this->postJson($url)->assertNotFound();
        }

        $this->getJson('/api/health-check')->assertOk()->assertJsonPath('status', 'ok');
        Bus::assertNothingDispatched();
    }
}
