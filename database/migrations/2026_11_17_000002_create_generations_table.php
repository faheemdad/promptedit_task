<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('generations', function (Blueprint $table) {
            // Our UUID doubles as the Pixelforge Idempotency-Key and is echoed back in metadata.
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider_id')->nullable()->unique();
            $table->string('status')->index();

            $table->text('prompt');
            $table->string('size');
            $table->string('quality');
            $table->json('reference_images');

            // All money columns are nano-USD integers. "user" amounts include markup.
            $table->bigInteger('estimated_cost');
            $table->bigInteger('max_cost');
            $table->bigInteger('provider_cost')->nullable();
            $table->bigInteger('charged_cost')->nullable();

            $table->json('rates')->nullable();
            $table->json('usage')->nullable();

            $table->text('image_url')->nullable();
            $table->timestamp('image_url_expires_at')->nullable();
            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();

            $table->unsignedInteger('submit_attempts')->default(0);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable()->index();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('generations');
    }
};
