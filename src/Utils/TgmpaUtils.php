<?php

namespace Jankx\PluginActivation\Utils;

defined('ABSPATH') || exit;

class TgmpaUtils
{
    public static $hasFilters;

    public static function wrapIn($tag, $string)
    {
        return '<' . $tag . '>' . wp_kses_post($string) . '</' . $tag . '>';
    }

    public static function wrapInEm($string)
    {
        return self::wrapIn('em', $string);
    }

    public static function wrapInStrong($string)
    {
        return self::wrapIn('strong', $string);
    }

    public static function validateBool($value)
    {
        if (!isset(self::$hasFilters)) {
            self::$hasFilters = extension_loaded('filter');
        }

        if (self::$hasFilters) {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN);
        }

        return self::emulateFilterBool($value);
    }

    protected static function emulateFilterBool($value)
    {
        $true = ['1', 'true', 'True', 'TRUE', 'y', 'Y', 'yes', 'Yes', 'YES', 'on', 'On', 'ON'];
        $false = ['0', 'false', 'False', 'FALSE', 'n', 'N', 'no', 'No', 'NO', 'off', 'Off', 'OFF'];

        if (is_bool($value)) {
            return $value;
        } elseif (is_int($value) && (0 === $value || 1 === $value)) {
            return (bool) $value;
        } elseif ((is_float($value) && !is_nan($value)) && ((float) 0 === $value || (float) 1 === $value)) {
            return (bool) $value;
        } elseif (is_string($value)) {
            $value = trim($value);
            if (in_array($value, $true, true)) {
                return true;
            } elseif (in_array($value, $false, true)) {
                return false;
            } else {
                return false;
            }
        }

        return false;
    }
}
