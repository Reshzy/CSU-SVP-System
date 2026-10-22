<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('bac_signatories')) {
            Schema::create('bac_signatories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('position');
                $table->string('prefix')->nullable();
                $table->string('suffix')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['user_id', 'position']);
            });
        }

        if (! Schema::hasTable('resolution_signatories')) {
            Schema::create('resolution_signatories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('purchase_request_id')->constrained()->cascadeOnDelete();
                $table->string('position');
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->string('name');
                $table->string('prefix')->nullable();
                $table->string('suffix')->nullable();
                $table->timestamps();

                $table->unique(['purchase_request_id', 'position']);
            });
        }

        if (! Schema::hasTable('rfq_signatories')) {
            Schema::create('rfq_signatories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('purchase_request_id')->nullable()->constrained()->cascadeOnDelete();
                $table->unsignedBigInteger('rfq_generation_id')->nullable();
                $table->string('position');
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->string('name');
                $table->string('prefix')->nullable();
                $table->string('suffix')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('rfq_signatories');
        Schema::dropIfExists('resolution_signatories');
        Schema::dropIfExists('bac_signatories');
    }
};
