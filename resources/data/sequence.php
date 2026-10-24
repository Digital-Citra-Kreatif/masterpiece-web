<?php

// The image sequence every type page shows (a WebRotate package built by
// scripts/build-media.mjs). Its parts (facade, then each floor) are keyframes in
// it: choosing a part, by scrolling or from the panel, animates the sequence to
// that frame and shows the part's text and denah (copy in the CMS: Globals →
// Tipe: bersama).
//
// Only Tipe 1 has real renders so far (the "Ruko Hook 01" facade orbit); the
// other types reuse it, flagged `placeholder` unless the type's "Fasad asli" is on.

return [
    'package' => '/media/tipe-1/facade/',
    'poster' => '/media/tipe-1/facade/images/facade_000.jpg',
    'width' => 760,
    'height' => 570,
    'firstImage' => 0,
    'frames' => 120,
];
