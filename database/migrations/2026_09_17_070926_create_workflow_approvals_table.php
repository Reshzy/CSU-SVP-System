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
        if (Schema::hasTable('workflow_approvals')) {
            return;
        }

        Schema::create('workflow_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_request_id')->constrained()->cascadeOnDelete();
            $table->string('step_name');
            $table->unsignedInteger('step_order')->default(0);
            $table->foreignId('approver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('pending');
            $table->text('comments')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->unique(['purchase_request_id', 'step_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workflow_approvals');
    }
};
