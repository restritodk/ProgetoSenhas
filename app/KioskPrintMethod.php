<?php

namespace App;

enum KioskPrintMethod: string
{
    case Browser = 'browser';
    case Agent = 'agent';

    public function label(): string
    {
        return match ($this) {
            self::Browser => 'Navegador',
            self::Agent => 'Humana Print Agent',
        };
    }

    public static function normalize(mixed $value): self
    {
        if ($value instanceof self) {
            return $value;
        }

        $raw = strtolower(trim(is_string($value) ? $value : (string) $value));

        return self::tryFrom($raw) ?? self::Browser;
    }
}
