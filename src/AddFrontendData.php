<?php

/*
 * This file is part of fof/clockwork.
 *
 * Copyright (c) 2019 FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\Clockwork;

use Flarum\Frontend\Document;
use Illuminate\Contracts\Container\Container;

class AddFrontendData
{
    /**
     * @var Container
     */
    private $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function __invoke($document = null)
    {
        if ($this->container->bound('clockwork.flarum')) {
            if (!$document instanceof Document) {
                $document = null;
            }

            $this->container['clockwork.flarum']->addDocumentData($document);
        }
    }
}
