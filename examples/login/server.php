<?php

require __DIR__ . '/../../vendor/autoload.php';

use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Server\DefaultErrorHandler;
use Amp\Http\Server\Request;
use Amp\Http\Server\RequestHandler\ClosureRequestHandler;
use Amp\Http\Server\Response;
use Amp\Http\Server\Router;
use Amp\Http\Server\Session\LocalSessionStorage;
use Amp\Http\Server\Session\Session;
use Amp\Http\Server\Session\SessionFactory;
use Amp\Http\Server\Session\SessionMiddleware;
use Amp\Http\Server\SocketHttpServer;
use Amp\Log\ConsoleFormatter;
use Amp\Log\StreamHandler;
use Amp\Socket;
use Amp\Sync\LocalKeyedMutex;
use Kelunik\OAuth\Providers\GitHubProvider;
use Monolog\Logger;
use function Amp\ByteStream\getStdout;
use function Amp\trapSignal;

// Run this script, then visit http://localhost:1337/auth/github in your browser.

$httpClient = HttpClientBuilder::buildDefault();

// Register your app on https://github.com/settings/developers
$authUrl = 'http://localhost:1337/auth/github';
$provider = new GitHubProvider($httpClient, $authUrl, '{{ client id }}', '{{ client secret }}');

$authHandler = new ClosureRequestHandler(static function (Request $request) use ($provider) {
    $session = $request->getAttribute(Session::class)->read();
    parse_str($request->getUri()->getQuery(), $query);

    if (!isset($query['state']) || !$session->has('oauth_state')) {
        $state = bin2hex(random_bytes(32));

        $session->open();
        $session->set('oauth_state', $state);
        $session->save();

        return new Response(302, ['location' => $provider->getAuthorizationUrl($state)]);
    }

    if (!hash_equals($query['state'], $session->get('oauth_state'))) {
        return new Response(400);
    }

    if (!isset($query['code'])) {
        return new Response(400);
    }

    $accessToken = $provider->exchangeAccessTokenForCode($query['code']);

    return new Response(
        200,
        ['content-type' => 'text/plain'],
        "Authenticated \\o/\r\n\r\naccess-token: {$accessToken}"
    );
});

$handler = new StreamHandler(getStdout());
$handler->setFormatter(new ConsoleFormatter(null, null, true));

$logger = new Logger('server');
$logger->pushHandler($handler);

$errorHandler = new DefaultErrorHandler();

$server = new SocketHttpServer($logger);
$server->expose(new Socket\InternetAddress('127.0.0.1', 1337));
$server->expose(new Socket\InternetAddress('::1', 1337));

$router = new Router($server, $errorHandler);
$router->stack(new SessionMiddleware(new SessionFactory(new LocalKeyedMutex, new LocalSessionStorage)));
$router->addRoute('GET', '/auth/github', $authHandler);

$server->start($router, $errorHandler);

trapSignal(\SIGINT);

$server->stop();
