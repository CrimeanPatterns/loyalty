<?php

namespace AwardWallet\Engine\amazongift;

class GifFpsChanger
{
    public static function changeFps(string $file, int $fps)
    {
        $compressedFile = str_replace("captcha-", "captcha-small-", $file);
        exec("ffmpeg -i {$file} -r {$fps} {$compressedFile}", $op);

        return $compressedFile;
    }
}
