<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Profile photo ceiling (kilobytes)
    |--------------------------------------------------------------------------
    |
    | What the server accepts BEFORE the photo is reduced. It was 2048, which is
    | smaller than most phone photos, so staff could not set a picture at all.
    |
    | The disk does not pay for a generous figure: App\Support\ProfilePhoto crops
    | every upload to a square 512px JPEG, so an 8MB photo is stored as roughly
    | 40KB. ProfilePhoto::maxKb() clamps this to the server's own
    | upload_max_filesize and post_max_size.
    |
    */

    'photo_max_kb' => env('PROFILE_PHOTO_MAX_KB', 12288),

];
