<?php

require __DIR__ . '/../../vendor/autoload.php';

use Amp\Http\Server\Request;
use Amp\Http\Server\RequestHandler\CallableRequestHandler;
use Amp\Http\Server\Response;
use Amp\Http\Server\Router;
use Amp\Http\Server\Server;
use Amp\Http\Server\Session\Driver;
use Amp\Http\Server\Session\InMemoryStorage;
use Amp\Http\Server\Session\Session;
use Amp\Http\Server\Session\SessionMiddleware;
use Amp\Log\ConsoleFormatter;
use Amp\Log\StreamHandler;
use Amp\Socket;
use Amp\Sync\LocalKeyedMutex;
use Kelunik\OAuth\Providers\GitHubProvider;
use Monolog\Logger;
use function Amp\ByteStream\getStdout;

// Run this script, then visit http://localhost:1337/auth/github in your browser.

Amp\Loop::run(static function () {
    $sockets = [
        Socket\Server::listen('127.0.0.1:1337'),
        Socket\Server::listen('[::1]:1337'),
    ];

    $httpClient = new Amp\Http\Client\Client;

    // Register your app on https://github.com/settings/developers
    $authUrl = 'http://localhost:1337/auth/github';
    $provider = new GitHubProvider($httpClient, $authUrl, '{{ client id }}', '{{ client secret }}');

    $authHandler = new CallableRequestHandler(static function (Request $request) use ($provider) {
        /** @var Session $session */
        $session = yield $request->getAttribute(Session::class)->read();
        \parse_str($request->getUri()->getQuery(), $query);

        if (!isset($query['state']) || !$session->has('oauth_state')) {
            $state = \bin2hex(\random_bytes(32));

            yield $session->open();
            $session->set('oauth_state', $state);
            yield $session->save();

            return new Response(302, ['location' => $provider->getAuthorizationUrl($state)]);
        }

        if (!\hash_equals($query['state'], $session->get('oauth_state'))) {
            return new Response(400);
        }

        if (!isset($query['code'])) {
            return new Response(400);
        }

        $accessToken = yield $provider->exchangeAccessTokenForCode($query['code']);

        return new Response(
            200,
            ['content-type' => 'text/plain'],
            "Authenticated \\o/\r\n\r\naccess-token: {$accessToken}"
        );
    });

    $router = new Router;
    $router->stack(new SessionMiddleware(new Driver(new LocalKeyedMutex, new InMemoryStorage)));
    $router->addRoute('GET', '/auth/github', $authHandler);

    $handler = new StreamHandler(getStdout());
    $handler->setFormatter(new ConsoleFormatter(null, null, true));

    $logger = new Logger('server');
    $logger->pushHandler($handler);

    $server = new Server($sockets, $router, $logger);

    yield $server->start();

    /** @noinspection PhpComposerExtensionStubsInspection */
    Amp\Loop::onSignal(\SIGINT, static function (string $watcherId) use ($server) {
        Amp\Loop::cancel($watcherId);
        yield $server->stop();
    });
});
