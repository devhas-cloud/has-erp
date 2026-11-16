@extends('layouts.app')

@section('title', 'Kartu Stok')
@section('page-title', 'Kartu Stok')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">Kartu Stok — {{ $product->code }}</h1>
        <p class="page-header-sub">{{ $product->name }}</p>
    </div>
    <div class="page-header-actions">
        <a href="{{ route('stock-report.index') }}" class="btn btn-secondary btn-sm">
            <i class="fa fa-arrow-left me-1"></i> Laporan Stok
        </a>
    </div>
</div>

<div class="card-custom fade-in mb-3">
    <div class="card-body-custom">
        <form method="GET" action="{{ route('stock-report.card', $product->id) }}" class="row g-3 align-items-end">
            <div class="col-lg-2">
                <label class="form-label">Dari</label>
                <input type="date" name="from" class="form-control" value="{{ $filters['from'] }}">
            </div>
            <div class="col-lg-2">
                <label class="form-label">Sampai</label>
                <input type="date" name="to" class="form-control" value="{{ $filters['to'] }}">
            </div>
            <div class="col-lg-3">
                <label class="form-label">Gudang</label>
                <select name="warehouse_id" class="form-select">
                    <option value="">Semua gudang</option>
                    @foreach($warehouses as $w)
                        <option value="{{ $w->id }}" {{ (string) $filters['warehouse_id'] === (string) $w->id ? 'selected' : '' }}>{{ $w->code }} — {{ $w->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-3">
                <label class="form-label">Kepemilikan</label>
                <select name="ownership" class="form-select">
                    <option value="">Semua</option>
                    <option value="free" {{ $filters['ownership'] === 'free' ? 'selected' : '' }}>Stok bebas</option>
                    @foreach($projects as $p)
                        <option value="{{ $p->id }}" {{ (string) $filters['ownership'] === (string) $p->id ? 'selected' : '' }}>Project {{ $p->project_number }} — {{ $p->project_name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-2">
                <button type="submit" class="btn btn-primary btn-sm"><i class="fa fa-filter me-1"></i> Terapkan</button>
            </div>
        </form>
    </div>
</div>

<div class="card-custom fade-in">
    <div class="card-header-custom d-flex justify-content-between align-items-center">
        <span><i class="fa-solid fa-clock-rotate-left me-2" style="color:var(--accent)"></i>Riwayat Pergerakan</span>
        @php $closing = $rows->last()['balance'] ?? $openingQty; $closingValue = $rows->last()['value'] ?? $openingValue; @endphp
        <span class="fw-semibold">Saldo akhir: {{ \App\Support\StockCost::qty($closing) }} &middot; Nilai: {{ \App\Models\Currency::baseName() }} {{ \App\Support\StockCost::format($closingValue) }}</span>
    </div>
    <div class="card-body-custom p-2">
        <div class="table-responsive">
            <table class="table table-custom align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width:95px">Tanggal</th>
                        <th>Jenis</th>
                        <th>Dokumen</th>
                        <th>SN / Batch</th>
                        <th>Gudang</th>
                        <th>Kepemilikan</th>
                        <th class="text-end" style="width:80px">Masuk</th>
                        <th class="text-end" style="width:80px">Keluar</th>
                        <th class="text-end" style="width:80px">Saldo</th>
                        <th class="text-end" style="width:110px">Harga</th>
                        <th class="text-end" style="width:130px">Nilai Saldo</th>
                        <th>Oleh</th>
                    </tr>
                </thead>
                <tbody>
                    <tr style="background:var(--bg-soft, #f8fafc)">
                        <td>{{ \Carbon\Carbon::parse($filters['from'])->format('d/m/Y') }}</td>
                        <td colspan="7" class="fw-semibold">Saldo awal</td>
                        <td class="text-end fw-semibold">{{ \App\Support\StockCost::qty($openingQty) }}</td>
                        <td></td>
                        <td class="text-end">{{ \App\Support\StockCost::format($openingValue) }}</td>
                        <td></td>
                    </tr>
                    @forelse($rows as $row)
                        @php $log = $row['log']; [$docLabel, $docUrl] = $log->ref_document; @endphp
                        <tr>
                            <td>{{ $log->log_date?->format('d/m/Y') }}</td>
                            <td><span class="badge" style="background:{{ $row['in'] ? 'var(--accent-soft);color:var(--accent)' : 'var(--danger-soft);color:#7f1d1d' }};font-size:10px">{{ $log->type_label }}</span></td>
                            <td>
                                @if($docUrl)
                                    <a href="{{ $docUrl }}">{{ $docLabel }}</a>
                                @else
                                    {{ $docLabel }}
                                @endif
                            </td>
                            <td style="font-size:12px">
                                <a href="{{ route('stock-detail.show', $log->stock_id) }}">{{ $log->stock?->serial_number ? 'SN '.$log->stock->serial_number : ($log->stock?->batch_number ? 'Batch '.$log->stock->batch_number : '#'.$log->stock_id) }}</a>
                            </td>
                            <td>{{ $log->warehouse?->code ?? '—' }}</td>
                            <td>{{ $log->project ? $log->project->project_number : 'Stok bebas' }}</td>
                            <td class="text-end">{{ $row['in'] !== null ? \App\Support\StockCost::qty($row['in']) : '' }}</td>
                            <td class="text-end">{{ $row['out'] !== null ? \App\Support\StockCost::qty($row['out']) : '' }}</td>
                            <td class="text-end fw-semibold">{{ \App\Support\StockCost::qty($row['balance']) }}</td>
                            <td class="text-end">{{ \App\Support\StockCost::format($log->unit_cost) }}</td>
                            <td class="text-end">{{ \App\Support\StockCost::format($row['value']) }}</td>
                            <td style="font-size:12px">{{ $log->creator?->display_name ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="12" class="text-center py-4" style="color:var(--text-muted)"><i class="fa-solid fa-inbox me-1"></i> Tidak ada pergerakan stok pada periode ini.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
