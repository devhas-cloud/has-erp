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
            <div class="col-lg-4">
                <label class="form-label">Gudang</label>
                <select name="warehouse_id" class="form-select">
                    <option value="">Semua gudang</option>
                    @foreach($warehouses as $w)
                        <option value="{{ $w->id }}" {{ (string) $filters['warehouse_id'] === (string) $w->id ? 'selected' : '' }}>{{ $w->code }} — {{ $w->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-5">
                <label class="form-label">Kepemilikan</label>
                <select name="ownership" class="form-select">
                    <option value="">Semua</option>
                    <option value="free" {{ $filters['ownership'] === 'free' ? 'selected' : '' }}>Stok bebas</option>
                    @foreach($projects as $p)
                        <option value="{{ $p->id }}" {{ (string) $filters['ownership'] === (string) $p->id ? 'selected' : '' }}>Project {{ $p->project_number }} — {{ $p->project_name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-3">
                <button type="submit" class="btn btn-primary btn-sm"><i class="fa fa-filter me-1"></i> Terapkan</button>
            </div>
        </form>
    </div>
</div>

<div class="card-custom fade-in">
    <div class="card-header-custom d-flex justify-content-between align-items-center">
        <span><i class="fa-solid fa-clock-rotate-left me-2" style="color:var(--accent)"></i>Riwayat Pergerakan</span>
        <span class="fw-semibold">Saldo akhir: {{ $rows->last()['balance'] ?? 0 }} &middot; Harga rata-rata saat ini: {{ \App\Models\Currency::baseName() }} {{ \App\Support\StockValuation::format($averageCost) }}</span>
    </div>
    <div class="card-body-custom p-2">
        <div class="table-responsive">
            <table class="table table-custom align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width:95px">Tanggal</th>
                        <th>Jenis</th>
                        <th>Dokumen</th>
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
                    @forelse($rows as $row)
                        @php $m = $row['movement']; @endphp
                        <tr>
                            <td>{{ $m->moved_at?->format('d/m/Y') }}</td>
                            <td><span class="badge" style="background:{{ $row['in'] ? 'var(--accent-soft);color:var(--accent)' : 'var(--danger-soft);color:#7f1d1d' }};font-size:10px">{{ $m->type_label }}</span></td>
                            <td>
                                @if($row['source_url'])
                                    <a href="{{ $row['source_url'] }}">{{ $m->note ?: $row['source_label'] }}</a>
                                @else
                                    {{ $m->note ?: $row['source_label'] }}
                                @endif
                            </td>
                            <td>{{ $m->warehouse?->code ?? '—' }}</td>
                            <td>{{ $m->project ? $m->project->project_number : 'Stok bebas' }}</td>
                            <td class="text-end">{{ $row['in'] ?? '' }}</td>
                            <td class="text-end">{{ $row['out'] ?? '' }}</td>
                            <td class="text-end fw-semibold">{{ $row['balance'] }}</td>
                            <td class="text-end">{{ \App\Support\StockValuation::format($m->unit_cost) }}</td>
                            <td class="text-end">{{ \App\Support\StockValuation::format($row['value']) }}</td>
                            <td style="font-size:12px">{{ $m->creator?->display_name ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11" class="text-center py-4" style="color:var(--text-muted)"><i class="fa-solid fa-inbox me-1"></i> Belum ada pergerakan stok.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
