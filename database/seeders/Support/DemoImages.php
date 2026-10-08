<?php

namespace Database\Seeders\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

/**
 * The demo photos bundled in database/seeders/images (see CREDITS.md there),
 * handed to the same Actions a real salon uses, so the demo goes through
 * the real image pipeline (WebP, random names, sizes).
 */
final class DemoImages
{
    private const string DIRECTORY = __DIR__.'/../images';

    /** A photo by its Pexels id, e.g. 1813272. */
    public static function photo(int $id): UploadedFile
    {
        return self::upload(self::DIRECTORY."/pexels-{$id}.jpg", "pexels-{$id}.jpg");
    }

    /** The square crop of a photo, for a professional's portrait. */
    public static function portrait(int $id): UploadedFile
    {
        return self::upload(self::DIRECTORY."/pexels-{$id}-square.jpg", "pexels-{$id}-square.jpg");
    }

    /**
     * A logo for a salon that has none: its initials on its brand color.
     */
    public static function monogram(string $name, string $color): UploadedFile
    {
        $initials = Str::of($name)->explode(' ')
            ->filter(fn (string $word) => $word !== '' && ctype_alnum(mb_substr($word, 0, 1)))
            ->take(2)
            ->map(fn (string $word) => mb_strtoupper(mb_substr($word, 0, 1)))
            ->join('');

        $image = (new ImageManager(new Driver))->create(512, 512)->fill($color);
        $image->text($initials ?: 'P', 256, 256, function ($font) use ($color): void {
            $font->filename(self::fontFile());
            $font->size(220);
            $font->color(self::isDark($color) ? '#ffffff' : '#111111');
            $font->align('center');
            $font->valign('middle');
        });

        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'peluweb-demo-'.Str::random(12).'.png';
        $image->save($path);

        return new UploadedFile($path, 'logo.png', 'image/png', null, true);
    }

    /**
     * A copy in the temp folder: the Actions may move or delete what they
     * receive, and the bundled originals must stay.
     */
    private static function upload(string $source, string $name): UploadedFile
    {
        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'peluweb-demo-'.Str::random(12).'.jpg';
        copy($source, $path);

        return new UploadedFile($path, $name, 'image/jpeg', null, true);
    }

    private static function fontFile(): string
    {
        foreach (['C:/Windows/Fonts/arialbd.ttf', '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf', '/System/Library/Fonts/Supplemental/Arial Bold.ttf'] as $font) {
            if (file_exists($font)) {
                return $font;
            }
        }

        // GD's built-in font: ugly but never missing.
        return '';
    }

    private static function isDark(string $hex): bool
    {
        [$r, $g, $b] = sscanf(ltrim($hex, '#'), '%02x%02x%02x');

        return (0.299 * $r + 0.587 * $g + 0.114 * $b) < 150;
    }
}
