<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Books of each library (spec 002).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('books', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('title');
            $table->string('author')->nullable();
            $table->string('isbn', 13)->nullable()->index();
            $table->string('publisher')->nullable();
            $table->string('cover_path')->nullable();

            $table->smallInteger('publication_year')->nullable();
            $table->string('genre')->nullable();
            $table->string('language')->nullable();
            $table->unsignedInteger('pages')->nullable();
            $table->string('reading_status')->nullable();
            $table->unsignedTinyInteger('rating')->nullable();
            $table->text('notes')->nullable();
            $table->string('condition')->nullable();

            // Location: a compartment and the position inside it, or nothing ("the table").
            $table->foreignId('compartment_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('position')->nullable();

            $table->timestamps();

            $table->index(['compartment_id', 'position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('books');
    }
};
