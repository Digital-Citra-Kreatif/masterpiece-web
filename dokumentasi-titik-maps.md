# Titik Masterplan dan Cluster (orbit 360°)

Dokumen ini menjelaskan titik di scene orbit Masterplan dan Cluster pada halaman proyek: cara editor mengisinya di CMS, cara sistem melacak titik di setiap frame, dan di mana datanya disimpan.

Untuk titik unit di Siteplan, lihat `dokumentasi-unit-cms.md`. Itu fitur terpisah.

## Untuk editor

### 1. Siapkan frame orbit

Scene orbit memakai satu set frame (gambar berurutan) hasil render putaran kamera. Ada tiga kemungkinan sumber gambar, dipakai dengan urutan ini:

1. **Frame dari ZIP** yang diunggah untuk proyek ini.
2. **Gambar diam** di field "Gambar atau ZIP 360°". Gambar ini diputar sedikit saat di-scroll, dan titiknya tidak dilacak.
3. **Frame contoh** bawaan (`public/media/scroll/kawasan/`), kalau dua sumber di atas kosong.

Ada dua cara mengunggah ZIP:

- Pilih file ZIP dari Media di field **Gambar atau ZIP 360°**, lalu klik **Simpan**. ZIP diproses sekali. Kalau ZIP dilepas dari field, frame-nya ikut terhapus.
- Klik **Unggah ZIP** di panel **Frame 360° (ZIP)** di bawah field titik. Cara ini untuk ZIP yang lebih besar dari batas Media, karena file dikirim per potongan 1 MB.

Syarat isi ZIP (dicek saat diproses):

| Syarat | Nilai |
|---|---|
| Format gambar | JPG, JPEG, PNG, WebP |
| Jumlah frame | 8 sampai 720 |
| Ukuran | Semua frame sama besar, sisi terpanjang maks 8192 px |
| Per gambar | Maks 40 MB |
| Total ZIP | Maks 2 GB |
| Urutan | Menurut nama file, urutan alami (`f_2` sebelum `f_10`) |

Panel Frame 360° menampilkan status ZIP: "Menunggu antrean", "Memproses frame", jumlah frame yang aktif, atau pesan gagal beserta alasannya.

### 2. Isi titik

Setiap titik adalah satu baris di repeater **Titik kawasan** (Masterplan) atau **Titik cluster** (Cluster). Kolom **Nama** wajib diisi. Baris tanpa nama tidak tampil di halaman.

Posisi titik diatur di panel **Penempatan** di bawah repeater, bukan di dalam baris:

1. Panel menampilkan **frame pertama** orbit beserta titik-titik yang sudah punya posisi.
2. Pilih titik lewat daftar nama di bawah gambar, atau klik titiknya di gambar. Klik di gambar hanya memilih, tidak memindahkan titik.
3. Isi **Posisi X (%)** dan **Posisi Y (%)** di bawah gambar:
   - X diukur dari tepi kiri frame pertama, Y dari tepi atas, dalam persen 0 sampai 100.
   - Desimal boleh pakai koma atau titik (`45,5` sama dengan `45.5`).
   - Angka di luar 0 sampai 100 ditandai merah dan tidak dipakai.
   - Kolom yang dikosongkan menyembunyikan titik itu.
4. Klik **Simpan** proyek.

Titik di gambar panel ikut bergeser saat angka diketik, sebelum Simpan. Kolom X/Y di dalam baris repeater sengaja disembunyikan selama frame tersedia, supaya posisi hanya diatur di satu tempat.

Saat Simpan, titik yang posisinya berubah langsung dilacak ulang. Simpan bisa beberapa detik lebih lama dari biasanya (sekitar 3 detik per titik yang diubah, diukur di laptop development).

## Alur data

```
CMS (repeater titik, X/Y dalam %)
        |
        | Simpan proyek
        v
ProjectTracks::make()  (pakai cache gerak frame di orbit-motion)
        |
        | node scripts/track-points.mjs
        v
storage/app/private/tracks/entry-{id}.json
  (posisi piksel tiap titik di setiap frame)
        |
        v
ProjectPage::page()  ->  data-tracks di <x-scroll-scene>
        |
        v
resources/js/scroll-scene.js menggambar frame dan menaruh penanda
```

Kunci titik dibentuk dari urutan baris yang punya nama: `masterplan-1`, `masterplan-2`, `cluster-1`, dan seterusnya. Kunci yang sama dipakai oleh `ProjectTracks::placed()` dan `ProjectPage::hotspots()`.

## Cara pelacakan bekerja

Editor hanya menaruh titik di frame pertama. Posisi di 119 frame lainnya dihitung oleh `scripts/track-points.mjs` dalam dua tahap.

### Tahap 1: gerak kamera antarframe (sekali per set frame)

Hasil tahap ini disimpan sebagai **cache gerak**, sehingga cukup dihitung sekali untuk setiap set frame.

1. Setiap frame ukuran `sm` (800 px) diubah ke abu-abu dan dibuat piramida 4 tingkat.
2. Di setiap pasangan frame berurutan, skrip memilih titik sudut (Shi-Tomasi, satu per sel di grid 24 × 14).
3. Gerak setiap sudut diikuti dengan aliran optik Lucas-Kanade berpiramida. Sudut yang tidak lolos cek maju-mundur (selisih lebih dari 1 px) dibuang.
4. Dari pasangan sudut itu, RANSAC (600 percobaan, batas inlier 2 px) menghitung **homografi**: transformasi bidang tanah dari frame `i` ke frame `i + 1`. Frame terakhir disambung ke frame 0, karena orbitnya berputar penuh.
5. Kalau pasangan sudut kurang dari 8 (misalnya frame polos), langkah itu dianggap tanpa gerak supaya skrip tidak macet.

### Tahap 2: lacak setiap titik

1. **Prediksi**: posisi titik di frame berikutnya diperkirakan dengan homografi bidang tanah. Prediksi ini tepat untuk titik di jalan dan tanah, dan tidak hanyut walaupun area sekitarnya polos.
2. **Koreksi lokal**: di frame ukuran `lg` (1280 px), pola 25 × 25 px di sekitar titik dicari dalam radius 8 px dari prediksi dengan korelasi (NCC). Skor diberi tarikan kecil ke arah prediksi. Koreksi hanya dipakai kalau korelasinya minimal 0,6 dan polanya tidak terlalu polos. Langkah ini menjaga titik di atap gedung tetap menempel, karena atap bergeser sedikit berbeda dari tanah.
3. **Maju dan mundur**: pelacakan dijalankan dari frame 0 ke depan dan dari frame 0 ke belakang, lalu digabung dengan bobot sesuai sudut putaran. Kesalahan kecil tidak menumpuk sampai ujung putaran.
4. **Penghalusan**: lonjakan lebih dari 6 px diganti median tetangganya, lalu jalur dihaluskan (Gaussian, sigma 1,2 frame).
5. **Kunci frame awal**: frame 0 dikembalikan tepat ke angka X/Y dari CMS. Selisihnya dipudarkan sampai 6 frame di kedua sisi.
6. Posisi di luar gambar disimpan sebagai `null`, dan penanda disembunyikan di frame itu.

Konstanta di atas ada di bagian atas `scripts/track-points.mjs`.

## Kapan pelacakan berjalan

| Kejadian | Yang terjadi |
|---|---|
| Simpan proyek dan cache gerak sudah ada | `TrackProjectPoints::afterSave()` langsung melacak titik yang berubah, setelah transaksi database selesai. Titik yang tidak berubah memakai jalur lamanya. |
| Simpan proyek dan cache gerak belum ada | Pelacakan dimasukkan ke antrean (`TrackProjectPoints`). Saat job jalan, cache gerak dibuat sekalian. |
| Pelacakan langsung gagal | Error dicatat ke log, lalu pelacakan dimasukkan ke antrean. |
| ZIP selesai diproses | `ProcessOrbitFrames` membuat cache gerak untuk frame baru, lalu mengantrekan pelacakan ulang semua titik. |
| Pelacakan belum selesai | Halaman memakai jalur lama yang digeser ke posisi baru. Titik baru yang belum punya jalur tampil diam di posisinya. |

Pemrosesan ZIP (sekitar 50 detik untuk 120 frame) dan pembuatan cache gerak pertama (sekitar 36 detik) selalu butuh **queue worker**. Mengubah posisi titik tidak butuh worker selama cache gerak sudah ada.

## Penyimpanan

| Lokasi | Isi |
|---|---|
| `storage/app/public/orbits/entry-{id}/{section}/v{waktu}/` | Frame hasil ZIP: `lg/f_000.jpg` (1280 px), `sm/f_000.jpg` (800 px), dan `manifest.json`. Folder versi terbaru yang dipakai. |
| `storage/app/private/orbit-uploads/entry-{id}-{section}.json` | Status ZIP terakhir dan media asal ZIP |
| `storage/app/private/orbit-motion/{md5 base}.json` | Cache gerak: homografi antarframe untuk satu set frame |
| `storage/app/private/tracks/entry-{id}.json` | Jalur titik per section: `hash`, `tracker`, `base`, `points` (X/Y %), `tracks` (piksel per frame, ukuran `lg`) |

`hash` dibentuk dari versi pelacak, folder frame, dan posisi titik. Kalau salah satunya berubah, jalur dianggap usang dan dilacak ulang.

## Halaman depan

- `ProjectPage::page()` memasang `tracks` ke data scene. Komponen `resources/views/components/scroll-scene.blade.php` menuliskannya ke atribut `data-tracks`.
- `resources/js/scroll-scene.js` menggambar frame dengan cover-fit ke canvas. Penanda ditaruh di `offset + posisi × skala` dari frame yang sedang tergambar, jadi titik dan gambar selalu memakai frame yang sama.
- Penanda disembunyikan di dekat tepi layar, di atas area teks, dan saat kamera zoom ke satu titik.
- Ujung pin penanda (`.spot--marker` di `resources/css/components/project-page.css`) jatuh tepat di titik karena `align-items: flex-start`. Tanpa itu, pin turun beberapa piksel mengikuti tinggi label.

## File terkait

| File | Isi |
|---|---|
| `public/cms/project-map.js` | Panel Frame 360° (ZIP) dan panel Penempatan titik di form proyek |
| `app/Http/Middleware/AdminProjectMap.php` | Menyisipkan `project-map.js` ke halaman admin |
| `app/Http/Controllers/Admin/OrbitUploadController.php` | Unggah ZIP per potongan, status, hapus frame |
| `app/Http/Controllers/Admin/OrbitFramesController.php` | Frame pertama orbit untuk panel Penempatan |
| `app/Support/OrbitFrames.php` | Cek isi ZIP, buat ukuran `lg`/`sm`, tulis manifest, status |
| `app/Support/OrbitZips.php` | ZIP yang dipilih di field "Gambar atau ZIP 360°" |
| `app/Jobs/ProcessOrbitFrames.php` | Job pemrosesan ZIP |
| `app/Support/ProjectTracks.php` | Jalur titik: simpan, baca, pakai ulang, cache gerak, versi pelacak |
| `app/Jobs/TrackProjectPoints.php` | Pelacakan langsung saat Simpan, atau lewat antrean |
| `scripts/track-points.mjs` | Pelacak (Node + `sharp`) |
| `app/Support/ProjectPage.php` | Titik dan jalur untuk halaman proyek |
| `resources/js/scroll-scene.js` | Scene orbit dan penanda di halaman depan |
| `tests/Feature/ProjectTracksTest.php`, `tests/Feature/OrbitUploadTest.php` | Test pelacakan dan unggah ZIP |

## API

Semua route ada di panel admin dan butuh login.

- `GET /admin/orbit-frames?project={id}`: frame pertama Masterplan dan Cluster untuk panel Penempatan.
- `GET /admin/orbit-upload/{project}/{section}`: status ZIP.
- `POST /admin/orbit-upload/{project}/{section}`: satu potongan ZIP (`upload`, `index`, `total`, `chunk`). Potongan terakhir memasukkan ZIP ke antrean.
- `DELETE /admin/orbit-upload/{project}/{section}`: hapus frame hasil unggahan panel.

`{section}` hanya `masterplan` atau `cluster`.

## Mengubah pelacak

- Setelah mengubah cara pelacakan di `scripts/track-points.mjs`, naikkan `TRACKER` di `app/Support/ProjectTracks.php`. Semua jalur lama dianggap usang. Lacak ulang setiap proyek dengan cara di poin ketiga, karena Simpan tanpa perubahan data tidak memicu pelacakan.
- Kalau cara menghitung gerak antarframe (tahap 1) berubah, naikkan juga `MOTION` di skrip, supaya cache gerak lama tidak dipakai.
- Untuk melacak ulang satu proyek tanpa menyimpan dari CMS, antrekan `TrackProjectPoints` dengan ID entry translation proyek itu, lalu jalankan worker.

## Deploy

- Server butuh **Node.js** dan paket `sharp` (`npm ci`), karena pelacak dan pemrosesan frame berjalan di Node.
- Jalankan `php artisan storage:link`, karena frame dilayani dari `/storage/orbits/...`.
- Pasang **queue worker** permanen (Supervisor, systemd, atau menu daemon/queue di panel hosting), atau cron tiap menit yang menjalankan `php artisan queue:work --stop-when-empty`. Worker dibutuhkan untuk ZIP dan pelacakan pertama.
- Jalankan `npm run build` supaya perubahan CSS dan JS halaman depan ikut terbit.
- Cache gerak tidak ikut repo. Cache dibuat otomatis di server saat ZIP diproses atau saat pelacakan pertama.

## Masalah umum

| Gejala | Penyebab dan langkah |
|---|---|
| Status ZIP tertahan di "Menunggu antrean" | Queue worker tidak jalan. Di lokal jalankan `composer run dev`, di server cek worker. |
| Titik tidak ikut berputar, diam di layar | Jalurnya belum ada: pelacakan masih di antrean, atau titik baru saja ditambahkan. Cek `tracks` di `storage/app/private/tracks/entry-{id}.json`. |
| Setelah Simpan, titik meleset sebentar lalu benar | Cache gerak belum ada, jadi pelacakan lewat antrean. Setelah job pertama selesai, Simpan berikutnya melacak langsung. |
| Simpan proyek terasa lambat | Titik yang berubah dilacak langsung, sekitar 3 detik per titik. |
| Panel Penempatan menulis "Gambar orbit belum tersedia." | Belum ada frame ZIP, gambar diam, atau frame contoh untuk section itu. |
| Angka X/Y merah | Isi angka 0 sampai 100. |
| Titik di atap bergeser dari tandanya | Koreksi lokal tidak menemukan pola yang cukup jelas (korelasi di bawah 0,6), jadi titik mengikuti bidang tanah. Taruh titik di bagian atap yang punya detail, misalnya tepi atau lubang atap. |
