<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Evidence attachments
    |--------------------------------------------------------------------------
    |
    | One place for the size and type rules that every "attach a file" screen
    | shares — PIP and goal evidence, appraisal attachments, and the employee's
    | own documents. They used to be written out per controller, with the
    | wording on the screen ("up to 10MB") hard-coded separately from the rule
    | that enforced it, so the two could drift apart without anybody noticing.
    |
    | The cap was 10MB. It was raised because the people using this attach
    | photographs and phone video as evidence on an improvement plan, and a
    | 205MB clip was silently refused — after the whole thing had finished
    | uploading, because a size rule only runs once the file has arrived.
    |
    | The ceiling above this is the server's own: post_max_size and
    | upload_max_filesize are both 2048M on the live host, so there is room,
    | but a file this size still takes real minutes on a Ugandan connection.
    | The browser now checks the size before it starts sending.
    |
    */

    'max_attachment_mb' => 512,

    /*
    | Extensions accepted on an evidence attachment. Video and audio are here
    | because a photograph or a short clip is often the only practical evidence
    | somebody on a site can produce.
    */
    'attachment_extensions' => [
        // documents
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'ppt', 'pptx', 'txt',
        // images
        'png', 'jpg', 'jpeg', 'webp', 'gif', 'heic',
        // video
        'mp4', 'mov', 'm4v', 'avi', 'mkv', 'webm', '3gp',
        // audio — a voice note from a supervisor on site
        'mp3', 'm4a', 'aac', 'ogg', 'wav', 'amr',
        // archives
        'zip',
    ],

];
