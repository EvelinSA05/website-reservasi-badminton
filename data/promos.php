<?php

return [
    [
        'slug' => 'happy-hour-weekday',
        'title' => 'Happy Hour Weekday',
        'badge' => 'Hemat',
        'detail' => 'Diskon 25% booking jam 10.00-15.00',
        'code' => 'HAPPY25',
        'period' => 'Berlaku 10 Apr 2026 - 30 Apr 2026',
        'summary' => 'Promo siang hari untuk pemain yang ingin booking lebih hemat di jam non-prime time.',
        'benefits' => [
            'Diskon 25% untuk booking lapangan pada jam 10.00-15.00.',
            'Cocok untuk latihan rutin weekday, sparring santai, atau sesi coaching singkat.',
            'Bisa dipakai saat memilih lapangan yang statusnya tersedia.',
        ],
        'steps' => [
            'Buka detail promo dan catat kode promo.',
            'Masuk ke halaman reservasi dan pilih jadwal di rentang jam promo.',
            'Tunjukkan kode promo saat proses verifikasi reservasi atau pembayaran.',
        ],
        'terms' => [
            'Promo hanya berlaku pada hari Senin sampai Jumat.',
            'Tidak dapat digabung dengan promo referral atau paket pelatih.',
            'Validasi akhir mengikuti ketersediaan lapangan dan jam booking.',
        ],
    ],
    [
        'slug' => 'paket-main-pelatih',
        'title' => 'Paket Main + Pelatih',
        'badge' => 'Favorit',
        'detail' => 'Gratis shuttlecock untuk sesi minimal 2 jam',
        'code' => 'COACHPLAY',
        'period' => 'Berlaku 10 Apr 2026 - 15 Mei 2026',
        'summary' => 'Bundling favorit untuk pemain yang ingin latihan lebih terarah bersama pelatih.',
        'benefits' => [
            'Gratis shuttlecock untuk sesi latihan minimal 2 jam.',
            'Lebih hemat untuk pemain yang booking lapangan sambil reservasi coach.',
            'Cocok untuk private session atau latihan teknik mingguan.',
        ],
        'steps' => [
            'Pilih promo ini lalu lanjut ke reservasi.',
            'Booking lapangan dengan durasi minimal 2 jam.',
            'Sampaikan kode promo saat mengatur sesi coach atau pembayaran.',
        ],
        'terms' => [
            'Durasi booking minimal 2 jam.',
            'Promo berlaku untuk sesi yang disertai pelatih.',
            'Bonus shuttlecock diberikan satu kali per transaksi yang memenuhi syarat.',
        ],
    ],
    [
        'slug' => 'member-referral',
        'title' => 'Member Referral',
        'badge' => 'Baru',
        'detail' => 'Ajak teman, dapat voucher Rp50.000',
        'code' => 'REFER50',
        'period' => 'Berlaku 10 Apr 2026 - 31 Mei 2026',
        'summary' => 'Program referral untuk member yang ingin mengajak teman baru bergabung ke SmashHub.',
        'benefits' => [
            'Voucher Rp50.000 setelah teman berhasil registrasi dan booking pertama.',
            'Mendorong komunitas bermain tumbuh lebih cepat.',
            'Bisa dipakai untuk potongan transaksi berikutnya sesuai verifikasi admin.',
        ],
        'steps' => [
            'Bagikan kode referral ke teman yang belum punya akun.',
            'Pastikan teman registrasi dan melakukan booking pertama.',
            'Voucher akan diverifikasi dan dapat dipakai pada transaksi berikutnya.',
        ],
        'terms' => [
            'Voucher diberikan setelah booking pertama teman selesai diverifikasi.',
            'Satu akun maksimal mendapatkan satu voucher aktif per referral yang valid.',
            'Promo referral tidak dapat diuangkan dan mengikuti kebijakan admin.',
        ],
    ],
];
