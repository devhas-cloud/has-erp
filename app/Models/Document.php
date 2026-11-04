<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * Dokumen lampiran: kontrak/perjanjian Project (TYPE_AGREEMENT), dokumen
 * pendukung termin penagihan (TYPE_SUPPORT), dan arsip pembelian
 * (PURCHASE_TYPE_LABELS — PO, Direct Purchase, Penerimaan Barang). Disimpan di disk private; dibuka
 * hanya lewat route project/invoice yang mengecek hak akses.
 */
class Document extends Model
{
    public const DISK = 'local';

    public const TYPE_AGREEMENT = 'agreement';

    public const TYPE_SUPPORT = 'support';

    // Arsip pembelian (PO, Direct Purchase, Penerimaan Barang)
    public const TYPE_SUPPLIER_QUOTE = 'supplier_quote';

    public const TYPE_SUPPLIER_CONFIRMATION = 'supplier_confirmation';

    public const TYPE_DELIVERY_NOTE = 'delivery_note';

    public const TYPE_SUPPLIER_INVOICE = 'supplier_invoice';

    public const TYPE_TAX_INVOICE = 'tax_invoice';

    public const TYPE_PAYMENT_PROOF = 'payment_proof';

    public const TYPE_OTHER = 'other';

    public const PURCHASE_TYPE_LABELS = [
        self::TYPE_SUPPLIER_QUOTE => 'Penawaran Supplier',
        self::TYPE_SUPPLIER_CONFIRMATION => 'PO Bertanda Tangan / Konfirmasi Supplier',
        self::TYPE_DELIVERY_NOTE => 'Surat Jalan Supplier',
        self::TYPE_SUPPLIER_INVOICE => 'Invoice / Nota Supplier',
        self::TYPE_TAX_INVOICE => 'Faktur Pajak',
        self::TYPE_PAYMENT_PROOF => 'Bukti Transfer / Pembayaran',
        self::TYPE_OTHER => 'Lain-lain',
    ];

    public const MAX_FILES = 10;

    public const VALIDATION_RULE = 'file|mimes:pdf,jpg,jpeg,png|max:10240';

    protected $fillable = [
        'documentable_type',
        'documentable_id',
        'type',
        'file_path',
        'file_name',
        'mime',
        'size',
        'notes',
        'uploaded_by',
    ];

    protected static function booted(): void
    {
        static::deleted(function (Document $document) {
            Storage::disk(self::DISK)->delete($document->file_path);
        });
    }

    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public static function storeFor(Model $owner, UploadedFile $file, string $type, ?string $notes = null): self
    {
        $path = $file->store('documents/'.now()->format('Y/m'), self::DISK);

        return $owner->documents()->create([
            'type' => $type,
            'file_path' => $path,
            'file_name' => $file->getClientOriginalName(),
            'mime' => $file->getClientMimeType(),
            'size' => $file->getSize(),
            'notes' => $notes !== null && trim($notes) !== '' ? trim($notes) : null,
            'uploaded_by' => Auth::id(),
        ]);
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime, 'image/');
    }

    public function iconClass(): string
    {
        return $this->isImage() ? 'fa-file-image' : 'fa-file-pdf';
    }

    public function sizeLabel(): string
    {
        $kb = $this->size / 1024;

        return $kb >= 1024 ? number_format($kb / 1024, 1).' MB' : number_format(max($kb, 1), 0).' KB';
    }

    public function inlineResponse()
    {
        abort_unless(Storage::disk(self::DISK)->exists($this->file_path), 404, 'File tidak ditemukan.');

        return Storage::disk(self::DISK)->response($this->file_path, $this->file_name, [
            'Content-Disposition' => 'inline; filename="'.addcslashes($this->file_name, '"\\').'"',
        ]);
    }

    /**
     * Data ringkas untuk UI (daftar dokumen di modal/tabel).
     */
    public function toListItem(string $viewUrl, ?string $deleteUrl): array
    {
        return [
            'id' => $this->id,
            'file_name' => $this->file_name,
            'type' => $this->type,
            'type_label' => self::PURCHASE_TYPE_LABELS[$this->type] ?? null,
            'notes' => $this->notes,
            'size' => $this->sizeLabel(),
            'icon' => $this->iconClass(),
            'uploaded_by' => $this->uploader?->display_name,
            'uploaded_at' => $this->created_at?->format('d M Y H:i'),
            'view_url' => $viewUrl,
            'delete_url' => $deleteUrl,
        ];
    }
}
