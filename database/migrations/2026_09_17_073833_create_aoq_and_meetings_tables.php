<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('aoq_generations')) {
            Schema::create('aoq_generations', function (Blueprint $table) {
                $table->id();
                $table->string('aoq_reference_number')->unique();
                $table->foreignId('purchase_request_id')->constrained()->cascadeOnDelete();
                $table->unsignedBigInteger('pr_item_group_id')->nullable();
                $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('document_hash')->nullable();
                $table->json('exported_data_snapshot')->nullable();
                $table->string('file_path')->nullable();
                $table->string('file_format')->default('docx');
                $table->unsignedInteger('supplier_count')->default(0);
                $table->unsignedInteger('item_count')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('aoq_item_decisions')) {
            Schema::create('aoq_item_decisions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('purchase_request_id')->constrained()->cascadeOnDelete();
                $table->foreignId('purchase_request_item_id')->constrained()->cascadeOnDelete();
                $table->foreignId('winning_quotation_item_id')->nullable()->constrained('quotation_items')->nullOnDelete();
                $table->string('decision_type');
                $table->text('justification')->nullable();
                $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('decided_at')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('aoq_signatories')) {
            Schema::create('aoq_signatories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('aoq_generation_id')->constrained()->cascadeOnDelete();
                $table->string('position');
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->string('name');
                $table->string('prefix')->nullable();
                $table->string('suffix')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('bac_meetings')) {
            Schema::create('bac_meetings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('purchase_request_id')->nullable()->constrained()->nullOnDelete();
                $table->dateTime('meeting_datetime');
                $table->string('location')->nullable();
                $table->string('status')->default('scheduled');
                $table->string('title');
                $table->text('agenda')->nullable();
                $table->text('minutes')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('bac_meeting_attendees')) {
            Schema::create('bac_meeting_attendees', function (Blueprint $table) {
                $table->id();
                $table->foreignId('bac_meeting_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('role_at_meeting')->nullable();
                $table->boolean('attended')->default(false);
                $table->text('remarks')->nullable();
                $table->timestamps();

                $table->unique(['bac_meeting_id', 'user_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bac_meeting_attendees');
        Schema::dropIfExists('bac_meetings');
        Schema::dropIfExists('aoq_signatories');
        Schema::dropIfExists('aoq_item_decisions');
        Schema::dropIfExists('aoq_generations');
    }
};
