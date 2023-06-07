<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit7a06bb37bbeb8fc0036f47fec6d0d15f
{
    public static $prefixLengthsPsr4 = array (
        'R' => 
        array (
            'Recurr\\' => 7,
        ),
        'D' => 
        array (
            'Doctrine\\Deprecations\\' => 22,
            'Doctrine\\Common\\Collections\\' => 28,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'Recurr\\' => 
        array (
            0 => __DIR__ . '/..' . '/simshaun/recurr/src/Recurr',
        ),
        'Doctrine\\Deprecations\\' => 
        array (
            0 => __DIR__ . '/..' . '/doctrine/deprecations/lib/Doctrine/Deprecations',
        ),
        'Doctrine\\Common\\Collections\\' => 
        array (
            0 => __DIR__ . '/..' . '/doctrine/collections/lib/Doctrine/Common/Collections',
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit7a06bb37bbeb8fc0036f47fec6d0d15f::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit7a06bb37bbeb8fc0036f47fec6d0d15f::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInit7a06bb37bbeb8fc0036f47fec6d0d15f::$classMap;

        }, null, ClassLoader::class);
    }
}