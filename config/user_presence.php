<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Chat / panel presence
    |--------------------------------------------------------------------------
    |
    | ATTENDANT Online/Offline follows desk lease (desk_assignments.last_seen_at).
    | SUPERVISOR uses users.last_seen_at heartbeat (no desk required).
    | Independent from Messages page; heartbeat runs on the attendant layout.
    |
    */

    /** How often the attendant shell reports activity (seconds). */
    'heartbeat_seconds' => (int) env('USER_PRESENCE_HEARTBEAT_SECONDS', 30),

    /** Online while last_seen_at is within this window (seconds). */
    'online_window_seconds' => (int) env('USER_PRESENCE_ONLINE_WINDOW_SECONDS', 90),

    /** Sidebar unread badge poll interval (seconds). Also refreshes presence. */
    'unread_poll_seconds' => (int) env('USER_PRESENCE_UNREAD_POLL_SECONDS', 10),
];
