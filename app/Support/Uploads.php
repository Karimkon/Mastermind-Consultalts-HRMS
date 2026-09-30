<?php

namespace App\Support;

/**
 * The one place that answers "how big, and what kind" for an attachment.
 *
 * Every attach-a-file screen used to spell its own rule out — `max:10240` in
 * the controller, "up to 10MB" typed into the Blade beside it. Two statements
 * of the same fact, neither aware of the other, so raising one silently left
 * the other lying to whoever was reading it.
 *
 * The browser now checks the same numbers before it starts sending, which is
 * the part that actually matters: a size rule in a controller can only run
 * once the whole file has arrived, so a 205MB clip spent several minutes
 * uploading and was then told it was too big.
 */
class Uploads
{
    public static function maxMb(): int
    {
        return (int) config('uploads.max_attachment_mb', 512);
    }

    /** Laravel's max: rule counts kilobytes. */
    public static function maxKb(): int
    {
        return self::maxMb() * 1024;
    }

    public static function maxBytes(): int
    {
        return self::maxKb() * 1024;
    }

    /** @return array<int,string> */
    public static function extensions(): array
    {
        return (array) config('uploads.attachment_extensions', []);
    }

    /**
     * The validation rules for an attachment field.
     *
     * `extensions` rather than `mimes`: the list deliberately includes formats
     * whose MIME type the framework's map does not carry (heic from an iPhone,
     * amr from a voice recorder), and `mimes` would reject those as unknown
     * even when they are exactly what was asked for. Nothing here is ever
     * executed — attachments are stored off the web root on the local disk and
     * only ever come back out through a controller that sends them as a
     * download — so extension is the right level to check at.
     *
     * @param  bool  $required
     * @return array<int,string>
     */
    public static function rules(bool $required = true): array
    {
        return [
            $required ? 'required' : 'nullable',
            'file',
            'max:' . self::maxKb(),
            'extensions:' . implode(',', self::extensions()),
        ];
    }

    /** Messages that say the limit rather than making somebody guess it. */
    public static function messages(string $field = 'file'): array
    {
        return [
            "{$field}.max"        => 'That file is larger than ' . self::maxMb() . 'MB.',
            "{$field}.extensions" => 'That file type is not accepted. Allowed: ' . self::humanList() . '.',
        ];
    }

    /** For the `accept` attribute on the file input, so the picker filters too. */
    public static function accept(): string
    {
        return '.' . implode(',.', self::extensions());
    }

    /** Short human summary for the hint under the field. */
    public static function humanList(): string
    {
        return 'PDF, Office, images, video, audio or zip';
    }

    /** The full sentence shown under a file input. */
    public static function hint(): string
    {
        return self::humanList() . ' — up to ' . self::maxMb() . 'MB.';
    }
}
