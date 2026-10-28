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
        Schema::create('unit_points', function (Blueprint $table) {
            $table->id();
            // ID entri CMS
            $table->unsignedBigInteger('project_id')->index();
            $table->unsignedBigInteger('unit_id')->unique();
            $table->decimal('x', 5, 2);
            $table->decimal('y', 5, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('unit_points');
    }
};
