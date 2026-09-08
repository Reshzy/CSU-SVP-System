<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Slice 2 stub. Only the columns `PpmpItem::getRemainingQuantity()` reads
     * are here — a PPMP line's remaining quantity depends on which requests
     * are still live. Slice 3 adds the rest of the file-02 hub columns
     * (numbering, actors, narrative, funding, workflow timestamps, earmark,
     * replacement links) in a follow-up migration.
     */
    public function up(): void
    {
        Schema::create('purchase_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requester_id')->constrained('users')->cascadeOnDelete();
            $table->string('status');
            $table->boolean('is_archived')->default(false);
            $table->timestamps();

            $table->index(['is_archived', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_requests');
    }
};
