<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->unsignedBigInteger('trainee_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (DB::table('tasks')->whereNull('trainee_id')->exists()) {
            throw new RuntimeException('Assign or remove task drafts before rolling back this migration.');
        }
        Schema::table('tasks', function (Blueprint $table) {
            $table->unsignedBigInteger('trainee_id')->nullable(false)->change();
        });
    }
};
