<?php

// autoload_real.php @generated by Composer

class ComposerAutoloaderInita377af024d8ded3a2a4f8ce70f216865
{
    private static $loader;

    public static function loadClassLoader($class)
    {
        if ('Composer\Autoload\ClassLoader' === $class) {
            require __DIR__ . '/ClassLoader.php';
        }
    }

    /**
     * @return \Composer\Autoload\ClassLoader
     */
    public static function getLoader()
    {
        if (null !== self::$loader) {
            return self::$loader;
        }

        require __DIR__ . '/platform_check.php';

        spl_autoload_register(array('ComposerAutoloaderInita377af024d8ded3a2a4f8ce70f216865', 'loadClassLoader'), true, true);
        self::$loader = $loader = new \Composer\Autoload\ClassLoader(\dirname(__DIR__));
        spl_autoload_unregister(array('ComposerAutoloaderInita377af024d8ded3a2a4f8ce70f216865', 'loadClassLoader'));

        require __DIR__ . '/autoload_static.php';
        call_user_func(\Composer\Autoload\ComposerStaticInita377af024d8ded3a2a4f8ce70f216865::getInitializer($loader));

        $loader->register(true);

        return $loader;
    }
}