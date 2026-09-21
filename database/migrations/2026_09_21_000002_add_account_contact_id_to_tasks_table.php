<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kontak yang dikunjungi — hanya relevan untuk task kategori "Visit",
     * opsional (boleh dikosongkan).
     */
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('account_contact_id')->nullable()->after('category_id')
                ->constrained('account_contacts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign(['account_contact_id']);
            $table->dropColumn('account_contact_id');
        });
    }
};
