<?php

namespace App\Http\Controllers;

use App\Models\Scenario;
use App\Models\ScenarioSupportingDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Supporting documents / evidence for a scenario. A document is either a
 * REFERENCE (URL / citation) or an uploaded FILE, each with a comment. Seeded
 * defaults carry the sources behind the scenario's shock magnitudes; users add
 * more here.
 */
class ScenarioSupportingDocumentController extends Controller
{
    private const DISK = 'uploads';

    /** List a scenario's supporting documents (defaults first, then newest). */
    public function index(Scenario $scenario)
    {
        return response()->json(
            $scenario->supportingDocuments()
                ->with('uploader:id,name')
                ->orderByDesc('is_default')->orderByDesc('id')
                ->get()
        );
    }

    /**
     * Add a reference (title + reference + comment) OR upload a file (file +
     * comment). One request does one or the other.
     */
    public function store(Request $request, Scenario $scenario)
    {
        $data = $request->validate([
            'title'     => ['required_without:file', 'nullable', 'string', 'max:255'],
            // Block dangerous URI schemes so a stored reference can never become a
            // script trigger when rendered as a link (defence-in-depth with the
            // front-end http(s)-only guard). Plain-text citations still pass.
            'reference' => ['nullable', 'string', 'max:1000', 'not_regex:/^\s*(?:javascript|data|vbscript|file):/i'],
            'comment'   => ['nullable', 'string', 'max:5000'],
            'file'      => ['nullable', 'file', 'max:20480', // 20 MB
                'mimes:pdf,doc,docx,xls,xlsx,csv,png,jpg,jpeg,txt'],
        ]);

        $doc = new ScenarioSupportingDocument([
            'scenario_id' => $scenario->id,
            'comment'     => $data['comment'] ?? null,
            'is_default'  => false,
            'uploaded_by' => $request->user()?->id,
        ]);

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $doc->kind              = 'file';
            $doc->stored_path       = $file->store('scenario-evidence/' . $scenario->id, self::DISK);
            $doc->original_filename = $file->getClientOriginalName();
            $doc->mime_type         = $file->getClientMimeType();
            $doc->file_size_bytes   = $file->getSize();
            $doc->title             = $data['title'] ?? $file->getClientOriginalName();
        } else {
            $doc->kind      = 'reference';
            $doc->reference = $data['reference'] ?? null;
            $doc->title     = $data['title'];
        }

        $doc->save();

        return response()->json($doc->fresh('uploader:id,name'), 201);
    }

    /** Download an uploaded document. */
    public function download(ScenarioSupportingDocument $document)
    {
        abort_unless($document->kind === 'file' && $document->stored_path
            && Storage::disk(self::DISK)->exists($document->stored_path), 404);

        return Storage::disk(self::DISK)->download(
            $document->stored_path, $document->original_filename
        );
    }

    /** Remove a document (and its file). Seeded defaults are protected. */
    public function destroy(ScenarioSupportingDocument $document)
    {
        abort_if($document->is_default, 403, 'Seeded default evidence cannot be deleted; edit its comment instead.');

        if ($document->kind === 'file' && $document->stored_path) {
            Storage::disk(self::DISK)->delete($document->stored_path);
        }
        $document->delete();

        return response()->json(['deleted' => true]);
    }

    /** Edit the comment on any document (incl. a seeded default). */
    public function updateComment(Request $request, ScenarioSupportingDocument $document)
    {
        $data = $request->validate(['comment' => ['nullable', 'string', 'max:5000']]);
        $document->update(['comment' => $data['comment'] ?? null]);

        return response()->json($document);
    }
}
