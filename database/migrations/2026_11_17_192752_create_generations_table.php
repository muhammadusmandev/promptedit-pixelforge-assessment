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
        Schema::create('generations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('provider_id')->nullable()->index();  // Generation ID returned by Pixelforge
            $table->text('prompt'); 
            $table->string('size')->nullable();   // Selected image dimensions (e.g. 1024x1024 (default), 1536x1024 or 1024x1536)
            $table->string('quality')->nullable(); // Selected quality (e.g. low, medium (default), high)
            $table->json('reference_urls')->nullable();
            $table->string('status', 30)->default('queued')->index(); // Generation status: queued, processing, succeeded, failed
            $table->decimal('estimated_cost', 12, 9)->default(0);  // Estimated amount charged to customer with 25% markup
            $table->decimal('actual_cost', 12, 9)->nullable();     // Customer's billed cost with 25% markup
            $table->decimal('provider_cost', 12, 9)->nullable();    // Actual Pixelforge cost with 0% markup
            $table->text('image_url')->nullable();
            $table->json('provider_usage')->nullable();  // Usage information returned by Pixelforge
            $table->json('provider_rates')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('generations');
    }
};
