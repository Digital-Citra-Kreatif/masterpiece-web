<?php

// Data beranda hardcoded: hero cadangan dan scene contoh proyek

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
    'cluster' => [
        'title' => 'Cluster',
        'intro' => 'Lebih dekat ke cluster ruko. Gulir untuk mengelilingi blok, klik penanda untuk menuju setiap titik.',
        'hotspots' => [
            $hotspot('cluster-plaza', 'Plaza cluster', 'Plaza', 'Ruang terbuka di tengah cluster, titik temu antara blok utara dan selatan.'),
            $hotspot('cluster-lakeside', 'Ruko hook tepi danau', 'Ruko hook', 'Unit sudut di ujung blok dengan dua muka bangunan dan pemandangan danau.'),
            $hotspot('cluster-commercial', 'Akses ke pusat komersial', 'Akses komersial', 'Cluster terhubung langsung ke pusat komersial melalui boulevard utama.'),
        ],
    ],
];
