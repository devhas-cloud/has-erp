@extends('layouts.app')

@section('title', 'Detail Tagihan Supplier')
@section('page-title', 'Detail Tagihan Supplier')

@php
    $money = fn ($v) => \App\Models\SupplierBill::formatMoney((float) $v);
    $pct = fn ($v) => \App\Support\Number::percent((float) $v);
@endphp

@section('styles')
<style>
    .sb-muted { color: var(--text-muted); font-size: 12px; }
    .sb-hero { background: #fff; border: 1px solid var(--card-border); border-radius: var(--radius); padding: 16px 18px; margin-bottom: 14px; display: flex; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
    .sb-number { font-size: 20px; font-weight: 700; color: var(--text-primary); margin: 4px 0 2px; }
    .sb-amount { text-align: right; }
    .sb-amount .val { font-size: 22px; font-weight: 700; font-variant-numeric: tabular-nums; }
    @media (max-width: 575.98px) { .sb-amount { text-align: left; } }
    .sb-next { display: flex; gap: 12px; align-items: center; flex-wrap: wrap; border: 1px solid; border-radius: var(--radius); padding: 12px 16px; margin-bottom: 14px; font-size: 13px; }
    .sb-next .txt { flex: 1; min-width: 220px; }
    .sb-next .txt strong { display: block; font-size: 14px; }
    .sb-next.info { background: #eff6ff; border-color: #bfdbfe; color: #1e3a8a; }
    .sb-next.warn { background: #fffbeb; border-color: #fde68a; color: #78350f; }
    .sb-next.danger { background: #fef2f2; border-color: #fecaca; color: #7f1d1d; }
    .sb-next.muted { background: #f9fafb; border-color: var(--card-border); color: #374151; }
    .sb-next.ok { background: #f0fdf4; border-color: #bbf7d0; color: #14532d; }
    .sb-dl { display: grid; grid-template-columns: 110px 1fr; gap: 7px 10px; margin: 0; font-size: 13px; }
    .sb-dl dt { color: var(--text-muted); font-weight: 500; font-size: 12px; }
    .sb-dl dd { margin: 0; word-break: break-word; }
    .sb-sum { display: flex; justify-content: space-between; padding: 6px 0; font-size: 13px; }
    .sb-sum .v { font-weight: 600; font-variant-numeric: tabular-nums; }
    .sb-sum.grand { border-top: 1px solid var(--card-border); margin-top: 4px; padding-top: 10px; font-size: 15px; font-weight: 700; }
</style>
@endsection

@section('content')
<div class="page-header">
    <div><h1 class="page-header-title">Detail Tagihan Supplier</h1></div>
    <div class="page-header-actions">
        <a href="{{ route('supplier-bill.index') }}" class="btn btn-secondary btn-sm"><i class="fa fa-arrow-left me-1"></i> Kembali</a>
    </div>
</div>

<div class="sb-hero fade-in">
    <div>
        {!! $bill->statusBadgeHtml() !!}
        @if($bill->isAdvance())<span class="status-badge" style="background:#fef3c7;color:#92400e;">Uang Muka</span>@endif
        <div class="sb-number">{{ $bill->bill_number }}</div>
        <div class="sb-muted">Invoice {{ $bill->supplier_invoice_number }} &middot; {{ $bill->supplier?->name }} &middot;
            @if($bill->purchase_order_id)<a href="{{ route('purchase-order.show', $bill->purchase_order_id) }}">{{ $bill->sourceNumber() }}</a>@elseif($bill->direct_purchase_id)<a href="{{ route('direct-purchase.show', $bill->direct_purchase_id) }}">{{ $bill->sourceNumber() }}</a>@endif
        </div>
    </div>
    <div class="sb-amount">
        <div class="sb-muted">Total Tagihan</div>
        <div class="val">{{ $bill->currency }} {{ $money($bill->total) }}</div>
        @if($bill->isForeign())
            <div class="sb-muted">≈ {{ $baseCurrency }} {{ $money($bill->toBook($bill->total)) }} · kurs {{ $money($bill->exchange_rate) }}
                @if($bill->rate_estimated)<span class="badge ms-1" style="background:#fef3c7;color:#92400e;font-size:10px" title="Kurs diisi otomatis dari kurs master, belum dikonfirmasi">kurs perkiraan</span>@endif
                @if($canCorrectRate)<button type="button" class="btn btn-link btn-sm p-0 ms-1" onclick="sbCorrectRate()" title="Koreksi kurs tanggal invoice supplier"><i class="fa fa-pen"></i></button>@endif
            </div>
        @endif
        <div class="sb-muted">Jatuh tempo {{ $bill->due_date?->format('d/m/Y') }}</div>
    </div>
</div>

@switch($bill->status)
    @case('draft')
        <div class="sb-next info">
            <div class="txt"><strong>Draft — menunggu verifikasi Finance</strong>Periksa qty, harga, dan dokumen invoice sebelum diverifikasi. Setelah diverifikasi, tagihan tercatat sebagai hutang.</div>
            <div class="d-flex gap-2 flex-wrap">
                @if($canUpdate)<a href="{{ route('supplier-bill.edit', $bill->id) }}" class="btn btn-secondary btn-sm"><i class="fa fa-pen me-1"></i> Edit</a>@endif
                @if($canDelete)<button type="button" class="btn btn-outline-danger btn-sm" onclick="sbDelete()"><i class="fa fa-trash me-1"></i> Hapus</button>@endif
                @if($canApprove)
                    <button type="button" class="btn btn-success btn-sm" id="sb-verify-btn" onclick="sbVerify()"><i class="fa fa-check me-1"></i> Verifikasi</button>
                @endif
            </div>
        </div>
        @break
    @case('verified')
    @case('partially_paid')
        <div class="sb-next {{ $bill->isOverdue() ? 'danger' : 'warn' }}">
            <div class="txt"><strong>{{ $bill->isOverdue() ? 'Lewat jatuh tempo '.(int) $bill->due_date->diffInDays(now()->startOfDay()).' hari' : 'Hutang aktif — jatuh tempo '.$bill->due_date->format('d/m/Y') }}</strong>Sisa hutang {{ $bill->currency }} {{ $money($bill->outstanding()) }}.</div>
            <div class="d-flex gap-2 flex-wrap">
                @if($canApprove && $bill->canBeVoided())
                    <button type="button" class="btn btn-outline-danger btn-sm" onclick="sbOpenVoid()"><i class="fa fa-ban me-1"></i> Void</button>
                @endif
                @if($canCreatePayment)
                    <a href="{{ route('supplier-payment.create', ['bill_id' => $bill->id]) }}" class="btn btn-primary btn-sm"><i class="fa fa-money-bill-transfer me-1"></i> Catat Pembayaran</a>
                @endif
            </div>
        </div>
        @break
    @case('paid')
        <div class="sb-next ok"><div class="txt"><strong><i class="fa fa-circle-check me-1"></i>Lunas</strong>Tagihan sudah dibayar penuh.</div></div>
        @break
    @case('void')
        <div class="sb-next muted"><div class="txt"><strong>Void{{ $bill->voider ? ' oleh '.$bill->voider->display_name : '' }}{{ $bill->voided_at ? ' · '.$bill->voided_at->format('d/m/Y H:i') : '' }}</strong>{{ $bill->void_reason }} — qty tagihan ini kembali bisa ditagih.</div></div>
        @break
@endswitch

<div class="row g-3 mb-4">
    <div class="col-xl-8">
        @if($bill->isAdvance())
        @php $advPo = $bill->purchaseOrder; @endphp
        <div class="card-custom fade-in mb-3">
            <div class="card-header-custom"><span><i class="fa-solid fa-hand-holding-dollar me-2" style="color:var(--accent)"></i>Uang Muka {{ $advPo?->po_number }}</span></div>
            <div class="card-body-custom">
                <p class="mb-2" style="font-size:13px">Pembayaran di muka sebelum barang diterima. Setelah dibayar, nilainya otomatis dipotongkan dari tagihan reguler PO ini; sisa yang tidak terpakai dikembalikan lewat Refund Supplier.</p>
                @if($advPo)
                <dl class="sb-dl">
                    <dt>Nilai PO</dt><dd>{{ $bill->currency }} {{ $money($advPo->netTotal()) }}</dd>
                    <dt>Batas Supplier</dt><dd>{{ rtrim(rtrim(number_format((float) $advPo->supplier?->max_advance_percent, 2), '0'), '.') }}% · {{ $bill->currency }} {{ $money($advPo->advanceLimit()) }}</dd>
                    <dt>Uang Muka Dibayar</dt><dd>{{ $bill->currency }} {{ $money($advPo->advancePaid()) }}</dd>
                    <dt>Sudah Dipotongkan</dt><dd>{{ $bill->currency }} {{ $money($advPo->advanceApplied()) }}</dd>
                    <dt>Saldo Uang Muka</dt><dd class="fw-semibold">{{ $bill->currency }} {{ $money($advPo->advanceBalance()) }}</dd>
                </dl>
                @endif
            </div>
        </div>
        @else
        <div class="card-custom fade-in mb-3">
            <div class="card-header-custom"><span><i class="fa-solid fa-list me-2" style="color:var(--accent)"></i>Baris Tagihan</span></div>
            <div class="card-body-custom p-2">
                <div class="table-responsive">
                    <table class="table table-custom mb-0">
                        <thead>
                            <tr>
                                <th>Barang</th>
                                <th>Project</th>
                                <th class="text-center">Qty</th>
                                <th class="text-end">Harga</th>
                                <th class="text-end">Jumlah</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($bill->items as $item)
                                @php $poPrice = $item->purchaseOrderItem?->price; @endphp
                                <tr>
                                    <td>@if($item->line_type === 'charge')<div class="fw-semibold" style="font-size:13px"><i class="fa-solid fa-truck-fast me-1" style="color:var(--text-muted)"></i>Biaya tambahan</div><div class="sb-muted">{{ $item->description }}</div>@else<div class="fw-semibold" style="font-size:13px">{{ $item->part_number ?: '—' }}</div><div class="sb-muted">{!! $item->description !!}</div>@endif</td>
                                    <td style="font-size:12.5px">{{ $item->project ? $item->project->project_number.' — '.$item->project->project_name : 'Stok / Internal' }}</td>
                                    <td class="text-center">{{ $item->qty }} <span class="sb-muted">{{ $item->unit }}</span></td>
                                    <td class="text-end">{{ $money($item->price) }}
                                        @if($poPrice !== null && abs($poPrice - $item->price) > 0.004)<div class="sb-muted" title="Harga PO">PO {{ $money($poPrice) }}</div>@endif
                                    </td>
                                    <td class="text-end fw-semibold">{{ $money($item->amount) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @endif
        @if($bill->allocations->isNotEmpty())
        <div class="card-custom fade-in mb-3">
            <div class="card-header-custom"><span><i class="fa-solid fa-money-bill-transfer me-2" style="color:var(--accent)"></i>Riwayat Pembayaran</span></div>
            <div class="card-body-custom p-2">
                <table class="table table-custom align-middle mb-0">
                    <tbody>
                        @foreach($bill->allocations->sortByDesc('id') as $allocation)
                            <tr @if($allocation->payment?->status !== 'posted') style="opacity:.6" @endif>
                                <td><a href="{{ route('supplier-payment.show', $allocation->supplier_payment_id) }}" class="fw-semibold">{{ $allocation->payment?->payment_number }}</a><div class="sb-muted">{{ $allocation->payment?->method_label }}{{ $allocation->payment?->reference_number ? ' · Ref '.$allocation->payment->reference_number : '' }}</div></td>
                                <td>{{ $allocation->payment?->payment_date?->format('d/m/Y') }}</td>
                                <td class="text-end fw-semibold" style="white-space:nowrap">{{ $bill->currency }} {{ $money($allocation->amount) }}
                                    @if($bill->isForeign())<div class="sb-muted" style="font-weight:400">{{ $baseCurrency }} {{ $money($allocation->amount_base_paid) }} · kurs {{ $money($allocation->payment?->exchange_rate) }}</div>@endif
                                </td>
                                @if($bill->isForeign())
                                <td class="text-end" style="white-space:nowrap" title="Selisih kurs: + rugi / − laba">
                                    <span class="{{ $allocation->fx_difference > 0 ? 'text-danger' : ($allocation->fx_difference < 0 ? 'text-success' : 'sb-muted') }}">{{ $allocation->fx_difference > 0 ? 'Rugi kurs ' : ($allocation->fx_difference < 0 ? 'Laba kurs ' : 'Selisih ') }}{{ $money(abs($allocation->fx_difference)) }}</span>
                                </td>
                                @endif
                                <td class="text-end">{!! $allocation->payment?->statusBadgeHtml() !!}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <div class="sb-muted px-2 pt-2">Hanya pembayaran Posted yang mengurangi hutang.@if($bill->isForeign()) Selisih kurs = Rupiah dibayar − nilai buku (kurs tagihan); total terealisasi {{ $bill->realizedFx() > 0 ? 'rugi' : 'laba' }} {{ $baseCurrency }} {{ $money(abs($bill->realizedFx())) }}.@endif</div>
            </div>
        </div>
        @endif
        @include('partials.purchase-documents', [
            'documents' => $documents,
            'types' => $bill->documentTypes(),
            'uploadUrl' => route('supplier-bill.documents.store', $bill->id),
            'canUpload' => $canCreate,
            'hint' => 'Lampirkan scan invoice dan faktur pajak. Setelah diverifikasi, dokumen hanya bisa ditambah.',
        ])
    </div>
    <div class="col-xl-4">
        <div class="card-custom fade-in mb-3">
            <div class="card-header-custom"><span><i class="fa-solid fa-calculator me-2" style="color:var(--accent)"></i>Total ({{ $bill->currency }})</span></div>
            <div class="card-body-custom">
                <div class="sb-sum"><span>Sub Total</span><span class="v">{{ $money($bill->subtotal) }}</span></div>
                @if($bill->discount_amount > 0)<div class="sb-sum"><span>Diskon {{ $pct($bill->discount_percent) }}%</span><span class="v text-danger">- {{ $money($bill->discount_amount) }}</span></div>@endif
                @if($bill->other_charges > 0)<div class="sb-sum"><span>Biaya Tambahan</span><span class="v">{{ $money($bill->other_charges) }}</span></div>@endif
                @if($bill->other_charges > 0 || $bill->discount_amount > 0)<div class="sb-sum"><span>DPP</span><span class="v">{{ $money($bill->taxBase()) }}</span></div>@endif
                @if($bill->ppn_amount > 0)<div class="sb-sum"><span>PPN {{ $pct($bill->ppn_percent) }}%</span><span class="v">{{ $money($bill->ppn_amount) }}</span></div>@endif
                <div class="sb-sum grand"><span>Total</span><span>{{ $money($bill->total) }}</span></div>
                @if($bill->pph_amount > 0)<div class="sb-sum"><span>{{ $bill->pph_label }} {{ $pct($bill->pph_percent) }}% dipotong<div class="sb-muted">dasar {{ $money($bill->pph_base) }}</div></span><span class="v text-danger">- {{ $money($bill->pph_amount) }}</span></div>@endif
                @if($bill->advance_deduction > 0)
                    <div class="sb-sum"><span>Dipotong Uang Muka</span><span class="v text-danger">- {{ $money($bill->advance_deduction) }}</span></div>
                @endif
                @if($bill->advance_deduction > 0 || $bill->pph_amount > 0)
                    <div class="sb-sum"><span class="fw-semibold">Yang Ditransfer</span><span class="v">{{ $money($bill->amountDue()) }}</span></div>
                @endif
                <div class="sb-sum"><span>Sudah Dibayar</span><span class="v">{{ $money($bill->paidAmount()) }}</span></div>
                <div class="sb-sum"><span>Sisa Hutang</span><span class="v">{{ $money($bill->outstanding()) }}</span></div>
            </div>
        </div>
        @if($bill->pph_amount > 0 && ! $bill->isDraft() && ! $bill->isVoid())
        <div class="card-custom fade-in mb-3">
            <div class="card-header-custom"><span><i class="fa-solid fa-receipt me-2" style="color:var(--accent)"></i>Bukti Potong {{ $bill->pph_label }}</span></div>
            <div class="card-body-custom">
                @if($bill->pph_slip_number)
                    <div class="fw-semibold">{{ $bill->pph_slip_number }}</div>
                    <div class="sb-muted">Tanggal {{ $bill->pph_slip_date?->format('d/m/Y') }} · {{ $bill->currency }} {{ $money($bill->pph_amount) }}</div>
                @else
                    <div class="sb-muted mb-2"><i class="fa fa-triangle-exclamation me-1 text-warning"></i>PPh {{ $bill->currency }} {{ $money($bill->pph_amount) }} sudah dipotong — bukti potong wajib diterbitkan untuk supplier.</div>
                @endif
                @if($canUpdate)
                <div class="row g-2 mt-1">
                    <div class="col-7"><input type="text" id="sb-slip-number" class="form-control form-control-sm" maxlength="100" placeholder="No. bukti potong" value="{{ $bill->pph_slip_number }}" aria-label="No. bukti potong"></div>
                    <div class="col-5"><input type="date" id="sb-slip-date" class="form-control form-control-sm" max="{{ now()->toDateString() }}" value="{{ $bill->pph_slip_date?->toDateString() ?? now()->toDateString() }}" aria-label="Tanggal bukti potong"></div>
                </div>
                <button type="button" class="btn btn-secondary btn-sm mt-2" onclick="sbSaveSlip()"><i class="fa fa-save me-1"></i> Simpan Bukti Potong</button>
                @endif
            </div>
        </div>
        @endif
        <div class="card-custom fade-in">
            <div class="card-header-custom"><span><i class="fa-solid fa-circle-info me-2" style="color:var(--accent)"></i>Informasi</span></div>
            <div class="card-body-custom">
                <dl class="sb-dl">
                    <dt>Supplier</dt><dd class="fw-semibold">{{ $bill->supplier?->name }}</dd>
                    @if($bill->supplier?->bank_account_no)<dt>Rekening</dt><dd>{{ $bill->supplier->bank_name }} {{ $bill->supplier->bank_account_no }}<div class="sb-muted">a.n. {{ $bill->supplier->bank_account_name }}</div></dd>@endif
                    <dt>Tgl Tagihan</dt><dd>{{ $bill->bill_date?->format('d/m/Y') }}</dd>
                    <dt>Jatuh Tempo</dt><dd>{{ $bill->due_date?->format('d/m/Y') }}</dd>
                    @if($bill->isForeign())<dt>Kurs Tagihan</dt><dd>1 {{ $bill->currency }} = {{ $baseCurrency }} {{ $money($bill->exchange_rate) }}<div class="sb-muted">{{ $bill->rate_estimated ? 'Perkiraan dari kurs master — koreksi sesuai tanggal invoice' : 'Kurs tanggal invoice supplier' }}</div></dd>@endif
                    @if($bill->tax_invoice_number)<dt>Faktur Pajak</dt><dd>{{ $bill->tax_invoice_number }}</dd>@endif
                    <dt>Dibuat</dt><dd>{{ $bill->creator?->display_name ?? '—' }}<div class="sb-muted">{{ $bill->created_at?->format('d/m/Y H:i') }}</div></dd>
                    @if($bill->verified_at)<dt>Diverifikasi</dt><dd>{{ $bill->verifier?->display_name ?? '—' }}<div class="sb-muted">{{ $bill->verified_at->format('d/m/Y H:i') }}</div></dd>@endif
                    @if($bill->notes)<dt>Catatan</dt><dd>{{ $bill->notes }}</dd>@endif
                </dl>
            </div>
        </div>
    </div>
</div>
@endsection

@push('modals')
<div class="modal fade" id="sbVoidModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title">Void Tagihan {{ $bill->bill_number }}</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="sb-muted">Tagihan dibatalkan dan tidak lagi dihitung sebagai hutang. Qty-nya kembali bisa ditagih.</p>
                <label class="form-label" for="sb-void-reason">Alasan <span class="text-danger">*</span></label>
                <textarea id="sb-void-reason" class="form-control" rows="3" placeholder="Contoh: invoice salah, supplier menerbitkan ulang"></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
                <button type="button" class="btn btn-danger btn-sm" id="sb-void-btn" onclick="sbVoid()"><i class="fa fa-ban me-1"></i> Void Tagihan</button>
            </div>
        </div>
    </div>
</div>
@endpush

@section('scripts')
<script>
const sbCsrf = '{{ csrf_token() }}';
let sbVoidModal = null;

function sbError(xhr, fallback) { toastr.error(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : fallback); }

function sbVerify() {
    Swal.fire({ title: 'Verifikasi tagihan?', text: 'Qty dan harga sudah cocok dengan PO dan penerimaan. Tagihan akan tercatat sebagai hutang.', icon: 'question', showCancelButton: true, confirmButtonText: 'Ya, Verifikasi', cancelButtonText: 'Batal' })
        .then(function(r) {
            if (!r.isConfirmed) return;
            $('#sb-verify-btn').prop('disabled', true);
            $.post('{{ route("supplier-bill.approve", $bill->id) }}', { _token: sbCsrf })
                .done(function(res) { toastr.success(res.message); setTimeout(function() { location.reload(); }, 700); })
                .fail(function(xhr) { sbError(xhr, 'Gagal memverifikasi.'); $('#sb-verify-btn').prop('disabled', false); });
        });
}

function sbDelete() {
    Swal.fire({ title: 'Hapus draft tagihan?', icon: 'warning', showCancelButton: true, confirmButtonText: 'Ya, Hapus', cancelButtonText: 'Batal' })
        .then(function(r) {
            if (!r.isConfirmed) return;
            $.ajax({ url: '{{ route("supplier-bill.destroy", $bill->id) }}', method: 'DELETE', headers: { 'X-CSRF-TOKEN': sbCsrf } })
                .done(function(res) { toastr.success(res.message); setTimeout(function() { location.href = '{{ route("supplier-bill.index") }}'; }, 600); })
                .fail(function(xhr) { sbError(xhr, 'Gagal menghapus.'); });
        });
}

function sbSaveSlip() {
    $.ajax({ url: '{{ route("supplier-bill.pph-slip", $bill->id) }}', method: 'PATCH', data: { _token: sbCsrf, pph_slip_number: $('#sb-slip-number').val().trim(), pph_slip_date: $('#sb-slip-date').val() } })
        .done(function(res) { toastr.success(res.message); setTimeout(function() { location.reload(); }, 600); })
        .fail(function(xhr) {
            var msg = xhr.responseJSON && xhr.responseJSON.errors ? xhr.responseJSON.errors[Object.keys(xhr.responseJSON.errors)[0]][0] : null;
            toastr.error(msg || (xhr.responseJSON && xhr.responseJSON.message) || 'Gagal menyimpan.');
        });
}

function sbCorrectRate() {
    Swal.fire({
        title: 'Kurs {{ $bill->currency }} → {{ $baseCurrency }}',
        text: 'Kurs pada tanggal invoice supplier {{ $bill->bill_date?->format('d/m/Y') }}.',
        input: 'text',
        inputValue: '{{ \App\Support\Number::rate($bill->exchange_rate) }}',
        showCancelButton: true,
        confirmButtonText: 'Simpan',
        cancelButtonText: 'Batal'
    }).then(function(r) {
        if (!r.isConfirmed) return;
        var rate = Num.parse(r.value);
        if (!(rate > 0)) { toastr.error('Kurs harus lebih dari 0.'); return; }
        $.ajax({ url: '{{ route("supplier-bill.exchange-rate", $bill->id) }}', method: 'PATCH', data: { _token: sbCsrf, exchange_rate: rate } })
            .done(function(res) { toastr.success(res.message); setTimeout(function() { location.reload(); }, 600); })
            .fail(function(xhr) { sbError(xhr, 'Gagal menyimpan kurs.'); });
    });
}

function sbOpenVoid() {
    $('#sb-void-reason').val('');
    if (!sbVoidModal) sbVoidModal = new bootstrap.Modal(document.getElementById('sbVoidModal'));
    sbVoidModal.show();
}

function sbVoid() {
    var reason = $('#sb-void-reason').val().trim();
    if (!reason) { toastr.error('Alasan void wajib diisi.'); return; }
    $('#sb-void-btn').prop('disabled', true);
    $.post('{{ route("supplier-bill.void", $bill->id) }}', { _token: sbCsrf, void_reason: reason })
        .done(function(res) { toastr.success(res.message); setTimeout(function() { location.reload(); }, 700); })
        .fail(function(xhr) { sbError(xhr, 'Gagal void.'); $('#sb-void-btn').prop('disabled', false); });
}
</script>
@endsection
