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

use Clockwork\Authentication\AuthenticatorInterface;
use Flarum\Http\RequestUtil;
use Flarum\Settings\SettingsRepositoryInterface;

class FlarumAuthenticator implements AuthenticatorInterface
{
    protected int $groupId;

    protected SettingsRepositoryInterface $settings;

    public function __construct(int $groupId, SettingsRepositoryInterface $settings)
    {
        $this->groupId = $groupId;
        $this->settings = $settings;
    }

    public function attempt(array $credentials): bool
    {
        return true;
    }

    public function check(mixed $request): bool
    {
        if ($this->settings->get('fof-clockwork.disable_auth')) {
            return true;
        }

        $user = RequestUtil::getActor($request);

        return !$user->isGuest() && $user->groups->contains($this->groupId);
    }

    public function requires(): array
    {
        return [];
    }
}
