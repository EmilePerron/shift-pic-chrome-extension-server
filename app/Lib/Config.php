<?php

namespace App\Lib;

abstract class Config {

    protected static $configs = null;

    public static function loadConfig($file)
    {
        if (self::$configs === null) {
            self::$configs = [];
        }

        if (!isset(self::$configs[$file])) {
            $filename = ROOT_DIR . 'config/' . $file . '.json';
            self::$configs[$file] = [];

            if (file_exists($filename)) {
                self::$configs[$file] = json_decode(file_get_contents($filename), true);
            }
        }
    }

    public static function get($file, $key = null, $fallback = null)
    {
        static::loadConfig($file);
        $data = self::$configs[$file];

        if ($key !== null) {
			$subkeys = explode('.', $key);

			foreach ($subkeys as $subkey) {
				if (isset($data[$subkey])) {
					$data = $data[$subkey];
                } else {
					return $fallback;
                }
			}
		}

        return $data;
    }

}
