<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Document;
use App\Models\Log;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Upload / lihat / hapus dokumen arsip pembelian. Controller pemakai
 * menyediakan pemilik dokumen (model ber-HasPurchaseDocuments) dan prefix
 * route ("{prefix}.documents.store|show|destroy"). Hak akses lewat middleware:
 * POST -> can_create, GET -> can_read, DELETE -> can_delete modul tsb.
 */
trait ManagesPurchaseDocuments
{
    abstract protected function documentOwner($id): Model;

    abstract protected function documentRoutePrefix(): string;

    abstract protected function documentModuleCode(): string;

    public function uploadDocument(Request $request, $id): JsonResponse
    {
        $owner = $this->documentOwner($id);

        $validated = $request->validate([
            'type' => 'required|in:'.implode(',', array_keys($owner->documentTypes())),
            'files' => 'required|array|min:1|max:'.Document::MAX_FILES,
            'files.*' => Document::VALIDATION_RULE,
            'notes' => 'nullable|string|max:255',
        ], [
            'type.required' => 'Pilih jenis dokumen.',
            'files.required' => 'Pilih minimal satu file.',
            'files.max' => 'Maksimal '.Document::MAX_FILES.' file sekali upload.',
            'files.*.mimes' => 'Dokumen harus berformat PDF, JPG, atau PNG.',
            'files.*.max' => 'Ukuran setiap file tidak boleh lebih dari 10MB.',
        ]);

        $documents = DB::transaction(fn () => collect($request->file('files'))
            ->map(fn ($file) => Document::storeFor($owner, $file, $validated['type'], $validated['notes'] ?? null)));

        Log::record(
            'upload_purchase_document',
            $documents->count().' dokumen '.$owner->documentTypes()[$validated['type']].' diunggah oleh '.Auth::user()->display_name,
            $this->documentModuleCode(),
            $owner
        );

        return response()->json([
            'success' => true,
            'message' => $documents->count().' dokumen berhasil diupload.',
            'documents' => $this->documentList($owner),
        ]);
    }

    public function viewDocument($id, $documentId)
    {
        $owner = $this->documentOwner($id);
        $document = Document::findOrFail($documentId);
        abort_unless($owner->ownsDocument($document), 404);

        return $document->inlineResponse();
    }

    public function deleteDocument($id, $documentId): JsonResponse
    {
        $owner = $this->documentOwner($id);
        $document = Document::findOrFail($documentId);
        abort_unless($owner->ownsDocument($document), 404);

        if (! $owner->canDeleteDocument($document, Auth::user())) {
            return response()->json(['success' => false, 'message' => 'Dokumen ini sudah menjadi arsip dan tidak bisa dihapus.'], 422);
        }

        $document->delete();

        return response()->json([
            'success' => true,
            'message' => 'Dokumen dihapus.',
            'documents' => $this->documentList($owner),
        ]);
    }

    /**
     * Daftar dokumen untuk UI (partial purchase-documents).
     */
    protected function documentList(Model $owner): array
    {
        $prefix = $this->documentRoutePrefix();
        $user = Auth::user();

        return $owner->documents()->with('uploader')->get()
            ->map(fn (Document $doc) => $doc->toListItem(
                route($prefix.'.documents.show', [$owner->getKey(), $doc->id]),
                $owner->canDeleteDocument($doc, $user) ? route($prefix.'.documents.destroy', [$owner->getKey(), $doc->id]) : null
            ))
            ->values()
            ->all();
    }
}
