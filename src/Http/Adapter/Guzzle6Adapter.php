<?php
namespace ShoppingFeed\Sdk\Http\Adapter;

use GuzzleHttp;
use Psr\Http\Message\RequestInterface;
use ShoppingFeed\Sdk\Client;
use ShoppingFeed\Sdk\Client\ClientOptions;
use ShoppingFeed\Sdk\Http;

/**
 * Http Client Adapter for GuzzleHttp v6
 */
class Guzzle6Adapter implements Http\Adapter\AdapterInterface
{
    /**
     * @var GuzzleHttp\HandlerStack
     */
    private $stack;

    /**
     * @var Client\ClientOptions
     */
    private $options;

    /**
     * @var GuzzleHttp\Client
     */
    private $client;

    /**
     * @var GuzzleHttp\Pool
     */
    private $pool;

    public function __construct(
        Client\ClientOptions $options = null,
        GuzzleHttp\HandlerStack $stack = null
    )
    {
        if (! interface_exists(GuzzleHttp\ClientInterface::class)
            || version_compare(GuzzleHttp\ClientInterface::VERSION, '6', '<')
            || version_compare(GuzzleHttp\ClientInterface::VERSION, '7', '>=')
        ) {
            throw new Http\Exception\MissingDependencyException(
                'No GuzzleHttp client v6 found, please install the dependency or add your own http adapter'
            );
        }

        $this->options = $options ?: new Client\ClientOptions();
        $this->stack   = $stack ?: $this->createHandlerStack();

        $this->initClient();
    }

    /**
     * @inheritdoc
     */
    public function configure(ClientOptions $options)
    {
        $this->options = $options;

        $this->createHandlerStack();
        $this->initClient();

        return $this;
    }

    /**
     * @inheritdoc
     *
     * @throws GuzzleHttp\Exception\GuzzleException
     */
    public function send(RequestInterface $request, array $options = [])
    {
        return $this->client->send($request, $options);
    }

    /**
     * @inheritdoc
     */
    public function batchSend(array $requests, array $options = [])
    {
        $this->pool = new GuzzleHttp\Pool($this->client, $requests, $options);
        $this->pool->promise()->wait(true);
    }

    /**
     * @inheritdoc
     */
    public function createRequest($method, $uri, array $headers = [], $body = null)
    {
        return new GuzzleHttp\Psr7\Request($method, $uri, $headers, $body);
    }

    /**
     * @inheritdoc
     *
     * @throws Http\Exception\MissingDependencyException
     */
    public function withToken($token)
    {
        $stack = clone $this->stack;
        $stack->push(
            GuzzleHttp\Middleware::mapRequest(function (RequestInterface $request) use ($token) {
                return $request->withHeader('Authorization', 'Bearer ' . trim($token));
            }),
            'token_auth'
        );

        return $this->createClient($this->options->getBaseUri(), $stack);
    }

    /**
     * Initialise Http Client
     */
    private function initClient()
    {
        $this->client = new GuzzleHttp\Client([
            'handler'  => $this->stack,
            'base_uri' => $this->options->getBaseUri(),
            'headers'  => [
                'Accept'          => 'application/json',
                'User-Agent'      => 'SF-SDK-PHP/' . Client\Client::VERSION,
                'Accept-Encoding' => 'gzip',
            ],
        ]);
    }

    /**
     * Create new adapter with given stack
     *
     * @param string                  $baseUri
     * @param GuzzleHttp\HandlerStack $stack
     *
     * @return Http\Adapter\AdapterInterface
     *
     * @throws Http\Exception\MissingDependencyException
     */
    private function createClient($baseUri, $stack)
    {
        $options = $this->options;
        $options->setBaseUri($baseUri);

        return new self($options, $stack);
    }

    /**
     * Create handler stack
     *
     * @return GuzzleHttp\HandlerStack
     */
    private function createHandlerStack()
    {
        $this->stack = GuzzleHttp\HandlerStack::create();
        $logger      = $this->options->getLogger();

        if ($this->options->handleRateLimit()) {
            $handler = new Http\Middleware\RateLimitHandler(3, $logger);
            $this->stack->push(GuzzleHttp\Middleware::retry([$handler, 'decide'], [$handler, 'delay']), 'rate_limit');
        }

        $retryCount = $this->options->getRetryOnServerError();
        if ($retryCount) {
            $handler = new Http\Middleware\ServerErrorHandler($retryCount);
            $this->stack->push(GuzzleHttp\Middleware::retry([$handler, 'decide']), 'retry_count');
        }

        if ($logger) {
            $this->stack->push(GuzzleHttp\Middleware::log($logger, new GuzzleHttp\MessageFormatter()), 'logger');
        }

        return $this->stack;
    }
}
