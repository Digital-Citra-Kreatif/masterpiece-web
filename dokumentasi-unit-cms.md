# Titik unit di CMS

Dokumen ini menjelaskan cara kerja peta titik unit di form proyek: bagaimana editor memakainya, di mana datanya disimpan, dan kenapa dibuat dengan cara ini.

## Untuk editor

Unit diurus di dua tempat:

1. **Units**: tambah unit, isi **Title** (kode unit, misalnya `A-1`), pilih **Proyek**, dan atur **Status** (Tersedia, Dipesan, Terjual). Slug terisi otomatis dari Title dan bisa diabaikan.
2. **Projects → Ketersediaan unit**: unggah **Siteplan** lalu simpan proyek. Peta titik unit muncul di bawah field Siteplan dan berisi semua unit yang memilih proyek ini.

Cara menempatkan titik di peta:

- Pilih unit di daftar, lalu klik peta. Unit berikutnya yang belum punya titik langsung terpilih.
- Geser titik dengan mouse, atau pilih titiknya lalu pakai tombol panah (0,1% per tekan, 1% dengan Shift).
- Posisi bisa diketik di kolom Posisi X / Y (persen dari lebar dan tinggi siteplan).
- **Hapus titik** mengosongkan posisi unit; unitnya tidak terhapus.
- Tekan **Simpan titik**. Titik langsung tayang di halaman proyek, tanpa perlu menerbitkan proyek.

Peta tidak muncul selama proyek belum punya unit di menu Units. Kalau Siteplan belum dipilih, peta meminta Siteplan diisi dan proyek disimpan dulu.

Unit yang belum punya titik tidak tampil di halaman proyek.

## Kenapa dibuat begini

**Peta tidak bisa jadi field CMS biasa.** Editor entri Sunrice hanya menampilkan tipe field bawaan (teks, gambar, repeater, dan sejenisnya). Sunrice tidak punya cara resmi untuk memasang input buatan sendiri seperti peta di dalam form entri. Satu-satunya jalan lain adalah mengubah kode Sunrice di `vendor/`, dan perubahan itu hilang setiap kali Sunrice di-update.

**Peta disisipkan oleh aplikasi sendiri.** Middleware `AdminProjectMap` menambahkan satu script ke setiap halaman admin. Script itu hanya aktif di form edit proyek, lalu memasang peta di bawah field Siteplan. Peta memakai React dan komponen UI bawaan panel Sunrice (`globalThis.Sunrice.modules`), jadi tampilannya mengikuti admin dan tidak ada paket tambahan.

**Titik disimpan di tabel sendiri, bukan di field CMS.** Kalau titik disimpan di field proyek, tombol Simpan titik akan bentrok dengan tombol simpan/terbit form proyek (nomor versi draf berbeda, dan perubahan lain di form bisa ikut terbit). Dengan tabel `unit_points`, peta dan form proyek berdiri sendiri. Konsekuensinya, perubahan titik tidak masuk riwayat revisi CMS.

**Unit tetap entri CMS.** Status dan kode unit tetap diurus editor di menu Units seperti konten lain, lengkap dengan draf, terbit, dan revisi.

## Alur data

```
Units (CMS)                    unit_points (tabel aplikasi)       Projects (CMS)
title    = kode unit           project_id = ID entri proyek        availability.siteplan
project  = ID entri proyek     unit_id    = ID entri unit (unik)
sale_status                    x, y       = % dari siteplan
        \                               |                           /
         `----------------- ProjectPage::availability() ------------'
                                        |
                            halaman proyek: titik status unit
```

`ProjectPage::availability()` mengambil unit yang **tayang** dan memilih proyek ini, lalu memasangkan posisinya dari `unit_points`. Unit tanpa titik dilewati.

## File terkait

| File | Isi |
|---|---|
| `app/Http/Middleware/AdminProjectMap.php` | Menyisipkan `public/cms/project-map.js` ke halaman admin (hanya muatan halaman penuh, bukan kunjungan Inertia) |
| `public/cms/project-map.js` | Peta di form proyek: mencari field Siteplan, memuat data, klik/geser/panah, simpan |
| `app/Http/Controllers/Admin/UnitPointsController.php` | API peta: `GET` dan `PUT` `/admin/unit-points/{project}` |
| `app/Providers/AdminPanelProvider.php` | Mendaftarkan middleware dan route API di panel |
| `app/Models/UnitPoint.php`, `database/migrations/*_create_unit_points_table.php` | Tabel titik |
| `app/Support/ProjectPage.php` (`availability()`) | Menggabungkan unit dan titik untuk halaman proyek |
| `database/seeders/SiteContentSeeder.php` | Blueprint Unit (Title, Proyek, Status) dan Project |
| `tests/Feature/UnitPointsTest.php` | Test API peta, hak akses, dan penyisipan script |

## API

Keduanya ada di dalam panel admin, jadi hanya bisa diakses pengguna yang sudah login.

- `GET /admin/unit-points/{project}`: mengembalikan siteplan proyek, unit milik proyek beserta posisinya, dan `canUpdate`. Butuh izin lihat entri **projects**.
- `PUT /admin/unit-points/{project}` dengan isi `{ points: [{ id, x, y }] }`: `x`/`y` kosong menghapus titik. Unit dari proyek lain ditolak (422). Butuh izin ubah entri **projects**. Token CSRF dikirim lewat header `X-XSRF-TOKEN`.

## Yang perlu diperhatikan saat update Sunrice

Peta bergantung pada dua hal dari Sunrice:

1. **Penanda field Siteplan**: script mencari `[role="group"][aria-labelledby="data-availability-siteplan-label"]` (konstanta `FIELD` di `project-map.js`). Kalau Sunrice mengubah cara menandai field, peta tidak muncul. Perbarui selektor itu. Kalau handle field `availability` atau `siteplan` di blueprint diganti, selektor juga harus ikut diganti.
2. **Runtime panel**: script memakai `globalThis.Sunrice.modules` (`react`, `react-dom/client`, `@inertiajs/react`, `@imajiner/admin-sdk/ui`) dan `adminUrl()`. Versi runtime yang dipakai sekarang adalah 3.

Setelah update Sunrice, buka form edit proyek dan pastikan peta muncul di bawah Siteplan.

## Deploy

Jalankan `php artisan migrate` untuk membuat tabel `unit_points`. Titik dari data lama tidak ikut pindah otomatis; tempatkan ulang lewat peta, atau salin dari data lama dengan skrip sekali jalan.

## Masalah umum

| Gejala | Penyebab |
|---|---|
| Peta tidak muncul | Proyek belum punya unit di menu Units, atau selektor field Siteplan tidak cocok lagi (lihat di atas) |
| Peta meminta Siteplan | Siteplan belum dipilih, atau proyek belum disimpan setelah memilih Siteplan |
| Unit ada di peta tapi tidak di halaman proyek | Unit belum diterbitkan, atau belum punya titik |
| "Peta titik unit gagal dimuat." | Request API gagal; cek log Laravel dan hak akses pengguna |
