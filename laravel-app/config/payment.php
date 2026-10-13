<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Pembayaran Kegiatan (HTM)
    |--------------------------------------------------------------------------
    */

    // Lama kursi/kuota ditahan sambil menunggu pembayaran.
    'expiry_minutes' => (int) env('PAYMENT_EXPIRY_MINUTES', 60),

    // Kunci internal => kode channel Tripay + label tampilan.
    'methods' => [
        'qris' => ['code' => 'QRIS', 'label' => 'QRIS (semua e-wallet & m-banking)', 'type' => 'qris'],
        'bca_va' => ['code' => 'BCAVA', 'label' => 'BCA Virtual Account', 'type' => 'va'],
        'bni_va' => ['code' => 'BNIVA', 'label' => 'BNI Virtual Account', 'type' => 'va'],
        'bri_va' => ['code' => 'BRIVA', 'label' => 'BRI Virtual Account', 'type' => 'va'],
        'mandiri_va' => ['code' => 'MANDIRIVA', 'label' => 'Mandiri Virtual Account', 'type' => 'va'],
    ],

    // Dipakai bila akun pemesan tidak memiliki nomor telepon (Tripay mewajibkan customer_phone).
    'fallback_customer_phone' => env('PAYMENT_FALLBACK_PHONE', ''),

];
