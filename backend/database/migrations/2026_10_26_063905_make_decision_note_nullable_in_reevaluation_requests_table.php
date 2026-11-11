<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('reevaluation_requests', function (Blueprint $table) {
            $table->text('decision_note')->nullable()->change();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reevaluation_requests', function (Blueprint $table) {
            $table->text('decision_note')->nullable(false)->change();

        });
    }
};
