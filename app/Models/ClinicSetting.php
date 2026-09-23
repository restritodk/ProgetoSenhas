<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClinicSetting extends Model
{
    public const DISK = 'public';

    public const LOGO_DIRECTORY = 'clinic-branding';

    public const LOGO_MAX_KILOBYTES = 2048;

    /** @var list<string> */
    public const LOGO_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
    ];

    /** @var list<string> */
    public const LOGO_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

    protected $fillable = [
        'clinic_id',
        'key',
        'value',
        'type',
    ];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }
}
