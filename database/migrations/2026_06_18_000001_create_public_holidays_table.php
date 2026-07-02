<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('public_holidays', function (Blueprint $table) {
            $table->id();
            $table->date('date')->index();
            $table->string('name');
            $table->string('type')->default('national'); // national, religious
            $table->string('country', 5)->default('UG');
            $table->boolean('is_paid')->default(true);
            $table->year('year')->index();
            $table->timestamps();

            $table->unique(['date', 'country']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('public_holidays');
    }
};
