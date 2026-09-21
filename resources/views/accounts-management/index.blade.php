@extends('layouts.app')

@section('title', 'Account Management')
@section('page-title', 'Account Management')

@section('styles')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
<style>
    .modal-account .modal-dialog { max-width: 900px; }
    .account-form-section {
        border: 1px solid var(--card-border);
        border-radius: var(--radius);
        margin-bottom: 16px;
        overflow: hidden;
    }
    .account-form-section-header {
        padding: 10px 16px;
        background: #f8fafc;
        border-bottom: 1px solid var(--card-border);
        font-weight: 700;
        font-size: 13px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .account-form-section-body { padding: 16px; display: none; }
    .account-form-section.open .account-form-section-body { display: block; }
    .account-form-section-header .chevron { transition: transform 0.2s; font-size: 11px; color: var(--text-muted); }
    .account-form-section.open .chevron { transform: rotate(180deg); }
    .account-form-row { display: flex; gap: 12px; margin-bottom: 12px; flex-wrap: wrap; }
    .account-form-row .form-group { flex: 1; min-width: 200px; }
    .account-form-row .form-group.small { flex: 0 0 160px; }
    .form-group label {
        display: block;
        font-size: 11px;
        font-weight: 600;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 4px;
    }
    .form-group input, .form-group select, .form-group textarea {
        width: 100%;
        padding: 8px 12px;
        border: 1px solid var(--card-border);
        border-radius: var(--radius-sm);
        font-size: 13px;
        font-family: inherit;
        color: var(--text-primary);
    }
    .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
        border-color: var(--accent);
        box-shadow: 0 0 0 3px var(--accent-soft);
        outline: none;
    }
    .form-group input.is-invalid,
    .form-group select.is-invalid,
    .form-group textarea.is-invalid {
        border-color: #dc3545 !important;
        box-shadow: 0 0 0 3px rgba(220, 53, 69, 0.1) !important;
    }
    select.is-invalid + .select2-container .select2-selection {
        border-color: #dc3545 !important;
        box-shadow: 0 0 0 3px rgba(220, 53, 69, 0.1) !important;
    }
    .account-map-toolbar {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 8px;
        flex-wrap: wrap;
    }
    .account-map-toolbar .map-coords {
        font-size: 11px;
        color: var(--text-muted);
        margin-left: auto;
    }
    .account-map {
        width: 100%;
        height: 280px;
        border-radius: var(--radius-sm);
        border: 1px solid var(--card-border);
        background: #f1f5f9;
        z-index: 1;
    }
    .account-map-status {
        font-size: 11px;
        color: var(--text-muted);
        margin: 6px 0;
        min-height: 16px;
    }
    .account-map-status.loading { color: #2563eb; }
    .account-map-status.error { color: #b91c1c; }
    .account-map-preview {
        display: none;
        border: 1px solid var(--accent-soft);
        background: #fff;
        border-radius: var(--radius-sm);
        padding: 10px 12px;
        margin-top: 8px;
    }
    .account-map-preview-title {
        font-weight: 700;
        font-size: 12px;
        color: var(--accent);
        margin-bottom: 4px;
        text-transform: uppercase;
        letter-spacing: 0.3px;
    }
    .account-map-preview-body {
        font-size: 12px;
        color: var(--text-primary);
        line-height: 1.5;
        margin-bottom: 8px;
        white-space: pre-line;
    }
    .account-map-preview-actions {
        display: flex;
        gap: 8px;
    }
</style>
@endsection

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-header-title">Account Management</h1>
        <p class="page-header-sub">Kelola data akun perusahaan</p>
    </div>
    @if($canCreate)
    <div class="page-header-actions">
        <button type="button" class="btn-accent" onclick="openCreateModal()">
            <i class="fa fa-plus"></i>
            <span>Add Account</span>
        </button>
    </div>
    @endif
</div>

<div class="card-custom fade-in">
    <div class="card-header-custom">
        <span><i class="fa fa-building me-2" style="color:var(--accent)"></i>Accounts List</span>
    </div>
    <div class="card-body-custom p-0">
        <div class="table-responsive">
        <table id="accounts-table" class="table table-custom align-middle mb-0" style="width:100%">
            <thead>
                <tr>
                    <th style="width:50px">#</th>
                    <th>Account Name</th>
                    <th>Phone</th>
                    <th>Owner</th>
                    <th class="text-center" style="width:120px">Action</th>
                </tr>
            </thead>
        </table>
        </div>
    </div>
</div>
@endsection

@push('modals')
<div class="modal fade modal-account" id="accountModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title" id="accountModalTitle">Add Account</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" style="max-height:70vh;overflow-y:auto;">
                <form id="account-form" autocomplete="off">
                    <input type="hidden" id="account-edit-id">

                    <div class="account-form-section open">
                        <div class="account-form-section-header" onclick="toggleAccountSection(this)">
                            <span><i class="fa fa-building me-2" style="color:var(--accent)"></i>Company Information</span>
                            <span class="chevron"><i class="fa fa-chevron-down"></i></span>
                        </div>
                        <div class="account-form-section-body">
                            <div class="account-form-row">
                                <div class="form-group">
                                    <label>Account Name <span class="text-danger">*</span></label>
                                    <input type="text" name="account_name" id="account-name" required>
                                </div>
                                <div class="form-group">
                                    <label>Field Type <span class="text-danger">*</span></label>
                                    <select name="types_accounts_companies_id" id="account-type">
                                        <option value="">— Pilih —</option>
                                        @foreach($typesAccountsCompanies as $tac)
                                        <option value="{{ $tac->id }}">{{ $tac->type_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Account Source <span class="text-danger">*</span></label>
                                    <select name="sources_id" id="account-source">
                                        <option value="">— Pilih —</option>
                                        @foreach($sources as $src)
                                        <option value="{{ $src->id }}">{{ $src->source_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="account-form-row">
                                <div class="form-group">
                                    <label>Website</label>
                                    <input type="text" name="website" id="account-website" placeholder="https://">
                                </div>
                                <div class="form-group">
                                    <label>Phone</label>
                                    <input type="text" name="phone" id="account-phone">
                                </div>
                            </div>
                            <div class="account-form-row">
                                <div class="form-group" style="flex:1 1 100%">
                                    <label>Description</label>
                                    <textarea name="description" id="account-description" rows="3"></textarea>
                                </div>
                            </div>


                            <div class="account-form-row">
                                <div class="form-group">
                                    <label>Segmentation <span class="text-danger">*</span></label>
                                    <select name="segmentation_id" id="account-segmentation">
                                        <option value="">— Pilih —</option>
                                        @foreach($segmentations as $seg)
                                        <option value="{{ $seg->id }}">{{ $seg->segmentation_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Business Entity <span class="text-danger">*</span></label>
                                    <select name="business_entities_id" id="account-biz-entity">
                                        <option value="">— Pilih —</option>
                                        @foreach($businessEntities as $be)
                                        <option value="{{ $be->id }}">{{ $be->entity_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="account-form-row">
                                <div class="form-group">
                                    <label>Business Value <span class="text-danger">*</span></label>
                                    <select name="business_values_id" id="account-biz-value">
                                        <option value="">— Pilih —</option>
                                        @foreach($businessValues as $bv)
                                        <option value="{{ $bv->id }}">{{ $bv->value_name }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="form-group" id="account-end-user-group" style="display:none">
                                    <label>End User <span class="text-danger" id="account-end-user-required" style="display:none">*</span></label>
                                    <select name="end_user" id="account-end-user">
                                        <option value="">— Pilih —</option>
                                        @foreach($accountCompanies as $ac)
                                        <option value="{{ $ac->id }}">{{ $ac->account_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group" id="account-parent-group">
                                    <label>Parent Account</label>
                                    <select name="parent_account_id" id="account-parent">
                                        <option value="">— Pilih —</option>
                                        @foreach($accountCompanies as $ac)
                                        <option value="{{ $ac->id }}">{{ $ac->account_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="account-form-row" style="display: none">
                                <div class="form-group">
                                    <label>Interaction Level <span class="text-danger">*</span></label>
                                    <select name="interaction_levels_id" id="account-interaction">
                                        <option value="">— Pilih —</option>
                                        @foreach($interactionLevels as $il)
                                        <option value="{{ $il->id }}">{{ $il->level_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="account-form-section">
                        <div class="account-form-section-header" onclick="toggleAccountSection(this)">
                            <span><i class="fa fa-file-invoice me-2" style="color:var(--accent)"></i>Billing Address</span>
                            <span class="chevron"><i class="fa fa-chevron-down"></i></span>
                        </div>
                        <div class="account-form-section-body">
                            <div class="account-map-toolbar">
                                <button type="button" class="btn btn-secondary btn-sm" onclick="accountMapPickLocation('billing')">
                                    <i class="fa fa-map-marker-alt me-1"></i> Pilih Titik di Peta
                                </button>
                                <button type="button" class="btn btn-secondary btn-sm" onclick="accountMapUseMyLocation('billing')">
                                    <i class="fa fa-location-crosshairs me-1"></i> Lokasi Saya
                                </button>
                                <span class="map-coords" id="account-map-coords">Belum ada titik.</span>
                            </div>
                            <div class="account-map" id="account-billing-map"></div>
                            <div class="account-map-status" id="account-map-status"></div>
                            <div class="account-map-preview" id="account-map-preview">
                                <div class="account-map-preview-title"><i class="fa fa-map-pin me-1"></i>Alamat dari Peta</div>
                                <div class="account-map-preview-body" id="account-map-preview-body"></div>
                                <div class="account-map-preview-actions">
                                    <button type="button" class="btn btn-primary btn-sm" id="account-map-apply" disabled onclick="accountMapApply('billing')">
                                        <i class="fa fa-check me-1"></i> Gunakan Alamat Ini
                                    </button>
                                </div>
                            </div>
                            <div class="account-form-row">
                                <div class="form-group">
                                    <label>Billing Street</label>
                                    <input type="text" name="address_billing_street" id="account-bill-street">
                                </div>
                            </div>
                            <div class="account-form-row">
                                <div class="form-group">
                                    <label>Billing City</label>
                                    <input type="text" name="address_billing_city" id="account-bill-city">
                                </div>
                                <div class="form-group">
                                    <label>Billing Province</label>
                                    <input type="text" name="address_billing_province" id="account-bill-province">
                                </div>
                                <div class="form-group small">
                                    <label>Billing Zip</label>
                                    <input type="text" name="address_billing_postal_code" id="account-bill-zip">
                                </div>
                                <div class="form-group">
                                    <label>Billing Country</label>
                                    <input type="text" name="address_billing_country" id="account-bill-country">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="account-form-section">
                        <div class="account-form-section-header" onclick="toggleAccountSection(this)">
                            <span><i class="fa fa-truck me-2" style="color:var(--accent)"></i>Shipping Address</span>
                            <span class="chevron"><i class="fa fa-chevron-down"></i></span>
                        </div>
                        <div class="account-form-section-body">
                            <div class="account-map-toolbar">
                                <button type="button" class="btn btn-secondary btn-sm" onclick="accountCopyBillingToShipping()">
                                    <i class="fa fa-copy me-1"></i> Same as billing address
                                </button>
                                <button type="button" class="btn btn-secondary btn-sm" onclick="accountMapPickLocation('shipping')">
                                    <i class="fa fa-map-marker-alt me-1"></i> Pilih Titik di Peta
                                </button>
                                <button type="button" class="btn btn-secondary btn-sm" onclick="accountMapUseMyLocation('shipping')">
                                    <i class="fa fa-location-crosshairs me-1"></i> Lokasi Saya
                                </button>
                                <span class="map-coords" id="account-ship-map-coords">Belum ada titik.</span>
                            </div>
                            <div class="account-map" id="account-shipping-map"></div>
                            <div class="account-map-status" id="account-ship-map-status"></div>
                            <div class="account-map-preview" id="account-ship-map-preview">
                                <div class="account-map-preview-title"><i class="fa fa-map-pin me-1"></i>Alamat dari Peta</div>
                                <div class="account-map-preview-body" id="account-ship-map-preview-body"></div>
                                <div class="account-map-preview-actions">
                                    <button type="button" class="btn btn-primary btn-sm" id="account-ship-map-apply" disabled onclick="accountMapApply('shipping')">
                                        <i class="fa fa-check me-1"></i> Gunakan Alamat Ini
                                    </button>
                                </div>
                            </div>
                            <div class="account-form-row">
                                <div class="form-group">
                                    <label>Shipping Street</label>
                                    <input type="text" name="address_shipping_street" id="account-ship-street">
                                </div>
                            </div>
                            <div class="account-form-row">
                                <div class="form-group">
                                    <label>Shipping City</label>
                                    <input type="text" name="address_shipping_city" id="account-ship-city">
                                </div>
                                <div class="form-group">
                                    <label>Shipping Province</label>
                                    <input type="text" name="address_shipping_province" id="account-ship-province">
                                </div>
                                <div class="form-group small">
                                    <label>Shipping Zip</label>
                                    <input type="text" name="address_shipping_postal_code" id="account-ship-zip">
                                </div>
                                <div class="form-group">
                                    <label>Shipping Country</label>
                                    <input type="text" name="address_shipping_country" id="account-ship-country">
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-danger btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary btn-sm" id="btn-save-account">
                    <i class="fa fa-save me-1"></i> Save
                </button>
            </div>
        </div>
    </div>
</div>
@endpush

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
let accountModalInstance = null;
let accountsTable = null;

const accountsCanUpdate = {{ $canUpdate ? 'true' : 'false' }};
const accountsCanDelete = {{ $canDelete ? 'true' : 'false' }};
const showUrl = '{{ route("accounts-management.show", "__ID__") }}';

// Billing Address dan Shipping Address masing-masing punya peta pilih-titik
// sendiri (independen) — dikonfigurasi lewat context supaya satu set fungsi
// generik (accountMapEnsure, accountMapPickLocation, dst) bisa dipakai untuk
// keduanya tanpa duplikasi kode.
const accountMapContexts = {
    billing: {
        mapId: 'account-billing-map',
        coordsId: 'account-map-coords',
        statusId: 'account-map-status',
        previewId: 'account-map-preview',
        previewBodyId: 'account-map-preview-body',
        applyBtnId: 'account-map-apply',
        fields: {
            street: 'account-bill-street',
            city: 'account-bill-city',
            province: 'account-bill-province',
            postal_code: 'account-bill-zip',
            country: 'account-bill-country',
        },
        map: null, marker: null, resolved: null, clickTimer: null,
    },
    shipping: {
        mapId: 'account-shipping-map',
        coordsId: 'account-ship-map-coords',
        statusId: 'account-ship-map-status',
        previewId: 'account-ship-map-preview',
        previewBodyId: 'account-ship-map-preview-body',
        applyBtnId: 'account-ship-map-apply',
        fields: {
            street: 'account-ship-street',
            city: 'account-ship-city',
            province: 'account-ship-province',
            postal_code: 'account-ship-zip',
            country: 'account-ship-country',
        },
        map: null, marker: null, resolved: null, clickTimer: null,
    },
};

function toggleAccountSection(header) {
    const section = header.closest('.account-form-section');
    section.classList.toggle('open');
    if (!section.classList.contains('open')) return;
    Object.keys(accountMapContexts).forEach(function(key) {
        if (section.querySelector('#' + accountMapContexts[key].mapId)) {
            accountMapEnsure(key);
            setTimeout(function() { invalidateAccountMap(key); }, 150);
        }
    });
}

// End User dan Parent Account saling eksklusif tergantung Segmentation:
// - Segmentation "Distributor/Partner" -> akun ini adalah distributor, jadi
//   yang relevan (dan wajib diisi) adalah End User (distributor menyebutkan
//   end user akhirnya); Parent Account disembunyikan & dikosongkan.
// - Segmentation lain -> yang relevan adalah Parent Account (opsional, boleh
//   tidak dipilih); End User disembunyikan & dikosongkan.
function accountSegmentationIsDistributor() {
    var text = $('#account-segmentation option:selected').text() || '';
    return text.toLowerCase().indexOf('distributor') !== -1;
}

function accountUpdateSegmentationDependentFields() {
    var isDistributor = accountSegmentationIsDistributor();

    var $endUserGroup = $('#account-end-user-group');
    var $endUserRequired = $('#account-end-user-required');
    var $parentGroup = $('#account-parent-group');

    if (isDistributor) {
        $endUserGroup.show();
        $endUserRequired.show();
        $parentGroup.hide();
        $('#account-parent').removeClass('is-invalid').val('').trigger('change');
    } else {
        $parentGroup.show();
        $endUserGroup.hide();
        $endUserRequired.hide();
        $('#account-end-user').removeClass('is-invalid').val('').trigger('change');
    }
}

function resetAccountForm() {
    document.getElementById('account-form').reset();
    document.getElementById('account-edit-id').value = '';
    document.querySelectorAll('.account-form-section').forEach(function(s) {
        s.classList.remove('open');
    });
    document.querySelector('.account-form-section').classList.add('open');
    $('#account-form .is-invalid').removeClass('is-invalid');
    $('#account-end-user').val('').trigger('change');
    $('#account-parent').val('').trigger('change');
    accountUpdateSegmentationDependentFields();
    accountMapResetAll();
}

function openCreateModal() {
    resetAccountForm();
    document.getElementById('accountModalTitle').textContent = 'Add Account';
    if (!accountModalInstance) {
        accountModalInstance = new bootstrap.Modal(document.getElementById('accountModal'));
    }
    accountModalInstance.show();
}

function openEditModal(id) {
    $.ajax({
        url: '{{ route("accounts-management.edit", "__ID__") }}'.replace('__ID__', id),
        type: 'GET',
        success: function(res) {
            resetAccountForm();
            document.getElementById('accountModalTitle').textContent = 'Edit Account';
            document.getElementById('account-edit-id').value = res.data.id;
            $('#account-name').val(res.data.account_name);
            $('#account-type').val(res.data.types_accounts_companies_id);
            $('#account-source').val(res.data.sources_id);
            $('#account-website').val(res.data.website);
            $('#account-description').val(res.data.description);
            $('#account-segmentation').val(res.data.segmentation_id);
            accountUpdateSegmentationDependentFields();
            $('#account-biz-entity').val(res.data.business_entities_id);
            $('#account-end-user').val(res.data.end_user).trigger('change');
            $('#account-parent').val(res.data.parent_account_id).trigger('change');
            $('#account-phone').val(res.data.phone);
            $('#account-biz-value').val(res.data.business_values_id);
            $('#account-interaction').val(res.data.interaction_levels_id);
            $('#account-bill-street').val(res.data.address_billing_street);
            $('#account-bill-city').val(res.data.address_billing_city);
            $('#account-bill-province').val(res.data.address_billing_province);
            $('#account-bill-zip').val(res.data.address_billing_postal_code);
            $('#account-bill-country').val(res.data.address_billing_country);
            $('#account-ship-street').val(res.data.address_shipping_street);
            $('#account-ship-city').val(res.data.address_shipping_city);
            $('#account-ship-province').val(res.data.address_shipping_province);
            $('#account-ship-zip').val(res.data.address_shipping_postal_code);
            $('#account-ship-country').val(res.data.address_shipping_country);
            if (!accountModalInstance) {
                accountModalInstance = new bootstrap.Modal(document.getElementById('accountModal'));
            }
            accountModalInstance.show();
        },
        error: function() {
            toastr.error('Gagal memuat data akun.');
        }
    });
}

// ── Peta alamat (Leaflet + OSM, sudah dimuat global di layout) ──
// Semua fungsi di bawah generik lewat parameter ctxKey ('billing'/'shipping'),
// lihat accountMapContexts di atas untuk konfigurasi/state per konteks.

function accountMapEnsure(ctxKey) {
    const ctx = accountMapContexts[ctxKey];
    if (ctx.map) {
        return;
    }
    ctx.map = L.map(ctx.mapId, {
        center: [-6.2088, 106.8456],
        zoom: 12,
        zoomControl: true,
    });
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap',
    }).addTo(ctx.map);
    ctx.map.on('click', function (e) {
        accountMapOnPick(ctxKey, e.latlng.lat, e.latlng.lng);
    });
}

function invalidateAccountMap(ctxKey) {
    const ctx = accountMapContexts[ctxKey];
    if (ctx.map) {
        ctx.map.invalidateSize();
    }
}

function accountMapReset(ctxKey) {
    const ctx = accountMapContexts[ctxKey];
    if (ctx.marker) {
        ctx.map.removeLayer(ctx.marker);
        ctx.marker = null;
    }
    ctx.resolved = null;
    if (ctx.clickTimer) {
        clearTimeout(ctx.clickTimer);
        ctx.clickTimer = null;
    }
    const coords = document.getElementById(ctx.coordsId);
    const status = document.getElementById(ctx.statusId);
    const preview = document.getElementById(ctx.previewId);
    if (coords) coords.textContent = 'Belum ada titik.';
    if (status) { status.textContent = ''; status.className = 'account-map-status'; }
    if (preview) preview.style.display = 'none';
    const applyBtn = document.getElementById(ctx.applyBtnId);
    if (applyBtn) applyBtn.disabled = true;
}

function accountMapResetAll() {
    Object.keys(accountMapContexts).forEach(accountMapReset);
}

function accountMapPickLocation(ctxKey) {
    const ctx = accountMapContexts[ctxKey];
    const section = document.querySelector('#' + ctx.mapId).closest('.account-form-section');
    if (section && !section.classList.contains('open')) {
        section.classList.add('open');
    }
    accountMapEnsure(ctxKey);
    setTimeout(function() { invalidateAccountMap(ctxKey); }, 150);
    toastr.info('Klik pada peta untuk memilih titik alamat.');
}

function accountMapUseMyLocation(ctxKey) {
    if (!navigator.geolocation) {
        toastr.error('Geolocation tidak didukung browser.');
        return;
    }
    const ctx = accountMapContexts[ctxKey];
    accountMapEnsure(ctxKey);
    setTimeout(function() { invalidateAccountMap(ctxKey); }, 150);
    const status = document.getElementById(ctx.statusId);
    status.className = 'account-map-status loading';
    status.textContent = 'Mendapatkan lokasi Anda...';
    navigator.geolocation.getCurrentPosition(
        function (pos) {
            const lat = pos.coords.latitude;
            const lng = pos.coords.longitude;
            ctx.map.setView([lat, lng], 15);
            accountMapOnPick(ctxKey, lat, lng);
        },
        function () {
            status.className = 'account-map-status error';
            status.textContent = 'Gagal mendapatkan lokasi. Berikan izin lalu coba lagi.';
        },
        { enableHighAccuracy: true, timeout: 10000 }
    );
}

function accountMapOnPick(ctxKey, lat, lng) {
    const ctx = accountMapContexts[ctxKey];
    if (ctx.marker) {
        ctx.map.removeLayer(ctx.marker);
    }
    ctx.marker = L.marker([lat, lng]).addTo(ctx.map);
    ctx.map.setView([lat, lng], 15);

    const coords = document.getElementById(ctx.coordsId);
    if (coords) coords.textContent = lat.toFixed(6) + ', ' + lng.toFixed(6);

    const status = document.getElementById(ctx.statusId);
    status.className = 'account-map-status loading';
    status.textContent = 'Mengambil alamat dari titik...';

    if (ctx.clickTimer) {
        clearTimeout(ctx.clickTimer);
    }
    ctx.clickTimer = setTimeout(function () {
        accountMapReverseGeocode(ctxKey, lat, lng);
    }, 600);
}

function accountMapReverseGeocode(ctxKey, lat, lng) {
    const ctx = accountMapContexts[ctxKey];
    const status = document.getElementById(ctx.statusId);
    const url = 'https://nominatim.openstreetmap.org/reverse?lat=' + lat + '&lon=' + lng + '&format=json&addressdetails=1&accept-language=id';
    fetch(url, { headers: { 'Accept': 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (!data || data.error) {
                throw new Error(data && data.error ? data.error : 'Alamat tidak ditemukan');
            }
            const addr = data.address || {};
            const street = [addr.road, addr.house_number].filter(Boolean).join(' ') || [addr.neighbourhood, addr.suburb, addr.pedestrian].filter(Boolean).join(', ') || '';
            const city = addr.city || addr.town || addr.village || addr.municipality || addr.county || '';
            const province = addr.state || addr.region || addr.county || '';
            const postal = addr.postcode || '';
            const country = addr.country || '';

            ctx.resolved = {
                street: street,
                city: city,
                province: province,
                postal_code: postal,
                country: country,
                display: [street, city, province, postal, country].filter(Boolean).join('\n'),
            };

            const preview = document.getElementById(ctx.previewId);
            const body = document.getElementById(ctx.previewBodyId);
            body.textContent = ctx.resolved.display || 'Alamat tidak lengkap.';
            preview.style.display = 'block';

            status.className = 'account-map-status';
            status.textContent = 'Alamat ditemukan. Klik "Gunakan Alamat Ini" untuk mengisi form.';

            const applyBtn = document.getElementById(ctx.applyBtnId);
            if (applyBtn) applyBtn.disabled = false;
        })
        .catch(function (err) {
            ctx.resolved = null;
            status.className = 'account-map-status error';
            status.textContent = 'Gagal mengambil alamat: ' + (err.message || 'coba lagi.');
            const preview = document.getElementById(ctx.previewId);
            if (preview) preview.style.display = 'none';
            const applyBtn = document.getElementById(ctx.applyBtnId);
            if (applyBtn) applyBtn.disabled = true;
        });
}

function accountMapApply(ctxKey) {
    const ctx = accountMapContexts[ctxKey];
    if (!ctx.resolved) {
        return;
    }
    const r = ctx.resolved;
    if (r.street) document.getElementById(ctx.fields.street).value = r.street;
    if (r.city) document.getElementById(ctx.fields.city).value = r.city;
    if (r.province) document.getElementById(ctx.fields.province).value = r.province;
    if (r.postal_code) document.getElementById(ctx.fields.postal_code).value = r.postal_code;
    if (r.country) document.getElementById(ctx.fields.country).value = r.country;
    toastr.success((ctxKey === 'billing' ? 'Alamat billing' : 'Alamat shipping') + ' diisi dari peta.');
}

function accountCopyBillingToShipping() {
    const fields = [
        ['account-bill-street', 'account-ship-street'],
        ['account-bill-city', 'account-ship-city'],
        ['account-bill-province', 'account-ship-province'],
        ['account-bill-zip', 'account-ship-zip'],
        ['account-bill-country', 'account-ship-country'],
    ];
    fields.forEach(function(pair) {
        const src = document.getElementById(pair[0]);
        const dst = document.getElementById(pair[1]);
        if (src && dst) dst.value = src.value;
    });
    toastr.success('Alamat billing disalin ke shipping.');
}

function initAccountsTable() {
    if (accountsTable) {
        accountsTable.destroy();
    }

    accountsTable = $('#accounts-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: '{{ route("accounts-management.data") }}',
        columns: [
            { data: 'DT_RowIndex', orderable: false, searchable: false, className: 'text-center' },
            {
                data: 'account_name', orderable: true, searchable: true,
                render: function(data, type, row) {
                    var avatar = row.icon
                        ? '<img src="' + row.icon + '" class="avatar-circle" alt="" style="background:transparent">'
                        : '<div class="avatar-circle">' + row.initials + '</div>';
                    return '<div style="display:flex;align-items:center;gap:10px">' +
                        avatar +
                        '<strong style="color:var(--text-primary);font-weight:600">' + row.name_display + '</strong>' +
                        '</div>';
                }
            },
            { data: 'phone' },
            { data: 'owner_name', orderable: false },
            {
                data: null,
                orderable: false,
                searchable: false,
                className: 'text-center',
                render: function(data, type, row) {
                    var html = '<div style="display:flex;gap:5px;justify-content:center">';
                    html += '<a href="' + showUrl.replace('__ID__', row.id) + '" class="btn-icon" title="Detail"><i class="fa fa-eye"></i></a>';
                    if (accountsCanUpdate) {
                        html += ' <button type="button" class="btn-icon" title="Edit" onclick="openEditModal(' + row.id + ')"><i class="fa fa-pen"></i></button>';
                    }
                    if (accountsCanDelete) {
                        html += ' <button type="button" class="btn-icon danger btn-delete-account" title="Hapus" data-id="' + row.id + '"><i class="fa fa-trash-can"></i></button>';
                    }
                    html += '</div>';
                    return html;
                }
            }
        ],
        order: [[1, 'asc']],
        pageLength: 10,
        lengthMenu: [10, 15, 25, 50, 100],
    });
}

$(document).on('click', '#btn-save-account', function() {
    const $btn = $(this);
    const editId = $('#account-edit-id').val();
    const isEdit = !!editId;

    $('#account-form .is-invalid').removeClass('is-invalid');

    const validations = [
        { field: '#account-name', label: 'Account Name' },
        { field: '#account-type', label: 'Field Type' },
        { field: '#account-source', label: 'Account Source' },
        { field: '#account-segmentation', label: 'Segmentation' },
        { field: '#account-biz-entity', label: 'Business Entity' },
        { field: '#account-biz-value', label: 'Business Value' },
        //{ field: '#account-interaction', label: 'Interaction Level' },
    ];

    if (accountSegmentationIsDistributor()) {
        validations.push({ field: '#account-end-user', label: 'End User' });
    }

    for (let i = 0; i < validations.length; i++) {
        const v = validations[i];
        const $el = $(v.field);
        const val = $el.val() ? $el.val().trim() : '';
        if (!val) {
            $el.addClass('is-invalid');
            const section = $el.closest('.account-form-section');
            if (section.length && !section.hasClass('open')) {
                section.addClass('open');
            }
            toastr.error(v.label + ' wajib diisi.');
            $el.focus();
            return;
        }
    }

    const formData = new FormData(document.getElementById('account-form'));

    const url = isEdit
        ? '{{ route("accounts-management.update", "__ID__") }}'.replace('__ID__', editId)
        : '{{ route("accounts-management.store") }}';

    formData.append('_token', '{{ csrf_token() }}');
    if (isEdit) formData.append('_method', 'PUT');

    Swal.fire({
        title: isEdit ? 'Update Account?' : 'Save Account?',
        text: isEdit ? 'Account data will be updated.' : 'A new account will be added.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: isEdit ? 'Yes, update!' : 'Yes, save!',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#2563eb',
        cancelButtonColor: '#64748b',
        reverseButtons: true,
    }).then(function(result) {
        if (!result.isConfirmed) return;

        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin me-1"></i> Saving...');

        $.ajax({
            url: url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(res) {
                toastr.success(res.message);
                if (accountModalInstance) accountModalInstance.hide();
                if (accountsTable) accountsTable.ajax.reload(null, false);
                $btn.prop('disabled', false).html('<i class="fa fa-save me-1"></i> Save');
            },
            error: function(xhr) {
                $btn.prop('disabled', false).html('<i class="fa fa-save me-1"></i> Save');
                var errors = xhr.responseJSON?.errors;
                if (errors) {
                    var first = Object.values(errors)[0];
                    toastr.error(Array.isArray(first) ? first[0] : first);
                } else {
                    toastr.error(xhr.responseJSON?.message || 'Failed to save data.');
                }
            }
        });
    });
});

$(document).on('change input', '#account-form input.is-invalid, #account-form select.is-invalid', function() {
    $(this).removeClass('is-invalid');
});

$(document).on('change', '#account-end-user', function() {
    $(this).removeClass('is-invalid');
});

$(document).on('change', '#account-segmentation', accountUpdateSegmentationDependentFields);

$(document).on('change', '#account-parent', function() {
    $(this).removeClass('is-invalid');
});

$(document).on('shown.bs.modal', '#accountModal', function() {
    if (!$('#account-end-user').hasClass('select2-hidden-accessible')) {
        $('#account-end-user').select2({
            theme: 'bootstrap-5',
            placeholder: '— Pilih —',
            allowClear: true,
            width: '100%',
            dropdownParent: $('#accountModal')
        });
    }
    if (!$('#account-parent').hasClass('select2-hidden-accessible')) {
        $('#account-parent').select2({
            theme: 'bootstrap-5',
            placeholder: '— Pilih —',
            allowClear: true,
            width: '100%',
            dropdownParent: $('#accountModal')
        });
    }
    Object.keys(accountMapContexts).forEach(function(key) {
        accountMapEnsure(key);
        setTimeout(function() { invalidateAccountMap(key); }, 200);
    });
});

$(document).on('click', '.btn-delete-account', function() {
    const id = $(this).data('id');
    Swal.fire({
        title: 'Sure to delete this account?',
        text: 'This action cannot be undone.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, delete it!',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#64748b',
        reverseButtons: true,
    }).then(function(result) {
        if (result.isConfirmed) {
            $.ajax({
                url: '{{ route("accounts-management.destroy", "__ID__") }}'.replace('__ID__', id),
                type: 'DELETE',
                data: { _token: '{{ csrf_token() }}' },
                success: function(res) {
                    toastr.success(res.message);
                    if (accountsTable) accountsTable.ajax.reload(null, false);
                },
                error: function() {
                    toastr.error('Failed to delete data.');
                }
            });
        }
    });
});

initAccountsTable();
</script>
@endsection
