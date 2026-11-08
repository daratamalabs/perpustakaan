# SatuPerpus — Daratama Labs

**SatuPerpus** adalah aplikasi perpustakaan sekolah berbasis web (PHP + MySQL) untuk banyak sekolah sekaligus.
Setiap sekolah punya alamat sendiri, misalnya:

| Sekolah            | Alamat                                               |
|--------------------|------------------------------------------------------|
| SMA Negeri 3 Pemalang | `https://satuperpus.daratamalabs.com/sman3pemalang` |
| SMA Negeri 1 Pemalang | `https://satuperpus.daratamalabs.com/sman1pemalang` |

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

## Cara pasang di Hostinger (deploy.sh)

Kode disimpan di GitHub (`daratamalabs/perpustakaan`). `deploy.sh` dijalankan dari Mac: ia menyinkronkan kode dengan GitHub, lalu mengirimkannya langsung ke server Hostinger lewat SSH. Server tidak butuh akses ke GitHub, jadi tidak ada kunci deploy yang perlu diatur.

### Persiapan di hPanel (sekali)

1. **Subdomain** `satuperpus.daratamalabs.com`. Foldernya dideteksi otomatis oleh `./deploy.sh setup` (`domains/satuperpus.daratamalabs.com/public_html` atau `domains/daratamalabs.com/public_html/satuperpus`). Kalau tidak terdeteksi, lihat foldernya di *Domains → Subdomains*.
2. **SSL** untuk subdomain itu (*Security → SSL*). HTTPS wajib agar kamera bisa dipakai untuk scan QR.
3. **Database** (*Databases → MySQL Databases*): buat database dan user baru, lalu catat nama database, user, dan password-nya.
4. **SSH Access** (*Advanced → SSH Access*): aktifkan, lalu catat IP, port (biasanya `65002`), dan username (`u123456789`). Agar tidak diminta password setiap deploy, tambahkan kunci SSH publik Mac (`~/.ssh/id_ed25519.pub`) di bagian *SSH Keys*.

### Pemasangan pertama

```bash
git clone https://github.com/daratamalabs/perpustakaan.git
cd perpustakaan
./deploy.sh setup
```

Kalau repo private dan Git meminta login, jalankan `gh auth login` sekali (pilih GitHub.com → HTTPS → login lewat browser), lalu ulangi `git clone`.

`setup` akan:
1. Menanyakan data SSH, lalu menyimpannya di `.deploy.env` (tidak ikut ke GitHub). Password SSH cukup dimasukkan sekali per perintah.
2. Memeriksa folder subdomain di server.
3. Mengirim kode ke server (repo git di `~/repos/perpustakaan.git`, di luar folder web) dan memasangnya di folder subdomain. Halaman bawaan Hostinger (`default.php`) ikut dihapus.

Jika `https://satuperpus.daratamalabs.com` menampilkan halaman 404 Hostinger, berarti kode belum ada di folder yang dipakai subdomain. Jalankan `./deploy.sh setup` lagi.

Lanjutkan di browser:

4. **Installer**: buka `https://satuperpus.daratamalabs.com/install`, isi data database dan buat akun super admin. Installer membuat tabel dan `config.php`, lalu terkunci.
5. **Tambah sekolah**: masuk ke `https://satuperpus.daratamalabs.com/superadmin`, pilih *Tambah sekolah*, lalu isi nama (SMA Negeri 3 Pemalang), alamat `sman3pemalang`, dan akun kepala perpustakaan.
6. **Siapkan sekolah**: kepala perpustakaan masuk ke `/sman3pemalang/admin`, lalu lengkapi *Pengaturan* (profil, jam buka, aturan pinjam, token WhatsApp, petugas).

Untuk sekolah berikutnya cukup ulangi langkah 5 dengan alamat lain, misalnya `sman1pemalang`.

### Pembaruan

```bash
./deploy.sh          # commit perubahan (jika ada), sinkron dengan GitHub, lalu kirim ke server
./deploy.sh status   # versi yang sedang berjalan di server
./deploy.sh ssh      # buka terminal server di folder aplikasi
./deploy.sh pindah   # pindah ke subdomain baru (lihat di bawah)
```

Saat deploy, folder aplikasi di server mengikuti isi repo persis: berkas yang dihapus di repo ikut terhapus di server. `config.php` dan isi `storage/` (sesi, foto unggahan) tidak pernah tersentuh karena tidak ikut di repo. Jangan mengedit kode langsung di server, karena perubahannya akan tertimpa pada deploy berikutnya.

### Pindah ke subdomain lain

Buat subdomain baru di hPanel (beserta SSL), ganti `APP_URL` di `deploy.sh`, lalu jalankan `./deploy.sh pindah`. Perintah ini:
- memasang kode di folder subdomain baru;
- membawa `config.php`, foto unggahan, dan cadangan ke sana, lalu mengganti `base_url`;
- memindahkan folder lama ke `~/arsip/` dan mengalihkan alamat lama (301) ke alamat baru, termasuk tautan display dan QR pendaftaran yang sudah tersebar.

Database tidak diubah. Petugas dan member hanya perlu masuk ulang sekali.

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

## Struktur folder

```
index.php          Front controller (semua permintaan masuk lewat sini)
deploy.sh          Deploy ke Hostinger lewat SSH + git
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
