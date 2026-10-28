<?php

// Data beranda hardcoded

$hotspot = fn (string $key, string $title, string $short, string $text): array => compact('key', 'title', 'short', 'text');

return [
    'hero' => [
        'title' => "Quiet Living,\nClose to Everything.",
        'subtitle' => "A residential cluster in Jakarta,\ndesigned for growing families.",
        'background' => 'slider',
        'slides' => [
            ['type' => 'image', 'src' => 'media/stills/ruko-front-34.jpg', 'alt' => ''],
            ['type' => 'image', 'src' => 'media/stills/fac-lake.jpg', 'alt' => ''],
            ['type' => 'video', 'src' => 'media/hero/teaser.mp4', 'alt' => ''],
            ['type' => 'image', 'src' => 'media/stills/fac-boulevard.jpg', 'alt' => ''],
        ],
        'cover' => 'media/hero/cover.jpg',
        'fullThumb' => 'images/hero-video-thumb.jpg',
        'teaser' => 'media/hero/teaser.mp4',
        'full' => 'media/hero/full.mp4',
    ],
    'masterplan' => [
        'title' => 'Masterplan',
        'text' => "A residential cluster in Jakarta,\ndesigned for growing families.",
    ],
    'kawasan' => [
        'title' => 'Masterplan',
        'intro' => '',
        'hotspots' => [
            $hotspot('area-plaza', 'Plaza & boulevard ruko', 'Plaza', 'Poros tengah kawasan yang membelah empat blok ruko. Plaza terbuka di ujungnya menghadap danau.'),
            $hotspot('area-commercial', 'Pusat komersial', 'Komersial', 'Bangunan komersial utama kawasan, dikelilingi area parkir dan jalur pejalan kaki.'),
            $hotspot('area-offices', 'Perkantoran', 'Kantor', 'Gedung perkantoran di sisi jalan utama, dekat akses masuk kawasan.'),
            $hotspot('area-lakeside', 'Tepi danau', 'Danau', 'Promenade di sepanjang danau, terhubung langsung dengan deretan ruko sisi selatan.'),
        ],
    ],
    'lokasi' => [
        'title' => 'Lokasi',
        'intro' => 'Posisi proyek di dalam kawasan yang lebih besar.',
        'map' => 'media/placeholder/area-map.svg',
        'alt' => 'Peta kawasan dengan lokasi proyek ditandai',
        'width' => 1600,
        'height' => 900,
        'placeholder' => true,
    ],
    'cluster' => [
        'title' => 'Cluster',
        'intro' => 'Lebih dekat ke cluster ruko. Gulir untuk mengelilingi blok, klik penanda untuk menuju setiap titik.',
        'hotspots' => [
            $hotspot('cluster-plaza', 'Plaza cluster', 'Plaza', 'Ruang terbuka di tengah cluster, titik temu antara blok utara dan selatan.'),
            $hotspot('cluster-lakeside', 'Ruko hook tepi danau', 'Ruko hook', 'Unit sudut di ujung blok dengan dua muka bangunan dan pemandangan danau.'),
            $hotspot('cluster-commercial', 'Akses ke pusat komersial', 'Akses komersial', 'Cluster terhubung langsung ke pusat komersial melalui boulevard utama.'),
        ],
    ],
    // Judul section
    'headings' => [
        'fasilitas' => ['title' => 'Ruang bersama', 'intro' => 'Fasilitas yang melengkapi kawasan, dari tepi danau hingga pusat komersial.'],
        'tipe' => ['title' => 'Pilih tipe', 'intro' => 'Bandingkan luas tanah, luas bangunan, dan jumlah kamar. Buka satu tipe untuk menjelajahi fasad dan setiap lantainya.'],
        'unit_update' => ['title' => 'Status unit', 'intro' => 'Status terbaru setiap unit di siteplan.'],
    ],
    'fasilitas' => [
        ['title' => 'Danau & promenade', 'image' => 'media/stills/fac-lake.jpg'],
        ['title' => 'Lapangan olahraga & kolam renang', 'image' => 'media/stills/fac-sport.jpg'],
        ['title' => 'Pusat komersial', 'image' => 'media/stills/fac-commercial.jpg'],
        ['title' => 'Boulevard utama', 'image' => 'media/stills/fac-boulevard.jpg'],
        ['title' => 'Deret ruko tepi air', 'image' => 'media/stills/fac-plaza.jpg'],
        ['title' => 'Ruang hijau', 'image' => 'media/stills/fac-green.jpg'],
    ],
    // Kartu tipe
    'tipe' => [
        ['tipe-1', 'Tipe 1', 'Ruko Hook', 'media/stills/ruko-front-34.jpg', 120, 180, 2, 2],
        ['tipe-2', 'Tipe 2', null, 'media/stills/ruko-34.jpg', 90, 150, 2, 2],
        ['tipe-3', 'Tipe 3', null, 'media/stills/ruko-side-2.jpg', 100, 165, 3, 2],
        ['tipe-4', 'Tipe 4', null, 'media/stills/ruko-front.jpg', 110, 170, 3, 3],
        ['tipe-5', 'Tipe 5', null, 'media/stills/ruko-back-34.jpg', 126, 190, 3, 3],
        ['tipe-6', 'Tipe 6', null, 'media/stills/ruko-side.jpg', 140, 240, 4, 3],
    ],
];
