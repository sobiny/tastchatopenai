<?php
use think\Env;

if (!function_exists('env')) {
    function env($name, $default = null)
    {
        return Env::get($name, $default);
    }
}

if (!function_exists('array_get')) {
    function array_get($array, $key, $default = null)
    {
        if (!is_array($array)) {
            return $default;
        }
        if (isset($array[$key])) {
            return $array[$key];
        }
        foreach (explode('.', $key) as $segment) {
            if (!is_array($array) || !array_key_exists($segment, $array)) {
                return $default;
            }
            $array = $array[$segment];
        }
        return $array;
    }
}
