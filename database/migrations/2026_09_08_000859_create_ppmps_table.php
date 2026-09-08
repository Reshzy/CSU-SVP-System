<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A department's Project Procurement Management Plan. Exactly one per
     * `(department_id, fiscal_year)` — `Ppmp::getOrCreateForDepartment()`
     * relies on the unique index below.
     */
    public function up(): void
    {
        Schema::create('ppmps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->year('fiscal_year');
            $table->string('status')->default('draft');
            $table->decimal('total_estimated_cost', 15, 2)->default(0);
            $table->timestamp('validated_at')->nullable();
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['department_id', 'fiscal_year']);
            $table->index('fiscal_year');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ppmps');
    }
};
