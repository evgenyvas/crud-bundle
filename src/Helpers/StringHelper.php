<?php

namespace Ecode\CRUDBundle\Helpers;

class StringHelper
{
    /**
     * detect string is json
     */
    public static function isJson($string) {
        if (!is_string($string)) {
            return false;
        }
        json_decode($string);
        if (is_numeric($string)) { // because can be simple element id
            return false;
        } else {
            return (json_last_error() === JSON_ERROR_NONE);
        }
    }

    /**
     * remove BOM from UTF string
     */
    function removeBOM($str="") {
        if(substr($str, 0, 3) == pack('CCC', 0xef, 0xbb, 0xbf)) {
            $str = substr($str, 3);
        }
        return $str;
    }

    /**
     * Generate random password string
     *
     * @param int $length password length
     * @return string new password
     */
    public static function generatePassword($length = 8) {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $count = mb_strlen($chars);
        for ($i = 0, $result = ''; $i < $length; $i++) {
            $index = rand(0, $count - 1);
            $result .= mb_substr($chars, $index, 1);
        }
        return $result;
    }

    public function generateToken() {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    public static function startsWith($haystack, $needle) {
        return mb_substr($haystack, 0, mb_strlen($needle)) === $needle;
    }

    public static function endsWith($haystack, $needle) {
        return mb_substr($haystack, -mb_strlen($needle)) === $needle;
    }

    /**
     * Ensure a string ends with a given string. If it doesn't already end in
     * it, this function will append it.
     */
    public static function ensureEndsWith(string $subject, string $desiredEnd): string
    {
        if (! self::endsWith($subject, $desiredEnd)) {
            $subject .= $desiredEnd;
        }

        return $subject;
    }

    /**
     * Ensure a string starts with a given string. If it doesn't already start
     * with it, this function will prepend it.
     */
    public static function ensureStartsWith(string $subject, string $desiredStart): string
    {
        if (! self::startsWith($subject, $desiredStart)) {
            $subject = $desiredStart . $subject;
        }

        return $subject;
    }
}
