<?php

return [

    /*
    |--------------------------------------------------------------------------
    | TV TTS fallback (server-side)
    |--------------------------------------------------------------------------
    |
    | Used when the TV browser cannot play speechSynthesis (common on Samsung
    | Tizen). Chrome/Edge keep using the native Web Speech API when the probe
    | succeeds — this driver is only a fallback audio stream.
    |
    | Driver "espeak" requires the espeak-ng binary on the host (not bundled).
    | Install on Ubuntu 24.04: sudo apt-get install -y espeak-ng
    | Do not enable until the binary is present; the app degrades gracefully.
    |
    */

    'enabled' => (bool) env('TV_TTS_ENABLED', true),

    'driver' => env('TV_TTS_DRIVER', 'espeak'),

    'espeak_binary' => env('TV_TTS_ESPEAK_BINARY', 'espeak-ng'),

    /** espeak-ng voice id (Portuguese Brazil typically "pt" or "pt-br"). */
    'voice' => env('TV_TTS_VOICE', 'pt'),

    /** Words per minute for espeak-ng (-s). */
    'rate' => (int) env('TV_TTS_RATE', 145),

    'cache_disk' => env('TV_TTS_CACHE_DISK', 'local'),

    'cache_directory' => env('TV_TTS_CACHE_DIRECTORY', 'tv-tts'),

    /** Soft cap on cached WAV files (oldest pruned after writes). */
    'cache_max_files' => (int) env('TV_TTS_CACHE_MAX_FILES', 400),

    /** Ignore cache entries older than N days during prune. */
    'cache_max_age_days' => (int) env('TV_TTS_CACHE_MAX_AGE_DAYS', 30),

    'process_timeout_seconds' => (int) env('TV_TTS_TIMEOUT', 8),
];
