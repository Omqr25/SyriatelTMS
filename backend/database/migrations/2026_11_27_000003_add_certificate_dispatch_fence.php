<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('certificates', function (Blueprint $table) {
            $table->uuid('dispatch_token')->nullable();
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Certificate dispatch fencing is forward-only. Keep the schema and private files when rolling back application code.');
    }
};
