<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Sensitive uploads (NID, licences, vehicle and vendor papers) live on the
 * private `local` disk and are only reachable through DocumentController,
 * which checks permissions. Profile photos stay on the public disk.
 */
class PrivateDocument
{
    public const DISK = 'local';

    /**
     * Store the upload (with a unique hashed name) and delete the file it
     * replaces, returning the new path.
     */
    public static function replace(?string $oldPath, UploadedFile $file, string $directory): string
    {
        self::delete($oldPath);

        return $file->store($directory, self::DISK);
    }

    public static function delete(?string $path): void
    {
        if ($path) {
            Storage::disk(self::DISK)->delete($path);
        }
    }

    public static function response(?string $path, bool $download = false): StreamedResponse
    {
        abort_unless($path && Storage::disk(self::DISK)->exists($path), 404);

        return $download
            ? Storage::disk(self::DISK)->download($path)
            : Storage::disk(self::DISK)->response($path);
    }
}
