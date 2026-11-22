<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_deliveries', function (Blueprint $table) {
            $table->string('outcome', 32)->nullable();
            $table->unsignedInteger('accepted_count')->default(0);
            $table->unsignedInteger('rejected_count')->default(0);
            $table->index(['outcome', 'available_at'], 'notification_delivery_outcome_due');
        });

        DB::table('notifieds')->where('read', true)->whereNull('read_at')->update([
            'read_at' => DB::raw('COALESCE(updated_at, created_at, CURRENT_TIMESTAMP)'),
        ]);
    }

    public function down(): void
    {
        Schema::table('notification_deliveries', function (Blueprint $table) {
            $table->dropIndex('notification_delivery_outcome_due');
            $table->dropColumn(['outcome', 'accepted_count', 'rejected_count']);
        });
    }
};
