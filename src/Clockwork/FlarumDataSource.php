<?php

/*
 * This file is part of fof/clockwork.
 *
 * Copyright (c) 2019 FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\Clockwork\Clockwork;

use Clockwork\DataSource\DataSource;
use Clockwork\Helpers\Serializer;
use Clockwork\Request\Log;
use Clockwork\Request\Request;
use Clockwork\Request\Timeline\Timeline;
use Clockwork\Request\UserData;
use Flarum\Api\Controller\AbstractSerializeController;
use Flarum\Foundation\Application;
use Flarum\Frontend\Document;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Arr;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class FlarumDataSource extends DataSource
{
    /**
     * Laravel container from which the data is retrieved.
     *
     * @var Container
     */
    protected $container;

    /**
     * Log data structure.
     */
    protected $log;

    /**
     * Timeline data structure.
     */
    protected $timeline;

    /**
     * @var ServerRequestInterface
     */
    protected $request;

    /**
     * @var ResponseInterface
     */
    protected $response;

    public $count;

    /**
     * Create a new data source, takes Laravel container instance as an argument.
     */
    public function __construct(Container $container)
    {
        $this->container = $container;

        $this->timeline = new Timeline();
    }

    /**
     * Adds request method, uri, controller, headers, response status, timeline data and log entries to the request.
     */
    public function resolve(Request $request)
    {
        $request->sessionData = $this->getSessionData();

        $this->resolveAuthenticatedUser($request);

        $this->timeline->event('Clockwork')->end();

        $this->timeline->finalize($request->time);

        $request->timeline()->merge($this->timeline);

        return $request;
    }

    // Set a log instance
    public function setLog(Log $log)
    {
        $this->log = $log;

        return $this;
    }

    public function setResponse(ResponseInterface $response)
    {
        $this->response = $response;

        return $this;
    }

    public function setRequest(ServerRequestInterface $request)
    {
        $this->request = $request;

        return $this;
    }

    /**
     * Hook up callbacks for various Laravel events, providing information for timeline and log entries.
     */
    public function listenToEvents()
    {
        $this->container['events']->listen('clockwork.controller.start', function () {
            $this->timeline->event('Request processing')->begin();
        });

        $this->container['events']->listen('clockwork.controller.end', function () {
            $this->timeline->event('Data serialization')->end();
            $this->timeline->event('Request processing')->end();

            if ($this->timeline->find('Clockwork') === null) {
                $this->timeline->event('Clockwork')->begin();
                $this->addDocumentData();
            }
        });

        $this->container['events']->listen('clockwork.running.end', function () {
            $this->timeline->event('Application running')->end();
        });
    }

    /**
     * Hook up callbacks for some Laravel events, that we need to register as soon as possible.
     */
    public function listenToEarlyEvents()
    {
        $this->timeline->event('Total execution time')->begin();
        $this->timeline->event('Application booting')->begin();

        $this->container['flarum']->booted(function () {
            $this->timeline->event('Application booting')->end();
            $this->timeline->event('Application running')->begin();
        });

        $this->count = [];

        $this->container['events']->listen('*', function ($event) {
            $str = is_string($event) ? $event : get_class($event);
            $this->count[$str] = Arr::get($this->count, $str) ?? 0;
            $this->count[$str]++;
        });

        AbstractSerializeController::addSerializationPreparationCallback(AbstractSerializeController::class, function() {
            $this->timeline->event('Controller logic')->begin();
        });

        AbstractSerializeController::addSerializationPreparationCallback(AbstractSerializeController::class, function() {
            $this->timeline->event('Controller logic')->end();
            $this->timeline->event('Data serialization')->begin();
        });
    }

    /**
     * Return session data (replace unserializable items, attempt to remove passwords).
     */
    protected function getSessionData()
    {
        $session = $this->request->getattribute('session');

        return $this->removePasswords((new Serializer())->normalizeEach($session->all()));
    }

    // Add authenticated user data to the request
    protected function resolveAuthenticatedUser(Request $request)
    {
        if (!($user = $this->request->getattribute('actor'))) {
            return;
        }
        if (!isset($user->email) || !isset($user->id)) {
            return;
        }

        $request->setAuthenticatedUser($user->email, $user->id, [
            'email' => $user->email,
            'name'  => $user->username,
        ]);

        $this->request->getServerParams();
    }

    public function addDocumentData(?Document $document = null)
    {
        $this->timeline->event('Clockwork')->begin();

        /**
         * @var UserData
         */
        $data = $this->container['clockwork']->userData('Flarum');

        $data->title('Flarum');

        $data->counters([
            'Installed Extensions' => $this->container['flarum.extensions']->getExtensions()->count(),
            'Enabled Extensions'   => count($this->container['flarum.extensions']->getEnabledExtensions()),
        ]);

        $data->table(null, [
            ['Versions' => 'Flarum', null => Application::VERSION],
            ['PHP', PHP_VERSION],
            ['MySQL', @$document->payload['mysqlVersion'] ?? $this->container['flarum.db']->selectOne('select version() as version')->version],
        ]);

        if (!$document) {
            return;
        }

        $data->table(null, [
            ['Content' => 'Layout View', null => $document->layoutView],
            ['App View', $document->appView],
            ['Content View', $document->contentView],
            ['Language', $document->language],
            ['Direction', $document->direction],
            ['Title', $document->title],
        ]);

        if (!empty($document->meta)) {
            $data->table(
                null,
                collect($document->meta)
                    ->map(function ($value, $key) {
                        return ['Meta' => $key, null => $value];
                    })
                    ->toArray()
            );
        }

        if (!empty($document->head)) {
            $data->table(
                null,
                collect($document->head)
                    ->map(function ($value) {
                        return ['Head' => $value];
                    })
                    ->toArray()
            );
        }

        if (!empty($document->payload)) {
            $data->table(
                null,
                collect($document->payload)
                    ->filter(function ($value, $key) {
                        return $key !== 'resources';
                    })
                    ->map(function ($value, $key) {
                        return ['Payload' => $key, null => $value];
                    })
                    ->toArray()
            );
        }

        if (!empty($this->count)) {
            $data->table(
                null,
                collect($this->count)
                    ->map(function ($value, $key) {
                        return ['Events' => $key, 'Count' => $value];
                    })
                    ->sortBy('Count', SORT_REGULAR, true)
                    ->toArray()
            );
        }
    }

    public function authenticate(RequestInterface $request)
    {
        $authenticator = $this->container['clockwork']->getAuthenticator();

        return $authenticator->check($request);
    }
}
