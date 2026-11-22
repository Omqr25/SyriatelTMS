<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            // An inline reference adds a nullable FK without rebuilding the existing table.
            DB::statement('ALTER TABLE applications ADD COLUMN application_form_id INTEGER REFERENCES application_forms(id) ON DELETE RESTRICT');
            Schema::table('applications', function (Blueprint $table) {
                $table->unique(['id', 'application_form_id'], 'applications_form_key');
                $table->index(['application_form_id', 'training_program_id'], 'applications_form_intake_idx');
            });

            return;
        }

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE applications
                ADD COLUMN application_form_id BIGINT UNSIGNED NULL,
                ADD UNIQUE INDEX applications_form_key (id, application_form_id),
                ADD INDEX applications_form_intake_idx (application_form_id, training_program_id),
                ADD CONSTRAINT applications_form_intake_fk FOREIGN KEY (application_form_id, training_program_id)
                    REFERENCES application_forms (id, training_program_id) ON DELETE RESTRICT');

            return;
        }

        Schema::table('applications', function (Blueprint $table) {
            $table->foreignId('application_form_id')->nullable();
            $table->unique(['id', 'application_form_id'], 'applications_form_key');
            $table->index(['application_form_id', 'training_program_id'], 'applications_form_intake_idx');
            $table->foreign(['application_form_id', 'training_program_id'], 'applications_form_intake_fk')
                ->references(['id', 'training_program_id'])->on('application_forms')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            Schema::table('applications', fn (Blueprint $table) => $table->dropForeign('applications_form_intake_fk'));
        }

        Schema::table('applications', function (Blueprint $table) {
            $table->dropUnique('applications_form_key');
            $table->dropIndex('applications_form_intake_idx');
        });
        Schema::table('applications', fn (Blueprint $table) => $table->dropColumn('application_form_id'));
    }
};
