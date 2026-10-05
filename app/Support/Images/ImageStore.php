<?php

namespace App\Support\Images;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

/**
 * Single entry point for every user-uploaded image (avatars, logos, gallery).
 *
 * Images are decoded and re-encoded to WebP, which drops EXIF data (camera
 * GPS location included) and anything smuggled after the pixels. Files get
 * random names and SVG is never accepted, since it can carry scripts.
 */
class ImageStore
{
    /** Validation rules every image upload must use. */
    public const array RULES = ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:max_width=8000,max_height=8000'];

    private const int WEBP_QUALITY = 82;

    public function __construct(private readonly string $disk = 'public') {}

    /**
     * Store the image scaled down to fit a square of $maxSize pixels and
     * return its path on the disk.
     */
    public function storeSquare(UploadedFile $file, string $directory, int $maxSize): string
    {
        $image = (new ImageManager(new Driver))
            ->read($file->getRealPath())
            ->coverDown($maxSize, $maxSize);

        $path = trim($directory, '/').'/'.Str::random(40).'.webp';

        Storage::disk($this->disk)->put($path, (string) $image->toWebp(quality: self::WEBP_QUALITY));

        return $path;
    }

    public function delete(?string $path): void
    {
        if ($path !== null && $path !== '') {
            Storage::disk($this->disk)->delete($path);
        }
    }

    public function url(?string $path): ?string
    {
        return $path === null ? null : Storage::disk($this->disk)->url($path);
    }
}
