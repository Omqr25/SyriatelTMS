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
            $table->unsignedBigInteger('notifier_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (DB::table('notifications')->whereNull('notifier_id')->exists()) {
            throw new RuntimeException('System notifications must be retained or migrated before rolling back this change.');
        }

        Schema::table('notifications', function (Blueprint $table) {
            $table->unsignedBigInteger('notifier_id')->nullable(false)->change();
        });
    }
};
