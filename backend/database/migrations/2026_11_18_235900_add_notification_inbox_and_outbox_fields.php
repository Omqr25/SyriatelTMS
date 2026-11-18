<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->string('event_key', 191)->nullable()->unique();
            $table->string('type', 80)->nullable();
            $table->json('metadata')->nullable();
        });

        Schema::table('notifieds', function (Blueprint $table) {
            $table->timestamp('read_at')->nullable();
            $table->index(['notified_id', 'read_at']);
        });
        DB::table('notifieds')->where('read', true)->update([
            'read_at' => DB::raw('COALESCE(updated_at, created_at, CURRENT_TIMESTAMP)'),
        ]);

        Schema::create('notification_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notified_id')->unique()->constrained('notifieds')->cascadeOnDelete();
            $table->timestamp('available_at');
            $table->timestamp('leased_until')->nullable();
            $table->uuid('lease_token')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->string('last_error')->nullable();
            $table->json('remaining_device_ids')->nullable();
            $table->index(['delivered_at', 'failed_at', 'available_at'], 'notification_delivery_due');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_deliveries');
        Schema::table('notifieds', function (Blueprint $table) {
            $table->dropIndex(['notified_id', 'read_at']);
            $table->dropColumn('read_at');
        });
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropUnique(['event_key']);
            $table->dropColumn(['event_key', 'type', 'metadata']);
        });
    }
};
