<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The PS-DBMS / APP reference catalog. One row per item per fiscal year;
     * `app:import` upserts on the natural key `(item_code, fiscal_year)`.
     *
     * `unit_price` is nullable because SOFTWARE and PART II items are priced
     * outside PS-DBM and arrive from the CSV without a figure.
     */
    public function up(): void
    {
        Schema::create('app_items', function (Blueprint $table) {
            $table->id();
            $table->year('fiscal_year');
            $table->string('category');
            $table->string('item_code');
            $table->string('item_name');
            $table->string('unit_of_measure');
            $table->decimal('unit_price', 15, 2)->nullable();
            $table->text('specifications')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['item_code', 'fiscal_year']);
            $table->index('fiscal_year');
            $table->index('category');
            $table->index('is_active');
            $table->index(['fiscal_year', 'is_active']);
            $table->index(['fiscal_year', 'category']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('app_items');
    }
};
