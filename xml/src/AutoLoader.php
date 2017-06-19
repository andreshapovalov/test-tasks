<?php

namespace ASH\XMLProcessor;

use ReflectionClass;

/**
 * It's a simple, project related autoloader
 */
class AutoLoader
{
    public static function register()
    {
        $sourceDir = dirname((new ReflectionClass(static::class))->getFileName());

        spl_autoload_register(function ($class) use ($sourceDir) {
            // project-specific namespace prefix
            $prefix = 'ASH\\XMLProcessor\\';

            // does the class use the namespace prefix?
            $prefixLength = strlen($prefix);
            if (strncmp($prefix, $class, $prefixLength) !== 0) {
                // no, move to the next registered autoloader
                return;
            }

            // get the relative class name
            $relativeClass = substr($class, $prefixLength);

            // replace the namespace prefix with the base directory, replace namespace
            // separators with directory separators in the relative class name, append
            // with .php
            require_once $sourceDir . '/' . str_replace('\\', '/', $relativeClass) . '.php';
        });
    }
}