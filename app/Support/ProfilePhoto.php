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

    /**
     * Square centre crop at AVATAR_PX, as JPEG bytes. Null if undecodable.
     *
     * Imagick is tried FIRST, and the order matters more than it looks. GD
     * decodes a JPEG into a full truecolor bitmap - four bytes per pixel,
     * whatever the file weighs - so a 48MP phone photo needs about 192MB. The
     * web process has 256MB, and crossing it is not an exception that can be
     * caught: the request dies and the member of staff sees a blank failure.
     *
     * Measured on production: 12MP peaked at 124MB, 27MP at 242MB, and 48MP
     * killed the process outright. That is the whole of "some staff can upload
     * a photo and some cannot" - it follows the camera in their phone and
     * nothing about them. File size does not predict it; a 48MP photo can be
     * 5MB on disk.
     *
     * Imagick's jpeg:size hint makes the decoder scale during the DCT pass, so
     * it never holds the full bitmap and memory stays roughly flat whatever the
     * input. It also reads HEIC where the delegate is installed.
     */
    private static function toSquareJpeg(UploadedFile $file): ?string
    {
        $raw = @file_get_contents($file->getRealPath());
        if ($raw === false || $raw === '') {
            return null;
        }

        if (class_exists(\Imagick::class)) {
            $jpeg = self::viaImagick($raw);
            if ($jpeg !== null) {
                return $jpeg;
            }
        }

        // GD only decodes at full size, so refuse what would not fit rather
        // than letting the request die with nothing to show for it.
        if (function_exists('imagecreatefromstring') && self::fitsInMemory($raw)) {
            return self::viaGd($raw, $file->getRealPath());
        }

        return null;
    }

    /** Decode pre-scaled, so memory does not track the input resolution. */
    private static function viaImagick(string $raw): ?string
    {
        try {
            $im = new \Imagick();
            // Ask the JPEG decoder for something near the size we need. Ignored
            // by other formats, which is harmless.
            $im->setOption('jpeg:size', (self::AVATAR_PX * 2) . 'x' . (self::AVATAR_PX * 2));
            $im->readImageBlob($raw);
            $im->autoOrient();
            $im->setImageBackgroundColor('white');
            $im = $im->flattenImages();
            $im->cropThumbnailImage(self::AVATAR_PX, self::AVATAR_PX);
            $im->setImageFormat('jpeg');
            $im->setImageCompressionQuality(self::JPEG_QUALITY);
            $blob = $im->getImageBlob();
            $im->clear();
            $im->destroy();

            return $blob ?: null;
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    /**
     * Would GD's full-size decode fit in what is left of the memory limit?
     *
     * Four bytes per pixel for the bitmap, plus the raw bytes already held, plus
     * the output canvas, plus headroom for everything else in the request.
     */
    private static function fitsInMemory(string $raw): bool
    {
        $info = @getimagesizefromstring($raw);
        if (! $info) {
            return false;
        }

        $limit = self::iniBytes(ini_get('memory_limit'));
        if ($limit <= 0) {
            return true;       // unlimited
        }

        $needed = ($info[0] * $info[1] * 4)
            + strlen($raw)
            + (self::AVATAR_PX * self::AVATAR_PX * 4);

        return ($needed + memory_get_usage(true)) < ($limit * 0.8);
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
