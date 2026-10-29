<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Tabel users pernah di-rename menjadi users_bk lalu dibuat ulang; MySQL ikut
 * memindahkan FK ke users_bk sehingga user baru (id di luar users_bk) gagal
 * disimpan di tabel relasi. Migration ini mengarahkan ulang semua FK yang
 * menunjuk users_bk ke users dengan aturan ON DELETE/ON UPDATE yang sama.
 * Tidak mengubah data dan tidak menghapus users_bk; no-op bila tidak ada FK
 * yang menunjuk users_bk.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->repoint('users_bk', 'users');
    }

    public function down(): void
    {
        // Tidak dikembalikan ke users_bk: FK ke tabel backup justru penyebab error.
    }

    private function repoint(string $from, string $to): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $foreignKeys = DB::select(
            'select k.TABLE_NAME as table_name, k.COLUMN_NAME as column_name, k.CONSTRAINT_NAME as name,
                    k.REFERENCED_COLUMN_NAME as referenced_column, r.DELETE_RULE as on_delete, r.UPDATE_RULE as on_update
             from information_schema.KEY_COLUMN_USAGE k
             join information_schema.REFERENTIAL_CONSTRAINTS r
               on r.CONSTRAINT_SCHEMA = k.TABLE_SCHEMA and r.CONSTRAINT_NAME = k.CONSTRAINT_NAME and r.TABLE_NAME = k.TABLE_NAME
             where k.TABLE_SCHEMA = ? and k.REFERENCED_TABLE_NAME = ?',
            [DB::getDatabaseName(), $from]
        );

        foreach ($foreignKeys as $fk) {
            DB::statement(sprintf('ALTER TABLE `%s` DROP FOREIGN KEY `%s`', $fk->table_name, $fk->name));
            DB::statement(sprintf(
                'ALTER TABLE `%s` ADD CONSTRAINT `%s` FOREIGN KEY (`%s`) REFERENCES `%s` (`%s`) ON DELETE %s ON UPDATE %s',
                $fk->table_name,
                $fk->name,
                $fk->column_name,
                $to,
                $fk->referenced_column,
                $fk->on_delete,
                $fk->on_update
            ));
        }
    }
};
