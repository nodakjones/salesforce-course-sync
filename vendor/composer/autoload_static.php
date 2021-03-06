<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit54dea75388b723301a1c7b529f2deb62
{
    public static $files = array (
        '320cde22f66dd4f5d3fd621d3e88b98f' => __DIR__ . '/..' . '/symfony/polyfill-ctype/bootstrap.php',
        '0e6d7bf4a5811bfa5cf40c5ccd6fae6a' => __DIR__ . '/..' . '/symfony/polyfill-mbstring/bootstrap.php',
        '4c56ab8a5f2e550eceb43880d5518a10' => __DIR__ . '/..' . '/prospress/action-scheduler/action-scheduler.php',
        'f0716f00319acb3d6e8f24be1fde2683' => __DIR__ . '/..' . '/pippinsplugins/wp-logging/WP_Logging.php',
    );

    public static $prefixLengthsPsr4 = array (
        'T' => 
        array (
            'Twig\\' => 5,
        ),
        'S' => 
        array (
            'Symfony\\Polyfill\\Mbstring\\' => 26,
            'Symfony\\Polyfill\\Ctype\\' => 23,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'Twig\\' => 
        array (
            0 => __DIR__ . '/..' . '/twig/twig/src',
        ),
        'Symfony\\Polyfill\\Mbstring\\' => 
        array (
            0 => __DIR__ . '/..' . '/symfony/polyfill-mbstring',
        ),
        'Symfony\\Polyfill\\Ctype\\' => 
        array (
            0 => __DIR__ . '/..' . '/symfony/polyfill-ctype',
        ),
    );

    public static $prefixesPsr0 = array (
        'T' => 
        array (
            'Twig_' => 
            array (
                0 => __DIR__ . '/..' . '/twig/twig/lib',
            ),
        ),
    );

    public static $classMap = array (
        'AllowFieldTruncationHeader' => __DIR__ . '/..' . '/developerforce/force.com-toolkit-for-php/soapclient/SforceHeaderOptions.php',
        'AssignmentRuleHeader' => __DIR__ . '/..' . '/developerforce/force.com-toolkit-for-php/soapclient/SforceHeaderOptions.php',
        'CallOptions' => __DIR__ . '/..' . '/developerforce/force.com-toolkit-for-php/soapclient/SforceHeaderOptions.php',
        'Email' => __DIR__ . '/..' . '/developerforce/force.com-toolkit-for-php/soapclient/SforceEmail.php',
        'EmailHeader' => __DIR__ . '/..' . '/developerforce/force.com-toolkit-for-php/soapclient/SforceHeaderOptions.php',
        'LocaleOptions' => __DIR__ . '/..' . '/developerforce/force.com-toolkit-for-php/soapclient/SforceHeaderOptions.php',
        'LoginScopeHeader' => __DIR__ . '/..' . '/developerforce/force.com-toolkit-for-php/soapclient/SforceHeaderOptions.php',
        'MassEmailMessage' => __DIR__ . '/..' . '/developerforce/force.com-toolkit-for-php/soapclient/SforceEmail.php',
        'MruHeader' => __DIR__ . '/..' . '/developerforce/force.com-toolkit-for-php/soapclient/SforceHeaderOptions.php',
        'PackageVersion' => __DIR__ . '/..' . '/developerforce/force.com-toolkit-for-php/soapclient/SforceHeaderOptions.php',
        'PackageVersionHeader' => __DIR__ . '/..' . '/developerforce/force.com-toolkit-for-php/soapclient/SforceHeaderOptions.php',
        'ProcessRequest' => __DIR__ . '/..' . '/developerforce/force.com-toolkit-for-php/soapclient/SforceProcessRequest.php',
        'ProcessSubmitRequest' => __DIR__ . '/..' . '/developerforce/force.com-toolkit-for-php/soapclient/SforceProcessRequest.php',
        'ProcessWorkitemRequest' => __DIR__ . '/..' . '/developerforce/force.com-toolkit-for-php/soapclient/SforceProcessRequest.php',
        'ProxySettings' => __DIR__ . '/..' . '/developerforce/force.com-toolkit-for-php/soapclient/ProxySettings.php',
        'QueryOptions' => __DIR__ . '/..' . '/developerforce/force.com-toolkit-for-php/soapclient/SforceHeaderOptions.php',
        'QueryResult' => __DIR__ . '/..' . '/developerforce/force.com-toolkit-for-php/soapclient/SforceBaseClient.php',
        'SObject' => __DIR__ . '/..' . '/developerforce/force.com-toolkit-for-php/soapclient/SforceBaseClient.php',
        'SforceBaseClient' => __DIR__ . '/..' . '/developerforce/force.com-toolkit-for-php/soapclient/SforceBaseClient.php',
        'SforceCustomField' => __DIR__ . '/..' . '/developerforce/force.com-toolkit-for-php/soapclient/SforceMetaObject.php',
        'SforceCustomObject' => __DIR__ . '/..' . '/developerforce/force.com-toolkit-for-php/soapclient/SforceMetaObject.php',
        'SforceEnterpriseClient' => __DIR__ . '/..' . '/developerforce/force.com-toolkit-for-php/soapclient/SforceEnterpriseClient.php',
        'SforceMetadataClient' => __DIR__ . '/..' . '/developerforce/force.com-toolkit-for-php/soapclient/SforceMetadataClient.php',
        'SforcePartnerClient' => __DIR__ . '/..' . '/developerforce/force.com-toolkit-for-php/soapclient/SforcePartnerClient.php',
        'SforceSearchResult' => __DIR__ . '/..' . '/developerforce/force.com-toolkit-for-php/soapclient/SforceBaseClient.php',
        'SforceSoapClient' => __DIR__ . '/..' . '/developerforce/force.com-toolkit-for-php/soapclient/SforcePartnerClient.php',
        'SingleEmailMessage' => __DIR__ . '/..' . '/developerforce/force.com-toolkit-for-php/soapclient/SforceEmail.php',
        'UserTerritoryDeleteHeader' => __DIR__ . '/..' . '/developerforce/force.com-toolkit-for-php/soapclient/SforceHeaderOptions.php',
        'WP_Logging' => __DIR__ . '/..' . '/pippinsplugins/wp-logging/WP_Logging.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit54dea75388b723301a1c7b529f2deb62::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit54dea75388b723301a1c7b529f2deb62::$prefixDirsPsr4;
            $loader->prefixesPsr0 = ComposerStaticInit54dea75388b723301a1c7b529f2deb62::$prefixesPsr0;
            $loader->classMap = ComposerStaticInit54dea75388b723301a1c7b529f2deb62::$classMap;

        }, null, ClassLoader::class);
    }
}
