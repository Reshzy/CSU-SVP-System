<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pr_item_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_request_id')->constrained()->cascadeOnDelete();
            $table->string('group_name');
            $table->string('group_code');
            $table->integer('display_order')->default(0);
            $table->string('status')->default('pending');
            $table->timestamps();

            $table->unique(['purchase_request_id', 'group_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pr_item_groups');
    }
};
