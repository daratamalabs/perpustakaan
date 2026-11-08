<?php
declare(strict_types=1);

/** Routes under /superadmin */
function routes_platform(): array
{
    return [
        ['GET', '/', 'platform_dashboard'],
        ['GET', '/login', 'platform_login_form'],
        ['POST', '/login', 'platform_login'],
        ['POST', '/logout', 'platform_logout'],
        ['GET', '/sekolah/baru', 'platform_school_form'],
        ['POST', '/sekolah/baru', 'platform_school_create'],
        ['GET', '/sekolah/{id}', 'platform_school_form'],
        ['POST', '/sekolah/{id}', 'platform_school_update'],
        ['POST', '/sekolah/{id}/reset-sandi', 'platform_school_reset_password'],
        ['GET', '/akun', 'platform_account'],
        ['POST', '/akun', 'platform_account_save'],
    ];
}

/** Routes under /{sekolah} */
function routes_tenant(): array
{
    return [
        // Halaman publik & member
        ['GET', '/', 'pub_home'],
        ['GET', '/daftar', 'pub_register_form'],
        ['POST', '/daftar', 'pub_register'],
        ['GET', '/daftar/verifikasi', 'pub_verify_form'],
        ['POST', '/daftar/verifikasi', 'pub_verify'],
        ['POST', '/daftar/kirim-ulang', 'pub_verify_resend'],
        ['GET', '/daftar/selesai', 'pub_register_done'],
        ['GET', '/masuk', 'pub_login_form'],
        ['POST', '/masuk', 'pub_login'],
        ['POST', '/keluar', 'pub_logout'],
        ['GET', '/kartu', 'pub_card'],
        ['GET', '/kartu/cetak', 'pub_card_print'],
        ['GET', '/beranda', 'pub_member_home'],
        ['POST', '/pinjaman/{id}/perpanjang', 'pub_extend'],
        ['GET', '/profil', 'pub_profile'],
        ['POST', '/profil', 'pub_profile_save'],
        ['GET', '/tata-tertib', 'pub_rules'],
        ['GET', '/media/{a}/{b}', 'pub_media'],

        // Kiosk meja depan & layar display
        ['GET', '/kiosk', 'kiosk_page'],
        ['POST', '/kiosk/scan', 'kiosk_scan'],
        ['GET', '/kiosk/cari', 'kiosk_search'],
        ['POST', '/kiosk/masuk', 'kiosk_checkin_id'],
        ['GET', '/kiosk/tamu', 'kiosk_guest_form'],
        ['POST', '/kiosk/tamu', 'kiosk_guest'],
        ['GET', '/display', 'display_page'],
        ['GET', '/display/data', 'display_data'],

        // Admin
        ['GET', '/admin/login', 'admin_login_form'],
        ['POST', '/admin/login', 'admin_login'],
        ['POST', '/admin/logout', 'admin_logout'],
        ['GET', '/admin', 'admin_dashboard'],

        ['GET', '/admin/buku', 'books_index'],
        ['GET', '/admin/buku/baru', 'books_form'],
        ['POST', '/admin/buku/baru', 'books_save'],
        ['GET', '/admin/buku/isbn', 'books_isbn'],
        ['GET', '/admin/buku/impor', 'books_import_form'],
        ['POST', '/admin/buku/impor', 'books_import'],
        ['GET', '/admin/buku/templat', 'books_template'],
        ['GET', '/admin/buku/ekspor', 'books_export'],
        ['GET', '/admin/buku/label', 'books_labels'],
        ['GET', '/admin/buku/{id}', 'books_form'],
        ['POST', '/admin/buku/{id}', 'books_save'],
        ['POST', '/admin/buku/{id}/hapus', 'books_delete'],
        ['POST', '/admin/buku/{id}/eksemplar', 'copies_add'],
        ['POST', '/admin/eksemplar/{id}', 'copies_update'],
        ['POST', '/admin/eksemplar/{id}/hapus', 'copies_delete'],

        ['GET', '/admin/sirkulasi', 'circ_index'],
        ['GET', '/admin/sirkulasi/member', 'circ_member_lookup'],
        ['GET', '/admin/sirkulasi/eksemplar', 'circ_copy_lookup'],
        ['POST', '/admin/sirkulasi/pinjam', 'circ_borrow'],
        ['POST', '/admin/sirkulasi/kembali', 'circ_return'],
        ['POST', '/admin/sirkulasi/{id}/perpanjang', 'circ_extend'],
        ['POST', '/admin/sirkulasi/{id}/lunas', 'circ_paid'],
        ['POST', '/admin/sirkulasi/{id}/ingatkan', 'circ_remind'],
        ['POST', '/admin/sirkulasi/ingatkan-semua', 'circ_remind_all'],

        ['GET', '/admin/aset', 'assets_index'],
        ['GET', '/admin/aset/baru', 'assets_form'],
        ['POST', '/admin/aset/baru', 'assets_save'],
        ['GET', '/admin/aset/label', 'assets_labels'],
        ['GET', '/admin/aset/ekspor', 'assets_export'],
        ['GET', '/admin/aset/{id}/ubah', 'assets_form'],
        ['POST', '/admin/aset/{id}/ubah', 'assets_save'],
        ['POST', '/admin/aset/{id}/log', 'assets_log'],
        ['POST', '/admin/aset/{id}/hapus', 'assets_delete'],

        ['GET', '/admin/kunjungan', 'visits_index'],
        ['POST', '/admin/kunjungan/manual', 'visits_manual'],
        ['POST', '/admin/kunjungan/{id}/keluar', 'visits_checkout'],
        ['POST', '/admin/kunjungan/{id}/hapus', 'visits_delete'],
        ['GET', '/admin/kunjungan/ekspor', 'visits_export'],
        ['GET', '/admin/kunjungan/cari-member', 'visits_member_search'],

        ['GET', '/admin/member', 'members_index'],
        ['GET', '/admin/member/baru', 'members_form'],
        ['POST', '/admin/member/baru', 'members_save'],
        ['POST', '/admin/member/massal', 'members_bulk'],
        ['GET', '/admin/member/impor', 'members_import_form'],
        ['POST', '/admin/member/impor', 'members_import'],
        ['GET', '/admin/member/templat', 'members_template'],
        ['GET', '/admin/member/kenaikan', 'members_promote_form'],
        ['POST', '/admin/member/kenaikan', 'members_promote'],
        ['GET', '/admin/member/kartu', 'members_cards'],
        ['GET', '/admin/member/ekspor', 'members_export'],
        ['GET', '/admin/member/{id}', 'members_form'],
        ['POST', '/admin/member/{id}', 'members_save'],
        ['POST', '/admin/member/{id}/setujui', 'members_approve'],
        ['POST', '/admin/member/{id}/tolak', 'members_reject'],
        ['POST', '/admin/member/{id}/status', 'members_status'],
        ['POST', '/admin/member/{id}/reset-pin', 'members_reset_pin'],
        ['POST', '/admin/member/{id}/kirim-kartu', 'members_send_card'],
        ['POST', '/admin/member/{id}/qr-baru', 'members_new_qr'],

        ['GET', '/admin/laporan', 'reports_index'],
        ['GET', '/admin/laporan/ekspor', 'reports_export'],

        ['GET', '/admin/pengaturan', 'settings_index'],
        ['POST', '/admin/pengaturan/{section}', 'settings_save'],
        ['POST', '/admin/petugas', 'staff_save'],
        ['POST', '/admin/petugas/{id}', 'staff_save'],
        ['GET', '/admin/cadangan', 'backup_download'],
        ['POST', '/admin/cadangan/pulihkan', 'backup_restore'],
    ];
}
