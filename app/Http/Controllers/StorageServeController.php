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
