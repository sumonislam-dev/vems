<?php

return [
    /*
     * §7: standard shift length used to derive overtime_minutes. A single
     * global value in v1 — not per-department/per-user yet.
     */
    'standard_shift_minutes' => env('ATTENDANCE_STANDARD_SHIFT_MINUTES', 480),
];
