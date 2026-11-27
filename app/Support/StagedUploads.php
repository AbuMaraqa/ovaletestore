<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Files uploaded ahead of a form save (product images on the add/edit screens): each file is sent on its own
 * as soon as it is picked, so a validation error elsewhere in the form never re-uploads the photos and no
 * request carries 10 images at once. The form then sends only the returned ids; the save attaches them.
 *
 * Private disk (`local`, storage/app/private), one folder per user: an id can only be claimed by the user
 * who uploaded it. Unclaimed files are pruned after a day (routes/console.php).
 */
final class StagedUploads
{
    private const DIR = 'staged-uploads';

    public static function put(UploadedFile $file, User $user): string
    {
        $id = (string) Str::uuid();
        $file->storeAs(self::folder($user), $id.'.'.($file->guessExtension() ?: 'bin'), 'local');

        return $id;
    }

    /** Absolute path of a staged file owned by this user, or null. */
    public static function path(User $user, string $id): ?string
    {
        if (! Str::isUuid($id)) {
            return null;
        }
        $disk = Storage::disk('local');
        $match = collect($disk->files(self::folder($user)))->first(fn (string $f) => pathinfo($f, PATHINFO_FILENAME) === $id);

        return $match ? $disk->path($match) : null;
    }

    public static function forget(User $user, string $id): void
    {
        if ($path = self::path($user, $id)) {
            @unlink($path);
        }
    }

    /** Removes staged files older than the given age. @return int files removed */
    public static function prune(int $hours = 24): int
    {
        $disk = Storage::disk('local');
        $removed = 0;
        foreach ($disk->allFiles(self::DIR) as $file) {
            if ($disk->lastModified($file) < now()->subHours($hours)->getTimestamp()) {
                $disk->delete($file);
                $removed++;
            }
        }

        return $removed;
    }

    private static function folder(User $user): string
    {
        return self::DIR.'/'.$user->getKey();
    }
}
