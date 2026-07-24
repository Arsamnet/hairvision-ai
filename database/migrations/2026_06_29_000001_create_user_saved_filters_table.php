<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Execute the up operation.
     *
     * @return void No value is returned.
     *
     * @since 1.0.0
     */
    public function up(): void
    {
        Schema::create('user_saved_filters', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('filter_key');
            $table->string('name');
            $table->json('filters_json')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'filter_key', 'name']);
            $table->index(['user_id', 'filter_key']);
        });
    }

    /**
     * Execute the down operation.
     *
     * @return void No value is returned.
     *
     * @since 1.0.0
     */
    public function down(): void
    {
        Schema::dropIfExists('user_saved_filters');
    }
};
