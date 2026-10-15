<?php

namespace App\Services\Eir;

use App\Models\GovernanceSetting;
use App\Models\SupportingDocument;
use App\Services\AuditLoggerService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use LogicException;

/**
 * Supporting documents of a governed change (Staging Module Specification v1,
 * section 8.3): a board or committee minute, a policy paper, an analysis, the
 * comparison-run export, correspondence. Stored on the private disk (never the
 * public web folder), fingerprinted with SHA-256 on upload and checked on
 * every opening, so a file changed on disk is reported, not served as if it
 * were the original. A document of a decided change is never deleted: removing
 * one marks it removed and keeps the file. Every addition, removal and opening
 * of an altered file is audit-logged.
 */
class GovernanceDocumentService
{
    public const DISK = 'local';
    public const FOLDER = 'governance-documents';
    public const TYPES = ['minute', 'policy', 'analysis', 'correspondence', 'other'];
    public const EXTENSIONS = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'png', 'jpg', 'jpeg'];
    public const MAX_BYTES = 20 * 1024 * 1024;

    public function attach(GovernanceSetting $setting, UploadedFile $file, string $type, ?string $description, int $userId): SupportingDocument
    {
        if (! in_array($type, self::TYPES, true)) {
            throw new InvalidArgumentException('The document type is one of: ' . implode(', ', self::TYPES) . '.');
        }
        $ext = strtolower($file->getClientOriginalExtension());
        if (! in_array($ext, self::EXTENSIONS, true)) {
            throw new InvalidArgumentException('A supporting document is a PDF, Word, Excel, CSV, PNG or JPEG file.');
        }
        if ($file->getSize() > self::MAX_BYTES) {
            throw new InvalidArgumentException('A supporting document is at most 20 MB.');
        }
        if (! in_array($setting->status, [GovernanceSetting::STATUS_PROPOSED], true)) {
            throw new LogicException('Documents are attached while the change is proposed; a decided change keeps the documents it was decided on.');
        }
        $hash = hash_file('sha256', $file->getRealPath());
        $path = $file->storeAs(self::FOLDER . '/' . $setting->id, $hash . '.' . $ext, self::DISK);
        $doc = SupportingDocument::create([
            'documentable_type' => GovernanceSetting::class, 'documentable_id' => $setting->id, 'disk' => self::DISK, 'path' => $path,
            'original_name' => mb_substr($file->getClientOriginalName(), 0, 255), 'mime_type' => $file->getClientMimeType(), 'size' => $file->getSize(),
            'extension' => $ext, 'document_type' => $type, 'description' => $description === null ? null : mb_substr($description, 0, 255), 'hash' => $hash, 'uploaded_by' => $userId,
        ]);
        AuditLoggerService::log('Governance Document Added', GovernanceSetting::class, $setting->id, [
            'new_values' => ['document' => $doc->id, 'name' => $doc->original_name, 'type' => $type, 'size' => $doc->size, 'sha256' => $hash, 'setting' => $setting->key],
            'meta' => ['user' => $userId],
        ]);

        return $doc;
    }

    /** Remove a document from a proposal: it is kept, marked removed, with who and why. */
    public function remove(SupportingDocument $doc, int $userId, string $reason): void
    {
        $setting = GovernanceSetting::findOrFail($doc->documentable_id);
        if ($setting->status !== GovernanceSetting::STATUS_PROPOSED) {
            throw new LogicException('A decided change keeps the documents it was decided on; none can be removed.');
        }
        if (mb_strlen(trim($reason)) < 10) {
            throw new InvalidArgumentException('Removing a document needs a reason of at least 10 characters.');
        }
        $doc->delete();
        AuditLoggerService::log('Governance Document Removed', GovernanceSetting::class, $setting->id, [
            'new_values' => ['document' => $doc->id, 'name' => $doc->original_name, 'sha256' => $doc->hash, 'reason' => trim($reason)],
            'meta' => ['user' => $userId],
        ]);
    }

    /**
     * The file, checked against its fingerprint.
     *
     * @return array{path:string,name:string,mime:?string,intact:bool}
     */
    public function open(SupportingDocument $doc, ?int $userId): array
    {
        $disk = Storage::disk($doc->disk);
        if (! $disk->exists($doc->path)) {
            throw new LogicException("The file of document {$doc->id} ({$doc->original_name}) is missing from storage.");
        }
        $intact = hash_file('sha256', $disk->path($doc->path)) === $doc->hash;
        if (! $intact) {
            AuditLoggerService::log('Governance Document Altered', GovernanceSetting::class, (int) $doc->documentable_id, [
                'new_values' => ['document' => $doc->id, 'name' => $doc->original_name, 'recorded_sha256' => $doc->hash, 'found_sha256' => hash_file('sha256', $disk->path($doc->path))],
                'meta' => ['user' => $userId],
            ]);
        }

        return ['path' => $disk->path($doc->path), 'name' => $doc->original_name, 'mime' => $doc->mime_type, 'intact' => $intact];
    }
}
