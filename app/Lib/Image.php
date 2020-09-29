<?php

namespace App\Lib;

abstract class Image {

    public static function isAnimatedGif(string $filename)
    {
        if (!($fh = @fopen($filename, 'rb'))) {
            return false;
        }

        $count = 0;

        //an animated gif contains multiple "frames", with each frame having a header made up of:
        // * a static 4-byte sequence (\x00\x21\xF9\x04)
        // * 4 variable bytes
        // * a static 2-byte sequence (\x00\x2C) (some variants may use \x00\x21 ?)

        // We read through the file til we reach the end of the file, or we've found at least 2 frame headers
        while(!feof($fh) && $count < 2) {
            $chunk = fread($fh, 1024 * 100); //read 100kb at a time
            $count += preg_match_all('#\x00\x21\xF9\x04.{4}\x00(\x2C|\x21)#s', $chunk, $matches);
       }

        fclose($fh);
        return $count > 1;
    }
}
