@extends('layouts.app')

@section('title', 'Detail Quotation '.($quotation->quotation_number ?? '#'.$quotation->id))
@section('page-title', 'Detail Quotation')

@section('styles')
<style>
    .info-table td { padding: 7px 0; vertical-align: top; line-height: 1.45; }
    .info-table td:first-child {
        color: var(--text-muted);
        width: 150px;
        font-size: 12px;
        font-weight: 500;
        white-space: nowrap;
        padding-right: 12px;
    }
    .info-table td:last-child {
        font-size: 13px;
        color: var(--text-primary);
        word-break: break-word;
    }
    .info-table tr + tr td { border-top: 1px solid var(--card-border); }
    .cat-title {
        font-size: 13px; font-weight: 700; color: var(--accent);
        text-transform: uppercase; letter-spacing: .5px;
        padding: 12px 16px 8px; margin: 0;
    }
</style>
@endsection

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">{{ $quotation->quotation_number ?? 'Quotation #'.$quotation->id }}</h1>
        <p class="page-header-sub">
            Dari Task #{{ $quotation->task_id ?? '—' }} &middot; {{ $quotation->to_name ?? '—' }}
        </p>
    </div>
    <div class="page-header-actions">
        <a href="{{ route('quotation.index') }}" class="btn btn-secondary btn-sm">
            <i class="fa fa-arrow-left me-1"></i> Kembali
        </a>
        @if(!$quotation->isLocked())
            <a href="{{ route('quotation.edit', $quotation->id) }}" class="btn btn-secondary btn-sm">
                <i class="fa fa-pen me-1"></i> Edit
            </a>
        @endif
        <a href="{{ route('quotation.pdf', $quotation->id) }}" target="_blank" class="btn-accent">
            <i class="fa fa-file-pdf me-1"></i> <span>View PDF</span>
        </a>
    </div>
</div>

<div class="card-custom fade-in mb-3">
    <div class="card-header-custom">
        <span><i class="fa-solid fa-file-invoice me-2" style="color:var(--accent)"></i>Informasi Quotation
            {!! $quotation->statusBadgeHtml() !!}
        </span>
    </div>
    <div class="card-body-custom">
        <table class="info-table">
            <tr>
                <td>Nomor Quotation</td>
                <td><strong>{{ $quotation->quotation_number ?? '—' }}</strong></td>
                <td>Tanggal</td>
                <td>{{ $quotation->date?->format('d/m/Y') ?? '—' }}</td>
            </tr>
            <tr>
                <td>Sumber Configuration</td>
                <td>
                    @if($quotation->configurations->isNotEmpty())
                        {{ count($quotation->configurations) }} config: {{ $quotation->configurations->implode('division.division_name', ' + ') }}
                    @else
                        #{{ $quotation->quote_configuration_id ?? '—' }}
                    @endif
                </td>
                <td>Currency</td>
                <td>{{ $quotation->currency ?? 'Rupiah' }}</td>
            </tr>
            <tr>
                <td>To</td>
                <td>{{ $quotation->to_name ?? '—' }}</td>
                <td>Your Ref</td>
                <td>{{ $quotation->your_ref ?? '—' }}</td>
            </tr>
            <tr>
                <td>Address</td>
                <td>{!! nl2br(e($quotation->address ?? '—')) !!}</td>
                <td>No of Pages</td>
                <td>{{ $quotation->no_of_pages }} Pages</td>
            </tr>
            <tr>
                <td>Attn</td>
                <td>{{ $quotation->attn_name ?? '—' }}</td>
                <td>From</td>
                <td>{{ $quotation->from_name ?? '—' }}</td>
            </tr>
            <tr>
                <td>Telp</td>
                <td>{{ $quotation->attn_phone ?? '—' }}</td>
                <td>Contact Person Phone</td>
                <td>{{ $quotation->contact_phone ?? '—' }}</td>
            </tr>
            <tr>
                <td>Email</td>
                <td>{{ $quotation->attn_email ?? '—' }}</td>
                <td>Dibuat Oleh</td>
                <td>{{ $quotation->creator?->username ?? '—' }}</td>
            </tr>
            <tr>
                <td>Parameter</td>
                <td colspan="3">{{ $quotation->parameter_note ?? '—' }}</td>
            </tr>
        </table>
    </div>
</div>

<div class="card-custom fade-in mb-3">
    <div class="card-header-custom">
        <span><i class="fa-solid fa-list me-2" style="color:var(--accent)"></i>Item Quotation</span>
    </div>
    <div class="card-body-custom p-2">
        <div class="table-responsive">
            <table class="table table-custom align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width:45px">No</th>
                        <th>Deskripsi</th>
                        <th style="width:70px" class="text-center">Qty</th>
                        <th style="width:140px" class="text-end">Unit Price</th>
                        <th style="width:150px" class="text-end">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @php $rows = $quotation->flattenTree(); @endphp
                    @forelse($rows as $row)
                        @php
                            $item = $row['item'];
                            $depth = $row['depth'];
                        @endphp
                        <tr>
                            <td class="text-center" style="padding-left:{{ 8 + $depth * 20 }}px">{{ $item->item_no }}</td>
                            <td>
                                <div style="margin-left:{{ $depth * 20 }}px">
                                    {!! nl2br(e($item->description)) !!}
                                    @if($item->part_number)
                                        <div><small style="color:var(--text-muted)">PN: {{ $item->part_number }}</small></div>
                                    @endif
                                </div>
                            </td>
                            <td class="text-center">{{ $item->qty ?: '' }} {{ $item->qty ? $item->unit : '' }}</td>
                            <td class="text-end">{{ $item->price ? \App\Models\Quotation::formatMoney($item->price) : '' }}</td>
                            <td class="text-end">{{ $item->qty && $item->price ? \App\Models\Quotation::formatMoney($item->qty * $item->price) : '' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center" style="color:var(--text-muted);padding:16px">Tidak ada item.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card-custom fade-in mb-3">
    <div class="card-header-custom">
        <span><i class="fa-solid fa-calculator me-2" style="color:var(--accent)"></i>Ringkasan Harga</span>
    </div>
    <div class="card-body-custom">
        <div class="row">
            <div class="col-md-6">
                @if($quotation->notes)
                    <div class="mb-2"><strong style="font-size:13px">Notes / Catatan</strong></div>
                    <div style="font-size:13px">{!! nl2br(e($quotation->notes)) !!}</div>
                @endif
            </div>
            <div class="col-md-6">
                <div class="table-responsive">
                    <table class="table table-custom align-middle mb-0">
                        <tbody>
                            <tr>
                                <td>Subtotal</td>
                                <td class="text-end fw-bold">{{ \App\Models\Quotation::formatMoney($quotation->subtotal) }}</td>
                            </tr>
                            <tr>
                                <td>DPP Pajak</td>
                                <td class="text-end">{{ \App\Models\Quotation::formatMoney($quotation->dpp) }}</td>
                            </tr>
                            <tr>
                                <td>PPN</td>
                                <td class="text-end">{{ \App\Models\Quotation::formatMoney($quotation->ppn) }}</td>
                            </tr>
                            <tr style="background:var(--accent-soft)">
                                <td class="fw-bold">Full Amount</td>
                                <td class="text-end fw-bold" style="color:var(--accent);font-size:16px">{{ \App\Models\Quotation::formatMoney($quotation->grand_total) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

@if($quotation->terms)
<div class="card-custom fade-in mb-3">
    <div class="card-header-custom">
        <span><i class="fa-solid fa-file-contract me-2" style="color:var(--accent)"></i>Term &amp; Conditions</span>
    </div>
    <div class="card-body-custom">
        <div style="font-family:monospace;font-size:12px; white-space: pre-wrap;">{!! e($quotation->terms) !!}</div>
    </div>
</div>
@endif

@if(!$quotation->isLocked())
<div class="d-flex gap-2 mb-4">
    <button type="button" class="btn-accent" onclick="issueQuotation({{ $quotation->id }})">
        <i class="fa fa-paper-plane me-1"></i> <span>Terbitkan (Issue)</span>
    </button>
    <button type="button" class="btn btn-danger" onclick="deleteQuotation({{ $quotation->id }})">
        <i class="fa fa-trash me-1"></i> Hapus
    </button>
</div>
@endif
@endsection

@section('scripts')
<script>
const quotationIssueUrl = '{{ route("quotation.issue", "__ID__") }}';
const quotationDeleteUrl = '{{ route("quotation.destroy", "__ID__") }}';

function issueQuotation(id) {
    Swal.fire({
        title: 'Terbitkan Quotation?',
        text: 'Quotation akan diterbitkan (Issued) dan terkunci. Pastikan data sudah final.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Ya, Terbitkan',
        cancelButtonText: 'Batal'
    }).then(function(result) {
        if (!result.isConfirmed) {
            return;
        }
        $.post(quotationIssueUrl.replace('__ID__', id), { _token: '{{ csrf_token() }}' })
            .done(function(res) {
                toastr.success(res.message || 'Quotation diterbitkan.');
                setTimeout(function() { window.location.reload(); }, 800);
            }).fail(function(xhr) {
                toastr.error(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Gagal menerbitkan.');
            });
    });
}

function deleteQuotation(id) {
    Swal.fire({
        title: 'Hapus Quotation?',
        text: 'Quotation #' + id + ' akan dihapus permanen.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Ya, Hapus',
        cancelButtonText: 'Batal'
    }).then(function(result) {
        if (!result.isConfirmed) {
            return;
        }
        $.ajax({
            url: quotationDeleteUrl.replace('__ID__', id),
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
        }).done(function(res) {
            toastr.success(res.message || 'Quotation dihapus.');
            setTimeout(function() { window.location.replace('{{ route("quotation.index") }}'); }, 800);
        }).fail(function(xhr) {
            toastr.error(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Gagal menghapus.');
        });
    });
}
</script>
@endsection
