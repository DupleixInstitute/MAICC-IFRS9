<?php

namespace App\Services;

use App\Models\SupportingDocument;
use App\Services\Eir\GovernanceDocumentService;
use App\Support\Approvals;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use LogicException;

/**
 * Supporting documents on the items of the approval workflows (Staging Module
 * Specification v1, sections 8.3 and 8.6), by the same rules as the Governance
 * Centre's (GovernanceDocumentService): the private disk, a SHA-256
 * fingerprint recorded on upload and checked on every opening, PDF, Word,
 * Excel, CSV, PNG or JPEG up to 20 MB, never deleted (a removed document is
 * kept, marked removed), each step audit-logged under the item.
 */
class ApprovalDocumentService
{
    public const FOLDER = 'approval-documents';

    public function attach(string $workflow, int $id, UploadedFile $file, string $type, ?string $description, int $userId): SupportingDocument
    {
        $w = Approvals::workflow($workflow);
        $row = Approvals::record($workflow, $id);
        if (! in_array($type, GovernanceDocumentService::TYPES, true)) {
            throw new InvalidArgumentException('The document type is one of: ' . implode(', ', GovernanceDocumentService::TYPES) . '.');
        }
        $ext = strtolower($file->getClientOriginalExtension());
        if (! in_array($ext, GovernanceDocumentService::EXTENSIONS, true)) {
            throw new InvalidArgumentException('A supporting document is a PDF, Word, Excel, CSV, PNG or JPEG file.');
        }
        if ($file->getSize() > GovernanceDocumentService::MAX_BYTES) {
            throw new InvalidArgumentException('A supporting document is at most 20 MB.');
        }
        if (! Approvals::pending($workflow, $row)) {
            throw new LogicException("Documents are attached while {$w['label']} {$id} waits for a decision; a decided item keeps the documents it was decided on.");
        }
        $hash = hash_file('sha256', $file->getRealPath());
        $path = $file->storeAs(self::FOLDER . '/' . $w['table'] . '/' . $id, $hash . '.' . $ext, GovernanceDocumentService::DISK);
        $doc = SupportingDocument::create([
            'documentable_type' => $w['table'], 'documentable_id' => $id, 'disk' => GovernanceDocumentService::DISK, 'path' => $path,
            'original_name' => mb_substr($file->getClientOriginalName(), 0, 255), 'mime_type' => $file->getClientMimeType(), 'size' => $file->getSize(),
            'extension' => $ext, 'document_type' => $type, 'description' => $description === null ? null : mb_substr($description, 0, 255), 'hash' => $hash, 'uploaded_by' => $userId,
        ]);
        AuditLoggerService::log('Approval Document Added', $w['table'], $id, [
            'new_values' => ['workflow' => $workflow, 'document' => $doc->id, 'name' => $doc->original_name, 'type' => $type, 'size' => $doc->size, 'sha256' => $hash],
            'meta' => ['user' => $userId],
        ]);

        return $doc;
    }

    public function remove(SupportingDocument $doc, int $userId, string $reason): void
    {
        $workflow = $this->workflowOf($doc);
        if (! Approvals::pending($workflow, Approvals::record($workflow, (int) $doc->documentable_id))) {
            throw new LogicException('A decided item keeps the documents it was decided on; none can be removed.');
        }
        if (mb_strlen(trim($reason)) < 10) {
            throw new InvalidArgumentException('Removing a document needs a reason of at least 10 characters.');
        }
        $doc->delete();
        AuditLoggerService::log('Approval Document Removed', $doc->documentable_type, (int) $doc->documentable_id, [
            'new_values' => ['workflow' => $workflow, 'document' => $doc->id, 'name' => $doc->original_name, 'sha256' => $doc->hash, 'reason' => trim($reason)],
            'meta' => ['user' => $userId],
        ]);
    }

    /** @return array{path:string,name:string,mime:?string,intact:bool} */
    public function open(SupportingDocument $doc, ?int $userId): array
    {
        $this->workflowOf($doc);
        $disk = Storage::disk($doc->disk);
        if (! $disk->exists($doc->path)) {
            throw new LogicException("The file of document {$doc->id} ({$doc->original_name}) is missing from storage.");
        }
        $found = hash_file('sha256', $disk->path($doc->path));
        if ($found !== $doc->hash) {
            AuditLoggerService::log('Approval Document Altered', $doc->documentable_type, (int) $doc->documentable_id, [
                'new_values' => ['document' => $doc->id, 'name' => $doc->original_name, 'recorded_sha256' => $doc->hash, 'found_sha256' => $found],
                'meta' => ['user' => $userId],
            ]);
        }

        return ['path' => $disk->path($doc->path), 'name' => $doc->original_name, 'mime' => $doc->mime_type, 'intact' => $found === $doc->hash];
    }

    /** The workflow a document belongs to, from its item's table. */
    public function workflowOf(SupportingDocument $doc): string
    {
        foreach (Approvals::WORKFLOWS as $key => $w) {
            if ($w['table'] === $doc->documentable_type) {
                return $key;
            }
        }
        throw new InvalidArgumentException("Document {$doc->id} does not belong to an approval workflow.");
    }
}
