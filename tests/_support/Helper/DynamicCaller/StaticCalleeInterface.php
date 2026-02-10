<?php

namespace Helper\DynamicCaller;

/**
 * @template T
 */
interface StaticCalleeInterface
{
    /**
     * @return T
     */
    public static function invoke(...$args);
}