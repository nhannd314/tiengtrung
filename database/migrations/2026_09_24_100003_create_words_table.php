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
        Schema::create('words', function (Blueprint $table) {
            $table->id();
            $table->string('hanzi', 50);
            $table->string('traditional', 50)->nullable();
            $table->string('pinyin', 100);
            $table->string('pinyin_number', 100);
            $table->string('han_viet', 100)->nullable();
            // List of [part_of_speech, meaning] pairs in display order, e.g. [["adjective", "tốt"], ["adjective", "khoẻ"]].
            $table->json('meanings');
            $table->unsignedTinyInteger('hsk_level')->nullable()->index();
            $table->unsignedTinyInteger('stroke_count')->nullable();
            $table->text('note')->nullable();
            // List of {sentence, pinyin, meaning, audio_url}, in display order.
            $table->json('examples')->nullable();
            $table->string('audio_url')->nullable();
            $table->string('image_url')->nullable();
            $table->timestamps();

            // Polyphonic characters (e.g. 行 xíng / háng) share hanzi but differ in pinyin.
            $table->unique(['hanzi', 'pinyin_number']);
            $table->index('pinyin_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('words');
    }
};
