<?php

namespace Kelunik\OAuth\Providers;

use Amp\Http\Client\HttpClient;
use Amp\Http\Client\Request;
use Kelunik\OAuth\HttpException;
use Kelunik\OAuth\Identity;
use Kelunik\OAuth\OAuthException;
use Kelunik\OAuth\Provider;

final class SpotifyProvider extends Provider
{
    protected string $authorizationUrl = 'https://accounts.spotify.com/authorize';
    protected string $accessTokenUrl = 'https://accounts.spotify.com/api/token';

    public function __construct(
        HttpClient $httpClient,
        string $redirectUrl,
        string $clientId,
        string $clientSecret,
        array $scopes = []
    ) {
        parent::__construct($httpClient, $redirectUrl, $clientId, $clientSecret, \implode(' ', $scopes));
    }

    public function getIdentity(string $accessToken): Identity
    {
        $request = new Request('https://api.spotify.com/v1/me');
        $request->setHeader('authorization', "Bearer {$accessToken}");
        $request->setHeader('accept', 'application/json');

        $response = $this->http->request($request);

        if ($response->getStatus() !== 200) {
            throw new HttpException(
                'Spotify API query failure, received bad HTTP status: ' . $response->getStatus(),
                $response
            );
        }

        $rawResponse = $response->getBody()->buffer();
        $response = \json_decode($rawResponse, true, 16);

        if (\json_last_error() !== \JSON_ERROR_NONE) {
            throw new OAuthException('Failed to decode JSON response: ' . $rawResponse);
        }

        if (!isset($response['id'])) {
            throw new OAuthException("Invalid Spotify response doesn't match API documentation: " . $rawResponse);
        }

        return new Identity(
            $this,
            $response['id'],
            $response['display_name'] ?? $response['id'],
            ''
        );
    }

    public function getInternalName(): string
    {
        return 'spotify';
    }

    public function getName(): string
    {
        return 'Spotify';
    }
}
