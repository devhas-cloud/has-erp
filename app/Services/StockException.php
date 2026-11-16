<?php

namespace App\Services;

use RuntimeException;

/**
 * Pelanggaran aturan stok (qty kurang, SN ganda/kosong, dsb). Pesannya
 * ditujukan ke user dan aman ditampilkan apa adanya (HTTP 422).
 */
class StockException extends RuntimeException {}
