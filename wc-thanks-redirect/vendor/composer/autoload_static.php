<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInitda03f946e49213c947fbe07ed24bb78c
{
    public static $prefixLengthsPsr4 = array (
        'N' => 
        array (
            'NeeBPlugins\\Wctr\\' => 17,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'NeeBPlugins\\Wctr\\' => 
        array (
            0 => __DIR__ . '/../..' . '/src',
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInitda03f946e49213c947fbe07ed24bb78c::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInitda03f946e49213c947fbe07ed24bb78c::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInitda03f946e49213c947fbe07ed24bb78c::$classMap;

        }, null, ClassLoader::class);
    }
}