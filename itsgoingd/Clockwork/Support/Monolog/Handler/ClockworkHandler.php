<?php

/*
 * This file is part of fof/clockwork.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Clockwork\Support\Monolog\Handler;

use Clockwork\Request\Log as ClockworkLog;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\LogRecord;

// Stores messages in a Clockwork log instance
class ClockworkHandler extends AbstractProcessingHandler
{
    protected ClockworkLog $clockworkLog;

    public function __construct(ClockworkLog $clockworkLog)
    {
        parent::__construct();

        $this->clockworkLog = $clockworkLog;
    }

    protected function write(LogRecord $record): void
    {
        $this->clockworkLog->log($record->level, $record->message);
    }
}
