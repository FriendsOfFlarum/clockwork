<?php

namespace Reflar\Clockwork\Clockwork;

use Clockwork\Authentication\AuthenticatorInterface;
use Flarum\User\User;

class FlarumAuthenticator implements AuthenticatorInterface
{
    protected $groupId;

    public function __construct($groupId)
    {
        $this->groupId = $groupId;
    }

    public function attempt(array $credentials)
    {
        return true;
    }

    public function check($request)
    {
        $user = $request->getAttribute('actor');

        return $user != null ? $user->groups->contains($this->groupId) : false;
    }

    public function requires()
    {
        return [];
    }
}