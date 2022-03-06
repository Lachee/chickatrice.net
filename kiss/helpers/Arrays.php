<?php namespace kiss\helpers;

class Arrays {

    /** Tries to get a value in the array or object
     * @param array|object $obj the object or array to get the value in
     * @param string $key the name of the value
     * @param mixed $default what to return when there is nothing
     * @return mixed the value in the array at the key, or the value of the property at the key. If neither are found or the object is null, then $default.
     */
    public static function value($obj, $key, $default = null) {
        if ($obj == null) return $default;
        if (is_array($obj)) return $obj[$key] ?? $default;
        return property_exists($obj, $key) ? $obj->{$key} : $default;
    }

    /** Merges arrays togther. It can have any number of arguments
     * @param array $arrays the different arrays
     * @return array the returned merge
     */
    public static function merge(...$arrays) {

        $merged = array();
        while ($arrays) {
            $array = array_shift($arrays);
            if (!is_array($array)) {
                trigger_error(__FUNCTION__ .' encountered a non array argument', E_USER_WARNING);
                return;
            }
            if (!$array)
                continue;
            foreach ($array as $key => $value)
                if (is_string($key))
                    if (is_array($value) && array_key_exists($key, $merged) && is_array($merged[$key]))
                        $merged[$key] = call_user_func(__FUNCTION__, $merged[$key], $value);
                    else
                        $merged[$key] = $value;
                else
                    $merged[] = $value;
        }
        return $merged;
    }

    /**
     * Merges two arrays like a zipping. Uneven sized arrays get items 
     * appended to the end. 
     * 
     * @param array $a An array to merge
     * @param array $b Another array to merge
     * @return array An array of merged values
     */
    public static function zipMerge(array $a, array $b) {
        $return = array();
        
        $count_a = count($a);
        $count_b = count($b);

        if ($count_a > $count_b) {
            // Ensure $b is greater or equal to $a
            $temp = $b;
            $b = $a;
            $a = $temp;

            $count_a = count($a);
            $count_b = count($b);
        }
        
        // Zip arrays
        for ($i = 0; $i < $count_a; $i++) {
            $return = array_merge_recursive($return, array_slice($a, $i, 1, true));
            $return = array_merge_recursive($return, array_slice($b, $i, 1, true));
        }
        
        $difference = $count_b - $count_a;
        if ($difference) {
            // There are more items to add on end so pop them at the end
            $return = array_merge_recursive($return, 
                array_slice($b, $count_a, $difference, true));
        }
        
        return $return;
    }

    /** Maps the value of the array */
    public static function map($array, $callback) {
        $tmp = [];
        foreach((array) $array as $k => $p) { 
            $tmp[$k] = call_user_func($callback, $p);
        }
        return $tmp;
    }

    /** Trns the array into an associative array */
    public static function assoc($array, $callback) {
        $tmp = [];
        foreach($array as $p) {
            $key = call_user_func($callback, $p);
            $tmp[$key] = $p;
        }
        return $tmp;
    }
    
    /** Maps an array. The callback needs to return an array with exactly 2 values.
     * callback($item) : ( $key, $value )
    */
    public static function mapArray($array, $callback) {
        $tmp = [];
        foreach($array as $p) {
            [ $k, $v ] = call_user_func($callback, $p);
            $tmp[$k] = $v;
        }
        return $tmp;
    }
}