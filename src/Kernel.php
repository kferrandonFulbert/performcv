<?php

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    public function boot(): void
    {
        // Doctrine datetime columns do not store a timezone; keep UTC on every entry point.
        date_default_timezone_set('UTC');
        parent::boot();
    }

    public function getCacheDir(): string
    {
        return in_array($this->environment, ['local', 'test'], true)
            ? $this->getProjectDir().'/.runtime/cache/'.$this->environment.'/php-'.PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION
            : parent::getCacheDir();
    }

    public function getLogDir(): string
    {
        return in_array($this->environment, ['local', 'test'], true)
            ? $this->getProjectDir().'/.runtime/log'
            : parent::getLogDir();
    }
}
