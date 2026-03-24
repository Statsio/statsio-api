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
        // create reference tables first
        Schema::create('genders', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('label')->nullable();
            $table->timestamps();
        });

        Schema::create('age_ranges', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->integer('min_age')->nullable();
            $table->integer('max_age')->nullable();
            $table->string('label')->nullable();
            $table->timestamps();
        });

        Schema::create('socio_professional_categories', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('label')->nullable();
            $table->timestamps();
        });

        Schema::create('education_levels', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('label')->nullable();
            $table->timestamps();
        });

        Schema::create('employment_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('label')->nullable();
            $table->timestamps();
        });

        Schema::create('user_profiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('phone')->nullable();
            $table->date('birthday')->nullable();
            $table->integer('birth_year')->nullable();
            $table->unsignedBigInteger('gender_id')->nullable();
            $table->string('country', 2)->nullable();
            $table->string('region')->nullable();
            $table->string('city')->nullable();
            $table->string('zip_code')->nullable();

            $table->unsignedBigInteger('socio_professional_category_id')->nullable();
            $table->unsignedBigInteger('education_level_id')->nullable();
            $table->unsignedBigInteger('employment_status_id')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('gender_id')->references('id')->on('genders')->onDelete('set null');
            $table->foreign('socio_professional_category_id')->references('id')->on('socio_professional_categories')->onDelete('set null');
            $table->foreign('education_level_id')->references('id')->on('education_levels')->onDelete('set null');
            $table->foreign('employment_status_id')->references('id')->on('employment_statuses')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_profiles');

        Schema::dropIfExists('genders');
        Schema::dropIfExists('age_ranges');
        Schema::dropIfExists('socio_professional_categories');
        Schema::dropIfExists('education_levels');
        Schema::dropIfExists('employment_statuses');
    }
};
