<?php

namespace Kelunik\OAuth;

class Identity
{
    private $provider;

    private $id;
    private $name;
    private $avatar;

    public function __construct(Provider $provider, string $id, string $name, string $avatar)
    {
        $this->provider = $provider;

        $this->id = $id;
        $this->name = $name;
        $this->avatar = $avatar;
    }

    final public function getProvider(): Provider
    {
        return $this->provider;
    }

    final public function getId(): string
    {
        return $this->id;
    }

    final public function getName(): string
    {
        return $this->name;
    }

    final public function getAvatar(): string
    {
        return $this->avatar;
    }
}
