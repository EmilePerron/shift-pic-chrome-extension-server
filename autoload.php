<?php

function customAutoloader($class)
{
    if (strpos($class, 'App\\') === 0) {
        $class = str_replace('App\\', 'app\\', $class);
        $filename = ROOT_DIR . str_replace('\\', '/', $class) . '.php';

        if (file_exists($filename)) {
            require_once $filename;
        }
    }
}
spl_autoload_register('customAutoloader');
