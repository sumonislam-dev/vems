<?php

return [
    /*
     * §7: standard shift length used to derive overtime_minutes. A single
     * global value in v1 — not per-department/per-user yet.
     */
    'standard_shift_minutes' => env('ATTENDANCE_STANDARD_SHIFT_MINUTES', 480),

    /*
     * A check-in further than this from every known factory/stop gets
     * flagged has_anomaly for HR review — not blocked, since GPS drift and
     * legitimate off-site work both look the same from a distance alone.
     */
    'geofence_radius_meters' => env('ATTENDANCE_GEOFENCE_RADIUS_METERS', 300),
];
