<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('generation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type');
            // Signed nano-USD: positive adds credit, negative spends it.
            $table->bigInteger('amount');
            $table->string('description')->nullable();
            $table->timestamps();

            // The database itself guarantees a generation is never held,
            // released or charged twice, even under concurrent webhooks.
            $table->unique(['generation_id', 'type']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_transactions');
    }
};
