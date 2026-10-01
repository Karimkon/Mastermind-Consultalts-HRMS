<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * The kinds of work somebody can ask to hear about.
 *
 * A job seeker picks categories when they register, so "tell me when a job
 * like this opens" has something to match on. A posting therefore needs to
 * carry one too — nullable, because the three postings that already exist
 * predate this and nobody should have to go back and classify them before the
 * careers page will load.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120)->unique();
            $table->string('slug', 140)->unique();
            $table->string('description', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('job_postings', function (Blueprint $table) {
            $table->foreignId('job_category_id')->nullable()->after('department_id')
                ->constrained('job_categories')->nullOnDelete();
        });

        // A starting set drawn from the work Mastermind actually places for:
        // security, cleaning, hospitality, drivers and the office roles.
        $seed = [
            'Security Services'        => 'Guards, supervisors and control room staff',
            'Cleaning & Janitorial'    => 'Cleaners, housekeeping and janitorial supervisors',
            'Hospitality & Catering'   => 'Waiters, cooks, stewards and kitchen staff',
            'Drivers & Logistics'      => 'Drivers, riders, loaders and store staff',
            'Administration & Office'  => 'Receptionists, clerks, secretaries and office assistants',
            'Finance & Accounting'     => 'Accountants, cashiers and audit staff',
            'Human Resources'          => 'HR officers, recruiters and training staff',
            'Sales & Marketing'        => 'Sales representatives, merchandisers and marketing staff',
            'Technical & Maintenance'  => 'Electricians, plumbers, mechanics and technicians',
            'Healthcare'               => 'Nurses, clinical and laboratory staff',
            'Information Technology'   => 'Developers, support and network staff',
            'Other'                    => 'Anything not covered above',
        ];

        $order = 0;
        foreach ($seed as $name => $description) {
            DB::table('job_categories')->insert([
                'name'        => $name,
                'slug'        => Str::slug($name),
                'description' => $description,
                'is_active'   => true,
                'sort_order'  => $order += 10,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('job_postings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('job_category_id');
        });

        Schema::dropIfExists('job_categories');
    }
};
