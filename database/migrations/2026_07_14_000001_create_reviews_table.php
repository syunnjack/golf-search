<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('course_id');
            $table->string('course_name');
            $table->string('prefecture');
            $table->string('nickname')->default('匿名');
            $table->unsignedTinyInteger('rating');
            $table->text('comment');
            $table->string('ip_hash', 64);
            $table->timestamps();

            $table->index('course_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
