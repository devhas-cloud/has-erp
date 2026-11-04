<?php

use App\Support\NumberSequence;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Penerimaan pembayaran customer: 1 invoice : 1 penerimaan aktif, nominal = total invoice.
        Schema::create('customer_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_number', 20)->unique();
            $table->foreignId('invoice_id')->constrained('invoices')->restrictOnDelete();
            $table->foreignId('company_id')->nullable()->constrained('account_companies')->nullOnDelete();
            $table->date('receipt_date');
            $table->string('method', 20)->default('transfer');
            $table->string('deposit_account', 150)->nullable();
            $table->string('reference_number', 100)->nullable();
            $table->decimal('amount', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('draft')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->text('void_reason')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->timestamps();
        });

        // Invoice lama yang sudah Paid lewat tombol "Pay" dicatatkan sebagai penerimaan Posted.
        $paid = DB::table('invoices')
            ->leftJoin('projects', 'projects.id', '=', 'invoices.project_id')
            ->where('invoices.status', 'paid')
            ->orderBy('invoices.id')
            ->get(['invoices.*', 'projects.company_id']);

        foreach ($paid as $invoice) {
            $date = $invoice->date_payment ?? substr((string) $invoice->updated_at, 0, 10);

            DB::table('customer_receipts')->insert([
                'receipt_number' => NumberSequence::make('customer_receipts', 'receipt_number', $date),
                'invoice_id' => $invoice->id,
                'company_id' => $invoice->company_id,
                'receipt_date' => $date,
                'method' => 'transfer',
                'reference_number' => $invoice->reference_number,
                'amount' => $invoice->total_payment,
                'notes' => 'Dipindahkan dari pencatatan pembayaran invoice sebelumnya.',
                'status' => 'posted',
                'created_by' => $invoice->paid_by,
                'posted_by' => $invoice->paid_by,
                'posted_at' => $invoice->updated_at,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_receipts');
    }
};
