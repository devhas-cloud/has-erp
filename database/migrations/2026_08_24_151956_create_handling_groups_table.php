<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('handling_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('handling_group_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('handling_group_id')->constrained('handling_groups')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['handling_group_id', 'user_id']);
        });

        $divisions = DB::table('division_handlers')
            ->select('division_id')
            ->distinct()
            ->pluck('division_id');

        foreach ($divisions as $divisionId) {
            $division = DB::table('divisions')->where('id', $divisionId)->first();
            if (! $division) {
                continue;
            }

            $groupId = DB::table('handling_groups')->insertGetId([
                'name' => $division->division_name,
                'description' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $userIds = DB::table('division_handlers')
                ->where('division_id', $divisionId)
                ->pluck('user_id');

            $rows = $userIds->map(fn ($userId) => [
                'handling_group_id' => $groupId,
                'user_id' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
            ])->all();

            DB::table('handling_group_users')->insert($rows);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('handling_group_users');
        Schema::dropIfExists('handling_groups');
    }
};
