<?php

namespace Kelunik\OAuth;

use Amp\Artax\Response;

class HttpException extends OAuthException {
    private $response;

    public function __construct($message = "", Response $response) {
        parent::__construct($message, 0, null);

        $this->response = $response;
    }

    public function getResponse(): Response {
        return $this->response;
    }
}
