<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('salary_components', function (Blueprint $table) {
            if (! Schema::hasColumn('salary_components', 'frequency')) {
                $table->string('frequency', 20)->default('monthly')->after('is_taxable');
            }

            if (! Schema::hasColumn('salary_components', 'prorate_basis')) {
                $table->string('prorate_basis', 20)->default('calendar_days')->after('frequency');
            }
        });

        Schema::table('payroll_periods', function (Blueprint $table) {
            if (! Schema::hasColumn('payroll_periods', 'is_thr')) {
                $table->boolean('is_thr')->default(false)->after('status');
            }
        });

        Schema::table('payslips', function (Blueprint $table) {
            if (! Schema::hasColumn('payslips', 'working_days')) {
                $table->unsignedInteger('working_days')->default(0)->after('present_days');
            }

            if (! Schema::hasColumn('payslips', 'attended_days')) {
                $table->unsignedInteger('attended_days')->default(0)->after('late_count');
            }

            if (! Schema::hasColumn('payslips', 'total_thr')) {
                $table->decimal('total_thr', 15, 2)->default(0)->after('total_bpjs_company');
            }
        });
    }

    public function down(): void
    {
        Schema::table('salary_components', function (Blueprint $table) {
            $table->dropColumn(['frequency', 'prorate_basis']);
        });

        Schema::table('payroll_periods', function (Blueprint $table) {
            $table->dropColumn('is_thr');
        });

        Schema::table('payslips', function (Blueprint $table) {
            $table->dropColumn(['working_days', 'attended_days', 'total_thr']);
        });
    }
};
