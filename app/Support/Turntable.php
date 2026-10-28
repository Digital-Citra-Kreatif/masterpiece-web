<?php

namespace App\Support;

/** Paket WebRotate dari daftar frame */
final class Turntable
{
    /** Folder config: /turntable/{project}/ */
    public static function root(int $project): string
    {
        return url("turntable/{$project}").'/';
    }

    /**
     * WebRotate membaca gambar relatif ke folder config, jadi src naik dua folder ke path asli.
     *
     * @param  list<string>  $frames
     * @return list<string>
     */
    public static function images(array $frames): array
    {
        return array_map(function (string $frame): string {
            $parts = parse_url($frame);
            $query = isset($parts['query']) ? '?'.$parts['query'] : '';

            return '../..'.($parts['path'] ?? '/').$query;
        }, $frames);
    }

    /**
     * Isi config.xml
     *
     * @param  list<string>  $images
     */
    public static function config(array $images, int $width, int $height): string
    {
        $attr = fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_XML1);
        $list = implode('', array_map(fn (string $image): string => '<image src="'.$attr($image).'"></image>', $images));

        return '<?xml version="1.0" encoding="utf-8"?>'
            .'<config><settings>'
            .'<preloader image="'.$attr($images[0]).'" />'
            .'<userInterface showZoomButtons="false" showToolTips="false" showHotspotsButton="false" showFullScreenButton="false" showTogglePlayButton="false" showArrows="false" showProgressNumbers="false" toolbarAutohide="false" skin="empty" />'
            .'<control dragSpeed="0.12" doubleClickZooms="false" singleClickZooms="false" disableMouseControl="false" mouseHoverDrag="false" mouseWheelDrag="false" hideHotspotsOnLoad="false" hideHotspotsOnZoom="true" dragSensitivity="10" inBrowserFullScreen="false" doubleClickFullscreen="false" pauseOnPreload="false" />'
            .'<rotation firstImage="0" rotate="false" rotatePeriod="24" bounce="false" rotateDirection="1" useInertia="true" inertiaRelToDragSpeed="true" inertiaTimeToStop="700" inertiaMaxInterval="120" />'
            .'</settings><hotspots></hotspots>'
            .'<images rows="1" highresWidth="'.$width.'" highresHeight="'.$height.'">'.$list.'</images>'
            .'</config>';
    }
}
