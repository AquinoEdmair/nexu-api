<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

final class TeamMember extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'title',
        'bio',
        'photo_path',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active'  => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function getPhotoUrlAttribute(): ?string
    {
        if (! $this->photo_path) {
            return null;
        }

        $url = Storage::disk('public')->url($this->photo_path);

        // Normalize double slashes that occur when APP_URL has a trailing slash
        return preg_replace('#([^:])//+#', '$1/', $url);
    }
}
