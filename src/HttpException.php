<?php

namespace Kelunik\OAuth;

use Amp\Http\Client\Response;

class HttpException extends OAuthException
{
    private $response;

    public function __construct(string $message, Response $response)
    {
        parent::__construct($message);

        $this->response = $response;
    }

    public function getResponse(): Response
    {
        return $this->response;
    }
}
