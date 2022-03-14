<?php namespace kiss\helpers;


class Strings {

    /** Cleans a string up, stripping html tags and trimming the result 
     * This should be used for most inputs
     * @param string $str the string to clean
     * @return string the trimmed and stripped tag. It is still recommended to encode the result.
    */
    public static function clean($str) {
        $str = strip_tags($str);
        return self::trim($str);
    }

    /** Trims whitespaces or other characters from the string
     * @param string $str the string to trim
     * @param string $charlist the list of characters to trim
     * @return string
     */
    public static function trim($str, $charlist = " \n\r\t\v\0\x0B") {
        return trim($str, $charlist);
    }

        /** Trims whitespaces or other characters from the start of the string
     * @param string $str the string to trim
     * @param string $charlist the list of characters to trim
     * @return string
     */
    public static function trimStart($str, $charlist = " \n\r\t\v\0\x0B") {
        return ltrim($str, $charlist);
    }

     /** Trims whitespaces or other characters from the end of the string
     * @param string $str the string to trim
     * @param string $charlist the list of characters to trim
     * @return string
     */
    public static function trimEnd($str, $charlist = " \n\r\t\v\0\x0B") {
        return rtrim($str, $charlist);
    }

    /**
     * Converts the string into printable characters only (ASCII)
     * @param string $str 
     * @param string $replace optional string to replace
     * @return string the resulting string
     */
    public static function printable($str, $replace = '') {
        return preg_replace('/[[:^print:]]/', $replace, $str);
    }

    /** Converts a string to lower case, respecting the encoding and not destroying UTF-8 
     * @return string the lowercase string*/
    public static function toLowerCase($str) {
        return mb_strtolower($str);
    }
    
    /** Converts a string to upper case, respecting the encoding and not destroying UTF-8 
     * @return string the upper string*/
    public static function toUpperCase($str) {
        return mb_strtoupper($str);
    }

    /** checks if the string starts with another substring */
    public static function startsWith (String $string, String  $needle) : bool
    { 
        $len = strlen($needle); 
        return (substr($string, 0, $len) === $needle); 
    } 

    /** checks if the string ends with another substring */
    public static function endsWith(String $string, String  $needle) : bool
    { 
        $len = strlen($needle); 
        if ($len == 0) { 
            return true; 
        } 
        return (substr($string, -$len) === $needle); 
    } 

    /** Checks if the string contains the substring */
    public static function contains(String $string, String $needle) : bool {
        return strrpos($string, $needle) !== false;
    }

    /** Generates a cryptographically secure random token
     * @return string Hexidecimal token.
     */
    public static function token($length = 16) {
        return bin2hex(random_bytes($length));
    }

    /** Turns a number into a pretty form 
     * @param int $n the number
     * @param int $precision how many decimal places
     * @return string
    */
    public static function shortNumber($n, $precision = 1) {
        
        if ($n > 1000000000) 
            return number_format($n / 1000000, $precision) . 'B';
        
        if ($n > 1000000)
            return number_format($n / 1000000, $precision) . 'M';
        
        if ($n > 1000)
            return number_format($n / 1000, $precision) . 'K';
        
        return number_format($n, 0);
    }

    /** Checks if the string looks like a url 
     * @return string|false returns the fully formed URL (with appropriate protocol) or false if it doesn't look like a url
    */
    public static function likeURL($str) {
        $regex = '/(https?:\/\/)?([-a-zA-Z0-9@:%._\+~#=]{1,256}\.[a-zA-Z0-9()]{1,6}\b)([-a-zA-Z0-9()@:%_\+.~#?&\/\/=]*)?/i';
        if (preg_match($regex, $str, $matches)) {
            $protocol = empty($matches[1]) ? 'https://' : $matches[1];
            return $protocol . $matches[2] . $matches[3];
        }

        return false;
    }

    /** Gets the extension in the filename
     * @param string $str the filename
     * @param bool $lower converts the extension to lowercase
     * @return string|false the extension, otherwise false if it cannot find it. Starts with .
     */
    public static function extension($str, $lower = true) {
        $url = $str;
        $url = explode('?', $url)[0];
        $index = strrpos($url, '.');
        if ($index === false) return false;
        $ext = substr($url, $index);
        return $lower ? strtolower($ext) : $ext;
    }
}