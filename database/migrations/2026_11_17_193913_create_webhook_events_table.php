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
        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_id')->unique();   // Unique event ID received from Pixelforge
            $table->foreignId('generation_id')
                ->nullable()
                ->constrained('generations')
                ->nullOnDelete();

            $table->string('event_type');    // Webhook event type (e.g. generation.completed)
            $table->json('payload');     // Original request payload
            $table->timestamp('processed_at')->nullable();   // When webhook event was successfully processed
            $table->timestamps();
            
            $table->index('generation_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
    }
};
