<?php

namespace Kelunik\OAuth\Providers;

use Amp\Http\Client\Client;
use Amp\Http\Client\Response;
use Amp\Promise;
use Kelunik\OAuth\HttpException;
use Kelunik\OAuth\Identity;
use Kelunik\OAuth\OAuthException;
use Kelunik\OAuth\Provider;
use function Amp\call;

final class StackExchangeProvider extends Provider
{
    protected $authorizationUrl = 'https://stackexchange.com/oauth';
    protected $accessTokenUrl = 'https://stackexchange.com/oauth/access_token';
    protected $clientKey;

    public function __construct(
        Client $http,
        string $redirectUrl,
        string $clientId,
        string $clientSecret,
        string $clientKey
    ) {
        parent::__construct($http, $redirectUrl, $clientId, $clientSecret, '');

        $this->clientKey = $clientKey;
    }

    public function getIdentity(string $accessToken): Promise
    {
        return call(function () use ($accessToken) {
            $query = \http_build_query([
                'key' => $this->clientKey,
                'site' => 'stackoverflow',
                'sort' => 'reputation',
                'access_token' => $accessToken,
            ]);

            /** @var Response $response */
            $response = yield $this->http->request("https://api.stackexchange.com/2.2/me?{$query}");

            if ($response->getStatus() !== 200) {
                throw new HttpException('Stack Exchange API query failure (' . $response->getStatus() . ')', $response);
            }

            $rawResponse = yield $response->getBody()->buffer();
            $response = \json_decode($rawResponse, true);

            if (isset($response['items'][0]['user_id'], $response['items'][0]['display_name'], $response['items'][0]['profile_image'], $response['items'][0]['user_type'])) {
                throw new OAuthException("Invalid Stack Exchange response doesn't match API documentation: " . $rawResponse);
            }

            if ($response['items'][0]['user_type'] !== 'registered') {
                throw new OAuthException('Stack Exchange user is not registered.');
            }

            return new Identity(
                $this,
                $response['items'][0]['user_id'],
                $response['items'][0]['display_name'],
                $response['items'][0]['profile_image']
            );
        });
    }

    public function getInternalName(): string
    {
        return 'stack-exchange';
    }

    public function getName(): string
    {
        return 'Stack Exchange';
    }
}
