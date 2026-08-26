# CKT Lampung - Sistem Gudang & Bon Material

Sistem Informasi Manajemen Logistik Gudang & Pengeluaran Bon Material Lapangan PT Cipta Karya Teknologi (Wilayah Lampung).

## Fitur Utama
- **Dashboard Operasional**: Ringkasan stok ONT Besar & Kecil, 4 varian Roll Kabel Drop Core (150m, 100m, 75m, 50m), serta monitoring status bon teknisi.
- **Manajemen Stok Real-Time**: Pencatatan stok material, alokasi serial number ONT, riwayat mutasi barang masuk/keluar.
- **Surat Bon Material Teknisi**: Penerbitan bon serah terima barang, auto-merge bon aktif, konfirmasi penyelesaian tugas lapangan.
- **Pelacakan Serial Number ONT**: Pelacakan SN & MAC Address, pelaporan status unit (Terpasang, Bad, Change).
- **Laporan & Rekapitulasi**: Filter periode tanggal, status, teknisi, serta fitur Export data ke CSV.
- **Keamanan Berlapis**: Dilengkapi perlindungan CSRF, Session Fixation Guard, Rate Limiting Brute Force, dan Apache .htaccess security.

## Teknologi
- **Backend**: PHP 8.x
- **Database**: MySQL / MariaDB (dengan fallback otomatis SQLite)
- **Frontend**: HTML5, Vanilla CSS3, JavaScript ES6+, Bootstrap Icons, SweetAlert2, Chart.js

## Panduan Instalasi Lokal (XAMPP)
1. Clone repositori ini ke folder `htdocs`:
   ```bash
   git clone https://github.com/albasith19t/cktlampung.git
   ```
2. Buka XAMPP Control Panel dan nyalakan **Apache** & **MySQL**.
3. Buka phpMyAdmin (`http://localhost/phpmyadmin`), buat database baru bernama `cktlampung`.
4. Import file `cktlampung.sql` ke dalam database `cktlampung`.
5. Akses aplikasi melalui browser:
   ```
   http://localhost/cktlampung/
   ```
6. Akun Default:
   - **Admin Gudang**: Username: `admin` | Password: `admin123`
   - **Teknisi Lapangan**: Username: `budi` / `rian` / `zaki` / `dimas` / `bayu` | Password: `123456`
