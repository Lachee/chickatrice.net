<?php
namespace kiss\helpers;

use kiss\exception\ArgumentException;
use kiss\Kiss;

class HTML {

    /** @var string title of the page */
    public static $title = 'KISS Dev';

    /** @var string[string] metadata of the page. Keys set override the defaults */
    public static $meta = [];

    /** @var string the current route */
    public static $route = '';


    /** Creates a HTML comment
     * @param string $comment the comment
     * @param string resulting html
     */
    public static function comment($comment) {
        return '<!-- ' . str_replace('-->', '→', $comment) . ' -->';
    }

    /** Creates all the HTML meta tags for open graph
     * @return string
    */    
    public static function openGraphMeta() {
        $meta = array_merge([
            'title'         => HTML::$title,
            'description'   => Kiss::$app->description,
            'url'           => Kiss::$app->baseURL(),
            'image'         => HTTP::url(Kiss::$app->logo, true),
        ], HTML::$meta);

        $html = '';
        foreach($meta as $name => $value) {
            $html .= HTML::tag('meta', '', [
                'property' => 'og:' . $name,
                'content' => HTML::encode($value)
            ]);
        }
        return $html;
    }

    /** Prepares a URL with special prefix:
     * http - indicates the full URL should be used
     * @    - relative route.
     * \    - a class route. 
     * @deprecated use HTTP::url instead 
     */
    public static function href($route, $excludeParameters = false, $absolute = false) {
        $url    = null;
        $params = [];
        $query  = '';

        if (is_array($route)) {
            foreach($route as $key => $pair) {
                if ($key === 0) {
                    $url = $pair; 
                    continue; 
                }
                
                $params[$key] = $pair;
            }
        } else {
            $url = $route;
        }

        //Build the query
        if (!$excludeParameters && count($params) > 0) {
            $query = '?' . http_build_query($params);
        }
        
        //Absolute, so lets return the http.
        if (preg_match('/^[a-z]{4,}:\/\//', $url)) {
            return $url . $query;
        }

        //Relative from the current controller
        if (strpos($url, '@') === 0) {
            $url = substr($url, 1);
            $mod = 1;

            if (strpos($url, '/') === 0 && Strings::endsWith(self::$route, '/'))
                $mode = 0;

            $route = substr(self::$route, 0, strrpos(self::$route, "/") + $mod);
            $url = $route . substr($url, 1);

        }
        
        //Convert the class to a route
        if (strpos($url, '\\') !== false) {
            $url = join('/', $url::getRouting()) . '/';
        }

        $finalUrl = ($absolute ? trim(Kiss::$app->baseURL(), '/') . $url : $url) . $query;
        return $finalUrl;
    }

    /** @return string encodes the content to be HTML safe */
    public static function encode($text) {
        return htmlspecialchars($text);
    }

    /** Alias of HTTP::safeURLEncode
     * @return string safely encoded URL. This does not make it HTML safe nessarily tho.
     */
    public static function urlencode($text) {
        return HTTP::safeURLEncode($text);
    }

    /** Converts the model into a JS representation
     * @param boolean $asJSON when enabled, it will wrap the object in a JSON.Parse. This can be more efficient when creating larger objects.
     * @return string encodes the model as a JSON structure and wraps that in some javascript to create a model */
    public static function toJS($model, $asJSON = true) {
        $json = json_encode($model);
        if ($asJSON) 
            return "JSON.parse(".json_encode($json).")";        
        return $json;
    }

    /**
     * Creates a HTML tag with specified contents
     * @param mixed $tag the element
     * @param mixed $contents the contents
     * @param array $options the attribute options
     * @return string the HTML tags
     * @throws ArgumentException 
     */
    public static function tag($tag, $contents, $options = []) {
        $html = self::begin($tag, $options);
        $html .= $contents;
        $html .= self::end($tag);
        return $html;
    }

    /** Creates a HTML input tag
     * @param string $type type of input
     * @param array $options the attribute options. disabled and value are treated specially.
     * @return string the HTML tag.
     */
    public static function input($type, $options = []) {
        $disabled = $options['disabled'] ?? false;
        $options['disabled'] = true;
        if (!$disabled) unset($options['disabled']);
        
        $options['type'] = $type;
        return self::tag('input', $type == 'textarea' ? ($options['value'] ?? '') : '', $options);
    }

    /** Creates a HTML checkbox
     * @param string $type type of input
     * @param array $options the attribute options. disabled and value are treated specially.
     * @return string the HTML tag.
     */
    public static function checkbox($options = []) {
        $disabled = $options['disabled'] ?? false;
        $options['disabled'] = true;
        if (!$disabled) unset($options['disabled']);
        
        if ($options['value']) $options['checked'] = true;
        return self::tag('checkbox', $options['label'] ?? '', $options);
    }

    /**
     * Creates a HTML A tag
     * @param mixed $route the route
     * @param mixed $contents the contents
     * @param array $options  the attribute options
     * @return string the HTML tag
     * @throws ArgumentException 
     */
    public static function a($route, $contents, $options = []){
        $options['href'] = HTTP::url($route);
        return self::tag('a', $contents, $options);
    }

    /**
     * Creates a paragraph
     * @param mixed $contents contents of the paragraph
     * @param array $options attribute options
     * @return string the HTML paragraph
     * @throws ArgumentException 
     */
    public static function p($contents, $options = []) {
        return self::tag('p', $contents, $options);
    }

    /** Starts a HTML tag */
    public static function begin($tag, $options = []) {
        $tags = explode(' ', $tag);
        $html = '';
        foreach ($tags as $t)
            $html .= "<{$t} " . join(' ', self::attributes($options)) . ">";
        return $html;
    }

    /** Ends a HTML tagg */
    public static function end($tag) {
        $tags = explode(' ', $tag);
        $html = '';
        foreach ($tags as $t)
            $html .= "</{$t}>";
        return $html;
    }

    /** Turns the array of options into HTML ready attributes
     * @return string[] list of attribute definitions
     */
    public static function attributes($options) {
        if (empty($options)) return [];
        if (!is_array($options)) throw new ArgumentException('Options must be an array');
        if (count($options) == 0) return [];

        $attributes = [];
        foreach($options as $key => $pair) {
            $value = $pair;
            switch($key) {
                default: break;
                case 'class': 
                    if (is_array($pair)) $value = join(' ', $pair);
                    break;
            }

            $attributes[] = self::encode($key) . '="' . self::encode($value) . '"';
        }

        return $attributes;
    }

    /**
     * Adds a class to the options.
     * It will convert the options into an array and ensure uniqueness
     * @param array $options the options
     * @param string|string[] $class the class to add.
     * @return void 
     */
    public static function addCssClass(&$options, $class) {
        if (is_array($class)) {
            foreach($class as $c) HTML::addCssClass($options, $c);
            return;
        }

        if (!isset($options['class'])) $options['class'] = [];
        if (!is_array($options['class'])) $options['class'] = explode(' ', $options['class']);
        $options['class'][] = $class;
        $options = array_unique($options);
    }

    /**
     * Removes a class from the options.
     * It will convert the options into an array and ensure uniqueness
     * @param array $options the options
     * @param string|string[] $class the class to add.
     * @return void|bool 
     */
    public static function removeCssClass(&$options, $class) {    
        if (is_array($class)) {
            $sucess = false;
            foreach($class as $c) { 
                if (HTML::removeCssClass($options, $c)) 
                    $sucess = true;
            }
            return $sucess;
        }
    
        if (!isset($options['class'])) return false;
        if (!is_array($options['class'])) $options['class'] = explode(' ', $options['class']);
        
        //Run through a unique
        $options = array_unique($options);

        //Remove the item
        for ($i = 0; $i < count($options['class']); $i++) {
            if ($options['class'][$i] == $class) {
                unset($options['class'][$i]);
                return true;
            }
        }

        //We removed none
        return false;
    }
}