<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One fiscal-year envelope per department. Available budget is
     * allocated minus utilized minus reserved.
     */
    public function up(): void
    {
        Schema::create('department_budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('fiscal_year');
            $table->decimal('allocated_budget', 15, 2)->default(0);
            $table->decimal('utilized_budget', 15, 2)->default(0);
            $table->decimal('reserved_budget', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('set_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['department_id', 'fiscal_year']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('department_budgets');
    }
};
