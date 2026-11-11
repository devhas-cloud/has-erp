<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->text('cancel_reason')->nullable()->after('status');
            $table->foreignId('cancelled_by')->nullable()->after('cancel_reason')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable()->after('cancelled_by');

            // Enum MySQL lama tidak memuat "cancelled"; string juga aman untuk SQLite test.
            $table->string('status', 20)->default('todo')->change();
        });
    }

    public function down(): void
    {
        DB::table('tasks')->where('status', 'cancelled')->update(['status' => 'todo']);

        Schema::table('tasks', function (Blueprint $table) {
            $table->enum('status', ['todo', 'in_progress', 'waiting_approval', 'done'])
                ->default('todo')->change();
            $table->dropConstrainedForeignId('cancelled_by');
            $table->dropColumn(['cancel_reason', 'cancelled_at']);
        });
    }
};
