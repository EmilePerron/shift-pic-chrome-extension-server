<?php

namespace App\Lib;

abstract class Controller {

    protected function config($file, $key = null, $fallback = null)
    {
        return Config::get($file, $key, $fallback);
    }

    protected function jsonResponse($payload) {
        header('Content-type: application/json');
        die(json_encode($payload));
    }

}
