<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('models', function (Blueprint $table) {
            $table->id(); // id
            $table->string('name')->unique(); // name
            $table->timestamps(); // created_at aur updated_at khud ba khud ban jayein ge
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('models');
    }
};