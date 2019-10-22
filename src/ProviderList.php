<?php

namespace Kelunik\OAuth;

class ProviderList implements \IteratorAggregate
{
    private $providers;

    public function __construct()
    {
        $this->providers = [];
    }

    /**
     * @param Provider $provider Provider to add.
     *
     * @throws OAuthException If the provider name is already registered
     */
    public function addProvider(Provider $provider)
    {
        $name = $provider->getInternalName();

        if (isset($this->providers[$name])) {
            throw new OAuthException("Duplicate provider name: '{$name}'");
        }

        $this->providers[$name] = $provider;
    }

    /**
     * @param string $name Name provided on registration.
     *
     * @return Provider Provider corresponding to given name.
     * @throws OAuthException If the provider has not been registered.
     */
    public function getProvider(string $name): Provider
    {
        if (!isset($this->providers[$name])) {
            throw new OAuthException("Invalid provider name: '{$name}'");
        }

        return $this->providers[$name];
    }

    public function getIterator(): \Iterator
    {
        foreach ($this->providers as $name => $provider) {
            yield $name => $provider;
        }
    }
}
