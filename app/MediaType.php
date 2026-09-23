<?php

namespace App;

enum MediaType: string
{
    case IMAGE = 'image';
    case VIDEO = 'video';
    case YOUTUBE = 'youtube';

    public function label(): string
    {
        return match ($this) {
            self::IMAGE => 'Imagem',
            self::VIDEO => 'Vídeo',
            self::YOUTUBE => 'YouTube',
        };
    }

    public function isUploadable(): bool
    {
        return $this === self::IMAGE || $this === self::VIDEO;
    }

    public function usesDuration(): bool
    {
        return $this === self::IMAGE;
    }
}
