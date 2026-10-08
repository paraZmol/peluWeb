<?php

namespace App\Support;

use App\Enums\BusinessType;

/**
 * The bundled demo photos (database/seeders/images, see CREDITS.md) used
 * to show an owner how their page would look once they add photos. Served
 * read-only by SamplePhotoController.
 */
final class SamplePhotos
{
    /** Filenames that may be served; nothing else in that folder is. */
    public const string FILE_PATTERN = '[a-z]+-[0-9]+(-square)?\.jpg';

    /**
     * @var array<string, array{cover: string, gallery: list<string>, portraits: list<string>}>
     */
    private const array BY_BUSINESS = [
        'barbershop' => [
            'cover' => 'pexels-1813272.jpg',
            'gallery' => ['pexels-1836983.jpg', 'pexels-2076932.jpg', 'pexels-3998417.jpg', 'pexels-7518728.jpg'],
            'portraits' => ['pexels-897265-square.jpg', 'pexels-18483774-square.jpg', 'pexels-4625648-square.jpg'],
        ],
        'beauty_salon' => [
            'cover' => 'pexels-3993133.jpg',
            'gallery' => ['pexels-3993311.jpg', 'pexels-2799605.jpg', 'pexels-3065171.jpg', 'pexels-705255.jpg'],
            'portraits' => ['pexels-3992875-square.jpg', 'pexels-3993312-square.jpg', 'pexels-7440133-square.jpg'],
        ],
        'nails' => [
            'cover' => 'pexels-3422099.jpg',
            'gallery' => ['pexels-22668324.jpg', 'pexels-16363470.jpg', 'pexels-14267564.jpg', 'pexels-17056221.jpg'],
            'portraits' => ['pexels-3422099-square.jpg', 'pexels-3993312-square.jpg', 'pexels-3992875-square.jpg'],
        ],
        'default' => [
            'cover' => 'pexels-705255.jpg',
            'gallery' => ['pexels-7518731.jpg', 'pexels-3065170.jpg', 'pexels-3356170.jpg', 'pexels-1319462.jpg'],
            'portraits' => ['pexels-7518731-square.jpg', 'pexels-3993312-square.jpg', 'pexels-4625648-square.jpg'],
        ],
    ];

    public static function directory(): string
    {
        return database_path('seeders/images');
    }

    public static function cover(BusinessType $type): string
    {
        return self::url(self::set($type)['cover']);
    }

    /**
     * @return list<array{url: string, caption: string|null}>
     */
    public static function gallery(BusinessType $type): array
    {
        return array_map(fn (string $file) => ['url' => self::url($file), 'caption' => null], self::set($type)['gallery']);
    }

    /**
     * A portrait for the n-th professional without a photo.
     */
    public static function portrait(BusinessType $type, int $index): string
    {
        $portraits = self::set($type)['portraits'];

        return self::url($portraits[$index % count($portraits)]);
    }

    /**
     * @return array{cover: string, gallery: list<string>, portraits: list<string>}
     */
    private static function set(BusinessType $type): array
    {
        return self::BY_BUSINESS[$type->value] ?? self::BY_BUSINESS['default'];
    }

    private static function url(string $file): string
    {
        return route('samples.show', $file);
    }
}
