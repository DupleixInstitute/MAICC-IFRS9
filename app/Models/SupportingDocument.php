<?php

// app/Models/SupportingDocument.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SupportingDocument extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'documentable_type',
        'documentable_id',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size',
        'extension',
        'document_type',
        'description',
        'hash',
        'uploaded_by',
    ];

    protected $casts = [
        'size' => 'integer',
    ];

    /**
     * Get the parent model (LGD, Customer, etc.)
     */
    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the user who uploaded the document
     */
    public function uploadedBy()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Get the file URL for display/download
     */
    public function getUrlAttribute()
    {
        return Storage::disk($this->disk)->url($this->path);
    }

    /**
     * Get the file download URL (with signed URL if private)
     */
    public function getDownloadUrlAttribute()
    {
        if ($this->disk === 'private' || $this->disk === 's3_private') {
            return Storage::disk($this->disk)->temporaryUrl(
                $this->path,
                now()->addMinutes(30)
            );
        }
        
        return Storage::disk($this->disk)->url($this->path);
    }

    /**
     * Check if file exists in storage
     */
    public function fileExists(): bool
    {
        return Storage::disk($this->disk)->exists($this->path);
    }
}
