<?php

namespace a15lam\AlarmDotCom;


class Cache
{
    const STORAGE_DIR = __DIR__ . "/../storage/data/";

    public static function put($key, $value)
    {
        $file = static::STORAGE_DIR . $key;
        $data = serialize($value);
        file_put_contents($file, $data);
    }


    public static function get($key, $default=null)
    {
        $file = static::STORAGE_DIR . $key;
        if(file_exists($file)) {
            $data = file_get_contents($file);
            $value = unserialize($data);
        }

        if(empty($value)){
            return $default;
        }

        return $value;
    }

    public static function forget($key)
    {
        $file = static::STORAGE_DIR . $key;
        @unlink($file);
    }
}