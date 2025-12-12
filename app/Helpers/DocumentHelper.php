<?php
namespace App\Helpers;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\SupportingDocument;

class DocumentHelper
{
    public static function upload(UploadedFile $file, $model, $type = null, $description = null)
    {
        $modelName = class_basename($model);
        $folder = Str::snake(Str::plural($modelName)) . '/' . $model->id . '/' . date('Y/m');
        
        $originalName = $file->getClientOriginalName();
        $extension = $file->getClientOriginalExtension();
        $safeName = Str::slug(pathinfo($originalName, PATHINFO_FILENAME));
        $filename = $safeName . '-' . Str::random(8) . '.' . $extension;
        
        $path = $file->storeAs($folder, $filename, config('filesystems.default'));
        
        $documentClass = config('document.model', SupportingDocument::class);
        
        return $documentClass::create([
            'documentable_type' => get_class($model),
            'documentable_id' => $model->id,
            'disk' => config('filesystems.default'),
            'path' => $path,
            'original_name' => $originalName,
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'extension' => $extension,
            'document_type' => $type,
            'description' => $description,
            'hash' => hash_file('sha256', $file->getRealPath()),
            'uploaded_by' => auth()->id(),
        ]);
    }
}