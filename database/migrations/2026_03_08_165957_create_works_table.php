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
        Schema::create('works', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('barbero_id');
            $table->string('title');
            $table->text('description')->nullable();
            // $table->string('image'); // Eliminado: las imágenes van en work_images
            $table->timestamp('work_date')->nullable();
            $table->timestamps();
            $table->foreign('barbero_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('works');
    }
};
