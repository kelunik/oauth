<?php

namespace Kelunik\OAuth;

use Error;
use Iterator;
use IteratorAggregate;

class ProviderList implements IteratorAggregate {
    private $providers;

    public function __construct() {
        $this->providers = [];
    }

    public function addProvider(Provider $provider) {
        $name = $provider->getInternalName();

        if (isset($this->providers[$name])) {
            throw new Error("Duplicate provider name: '{$name}'");
        }

        $this->providers[$name] = $provider;
    }

    public function getProvider(string $name): Provider {
        if (!isset($this->providers[$name])) {
            throw new Error("Invalid provider name: '{$name}'");
        }

        return $this->providers[$name];
    }

    public function getIterator(): Iterator {
        foreach ($this->providers as $name => $provider) {
            yield $name => $provider;
        }
    }
}
