# Masterpiece Web

Situs pemasaran untuk kawasan ruko dan hunian: pengunjung menjelajahi kawasan, cluster, fasilitas, dan tipe unit, termasuk tampilan 360° dan status unit di siteplan.

Dibangun dengan Laravel 13 (PHP 8.5), Blade, dan Vite. Konten dikelola lewat CMS **sunrice/admin** + **sunrice/cms**.

## Halaman

**Beranda** (`/`), berurutan dari atas:

1. **Hero banner**: heading, deskripsi, dan latar berupa gambar/video. Lebih dari satu media jadi slider. Ada tombol Play Full Video.
2. **Masterplan + Kawasan**: scene yang di-pin. Frame Masterplan membesar, lalu gulir memutar orbit kawasan dengan hotspot.
3. **Lokasi**: peta kawasan (statis).
4. **Cluster**: scroll scene seperti Kawasan.
5. **Fasilitas**: carousel.
6. **Tipe unit**: kartu yang menuju halaman tiap tipe.
7. **Status siteplan**: titik status setiap unit (tersedia, dipesan, terjual).

**Halaman tipe** (`/tipe/{slug}`): sekuens 360° fasad (WebRotate), bagian per lantai dengan denah, galeri, dan tur VR (3DVista).

## Konten

| Konten | Sumber |
|---|---|
| Hero banner, judul + deskripsi tiap section Beranda | CMS: Pages → Beranda |
| Gambar, hotspot, daftar fasilitas, kartu tipe di Beranda | Sementara hardcode di `resources/data/home.php` (sekaligus jadi isi bawaan saat isian CMS kosong) |
| Tipe unit (spesifikasi, denah, galeri, VR) | CMS: koleksi Tipe |
| Status unit di siteplan | CMS: koleksi Unit |
| Menu, label status, siteplan, proteksi media | CMS: Globals → Situs |
| Teks fasad dan lantai yang sama untuk semua tipe | CMS: Globals → Tipe: bersama |
| Posisi hotspot di scene, sekuens 360° | `resources/data/area.json`, `resources/data/sequence.php` (paket dari tim 3D) |

Data dari CMS diolah ke bentuk yang dibaca view di `app/Support/Content.php`.

Struktur CMS (blueprint, koleksi, globals) dan konten awal ada di `database/seeders/SiteContentSeeder.php`. Seeder hanya menambahkan yang belum ada, jadi perubahan yang dibuat di admin tidak ditimpa.

## Struktur folder

```
app/Support/Content.php        konten CMS → data untuk view
resources/views/pages/         halaman Beranda dan tipe
resources/views/components/    hero, scroll scene, fasilitas, kartu tipe, status unit, dll.
resources/views/cms/           template yang dipilih CMS per koleksi/entri
resources/css/components/      CSS per komponen
resources/js/                  hero slider, scroll scene, masterplan, carousel, 360°, smooth scroll
resources/data/                data hardcode dan paket dari tim 3D
public/media/                  media hasil build (frame scene, video hero, still, placeholder)
scripts/                       build media dari render mentah, tracking hotspot
frontend/                      prototipe statis awal (referensi)
```

## Menjalankan secara lokal

Situs dilayani Laravel Herd di `https://masterpiece-web.test`.

```bash
composer setup                                         # install, .env, key, migrate, build aset
php artisan db:seed --class=SiteContentSeeder          # struktur CMS + konten awal
php artisan sunrice:make-admin                         # akun admin CMS
npm run dev                                            # Vite saat mengembangkan
```

Admin CMS ada di `/admin`.

Video hero berukuran lebih dari 2 MB. Kalau seeder gagal mengunggahnya, naikkan `upload_max_filesize` dan `post_max_size` di php.ini.

## Media

Render mentah dari klien diolah ke `public/media` dengan:

```bash
npm run media          # build media yang belum ada (--force untuk build ulang semua)
npm run track          # posisi hotspot sementara untuk scene kawasan
```

## Test

```bash
php artisan test --compact
vendor/bin/pint --dirty --format agent    # format kode PHP
```
