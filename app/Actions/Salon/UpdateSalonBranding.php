<?php

namespace App\Actions\Salon;

use App\Models\Salon;
use App\Support\BrandColor;
use App\Support\Images\ImageStore;
use Illuminate\Http\UploadedFile;

class UpdateSalonBranding
{
    public const int LOGO_SIZE = 512;

    public const int COVER_WIDTH = 1600;

    public const int COVER_HEIGHT = 900;

    public function __construct(private readonly ImageStore $images) {}

    public function setLogo(Salon $salon, UploadedFile $file): void
    {
        $this->replaceImage($salon, 'logo_path', $this->images->storeSquare($file, "salons/{$salon->id}/logo", self::LOGO_SIZE));
    }

    public function setCover(Salon $salon, UploadedFile $file): void
    {
        $path = $this->images->storeCropped($file, "salons/{$salon->id}/cover", self::COVER_WIDTH, self::COVER_HEIGHT);

        $this->replaceImage($salon, 'cover_path', $path);
    }

    /**
     * @param  'logo_path'|'cover_path'  $column
     */
    public function removeImage(Salon $salon, string $column): void
    {
        $this->replaceImage($salon, $column, null);
    }

    /**
     * Store the color the owner picked; the readable variants are derived
     * on the fly by BrandColor. Returns whether it had to be adjusted.
     */
    public function setColor(Salon $salon, ?string $hex): bool
    {
        $salon->brand_color = $hex === null ? null : BrandColor::normalize($hex);
        $salon->save();

        return $salon->brandTokens()['adjusted'] ?? false;
    }

    /**
     * @param  'logo_path'|'cover_path'  $column
     */
    private function replaceImage(Salon $salon, string $column, ?string $path): void
    {
        $previous = $salon->{$column};

        $salon->{$column} = $path;
        $salon->save();

        $this->images->delete($previous);
    }
}
