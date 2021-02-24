<?php

/*
 * This file is part of fof/clockwork.
 *
 * Copyright (c) 2019 FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Flarum\Foundation\Paths;

if (!function_exists('base_path')) {
    /**
     * Get the path to the base of the install.
     *
     * @param  string  $path
     * @return string
     * @deprecated Will be removed in Beta.15.
     */
    function base_path($path = '')
    {
        return app(Paths::class)->base.($path ? DIRECTORY_SEPARATOR.$path : $path);
    }
}

if (!function_exists('public_path')) {
    /**
     * Get the path to the public folder.
     *
     * @param  string  $path
     * @return string
     * @deprecated Will be removed in Beta.15.
     */
    function public_path($path = '')
    {
        return app(Paths::class)->public.($path ? DIRECTORY_SEPARATOR.$path : $path);
    }
}

if (!function_exists('storage_path')) {
    /**
     * Get the path to the storage folder.
     *
     * @param  string  $path
     * @return string
     * @deprecated Will be removed in Beta.15.
     */
    function storage_path($path = '')
    {
        return app(Paths::class)->storage.($path ? DIRECTORY_SEPARATOR.$path : $path);
    }
}
