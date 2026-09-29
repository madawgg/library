<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Book covers (spec 002, RF-03)
    |--------------------------------------------------------------------------
    |
    | Covers are converted to WebP, resized to fit the maximum dimension and
    | stored privately. When the result is bigger than the maximum size, its
    | quality is reduced and the user must confirm the result.
    |
    */

    'cover_disk' => 'local',

    'cover_directory' => 'covers',

    'cover_max_dimension' => 1200,

    'cover_max_bytes' => 2 * 1024 * 1024,

    'cover_upload_max_kilobytes' => 10 * 1024,

];
