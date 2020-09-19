<?php

namespace App\Lib;

use Crew\Unsplash\HttpClient;
use Crew\Unsplash\Search;
use Crew\Unsplash\Photo;

abstract class Unsplash {

    protected static $initialized = false;

    protected static function init()
    {
        if (!static::$initialized) {
            static::$initialized = true;
            HttpClient::init([
                'applicationId'	=> $_ENV['unsplash_access_key'],
                'secret'	=> $_ENV['unsplash_secret_key'],
                'utmSource' => $_ENV['unsplash_utm_source'],
            ]);
        }
    }

    public static function search($query, $page = 1)
    {
        if ($cachedValue = Cache::magicGet()) {
            return $cachedValue;
        }

        static::init();
        $response = Search::photos($query, $page, 20)->getResults();

        return Cache::magicSet($response, 3600);
    }

    public static function recent()
    {
        if ($cachedValue = Cache::magicGet()) {
            return $cachedValue;
        }

        static::init();
        $response = Photo::all(1, 20)->toArray();

        return Cache::magicSet($response, 900);
    }

    public static function triggerDownload($id)
    {
        static::init();
        $photo = Photo::find($id);
        $photo->download();
    }

    public static function addUtmParams($url)
    {
        $utmString = '';
        $delimiter = strpos($url, '?') === false ? '?' : '&';

        if (strpos($url, 'utm_medium') === false) {
            $utmString .= $delimiter . 'utm_medium=referral';
            $delimiter = '&';
        }

        if (strpos($url, 'utm_source') === false) {
            $utmString .= $delimiter . 'utm_source=' . strtolower(str_replace(' ', '_', $_ENV['unsplash_utm_source']));
            $delimiter = '&';
        }

        return $url . $utmString;
    }
}
