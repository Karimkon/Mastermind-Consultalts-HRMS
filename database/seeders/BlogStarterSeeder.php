<?php

namespace Database\Seeders;

use App\Models\BlogApiToken;
use App\Models\BlogCategory;
use App\Services\IndexNowService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Starter topics for the Insights section, plus the first AI publishing key.
 * Safe to re-run: it only creates what is missing.
 *
 *   php artisan db:seed --class=BlogStarterSeeder
 */
class BlogStarterSeeder extends Seeder
{
    public function run(): void
    {
        $topics = [
            ['name' => 'Hiring & Recruitment', 'color' => '#1d4ed8', 'icon' => 'fa-user-plus',   'description' => 'Finding, assessing and landing the right people in a tight Ugandan market.'],
            ['name' => 'Payroll & Compliance', 'color' => '#059669', 'icon' => 'fa-file-invoice', 'description' => 'PAYE, NSSF, statutory deductions and staying on the right side of the labour law.'],
            ['name' => 'People Management',    'color' => '#7c3aed', 'icon' => 'fa-users',        'description' => 'Performance, appraisals, retention and the day-to-day of leading teams.'],
            ['name' => 'Workplace Wellbeing',  'color' => '#0891b2', 'icon' => 'fa-heart-pulse',  'description' => 'Culture, mental health, leave and the things that keep good staff from leaving.'],
            ['name' => 'HR Systems',           'color' => '#ea580c', 'icon' => 'fa-laptop-code',  'description' => 'Digitising HR: attendance, payroll systems, records and reporting.'],
            ['name' => 'Company News',         'color' => '#be123c', 'icon' => 'fa-bullhorn',     'description' => 'Announcements and milestones from the Mastermind Consult team.'],
        ];

        $created = 0;
        $order = 0;

        foreach ($topics as $topic) {
            $slug = Str::slug($topic['name']);

            if (BlogCategory::where('slug', $slug)->exists()) {
                $order++;
                continue;
            }

            BlogCategory::create($topic + [
                'slug' => $slug,
                'is_active' => true,
                'sort_order' => $order++,
                'meta_title' => $topic['name'] . ' | Mastermind Consult Insights',
                'meta_description' => $topic['description'],
            ]);

            $created++;
        }

        $this->command->info(sprintf('Blog topics: %d created, %d already present.', $created, count($topics) - $created));

        if (BlogApiToken::count() === 0) {
            $token = BlogApiToken::generate('AI blog writer');
            $this->command->newLine();
            $this->command->info('Publishing key created — paste this into the AI writer:');
            $this->command->line('  ' . $token->token);
        } else {
            $this->command->line('Publishing key already exists: ' . BlogApiToken::first()->token);
        }

        $this->command->line('IndexNow key: ' . app(IndexNowService::class)->key());
    }
}
