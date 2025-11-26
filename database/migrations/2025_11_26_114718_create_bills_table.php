<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('split_groups')->onDelete('cascade');
            $table->foreignId('paid_by')->constrained('users')->onDelete('cascade');
            $table->string('description');
            $table->decimal('amount', 10, 2);
            $table->date('bill_date');
            $table->json('split_among')->nullable(); // Store user IDs among whom bill is split
            $table->enum('split_type', ['equal', 'custom'])->default('equal');
            $table->json('custom_split')->nullable(); // For custom split amounts
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bills');
    }
};
