<?php

/*
|--------------------------------------------------------------------------
| Project Tracking Dashboard
|--------------------------------------------------------------------------
| alert_days: batas hari untuk peringatan "tertahan" — approval PO Supplier,
| barang PO / DP belum datang, barang project siap tapi belum dikirim.
*/

return [
    'alert_days' => (int) env('PROJECT_TRACKING_ALERT_DAYS', 14),
];
