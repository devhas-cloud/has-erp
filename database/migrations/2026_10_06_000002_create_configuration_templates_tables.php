<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configuration_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('division_id')->nullable()->constrained('divisions')->nullOnDelete();
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index('division_id');
        });

        Schema::create('configuration_template_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained('configuration_templates')->cascadeOnDelete();
            $table->string('item_no', 50)->nullable();
            $table->foreignId('parent_id')->nullable()
                ->constrained('configuration_template_items')
                ->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('master_products')->nullOnDelete();
            $table->string('category', 100)->nullable();
            $table->string('part_number', 100)->nullable();
            $table->text('description')->nullable();
            $table->integer('qty')->default(1);
            $table->decimal('price', 15, 2)->nullable();
            $table->decimal('price_currency', 15, 2)->nullable();
            $table->string('currency', 10)->nullable();
            $table->string('unit', 50)->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // Migrasi data template Water lama ke tabel generic (id dipertahankan,
        // division_id mengikuti divisi Water). Dijalankan hanya bila tabel lama
        // ada dan divisi Water terdaftar.
        if (Schema::hasTable('water_configuration_templates')) {
            $waterDivisionId = DB::table('divisions')->where('division_name', 'Water')->value('id');

            if ($waterDivisionId) {
                DB::table('water_configuration_templates')->orderBy('id')->chunkById(500, function ($templates) use ($waterDivisionId) {
                    foreach ($templates as $template) {
                        DB::table('configuration_templates')->insert([
                            'id' => $template->id,
                            'division_id' => $waterDivisionId,
                            'name' => $template->name,
                            'description' => $template->description,
                            'created_by' => $template->created_by,
                            'created_at' => $template->created_at,
                            'updated_at' => $template->updated_at,
                        ]);
                    }
                });

                $items = DB::table('water_configuration_template_items')->orderBy('id')->get();
                foreach ($items as $item) {
                    DB::table('configuration_template_items')->insert([
                        'id' => $item->id,
                        'template_id' => $item->template_id,
                        'item_no' => $item->item_no,
                        'parent_id' => $item->parent_id,
                        'product_id' => $item->product_id,
                        'category' => $item->category,
                        'part_number' => $item->part_number,
                        'description' => $item->description,
                        'qty' => $item->qty,
                        'price' => $item->price,
                        'price_currency' => $item->price_currency,
                        'currency' => $item->currency,
                        'unit' => $item->unit,
                        'sort_order' => $item->sort_order,
                        'created_at' => $item->created_at,
                        'updated_at' => $item->updated_at,
                    ]);
                }
            } else {
                DB::table('water_configuration_templates')->orderBy('id')->chunkById(500, function ($templates) {
                    foreach ($templates as $template) {
                        DB::table('configuration_templates')->insert([
                            'id' => $template->id,
                            'division_id' => null,
                            'name' => $template->name,
                            'description' => $template->description,
                            'created_by' => $template->created_by,
                            'created_at' => $template->created_at,
                            'updated_at' => $template->updated_at,
                        ]);
                    }
                });

                $items = DB::table('water_configuration_template_items')->orderBy('id')->get();
                foreach ($items as $item) {
                    DB::table('configuration_template_items')->insert([
                        'id' => $item->id,
                        'template_id' => $item->template_id,
                        'item_no' => $item->item_no,
                        'parent_id' => $item->parent_id,
                        'product_id' => $item->product_id,
                        'category' => $item->category,
                        'part_number' => $item->part_number,
                        'description' => $item->description,
                        'qty' => $item->qty,
                        'price' => $item->price,
                        'price_currency' => $item->price_currency,
                        'currency' => $item->currency,
                        'unit' => $item->unit,
                        'sort_order' => $item->sort_order,
                        'created_at' => $item->created_at,
                        'updated_at' => $item->updated_at,
                    ]);
                }
            }

            Schema::dropIfExists('water_configuration_template_items');
            Schema::dropIfExists('water_configuration_templates');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('configuration_template_items');

        // Recreate tabel water lama (best-effort, tanpa salin data balik).
        if (! Schema::hasTable('water_configuration_templates')) {
            Schema::create('water_configuration_templates', function (Blueprint $table) {
                $table->id();
                $table->string('name', 255);
                $table->text('description')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });

            Schema::create('water_configuration_template_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('template_id')->constrained('water_configuration_templates')->cascadeOnDelete();
                $table->string('item_no', 50)->nullable();
                $table->foreignId('parent_id')->nullable()
                    ->constrained('water_configuration_template_items')
                    ->cascadeOnDelete();
                $table->foreignId('product_id')->nullable()->constrained('master_products')->nullOnDelete();
                $table->string('category', 100)->nullable();
                $table->string('part_number', 100)->nullable();
                $table->text('description')->nullable();
                $table->integer('qty')->default(1);
                $table->decimal('price', 15, 2)->nullable();
                $table->decimal('price_currency', 15, 2)->nullable();
                $table->string('currency', 10)->nullable();
                $table->string('unit', 50)->nullable();
                $table->integer('sort_order')->default(0);
                $table->timestamps();
            });
        }

        Schema::dropIfExists('configuration_templates');
    }
};
