<?php

namespace Microsoft\Graph\Core\Adapter;

use Http\Client\HttpAsyncClient;
use Http\Client\HttpClient;
use Microsoft\Graph\Core\HttpClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class HttplugAdapter implements HttpClientInterface
{
    /**
     * @var HttpClient&HttpAsyncClient

     */
    private $client;

    /**
     * @param HttpClient&HttpAsyncClient $client
     */
    public function __construct($client)
    {
        $this->client = $client;
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        return $this->client->sendRequest($request);
    }

    public function sendAsyncRequest(RequestInterface $request)
    {
        return $this->client->sendAsyncRequest($request);
    }
}
