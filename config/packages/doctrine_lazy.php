<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    // Native lazy objects are available from PHP 8.4; retain PHP 8.2/8.3 support.
    if (PHP_VERSION_ID >= 80400) {
        $container->extension('doctrine', ['orm' => ['enable_native_lazy_objects' => true]]);
    }
};
