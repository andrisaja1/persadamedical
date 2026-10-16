<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$route['default_controller'] = 'Home';
$route['404_override'] = '';
$route['translate_uri_dashes'] = FALSE;

// Halaman Publik
$route['tentang'] = 'Pages/tentang';
$route['tentang/persada-medical'] = 'Pages/tentang_persada';
$route['tentang/klinik-asahi'] = 'Pages/tentang_asahi';
$route['layanan'] = 'Pages/layanan';
$route['layanan/(:any)'] = 'Pages/layanan_detail/$1';
$route['dokter'] = 'Pages/dokter';
$route['klinik'] = 'Pages/klinik';
$route['klinik-asahi'] = 'Pages/klinik_asahi';
$route['promo'] = 'Pages/promo';
$route['kontak'] = 'Pages/kontak';
$route['kontak/kirim'] = 'Pages/kontak_kirim';
$route['artikel'] = 'Pages/berita';
$route['baca/(:num)'] = 'Pages/berita_legacy/$1';
$route['profil-dokter/(:num)'] = 'Pages/profil_dokter/$1';
$route['profil-klinik'] = 'Pages/profil_klinik/1';
$route['profil-klinik/(:num)'] = 'Pages/profil_klinik/$1';

// Berita & Artikel
$route['berita'] = 'Pages/berita';
$route['berita/(:any)'] = 'Pages/berita_detail/$1';

// Panel Admin
$route['admin'] = 'Admin/index';
$route['admin/login'] = 'Admin/login';
$route['admin/logout'] = 'Admin/logout';
$route['admin/dashboard'] = 'Admin/dashboard';

// Profil Klinik (Tentang Kami)
$route['admin/profil-klinik'] = 'Admin/profil_klinik';
$route['admin/profil-klinik/simpan/(:num)'] = 'Admin/profil_klinik_simpan/$1';
$route['admin/profil-klinik/penghargaan/(:num)'] = 'Admin/penghargaan/$1';
$route['admin/profil-klinik/penghargaan/(:num)/tambah'] = 'Admin/penghargaan_tambah/$1';
$route['admin/profil-klinik/penghargaan/edit/(:num)'] = 'Admin/penghargaan_edit/$1';
$route['admin/profil-klinik/penghargaan/hapus/(:num)'] = 'Admin/penghargaan_hapus/$1';
$route['admin/profil-klinik/penghargaan/toggle/(:num)'] = 'Admin/penghargaan_toggle/$1';

// Hero Section
$route['admin/hero'] = 'Admin/hero';
$route['admin/hero/tambah'] = 'Admin/hero_tambah';
$route['admin/hero/edit/(:num)'] = 'Admin/hero_edit/$1';
$route['admin/hero/hapus/(:num)'] = 'Admin/hero_hapus/$1';
$route['admin/hero/toggle/(:num)'] = 'Admin/hero_toggle/$1';
$route['admin/hero/order'] = 'Admin/hero_order';

// Dokter
$route['admin/dokter'] = 'Admin/dokter';
$route['admin/dokter/tambah'] = 'Admin/dokter_tambah';
$route['admin/dokter/edit/(:num)'] = 'Admin/dokter_edit/$1';
$route['admin/dokter/hapus/(:num)'] = 'Admin/dokter_hapus/$1';
$route['admin/dokter/toggle/(:num)'] = 'Admin/dokter_toggle/$1';

// Jadwal Praktik
$route['admin/jadwal'] = 'Admin/jadwal';
$route['admin/jadwal/tambah'] = 'Admin/jadwal_tambah';
$route['admin/jadwal/edit/(:num)'] = 'Admin/jadwal_edit/$1';
$route['admin/jadwal/hapus/(:num)'] = 'Admin/jadwal_hapus/$1';
$route['admin/jadwal/toggle/(:num)'] = 'Admin/jadwal_toggle/$1';

// Cabang Klinik
$route['admin/cabang'] = 'Admin/cabang';
$route['admin/cabang/tambah'] = 'Admin/cabang_tambah';
$route['admin/cabang/edit/(:num)'] = 'Admin/cabang_edit/$1';
$route['admin/cabang/hapus/(:num)'] = 'Admin/cabang_hapus/$1';
$route['admin/cabang/toggle/(:num)'] = 'Admin/cabang_toggle/$1';

// Fasilitas Klinik
$route['admin/fasilitas'] = 'Admin/fasilitas';
$route['admin/fasilitas/tambah'] = 'Admin/fasilitas_tambah';
$route['admin/fasilitas/edit/(:num)'] = 'Admin/fasilitas_edit/$1';
$route['admin/fasilitas/hapus/(:num)'] = 'Admin/fasilitas_hapus/$1';
$route['admin/fasilitas/toggle/(:num)'] = 'Admin/fasilitas_toggle/$1';

// Kategori & Layanan Medis
$route['admin/layanan'] = 'Admin/layanan';
$route['admin/layanan/tambah'] = 'Admin/layanan_tambah';
$route['admin/layanan/edit/(:num)'] = 'Admin/layanan_edit/$1';
$route['admin/layanan/hapus/(:num)'] = 'Admin/layanan_hapus/$1';
$route['admin/layanan/toggle/(:num)'] = 'Admin/layanan_toggle/$1';
$route['admin/layanan/toggle_featured/(:num)'] = 'Admin/layanan_toggle_featured/$1';
$route['admin/layanan/kategori_tambah'] = 'Admin/kategori_tambah';
$route['admin/layanan/kategori_edit/(:num)'] = 'Admin/kategori_edit/$1';
$route['admin/layanan/kategori_hapus/(:num)'] = 'Admin/kategori_hapus/$1';
$route['admin/layanan/kategori_toggle/(:num)'] = 'Admin/kategori_toggle/$1';

// Promo & Iklan
$route['admin/promo'] = 'Admin/promo';
$route['admin/promo/tambah'] = 'Admin/promo_tambah';
$route['admin/promo/edit/(:num)'] = 'Admin/promo_edit/$1';
$route['admin/promo/hapus/(:num)'] = 'Admin/promo_hapus/$1';
$route['admin/promo/toggle/(:num)'] = 'Admin/promo_toggle/$1';

// Inbox Pesan
$route['admin/inbox'] = 'Admin/inbox';
$route['admin/inbox/detail/(:num)'] = 'Admin/inbox_detail/$1';
$route['admin/inbox/status/(:num)'] = 'Admin/inbox_status/$1';
$route['admin/inbox/hapus/(:num)'] = 'Admin/inbox_hapus/$1';

// FAQ
$route['admin/faq'] = 'Admin/faq';
$route['admin/faq/tambah'] = 'Admin/faq_tambah';
$route['admin/faq/edit/(:num)'] = 'Admin/faq_edit/$1';
$route['admin/faq/hapus/(:num)'] = 'Admin/faq_hapus/$1';
$route['admin/faq/toggle/(:num)'] = 'Admin/faq_toggle/$1';

// Pengaturan Website
$route['admin/pengaturan'] = 'Admin/pengaturan';

// Manajemen Akun Admin
$route['admin/akun'] = 'Admin/akun';
$route['admin/akun/tambah'] = 'Admin/akun_tambah';
$route['admin/akun/edit/(:num)'] = 'Admin/akun_edit/$1';

// Audit Log & Backup
$route['admin/audit-log'] = 'Admin/audit_log';
$route['admin/backup'] = 'Admin/backup';

// Berita & Artikel Admin
$route['admin/berita'] = 'Admin/berita';
$route['admin/berita/tambah'] = 'Admin/berita_tambah';
$route['admin/berita/edit/(:num)'] = 'Admin/berita_edit/$1';
$route['admin/berita/hapus/(:num)'] = 'Admin/berita_hapus/$1';
$route['admin/berita/toggle/(:num)'] = 'Admin/berita_toggle/$1';

// Mitra Kami Admin
$route['admin/mitra'] = 'Admin/mitra';
$route['admin/mitra/tambah'] = 'Admin/mitra_tambah';
$route['admin/mitra/edit/(:num)'] = 'Admin/mitra_edit/$1';
$route['admin/mitra/hapus/(:num)'] = 'Admin/mitra_hapus/$1';
$route['admin/mitra/toggle/(:num)'] = 'Admin/mitra_toggle/$1';
