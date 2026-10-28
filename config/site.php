<?php

/*
 * Developer-only settings of the public site. Everything an editor changes
 * lives in the CMS instead (Globals → Situs, the homepage, Tipe, Unit).
 */

return [

    /*
     * Small "contoh" badges on stand-in media and data. Off: the site is presented as live.
     */
    'placeholder_badges' => (bool) env('SITE_PLACEHOLDER_BADGES', false),

    /*
     * The CMS menu (Content → Menus) the home header shows, by handle. Without
     * links there, the header lists the homepage sections (Globals → Situs).
     */
    'menu' => 'primary_menu',

    /*
     * The header logo: one for the transparent header over the hero, one for the solid header.
     *
     * @var array{light: string, dark: string, width: int, height: int}
     */
    'logo' => [
        'light' => 'images/masterpiece-logo.png',
        'dark' => 'images/masterpiece-logo-dark.png',
        'width' => 599,
        'height' => 105,
    ],

];
