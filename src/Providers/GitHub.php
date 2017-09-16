<?php

namespace Kelunik\OAuth\Providers;

use Amp\Artax\Client;
use Amp\Artax\Request;
use Amp\Artax\Response;
use Amp\Promise;
use Kelunik\OAuth\HttpException;
use Kelunik\OAuth\Identity;
use Kelunik\OAuth\OAuthException;
use Kelunik\OAuth\Provider;
use function Amp\call;

class GitHub extends Provider {
    protected $authorizationUrl = "https://github.com/login/oauth/authorize";
    protected $accessTokenUrl = "https://github.com/login/oauth/access_token";

    public function __construct(Client $http, string $redirectUrl, string $clientId, string $clientSecret, array $scopes = []) {
        parent::__construct($http, $redirectUrl, $clientId, $clientSecret, \implode(" ", $scopes));
    }

    public function getIdentity(string $accessToken): Promise {
        return call(function () use ($accessToken) {
            $request = (new Request("https://api.github.com/user"))
                ->withHeader("authorization", "token {$accessToken}")
                ->withHeader("accept", "application/vnd.github.v3+json");

            /** @var Response $response */
            $response = yield $this->http->request($request);

            if ($response->getStatus() !== 200) {
                throw new HttpException("GitHub API query failure, received bad HTTP status: " . $response->getStatus(), $response);
            }

            $response = \json_decode(yield $response->getBody(), true);

            if (!isset($response["id"], $response["login"], $response["avatar_url"])) {
                throw new OAuthException("Invalid GitHub response doesn't match API documentation.");
            }

            return new Identity(
                $this,
                $response["id"],
                $response["login"],
                $response["avatar_url"]
            );
        });
    }

    public function getInternalName(): string {
        return "github";
    }

    public function getName(): string {
        return "GitHub";
    }
}
