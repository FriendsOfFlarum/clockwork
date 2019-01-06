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
        $user = User::where('username', $credentials['username'])
            ->orWhere('email', $credentials['username'])->first();

        if ($user == null ||
            !$user->checkPassword($credentials['password']) ||
            !$user->groups->contains($this->groupId)) {
            return false;
        };

        return $user->password;
    }
    public function check($token)
    {
        $user = User::where('password', $token)->first();

        return $user != null ? $user->groups->contains($this->groupId) : false;
    }
    public function requires()
    {
        return [ static::REQUIRES_USERNAME, static::REQUIRES_PASSWORD ];
    }
}