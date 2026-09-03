<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('school')->table('employees', function (Blueprint $table): void {
            $table->string('dapodik_id')->nullable()->unique();
            $table->string('normalized_name')->nullable()->index();
            $table->string('birth_place')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('religion')->nullable();
            $table->string('last_education')->nullable();
            $table->string('last_study_field')->nullable();
            $table->string('rank_group')->nullable();
            $table->boolean('is_primary_school')->nullable();
            $table->timestamp('last_synced_at')->nullable();
        });

        Schema::connection('school')->create('students', function (Blueprint $table): void {
            $table->id();
            $table->string('source_type', 20)->default('MANUAL');
            $table->string('source_key', 100)->unique();
            $table->string('dapodik_id')->nullable()->unique();
            $table->string('name');
            $table->string('normalized_name')->index();
            $table->string('nisn')->nullable()->index();
            $table->string('nipd')->nullable();
            $table->string('nik')->nullable();
            $table->string('gender', 10)->nullable();
            $table->string('birth_place')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('religion')->nullable();
            $table->text('address')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('father_name')->nullable();
            $table->string('mother_name')->nullable();
            $table->string('guardian_name')->nullable();
            $table->string('class_name')->nullable()->index();
            $table->string('class_id')->nullable();
            $table->string('grade_level')->nullable();
            $table->string('semester_id')->nullable();
            $table->string('registration_type')->nullable();
            $table->string('previous_school')->nullable();
            $table->date('school_entry_date')->nullable();
            $table->boolean('special_needs')->default(false);
            $table->unsignedSmallInteger('child_order')->nullable();
            $table->decimal('height', 6, 2)->nullable();
            $table->decimal('weight', 6, 2)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->json('payload')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });

        Schema::connection('school')->create('dapodik_connections', function (Blueprint $table): void {
            $table->id();
            $table->string('base_url')->default('http://localhost:5774');
            $table->string('npsn', 20);
            $table->text('token');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_synced_at')->nullable();
            $table->string('last_status', 30)->nullable();
            $table->text('last_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('school')->dropIfExists('dapodik_connections');
        Schema::connection('school')->dropIfExists('students');
        Schema::connection('school')->table('employees', function (Blueprint $table): void {
            $table->dropUnique(['dapodik_id']);
            $table->dropIndex(['normalized_name']);
            $table->dropColumn(['dapodik_id', 'normalized_name', 'birth_place', 'birth_date', 'religion', 'last_education', 'last_study_field', 'rank_group', 'is_primary_school', 'last_synced_at']);
        });
    }
};
