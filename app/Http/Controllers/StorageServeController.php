<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StorageServeController extends Controller
{
    /**
     * Serve a file from the public storage disk.
     * Fixes 403 when symlink is missing or not followed (e.g. Windows/XAMPP).
     */
    public function __invoke(Request $request, string $path): \Symfony\Component\HttpFoundation\Response
    {
        $path = str_replace(['../', '..\\'], '', $path);

        if (! Storage::disk('public')->exists($path)) {
            $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'], true)) {
                $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 128 128" width="128" height="128">
                    <rect width="128" height="128" fill="#F3F4F6" rx="64"/>
                    <path fill="#9CA3AF" d="M64 24a24 24 0 100 48 24 24 0 000-48zM28 104c0-19.9 16.1-36 36-36s36 16.1 36 36H28z"/>
                </svg>';

                return response($svg, 200, [
                    'Content-Type' => 'image/svg+xml',
                    'Cache-Control' => 'public, max-age=86400',
                ]);
            }

            abort(404);
        }

        $mimeType = Storage::disk('public')->mimeType($path);
        $filename = basename($path);

        return Storage::disk('public')->response($path, $filename, [
            'Content-Type' => $mimeType ?: 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
        ]);
    }
}
