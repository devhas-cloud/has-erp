<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_roles', function (Blueprint $table) {
            $table->id();
            $table->string('role_name', 50);
            $table->unsignedInteger('hierarchy_level');
            $table->boolean('is_global_delegator')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_roles');
    }
};
