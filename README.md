# Perpustakaan Digital Sekolah — Daratama Labs

Aplikasi perpustakaan sekolah berbasis web (PHP + MySQL) untuk banyak sekolah sekaligus.
Setiap sekolah punya alamat sendiri, misalnya:

| Sekolah            | Alamat                                               |
|--------------------|------------------------------------------------------|
| SMA Negeri 3 Pemalang | `https://perpustakaan.daratamalabs.com/sman3pemalang` |
| SMA Negeri 1 Pemalang | `https://perpustakaan.daratamalabs.com/sman1pemalang` |

Data tiap sekolah (buku, aset, member, kunjungan, petugas, pengaturan) terpisah satu sama lain.

## Fitur

- **Katalog buku & eksemplar**: tambah buku (isi otomatis dari ISBN), kategori, rak, eksemplar dengan barcode, cetak label, impor/ekspor Excel.
- **Sirkulasi**: pinjam, kembalikan, perpanjang lewat scan barcode/QR (scanner USB atau kamera), batas pinjam per jenis member, denda keterlambatan, pengingat WhatsApp.
- **Aset & inventaris**: kode inventaris otomatis, kondisi barang, riwayat perubahan, foto, label QR, ekspor Excel.
- **Kunjungan**: check-in pakai kartu QR di kiosk, input manual oleh petugas, buku tamu, rekap & ekspor.
- **Layar display meja depan**: jumlah pengunjung hari ini, pengunjung terbaru, grafik kunjungan per jam. Diperbarui otomatis.
- **Member**: pendaftaran mandiri (siswa/guru/tendik) dengan verifikasi data induk, persetujuan petugas, kartu QR digital & cetak, kirim kartu lewat WhatsApp, impor data siswa dari Excel, kenaikan kelas massal.
- **Laporan bulanan**: kunjungan, peminjaman, koleksi, aset, member. Cetak/PDF dan Excel.
- **Pengaturan sekolah**: profil, jam buka, aturan pinjam, WhatsApp (Fonnte), petugas & hak akses, cadangan & pemulihan data.
- **Super admin platform** (`/superadmin`): tambah/nonaktifkan sekolah dan akun kepala perpustakaannya.

### Peran petugas per sekolah

| Peran | Akses |
|-------|-------|
| Kepala perpustakaan | Semua menu, termasuk kelola petugas dan pulihkan cadangan |
| Pustakawan (admin) | Semua menu kecuali kelola petugas dan pulihkan cadangan |
| Petugas piket | Kunjungan dan kiosk saja |

## Kebutuhan server

- PHP **8.1 atau lebih baru** dengan ekstensi `pdo_mysql`, `mbstring`, `zip` (Excel), `curl` (WhatsApp & ISBN).
- MySQL 5.7+ atau MariaDB 10.3+.
- Apache/LiteSpeed dengan `mod_rewrite` (sudah standar di Hostinger).
- **HTTPS wajib** agar kamera (scan QR di HP/laptop) bisa dipakai browser.

Paket Hostinger shared hosting yang sama dengan Daratama POS sudah memenuhi semuanya. Installer akan menampilkan hasil pemeriksaan server sebelum memasang.

## Cara pasang di Hostinger

1. **Buat subdomain.** hPanel → *Domains* → *Subdomains* → buat `perpustakaan` untuk `daratamalabs.com`. Catat folder yang dibuat (biasanya `public_html/perpustakaan`).
2. **Aktifkan SSL** untuk `perpustakaan.daratamalabs.com` (hPanel → *Security* → *SSL*). Tunggu sampai aktif.
3. **Buat database.** hPanel → *Databases* → *MySQL Databases*: buat database dan user baru, lalu catat nama database, user, dan password-nya.
4. **Unggah aplikasi.** Buka *File Manager*, masuk ke folder subdomain, unggah `perpustakaan.zip`, lalu *Extract* di folder itu. Pastikan `index.php`, `.htaccess`, `app/`, `assets/`, dan `storage/` berada langsung di folder subdomain (bukan di dalam subfolder lagi).
5. **Jalankan installer.** Buka `https://perpustakaan.daratamalabs.com/install`, isi data database dan buat akun super admin. Installer membuat tabel dan `config.php` secara otomatis, lalu terkunci.
6. **Tambah sekolah.** Masuk `https://perpustakaan.daratamalabs.com/superadmin` → *Tambah sekolah*: isi nama (SMA Negeri 3 Pemalang), alamat `sman3pemalang`, dan akun kepala perpustakaan.
7. **Siapkan sekolah.** Kepala perpustakaan masuk ke `/sman3pemalang/admin`, lalu buka *Pengaturan* untuk melengkapi profil, jam buka, aturan pinjam, token WhatsApp, dan akun petugas.

Untuk sekolah berikutnya cukup ulangi langkah 6 dengan alamat lain, misalnya `sman1pemalang`.

> Jika installer tidak bisa menulis `config.php`, ia akan menampilkan isi berkasnya. Buat `config.php` secara manual di folder aplikasi dengan isi tersebut. Contoh format ada di `config.sample.php`.

## Alamat penting tiap sekolah

Ganti `sman3pemalang` dengan alamat sekolah.

| Halaman | Alamat | Untuk |
|---------|--------|-------|
| Beranda & daftar member | `/sman3pemalang` dan `/sman3pemalang/daftar` | Siswa, guru, tendik |
| Kartu member | `/sman3pemalang/masuk` (NISN/NIP/nomor anggota + PIN) | Member |
| Admin | `/sman3pemalang/admin` | Petugas |
| Kiosk check-in | `/sman3pemalang/kiosk` | Komputer/tablet meja depan (login petugas) |
| Layar display | `/sman3pemalang/display?key=…` | Monitor meja depan. Tautan lengkap dengan kunci ada di *Pengaturan → Kiosk & layar display* |

Tips meja depan:
- **Kiosk**: buka di komputer dengan scanner barcode USB (langsung bisa dipakai, scanner bekerja seperti keyboard) atau webcam. Tekan F11 untuk layar penuh.
- **Display**: buka tautan display di browser monitor depan lalu F11. Tidak perlu login. Ganti kunci di Pengaturan jika tautan tersebar.
- **Poster pendaftaran**: QR menuju halaman daftar tersedia di *Pengaturan → Tautan & QR*.

## WhatsApp (Fonnte)

1. Daftar di fonnte.com, hubungkan nomor WhatsApp perpustakaan, salin token perangkat.
2. *Pengaturan → WhatsApp*: tempel token, aktifkan, lalu kirim pesan tes.

WhatsApp dipakai untuk kode verifikasi pendaftaran, kartu member setelah disetujui, PIN baru, dan pengingat jatuh tempo. Aplikasi tetap berjalan tanpa WhatsApp.

## Cadangan data

- *Pengaturan → Cadangan data* mengunduh seluruh data sekolah dalam satu berkas `.json` (foto dan logo tidak ikut). Lakukan rutin, misalnya tiap akhir bulan.
- Hanya kepala perpustakaan yang bisa memulihkan cadangan, dan hanya ke sekolah yang sama.
- Foto (sampul buku, foto member, foto aset) tersimpan di `storage/uploads`. Ikut cadangkan folder ini lewat fitur backup Hostinger.

## Pembaruan aplikasi

Unggah berkas versi baru dan timpa berkas lama, **kecuali** `config.php` dan folder `storage/`. Keduanya berisi pengaturan dan data unggahan yang harus tetap ada.

## Struktur folder

```
index.php          Front controller (semua permintaan masuk lewat sini)
.htaccess          Aturan URL & proteksi folder
config.sample.php  Contoh konfigurasi (config.php dibuat installer)
app/               Kode aplikasi (tidak bisa diakses dari browser)
  schema.sql       Struktur database
  controllers/     Logika halaman
  services/        Peminjaman, kunjungan, member, WhatsApp, Excel, statistik
  views/           Tampilan
assets/            CSS, JavaScript, font, pustaka QR/barcode
storage/           Sesi login & berkas unggahan (tidak bisa diakses langsung)
```

## Lisensi pihak ketiga

- Font Plus Jakarta Sans & Newsreader: SIL Open Font License (lihat `assets/fonts/`).
- jsQR (Apache-2.0), qrcode-generator (MIT), JsBarcode (MIT): lihat `assets/vendor/THIRD-PARTY-LICENSES.txt`.
