<?php
namespace Helper;

class ClosureStorage
{
    /**
     * @var \Closure[]
     */
    private static $storage = [];

    /**
     * @param $name
     *
     * @return \Closure
     */
    public static function get($name)
    {
        return self::$storage[$name];
    }

    public static function set($name, \Closure $closure)
    {
        self::$storage[$name] = $closure;
    }

    public static function clear()
    {
        self::$storage = [];
    }
}