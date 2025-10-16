<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class File extends Model
{
    use HasFactory;

    protected $appends = [
        'time_ago',
        'file_size_in_units',
        'download_link',
    ];

    public function getTimeAgoAttribute()
    {
        return $this->created_at->diffForHumans();
    }

    public function getFileSizeInUnitsAttribute()
    {
        return $this->formatSizeUnits($this->file_size);
    }

    public function getDownloadLinkAttribute()
    {
        return Storage::disk($this->disk ?: 'public')->url($this->link);
    }

    public function formatSizeUnits($bytes)
    {
        if ($bytes >= 1073741824) {
            $bytes = number_format($bytes / 1073741824, 2) . ' GB';
        } elseif
        ($bytes >= 1048576) {
            $bytes = number_format($bytes / 1048576, 2) . ' MB';
        } elseif
        ($bytes >= 1024) {
            $bytes = number_format($bytes / 1024, 2) . ' KB';
        } elseif
        ($bytes > 1) {
            $bytes .= ' bytes';
        } elseif
        ($bytes == 1) {
            $bytes .= ' byte';
        } else {
            $bytes = '0 bytes';
        }

        return $bytes;
    }
}
