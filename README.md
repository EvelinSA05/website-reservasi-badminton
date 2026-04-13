# Website Reservasi Badminton

## Deskripsi Aplikasi

Website Reservasi Badminton adalah aplikasi berbasis web yang dibuat untuk membantu proses pemesanan lapangan badminton secara online. Aplikasi ini mendukung alur pemesanan dari sisi pelanggan maupun pengelolaan data dari sisi internal venue, sehingga proses reservasi, pembayaran, dan pemantauan operasional dapat dilakukan dalam satu sistem.

Pada proyek ini, pengguna dapat melihat informasi venue, memilih lapangan, menentukan jadwal bermain, melakukan reservasi, mengunggah bukti pembayaran, serta memantau status booking. Di sisi admin, sistem menyediakan pengelolaan data reservasi, pembayaran, lapangan, dan laporan operasional.

## Fitur Utama

- Landing page informasi venue badminton.
- Login dan autentikasi pengguna.
- Role pengguna `pelanggan`, `admin`, `kasir`, dan `owner`.
- Dashboard berbeda sesuai hak akses masing-masing role.
- Reservasi lapangan berdasarkan tanggal dan jam bermain.
- Validasi bentrok jadwal reservasi.
- Perhitungan durasi dan total biaya otomatis.
- Pengelolaan pembayaran dan upload bukti transfer.
- Verifikasi status pembayaran oleh admin atau kasir.
- Pengelolaan data lapangan.
- Halaman laporan operasional.
- Konten tambahan seperti promo, pelatih, partner main, dan artikel.

## Tech Stack

- `PHP` sebagai bahasa pemrograman utama backend.
- `MySQL` sebagai basis data utama aplikasi.
- `HTML` untuk struktur halaman web.
- `CSS` untuk styling antarmuka.
- `JavaScript` untuk interaksi di sisi client.

## Informasi Kelompok

Silakan ganti data placeholder berikut sesuai anggota kelompok kalian.

| No | Nama Anggota | NPM | Peran |
| --- | --- | --- | --- |
| 1 | Mochamad Rico Andreano | 24082010005 | Dokumentasi / Testing |
| 2 | Hafida Zahra Sofiya L. | 24082010013 | Frontend Developer |
| 3 | Evelin Salsabila A. | 24082010017 | Backend Developer |

## Pembagian Tugas Tiap Anggota

Sesuaikan pembagian berikut dengan pengerjaan asli kelompok kalian.

| Nama Anggota | NPM | Tugas yang Dikerjakan |
| --- | --- | --- |
| Mochamad Rico Andreano | 24082010005 | Menyusun dokumentasi proyek, membantu pengujian fitur, serta melakukan pengecekan alur aplikasi agar berjalan sesuai kebutuhan. |
| Hafida Zahra Sofiya L. | 24082010013 | Mengerjakan tampilan antarmuka aplikasi, styling halaman, serta interaksi pengguna di sisi client menggunakan HTML, CSS, dan JavaScript. |
| Evelin Salsabila A. | 24082010017 | Mengembangkan logika backend aplikasi menggunakan PHP, termasuk fitur login, reservasi, pembayaran, dan integrasi proses utama sistem.  |

## Modul Aplikasi

- `Landing Page`: menampilkan informasi venue, lapangan, promo, artikel, pelatih, dan partner main.
- `Autentikasi`: login pengguna dan pengelolaan sesi.
- `Reservasi`: pembuatan booking lapangan, validasi jadwal, dan status reservasi.
- `Pembayaran`: input pembayaran, upload bukti transfer, dan verifikasi status pembayaran.
- `Dashboard`: ringkasan data dan navigasi sesuai role pengguna.
- `Laporan`: menampilkan data operasional untuk kebutuhan monitoring.

## Struktur Singkat Proyek

```text
website-reservasi-badminton/
|-- actions/         # proses logout dan session
|-- assets/          # CSS, JavaScript, gambar, dan media
|-- core/            # koneksi database, autentikasi, helper role
|-- data/            # data statis aplikasi
|-- pages/           # halaman dashboard, reservasi, pembayaran, laporan, dll
|-- uploads/         # file upload bukti pembayaran
|-- index.php        # halaman utama aplikasi
|-- login.php        # halaman login pengguna
|-- README.md        # dokumentasi proyek
```

## Tujuan Pengembangan

Proyek ini dikembangkan untuk memberikan solusi digital pada proses reservasi lapangan badminton agar lebih rapi, cepat, dan mudah dikelola. Dengan adanya sistem ini, pelanggan dapat melakukan booking tanpa datang langsung ke lokasi, sementara pihak pengelola venue dapat memantau operasional dengan lebih efisien.
