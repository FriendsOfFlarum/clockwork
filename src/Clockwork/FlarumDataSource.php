<?php

/*
 * This file is part of fof/clockwork.
 *
 * Copyright (c) FriendsOfFlarum.
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
use Flarum\Api\Resource\AbstractDatabaseResource;
use Flarum\Api\Resource\AbstractResource;
use Flarum\Foundation\Application;
use Flarum\Frontend\Document;
use Flarum\Http\RequestUtil;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Arr;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Tobyz\JsonApiServer\Endpoint\Endpoint;

class FlarumDataSource extends DataSource
{
    /**
     * Laravel container from which the data is retrieved.
     *
     * @var Container
     */
    protected $container;

    protected ?Log $log = null;

    protected Timeline $timeline;

    protected ServerRequestInterface $request;

    protected ResponseInterface $response;

    /** @var array<string, int> */
    public array $count = [];

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
    public function resolve(Request $request): Request
    {
        $request->sessionData = $this->getSessionData();

        $this->resolveAuthenticatedUser($request);

        $this->timeline->event('Clockwork')->end();

        $this->timeline->finalize($request->time);

        $request->timeline()->merge($this->timeline);

        return $request;
    }

    // Set a log instance
    public function setLog(Log $log): static
    {
        $this->log = $log;

        return $this;
    }

    public function setResponse(ResponseInterface $response): static
    {
        $this->response = $response;

        return $this;
    }

    public function setRequest(ServerRequestInterface $request): static
    {
        $this->request = $request;

        return $this;
    }

    /**
     * Hook up callbacks for various Laravel events, providing information for timeline and log entries.
     */
    public function listenToEvents(): void
    {
        $this->container['events']->listen('clockwork.middleware.start', function () {
            $this->timeline->event('Request processing')->color('purple')->begin();
            $this->timeline->event('Middleware')->color('yellow')->begin();
        });

        $this->container['events']->listen('clockwork.controller.start', function () {
            $this->timeline->event('Middleware')->end();
            $this->timeline->event('Controller logic')->color('purple')->begin();
        });

        $this->container['events']->listen('clockwork.controller.end', function () {
            $this->timeline->event('Data serialization')->end();
        });

        $this->container['events']->listen('clockwork.middleware.end', function () {
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
    public function listenToEarlyEvents(): void
    {
        $this->timeline->event('Total execution time')->begin();
        $this->timeline->event('Application booting')->begin();

        $this->container['flarum']->booted(function () {
            $this->timeline->event('Application booting')->end();
            $this->timeline->event('Application running')->color('green')->begin();
        });

        $this->count = [];

        $this->container['events']->listen('*', function ($event) {
            $str = is_string($event) ? $event : get_class($event);
            $this->count[$str] = Arr::get($this->count, $str) ?? 0;
            $this->count[$str]++;
        });

        $recordAfterEndpoint = function () {
            $this->timeline->event('Controller logic')->end();
            $this->timeline->event('Data serialization')->color('purple')->begin();
        };

        foreach ([AbstractResource::class, AbstractDatabaseResource::class] as $resourceClass) {
            $resourceClass::mutateEndpoints(function ($endpoints) use ($recordAfterEndpoint) {
                ///** @var Endpoint $endpoint */
                foreach ($endpoints as $endpoint) {
                    $endpoint->beforeSerialization($recordAfterEndpoint);
                }

                return $endpoints;
            });
        }
    }

    /**
     * Return session data (replace unserializable items, attempt to remove passwords).
     */
    protected function getSessionData(): array
    {
        $session = $this->request->getAttribute('session');

        if (!$session) {
            return [];
        }

        return $this->removePasswords((new Serializer())->normalizeEach($session->all()));
    }

    // Add authenticated user data to the request
    protected function resolveAuthenticatedUser(Request $request): void
    {
        $user = RequestUtil::getActor($this->request);
        if ($user->isGuest()) {
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

    public function addDocumentData(?Document $document = null): void
    {
        $this->timeline->event('Clockwork')->begin();

        /** @var \Flarum\Extension\ExtensionManager $extensionManager */
        $extensionManager = $this->container['flarum.extensions'];
        $extensions = $extensionManager->getExtensions();
        $enabledExtensions = $extensionManager->getEnabledExtensions();

        /**
         * @var UserData $data
         */
        $data = $this->container['clockwork']->userData('Flarum');

        // Set tab with title "Flarum"
        $data->title('Flarum');

        $data->counters([
            'Installed Extensions' => $extensions->count(),
            'Enabled Extensions'   => count($enabledExtensions),
        ]);

        $data->table('Core application versions', [
            ['Software' => 'Flarum', 'Version' => Application::VERSION],
            ['PHP', PHP_VERSION],
            ['MySQL', @$document->payload['mysqlVersion'] ?? $this->container['flarum.db']->selectOne('select version() as version')->version],
        ]);

        // Build list of extensions
        $formattedExtensionList = [];

        foreach ($extensions as $extension) {
            /** @var \Flarum\Extension\Extension $extension */
            $formattedExtension = [];

            $formattedExtension = Arr::add($formattedExtension, 'Name', $extension->name);
            $formattedExtension = Arr::add($formattedExtension, 'Version', $extension->getVersion());
            $formattedExtension = Arr::add($formattedExtension, 'Enabled', $extensionManager->isEnabled($extension->getId()) ? '✓' : '');

            $formattedExtensionList[] = $formattedExtension;
        }

        $data->table('Flarum Extensions', $formattedExtensionList);

        if (!empty($this->count)) {
            $data->table(
                'Event Counts',
                collect($this->count)
                    ->map(function ($value, $key) {
                        return ['Events' => $key, 'Count' => $value];
                    })
                    ->sortBy('Count', SORT_REGULAR, true)
                    ->toArray()
            );
        }

        if ($document) {
            $this->addDocumentTables($document);
        }
    }

    protected function addDocumentTables(Document $document): void
    {
        $data = $this->container['clockwork']->userData('Document')->title('Document Data');

        $data->table('Document', [
            ['Item' => 'Layout View', '' => $document->layoutView],
            ['App View', $document->appView],
            ['Content View', $document->contentView],
            ['Language', $document->language],
            ['Direction', $document->direction],
            ['Title', $document->title],
        ]);

        if (!empty($document->meta)) {
            $data->table(
                'HTML Meta Tags',
                collect($document->meta)
                    ->map(function ($value, $key) {
                        return ['Key' => $key, '' => $value];
                    })
                    ->toArray()
            );
        }

        if (!empty($document->head)) {
            $data->table(
                'HTML Head',
                collect($document->head)
                    ->map(function ($value) {
                        return ['Head' => $value];
                    })
                    ->toArray()
            );
        }

        if (!empty($document->payload)) {
            $data->table(
                'Document Payload',
                collect($document->payload)
                    ->filter(function ($value, $key) {
                        return $key !== 'resources';
                    })
                    ->map(function ($value, $key) {
                        return ['Payload' => $key, '' => $value];
                    })
                    ->toArray()
            );
        }
    }

    public function authenticate(RequestInterface $request): bool
    {
        $authenticator = $this->container['clockwork']->getAuthenticator();

        return $authenticator->check($request);
    }
}
