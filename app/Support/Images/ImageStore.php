<?php

namespace App\Support\Images;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;
use Throwable;

/**
 * Single entry point for every user-uploaded image (avatars, logos, gallery).
 *
 * Images are decoded and re-encoded to WebP, which drops EXIF data (camera
 * GPS location included) and anything smuggled after the pixels. Files get
 * random names and SVG is never accepted, since it can carry scripts.
 */
class ImageStore
{
    /**
     * Validation rules every image upload must use. The pixel cap matters:
     * a tiny PNG of 8000x8000 decodes to ~256 MB and kills the worker.
     */
    public const array RULES = ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:max_width=4000,max_height=4000'];

    private const int WEBP_QUALITY = 82;

    public function __construct(private readonly string $disk = 'public') {}

    /**
     * Store the image cropped to a square of at most $maxSize pixels
     * (avatars, logos) and return its path on the disk.
     */
    public function storeSquare(UploadedFile $file, string $directory, int $maxSize): string
    {
        return $this->storeCropped($file, $directory, $maxSize, $maxSize);
    }

    /**
     * Store the image cropped to the given box, never upscaled (covers),
     * and return its path on the disk.
     */
    public function storeCropped(UploadedFile $file, string $directory, int $width, int $height): string
    {
        $image = $this->read($file)->coverDown($width, $height);

        return $this->save($image, $directory);
    }

    /**
     * Store the image scaled to fit inside the box keeping its proportions
     * (gallery photos, where cropping would cut the haircut).
     */
    public function storeFitted(UploadedFile $file, string $directory, int $maxWidth, int $maxHeight): string
    {
        $image = $this->read($file)->scaleDown($maxWidth, $maxHeight);

        return $this->save($image, $directory);
    }

    private function read(UploadedFile $file): ImageInterface
    {
        try {
            return (new ImageManager(new Driver))->read($file->getRealPath());
        } catch (Throwable) {
            // The header looked like an image but the rest does not decode.
            throw ValidationException::withMessages([$this->field($file) => 'No pudimos leer esa imagen. Prueba con otra foto.']);
        }
    }

    /**
     * Re-encode to WebP (drops EXIF/GPS and any trailing payload) under a
     * random name.
     */
    private function save(ImageInterface $image, string $directory): string
    {
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

    /**
     * The form field the file came in, to hang the error on it.
     */
    private function field(UploadedFile $file): string
    {
        foreach (request()->allFiles() as $name => $uploaded) {
            if ($uploaded === $file) {
                return (string) $name;
            }
        }

        return 'photo';
    }
}
