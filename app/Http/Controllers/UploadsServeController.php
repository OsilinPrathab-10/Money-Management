<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class UploadsServeController extends Controller
{
    /**
     * Serve a file from public/uploads.
     *
     * Live DocumentRoot is the repo root, so /uploads/... is not a real file
     * on disk (it lives under public/uploads). Without this route those
     * requests fall through Laravel and 404 — which is what the customer
     * app hits for app logos, splash screens, and banners.
     */
    public function __invoke(Request $request, string $path): BinaryFileResponse
    {
        $path = str_replace(['../', '..\\'], '', $path);
        $path = ltrim(str_replace('\\', '/', $path), '/');

        $base = realpath(public_path('uploads'));
        $full = $base ? realpath($base . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path)) : false;

        if (! $base || ! $full || ! is_file($full) || ! str_starts_with($full, $base)) {
            abort(404);
        }

        $mime = mime_content_type($full) ?: 'application/octet-stream';

        return response()->file($full, [
            'Content-Type' => $mime,
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
