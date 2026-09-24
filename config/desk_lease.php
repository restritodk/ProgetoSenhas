<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Desk claim lease (attendant exclusivity)
    |--------------------------------------------------------------------------
    |
    | A desk_assignments row is "active" only while last_seen_at is within
    | ttl_seconds. The attendant panel poll (~2.5s) refreshes last_seen_at via
    | OperationalContext::activeDesk. Logout releases immediately.
    |
    */

    'ttl_seconds' => (int) env('DESK_LEASE_TTL_SECONDS', 90),

    /** Advisory client hint only — server trust is last_seen_at. */
    'heartbeat_hint_seconds' => (int) env('DESK_LEASE_HEARTBEAT_HINT_SECONDS', 25),
];
