<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Taking a profile photo and turning it into an avatar.
 *
 * The rule used to be `image|max:2048`, which is two megabytes. A photo taken on
 * any phone made in the last decade is three to eight, so choosing one failed
 * validation and the only thing staff saw was an error - the picture they had
 * just taken was simply refused. One of the avatars already on the system is
 * 1.5MB, so the ceiling was barely above what was already in use.
 *
 * Rather than storing whatever arrives, the photo is now accepted large and
 * reduced here: a square centre crop at AVATAR_PX, re-encoded as JPEG. An 8MB
 * phone photo lands as roughly 40KB, so the limit can be generous without the
 * disk paying for it.
 *
 * EXIF orientation is applied before cropping. Phones record the rotation in
 * metadata rather than in the pixels, so a photo taken in portrait arrives on
 * its side; that is the other half of "the photo upload is broken".
 */
class ProfilePhoto
{
    /** Avatars are shown at 128px at most; 512 keeps them crisp on retina. */
    private const AVATAR_PX = 512;

    private const JPEG_QUALITY = 85;

    /** What the server will take, before it is reduced. */
    public static function maxKb(): int
    {
        $limit = (int) config('profile.photo_max_kb', 12288);

        // PHP refuses an oversized request before validation ever runs, so the
        // advertised limit must not exceed what this server will accept.
        foreach ([ini_get('upload_max_filesize'), ini_get('post_max_size')] as $ini) {
            $kb = (int) (self::iniBytes($ini) / 1024);
            if ($kb > 0 && $kb < $limit) {
                $limit = $kb;
            }
        }

        return max(1024, $limit);
    }

    private static function iniBytes($value): int
    {
        $value = trim((string) $value);
        if ($value === '' || $value === '-1') {
            return 0;
        }

        $number = (int) $value;

        return match (strtolower(substr($value, -1))) {
            'g' => $number * 1024 * 1024 * 1024,
            'm' => $number * 1024 * 1024,
            'k' => $number * 1024,
            default => $number,
        };
    }

    /**
     * Validation rules, shared by the web and the mobile API.
     *
     * `mimes` rather than `image`: the `image` rule rejects HEIC, which is what
     * an iPhone produces whenever the browser does not convert it on the way
     * out, and being told your own camera roll is not an image is a poor answer.
     */
    public static function rules(): array
    {
        return [
            'avatar' => [
                'required',
                'file',
                'mimes:jpeg,jpg,png,gif,webp,bmp,heic,heif',
                'max:' . self::maxKb(),
            ],
        ];
    }

    public static function messages(): array
    {
        $mb = round(self::maxKb() / 1024);

        return [
            'avatar.max' => "That photo is larger than {$mb} MB. Try again with a smaller one.",
            'avatar.mimes' => 'That file is not a picture. Use a photo from your camera or gallery.',
        ];
    }

    /**
     * Store the photo and return its path on the public disk.
     *
     * Deletes whatever the user had before, so the disk does not collect every
     * picture anybody has ever set.
     */
    public static function store(UploadedFile $file, ?string $replacing = null): string
    {
        $jpeg = self::toSquareJpeg($file);

        if ($jpeg !== null) {
            $path = 'avatars/' . Str::random(40) . '.jpg';
            Storage::disk('public')->put($path, $jpeg);
        } else {
            // Nothing on this server could decode it - HEIC without the delegate,
            // most likely. Keep the original rather than losing the upload; the
            // browser may still render it, and the record is at least not lost.
            $path = $file->store('avatars', 'public');
        }

        if ($replacing && $replacing !== $path) {
            Storage::disk('public')->delete($replacing);
        }

        return $path;
    }

    /** Square centre crop at AVATAR_PX, as JPEG bytes. Null if undecodable. */
    private static function toSquareJpeg(UploadedFile $file): ?string
    {
        $raw = @file_get_contents($file->getRealPath());
        if ($raw === false || $raw === '') {
            return null;
        }

        if (function_exists('imagecreatefromstring')) {
            $jpeg = self::viaGd($raw, $file->getRealPath());
            if ($jpeg !== null) {
                return $jpeg;
            }
        }

        // GD cannot read HEIC; Imagick can when the delegate is installed.
        if (class_exists(\Imagick::class)) {
            try {
                $im = new \Imagick();
                $im->readImageBlob($raw);
                $im->autoOrient();
                $im->setImageBackgroundColor('white');
                $im = $im->flattenImages();
                $im->cropThumbnailImage(self::AVATAR_PX, self::AVATAR_PX);
                $im->setImageFormat('jpeg');
                $im->setImageCompressionQuality(self::JPEG_QUALITY);
                $blob = $im->getImageBlob();
                $im->clear();

                return $blob;
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return null;
    }

    private static function viaGd(string $raw, string $path): ?string
    {
        $src = @imagecreatefromstring($raw);
        if ($src === false) {
            return null;
        }

        try {
            $src = self::applyExifOrientation($src, $path);

            $w = imagesx($src);
            $h = imagesy($src);
            $side = min($w, $h);
            $x = (int) (($w - $side) / 2);
            $y = (int) (($h - $side) / 2);

            $out = imagecreatetruecolor(self::AVATAR_PX, self::AVATAR_PX);
            // A transparent PNG would otherwise go black once it is JPEG.
            imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255));
            imagecopyresampled($out, $src, 0, 0, $x, $y,
                self::AVATAR_PX, self::AVATAR_PX, $side, $side);

            ob_start();
            imagejpeg($out, null, self::JPEG_QUALITY);
            $jpeg = ob_get_clean();

            imagedestroy($out);
            imagedestroy($src);

            return $jpeg ?: null;
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    /**
     * Phones store rotation in EXIF rather than rotating the pixels, so a photo
     * taken in portrait arrives on its side unless this is applied.
     */
    private static function applyExifOrientation($image, string $path)
    {
        if (! function_exists('exif_read_data')) {
            return $image;
        }

        $exif = @exif_read_data($path);
        $orientation = $exif['Orientation'] ?? null;

        if (! $orientation) {
            return $image;
        }

        $rotated = match ((int) $orientation) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => null,
        };

        if ($rotated) {
            imagedestroy($image);

            return $rotated;
        }

        return $image;
    }
}
