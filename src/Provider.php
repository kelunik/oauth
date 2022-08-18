<?php

namespace Kelunik\OAuth;

use Amp\Http\Client\Body\FormBody;
use Amp\Http\Client\HttpClient;
use Amp\Http\Client\Request;

abstract class Provider
{
    protected HttpClient $http;

    protected string $redirectUri;

    protected string $authorizationUrl;

    protected string $accessTokenUrl;

    protected string $clientId;

    protected string $clientSecret;

    protected string $scope;

    public function __construct(
        HttpClient $httpClient,
        string $redirectUri,
        string $clientId,
        string $clientSecret,
        string $scope
    ) {
        $this->http = $httpClient;
        $this->redirectUri = $redirectUri;
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->scope = $scope;
    }

    public function getAuthorizationUrl(string $state): string
    {
        $data = [
            'client_id' => $this->clientId,
            'scope' => $this->scope,
            'state' => $state,
            'redirect_uri' => $this->redirectUri,
            'response_type' => 'code',
        ];

        return $this->authorizationUrl . '?' . \http_build_query($data);
    }

    public function exchangeAccessTokenForCode(string $code): string
    {
        $form = new FormBody;
        $form->addField('grant_type', 'authorization_code');
        $form->addField('redirect_uri', $this->redirectUri);
        $form->addField('client_id', $this->clientId);
        $form->addField('client_secret', $this->clientSecret);
        $form->addField('code', $code);

        $request = new Request($this->accessTokenUrl, 'POST');
        $request->setBody($form);

        $response = $this->http->request($request);
        $body = $response->getBody()->buffer();

        if (\strtok($response->getHeader('content-type'), ';') === 'application/json') {
            $data = \json_decode($body, true, 5);

            if (\json_last_error() !== \JSON_ERROR_NONE) {
                throw new OAuthException('Failed to decode JSON response: ' . $body);
            }
        } else {
            \parse_str($body, $data);
        }

        if (!isset($data['access_token'])) {
            throw new OAuthException($data['error_description'] ?? $data['error'] ?? ('No access token provided: ' . $body));
        }

        return $data['access_token'];
    }

    abstract public function getIdentity(string $accessToken): Identity;

    abstract public function getInternalName(): string;

    abstract public function getName(): string;
}
