<?php

namespace FoF\Clockwork\Extend;

use Flarum\Extend\ExtenderInterface;
use Flarum\Extend\LifecycleInterface;
use Flarum\Extension\Extension;
use Illuminate\Contracts\Container\Container;
use League\Flysystem\Adapter\Local;

class FileStoragePath implements LifecycleInterface, ExtenderInterface
{
    public function extend(Container $container, Extension $extension = null)
    {
        // TODO: Implement extend() method.
    }

    public function onEnable(Container $container, Extension $extension)
    {
        if (! $this->storage()->has('clockwork')) {
            $this->storage()->createDir('clockwork');
        }
    }

    protected function storage(): Local
    {
        return new Local(storage_path());
    }

    public function onDisable(Container $container, Extension $extension)
    {
        if ($this->storage()->has('clockwork')) {
            $this->storage()->deleteDir('clockwork');
        }
    }
}
