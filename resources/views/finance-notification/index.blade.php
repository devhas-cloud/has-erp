@extends('layouts.app')

@section('title', 'Notifikasi Finance')
@section('page-title', 'Notifikasi Finance')

@section('styles')
<style>
    .fn-muted { font-size: 12px; color: var(--text-muted); }
    .fn-preview { white-space: pre-wrap; font-family: inherit; font-size: 12.5px; background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: var(--radius-sm); padding: 12px; max-height: 320px; overflow: auto; margin: 0; }
    .fn-card .card-header-custom { gap: 8px; flex-wrap: wrap; }
</style>
@endsection

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">Notifikasi Finance</h1>
        <p class="page-header-sub">Pengingat WhatsApp harian untuk hutang supplier dan piutang customer. Satu ringkasan per hari, dikirim pada atau setelah jam kirim; tidak dikirim bila tidak ada yang perlu diingatkan.</p>
    </div>
</div>

@if($waGroups->isEmpty())
    <div class="alert alert-warning py-2 small"><i class="fa fa-triangle-exclamation me-1"></i>Belum ada grup WhatsApp aktif. Tambahkan di <a href="{{ route('configuration.index') }}">Configuration → WhatsApp Group</a>, atau gunakan WA pribadi.</div>
@endif

<div class="row g-3 mb-4">
    @foreach($cards as $card)
        @php $s = $card['settings']; $k = $card['key']; @endphp
        <div class="col-xl-6">
            <div class="card-custom fade-in fn-card h-100" data-type="{{ $k }}" data-flag="{{ $card['flag'] }}">
                <div class="card-header-custom d-flex justify-content-between align-items-center">
                    <span><i class="fa-brands fa-whatsapp me-2" style="color:#16a34a"></i>{{ $card['title'] }}
                        <span class="status-badge ms-1" style="{{ $s['enabled'] ? 'background:#dcfce7;color:#166534' : 'background:#f3f4f6;color:#4b5563' }}">{{ $s['enabled'] ? 'Aktif · '.$s['send_time'] : 'Nonaktif' }}</span>
                    </span>
                    <span class="fn-muted">{{ $s['last_sent_date'] ? 'Terakhir terkirim '.\Illuminate\Support\Carbon::parse($s['last_sent_date'])->format('d/m/Y') : 'Belum pernah terkirim' }}</span>
                </div>
                <div class="card-body-custom">
                    <p class="fn-muted mb-3">{{ $card['description'] }} Data dari <a href="{{ route($card['report_route']) }}">{{ $card['report_label'] }}</a>.</p>

                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="fn-{{ $k }}-enabled" data-field="enabled" @checked($s['enabled']) @disabled(! $canUpdate)>
                        <label class="form-check-label fw-semibold" for="fn-{{ $k }}-enabled">Kirim otomatis setiap hari</label>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-sm-6">
                            <label class="form-label" for="fn-{{ $k }}-time">Jam kirim</label>
                            <input type="time" id="fn-{{ $k }}-time" class="form-control" data-field="send_time" value="{{ $s['send_time'] }}" @disabled(! $canUpdate)>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label" for="fn-{{ $k }}-days">Ingatkan jatuh tempo dalam</label>
                            <div class="input-group"><input type="number" id="fn-{{ $k }}-days" class="form-control" min="0" max="30" data-field="days_ahead" value="{{ $s['days_ahead'] }}" @disabled(! $canUpdate)><span class="input-group-text">hari</span></div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="fn-{{ $k }}-group">Grup WhatsApp Finance</label>
                        <select id="fn-{{ $k }}-group" class="form-select" data-field="whatsapp_group_id" @disabled(! $canUpdate)>
                            <option value="">— Tidak dikirim ke grup —</option>
                            @foreach($waGroups as $group)
                                <option value="{{ $group->id }}" @selected((int) $s['whatsapp_group_id'] === $group->id)>{{ $group->group_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-check mb-1">
                        <input class="form-check-input" type="checkbox" id="fn-{{ $k }}-users" data-field="{{ $card['flag'] }}" @checked($s[$card['flag']]) @disabled(! $canUpdate)>
                        <label class="form-check-label" for="fn-{{ $k }}-users">Kirim juga ke WhatsApp pribadi {{ $card['recipient_label'] }}</label>
                    </div>
                    <div class="fn-muted mb-3 ms-4">
                        @if($card['recipients']->isEmpty())
                            Belum ada {{ $card['recipient_label'] }} yang punya nomor WhatsApp.
                        @else
                            {{ $card['recipients']->count() }} penerima: {{ $card['recipients']->pluck('display_name')->implode(', ') }}
                        @endif
                    </div>

                    <label class="form-label">Pratinjau pesan</label>
                    <pre class="fn-preview mb-3" data-preview>{{ $card['preview'] ?? 'Tidak ada yang perlu diingatkan saat ini.' }}</pre>

                    <div class="d-flex gap-2 flex-wrap">
                        @if($canUpdate)<button type="button" class="btn btn-primary btn-sm" data-action="save"><i class="fa fa-save me-1"></i> Simpan Pengaturan</button>@endif
                        @if($canCreate)<button type="button" class="btn btn-outline-success btn-sm" data-action="send"><i class="fa-brands fa-whatsapp me-1"></i> Kirim Sekarang</button>@endif
                    </div>
                </div>
            </div>
        </div>
    @endforeach
</div>
@endsection

@section('scripts')
<script>
const fnUpdateUrl = '{{ route("finance-notification.update", "__TYPE__") }}';
const fnSendUrl = '{{ route("finance-notification.send", "__TYPE__") }}';
const fnCsrf = '{{ csrf_token() }}';

function fnErr(xhr, fallback) {
    var res = xhr.responseJSON || {};
    toastr.error(res.errors ? res.errors[Object.keys(res.errors)[0]][0] : (res.message || fallback));
}

$(document).on('click', '.fn-card [data-action="save"]', function() {
    var $card = $(this).closest('.fn-card');
    var $btn = $(this).prop('disabled', true);
    var data = { _token: fnCsrf, _method: 'PATCH' };
    $card.find('[data-field]').each(function() {
        data[$(this).data('field')] = $(this).is(':checkbox') ? ($(this).is(':checked') ? 1 : 0) : $(this).val();
    });

    $.post(fnUpdateUrl.replace('__TYPE__', $card.data('type')), data)
        .done(function(res) {
            toastr.success(res.message);
            $card.find('[data-preview]').text(res.preview || 'Tidak ada yang perlu diingatkan saat ini.');
            setTimeout(function() { location.reload(); }, 900);
        })
        .fail(function(xhr) { fnErr(xhr, 'Gagal menyimpan.'); })
        .always(function() { $btn.prop('disabled', false); });
});

$(document).on('click', '.fn-card [data-action="send"]', function() {
    var $card = $(this).closest('.fn-card');
    var $btn = $(this);
    Swal.fire({ title: 'Kirim pengingat sekarang?', text: 'Ringkasan dikirim ke tujuan yang sudah tersimpan.', icon: 'question', showCancelButton: true, confirmButtonText: 'Ya, Kirim', cancelButtonText: 'Batal' })
        .then(function(r) {
            if (!r.isConfirmed) return;
            $btn.prop('disabled', true);
            $.post(fnSendUrl.replace('__TYPE__', $card.data('type')), { _token: fnCsrf })
                .done(function(res) { (res.success ? toastr.success : toastr.info)(res.message); })
                .fail(function(xhr) { fnErr(xhr, 'Gagal mengirim.'); })
                .always(function() { $btn.prop('disabled', false); });
        });
});
</script>
@endsection
