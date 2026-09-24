<?php

namespace App\Support;

/**
 * Detects embedded Smart TV browsers (e.g. Samsung Tizen) from a User-Agent.
 *
 * Must stay aligned with window.__humanaTvCallAudio.isSmartTvBrowser() in tv-display.
 * Intentionally does NOT treat mobile SamsungBrowser as a Smart TV.
 */
final class SmartTvBrowser
{
    public static function matches(?string $userAgent): bool
    {
        $ua = trim((string) $userAgent);
        if ($ua === '') {
            return false;
        }

        // Strong Smart TV platform markers.
        if (preg_match('/\bTizen\b/i', $ua) === 1) {
            return true;
        }

        if (preg_match('/SMART[\s_-]?TV/i', $ua) === 1) {
            return true;
        }

        if (preg_match('/\bSmartTV\b/i', $ua) === 1) {
            return true;
        }

        if (preg_match('/\bHbbTV\b/i', $ua) === 1) {
            return true;
        }

        // LG webOS TV (defensive).
        if (preg_match('/\bWeb0S\b/i', $ua) === 1 || preg_match('/\bwebOS\b.*\bTV\b/i', $ua) === 1) {
            return true;
        }

        // SamsungBrowser on TV only when combined with TV markers (not phones).
        if (preg_match('/SamsungBrowser/i', $ua) === 1) {
            if (preg_match('/\b(TV|Tizen|SMART[\s_-]?TV|SmartTV)\b/i', $ua) === 1) {
                return true;
            }

            // Mobile SamsungBrowser typically includes Android + device model, not TV OS.
            return false;
        }

        return false;
    }
}
