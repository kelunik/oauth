<?php

namespace Kelunik\OAuth;

use Amp\Artax\Client;
use Amp\Artax\FormBody;
use Amp\Artax\Request;
use Amp\Artax\Response as HttpResponse;
use Amp\Promise;
use function Amp\call;

abstract class Provider {
    /** @var Client */
    protected $http;

    /** @var string */
    protected $redirectUri;

    /** @var string */
    protected $authorizationUrl;

    /** @var string */
    protected $accessTokenUrl;

    /** @var string */
    protected $clientId;

    /** @var string */
    protected $clientSecret;

    /** @var string */
    protected $scope;

    public function __construct(Client $http, string $redirectUri, string $clientId, string $clientSecret, string $scope) {
        $this->http = $http;
        $this->redirectUri = $redirectUri;
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->scope = $scope;
    }

    public function getAuthorizationUrl(string $state): string {
        $data = [
            "client_id" => $this->clientId,
            "scope" => $this->scope,
            "state" => $state,
            "redirect_uri" => $this->redirectUri,
        ];

        return $this->authorizationUrl . "?" . http_build_query($data);
    }

    public function exchangeAccessTokenForCode(string $code): Promise {
        return call(function () use ($code) {
            $form = new FormBody;
            $form->addField("redirect_uri", $this->redirectUri);
            $form->addField("client_id", $this->clientId);
            $form->addField("client_secret", $this->clientSecret);
            $form->addField("code", $code);

            $request = (new Request($this->accessTokenUrl, "POST"))->withBody($form);

            /** @var HttpResponse $response */
            $response = yield $this->http->request($request);
            $body = $response->getBody();

            parse_str($body, $data);

            if (!isset($data["access_token"])) {
                throw new OAuthException($data["error_description"] ?? $data["error"] ?? "no access token provided");
            }

            return $data["access_token"];
        });
    }

    abstract public function getIdentity(string $accessToken): Promise;

    abstract public function getInternalName(): string;

    abstract public function getName(): string;
}
