<?php

namespace Helper\DynamicCaller;

class StaticCalleeFactory
{
    /**
     * @template T
     * @param callable():T $callable
     *
     * @return class-string<StaticCalleeInterface<T>>
     */
    public static function makeCallee(callable $callable): string
    {
        $isParseAllowedCallableStorage =
            new
            /**
             * @template TInner
             * @implements StaticCalleeInterface<TInner>
             */
            class($callable) implements StaticCalleeInterface {
            /**
             * @var callable(): TInner
             */
            private static $callable;

            /**
             * @param callable(): TInner $callable
             */
            public function __construct(callable $callable)
            {
                self::$callable = $callable;
            }

            /**
             * @return TInner
             */
            public static function invoke(...$args)
            {
                return (self::$callable)(...$args);
            }
        };

        return \get_class($isParseAllowedCallableStorage);
    }
}