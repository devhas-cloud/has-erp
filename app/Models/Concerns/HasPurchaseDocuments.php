<?php

namespace App\Models\Concerns;

use App\Models\Document;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Arsip dokumen pembelian (opsional) pada PO, Direct Purchase, dan Penerimaan
 * Barang. Model pemakai menentukan jenis dokumen yang boleh diunggah dan kapan
 * dokumen boleh dihapus; menambah dokumen selalu boleh (arsip hanya bertambah).
 */
trait HasPurchaseDocuments
{
    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable')->orderBy('id');
    }

    /**
     * Jenis dokumen yang boleh diunggah: [type => label].
     *
     * @return array<string, string>
     */
    abstract public function documentTypes(): array;

    abstract public function canDeleteDocument(Document $document, User $user): bool;

    public function ownsDocument(Document $document): bool
    {
        return $document->documentable_type === $this->getMorphClass()
            && (int) $document->documentable_id === (int) $this->getKey();
    }

    protected static function purchaseDocumentTypes(array $types): array
    {
        return array_intersect_key(Document::PURCHASE_TYPE_LABELS, array_flip($types));
    }
}
