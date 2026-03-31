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

use Clockwork\Request\Request;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Jobs\SyncJob;

class QueueJobTracker
{
    /**
     * @var Container
     */
    private $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function listenToEvents(): void
    {
        $this->container['events']->listen(JobProcessing::class, function (JobProcessing $event) {
            // Sync jobs are recorded as part of the parent HTTP request
            if ($event->job instanceof SyncJob) {
                return;
            }

            $payload = $event->job->payload();

            if (!isset($payload['clockwork_id'])) {
                return;
            }

            $request = new Request(['id' => $payload['clockwork_id']]);

            if (isset($payload['clockwork_parent_id'])) {
                $request->setParent($payload['clockwork_parent_id']);
            }

            $this->container->make('clockwork')->reset()->request($request);

            $this->container['clockwork.queue']->setCurrentRequestId($request->id);
        });

        $this->container['events']->listen(JobProcessed::class, function (JobProcessed $event) {
            $this->processJob($event->job);
        });

        $this->container['events']->listen(JobFailed::class, function (JobFailed $event) {
            $this->processJob($event->job, $event->exception);
        });
    }

    protected function processJob(Job $job, ?\Throwable $exception = null): void
    {
        // Sync jobs are recorded as part of the parent HTTP request
        if ($job instanceof SyncJob) {
            return;
        }

        $payload = $job->payload();

        if (!isset($payload['clockwork_id'])) {
            return;
        }

        $unserialized = isset($payload['data']['command']) ? unserialize($payload['data']['command']) : null;

        if (!$unserialized) {
            return;
        }

        $clockwork = $this->container->make('clockwork');

        if ($exception) {
            $clockwork->error($exception->getMessage(), ['exception' => $exception]);
        }

        $clockwork
            ->resolveAsQueueJob(
                get_class($unserialized),
                $payload['displayName'],
                $job->hasFailed() ? 'failed' : ($job->isReleased() ? 'released' : 'done'),
                $unserialized,
                $job->getQueue(),
                $job->getConnectionName(),
                array_filter([
                    'maxTries'     => $payload['maxTries'] ?? null,
                    'delaySeconds' => $payload['delaySeconds'] ?? null,
                    'timeout'      => $payload['timeout'] ?? null,
                    'timeoutAt'    => $payload['timeoutAt'] ?? null,
                ])
            )
            ->storeRequest();
    }
}
