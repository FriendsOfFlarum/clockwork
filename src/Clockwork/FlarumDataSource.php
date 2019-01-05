<?php

namespace Reflar\Clockwork\Clockwork;

use Clockwork\DataSource\DataSource;
use Clockwork\Helpers\Serializer;
use Clockwork\Request\Log;
use Clockwork\Request\Request;
use Clockwork\Request\Timeline;
use Clockwork\Request\UserData;
use Flarum\Extension\ExtensionManager;
use Flarum\Foundation\Application;
use Flarum\Frontend\Document;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class FlarumDataSource extends  DataSource
{
    /**
     * Laravel application from which the data is retrieved
     */
    protected $app;

    /**
     * Log data structure
     */
    protected $log;

    /**
     * Timeline data structure
     */
    protected $timeline;

    /**
     * Timeline data structure for views data
     */
    protected $views;

    // Whether we should collect views
    protected $collectViews = true;

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
     * Create a new data source, takes Laravel application instance as an argument
     */
    public function __construct(Application $app)
    {
        $this->app = $app;

        $this->timeline = new Timeline();
        $this->views    = new Timeline();
    }

    /**
     * Adds request method, uri, controller, headers, response status, timeline data and log entries to the request
     */
    public function resolve(Request $request)
    {
        $request->sessionData    = $this->getSessionData();

        $this->resolveAuthenticatedUser($request);

        $request->timelineData = $this->timeline->finalize($request->time);

        $this->timeline->endEvent('clockwork.flarum');

        $request->viewsData    = $this->views->finalize();

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

    public function setRequest(ServerRequestInterface $request) {
        $this->request = $request;
        return $this;
    }

    // Enable or disable collecting views
    public function collectViews($collectViews = true)
    {
        $this->collectViews = $collectViews;
        return $this;
    }

    /**
     * Hook up callbacks for various Laravel events, providing information for timeline and log entries
     */
    public function listenToEvents()
    {
        $this->app['events']->listen('clockwork.controller.start', function () {
            $this->timeline->startEvent('controller', 'Controller running.');
        });

        $this->app['events']->listen('clockwork.controller.end', function () {
            $this->timeline->endEvent('controller');
        });

        $this->app['events']->listen('composing:*', function ($view, $data = null) {
            if (! $this->collectViews) return;

            if (is_string($view) && is_array($data)) { // Laravel 5.4 wildcard event
                $view = $data[0];
            }

            $time = microtime(true);
            $data = $view->getData();
            unset($data['__env']);

            $this->views->addEvent(
                'view ' . $view->getName(),
                'Rendering a view',
                $time,
                $time,
                [ 'name' => $view->getName(), 'data' => (new Serializer)->normalize($data) ]
            );
        });
    }

    /**
     * Hook up callbacks for some Laravel events, that we need to register as soon as possible
     */
    public function listenToEarlyEvents()
    {
        $this->timeline->startEvent('total', 'Total execution time.', 'start');
        $this->timeline->startEvent('initialisation', 'Application initialisation.', 'start');

        $this->app->booting(function () {
            $this->timeline->endEvent('initialisation');
            $this->timeline->startEvent('boot', 'Framework booting.');
            $this->timeline->startEvent('run', 'Framework running.');
        });

        $this->app->booted(function () {
            $this->timeline->endEvent('initialisation');
            $this->timeline->endEvent('boot');
        });

        $this->count = [];

        $this->app['events']->listen('*', function ($event) {
            $str = is_string($event) ? $event : get_class($event);
            $this->count[$str] = array_get($this->count, $str) ?? 0;
            $this->count[$str]++;
        });
    }

    /**
     * Return session data (replace unserializable items, attempt to remove passwords)
     */
    protected function getSessionData()
    {
        $session = $this->request->getattribute('session');

        return $this->removePasswords((new Serializer)->normalizeEach($session->all()));
    }

    // Add authenticated user data to the request
    protected function resolveAuthenticatedUser(Request $request)
    {
        if (! ($user = $this->request->getattribute('actor'))) return;
        if (! isset($user->email) || ! isset($user->id)) return;

        $request->setAuthenticatedUser($user->email, $user->id, [
            'email' => $user->email,
            'name'  => $user->username
        ]);

        $this->request->getServerParams();
    }

    public function addDocumentData(Document $document) {
        $this->timeline->startEvent('clockwork.flarum', 'Clockwork');

        /**
         * @var $data UserData
         */
        $data = app('clockwork')->userData('HtmlDocument');

        $data->title('Flarum');

        $data->counters([
            'Installed Extensions' => app('flarum.extensions')->getExtensions()->count(),
            'Enabled Extensions' => sizeof(app('flarum.extensions')->getEnabledExtensions()),
        ]);

        $data->table(null, [
            ['Versions' => 'Flarum', null => app()->version()],
            ['PHP', PHP_VERSION],
            ['MySQL', app('flarum.db')->selectOne('select version() as version')->version],
        ]);

        $data->table(null, [
            ['Content' => 'Layout View', null => $document->layoutView],
            ['App View', $document->appView],
            ['Content View', $document->contentView],
            ['Language', $document->language],
            ['Direction', $document->direction],
            ['Title', $document->title],
            ['SEO', $document->content],
        ]);



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