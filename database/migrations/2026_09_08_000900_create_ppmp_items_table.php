<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * PPMP lines: a catalog item planned by quarter for one department.
     * A catalog item may appear only once per PPMP.
     */
    public function up(): void
    {
        Schema::create('ppmp_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ppmp_id')->constrained()->cascadeOnDelete();
            $table->foreignId('app_item_id')->constrained()->cascadeOnDelete();
            $table->integer('q1_quantity')->default(0);
            $table->integer('q2_quantity')->default(0);
            $table->integer('q3_quantity')->default(0);
            $table->integer('q4_quantity')->default(0);
            $table->integer('total_quantity')->default(0);
            $table->decimal('estimated_unit_cost', 15, 2);
            $table->decimal('estimated_total_cost', 15, 2);
            $table->timestamps();

            $table->unique(['ppmp_id', 'app_item_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ppmp_items');
    }
};
