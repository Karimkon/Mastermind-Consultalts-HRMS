<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Document upload ceiling (kilobytes)
    |--------------------------------------------------------------------------
    |
    | Applies to controlled documents and quality goal evidence. No file format
    | is refused - a controlled document may be a PDF policy, an Excel register,
    | a CAD drawing, a scanned manual or a training video - so size is the only
    | limit there is.
    |
    | QualityDocument::maxUploadKb() clamps this to the server's own
    | upload_max_filesize and post_max_size, because PHP rejects an oversized
    | request before Laravel validation ever sees it. Raising this number alone
    | will not raise the real limit; php.ini has to allow it too.
    |
    */

    'max_upload_kb' => env('QUALITY_MAX_UPLOAD_KB', 51200),

];
