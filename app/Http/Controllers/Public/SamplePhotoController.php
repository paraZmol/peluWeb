<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Support\SamplePhotos;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Serves the bundled sample photos (only files matching the allowed
 * pattern, from that one folder) for previews and the landing page.
 */
class SamplePhotoController extends Controller
{
    private const int CACHE_SECONDS = 60 * 60 * 24 * 30;

    public function show(string $file): BinaryFileResponse
    {
        $path = SamplePhotos::directory().DIRECTORY_SEPARATOR.$file;

        abort_unless(preg_match('/^'.SamplePhotos::FILE_PATTERN.'$/', $file) === 1 && is_file($path), 404);

        return response()->file($path, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'public, max-age='.self::CACHE_SECONDS.', immutable',
        ]);
    }
}
