<?php
/**
 * Copyright (c) Microsoft Corporation.  All Rights Reserved.
 * Licensed under the MIT License.  See License in the project root
 * for license information.
 */


namespace Microsoft\Graph\Test\Task;


use GuzzleHttp\Psr7\Utils;
use Microsoft\Graph\Exception\GraphClientException;
use Microsoft\Graph\Http\AbstractGraphClient;
use Microsoft\Graph\Http\GraphCollectionRequest;
use Microsoft\Graph\Http\GraphResponse;
use Microsoft\Graph\Http\RequestOptions;
use Microsoft\Graph\Task\PageIterator;
use Microsoft\Graph\Test\Http\Request\BaseGraphRequestTest;
use Microsoft\Graph\Test\Http\Request\MockHttpClientResponseConfig;
use Microsoft\Graph\Test\Http\SampleGraphResponsePayload;
use Microsoft\Graph\Test\TestData\Model\User;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\NetworkExceptionInterface;


class PageIteratorTest extends BaseGraphRequestTest
{
    private $defaultCollectionResponse;
    private $defaultPageIterator;
    private $defaultCallback;
    private $callbackNumEntitiesProcessed = 0;
    private $callbackIsEntityUser = false;

    public function setUp(): void
    {
        parent::setUp();
        $this->setupCallback();
        $this->defaultCollectionResponse = $this->createCollectionResponse(SampleGraphResponsePayload::COLLECTION_PAYLOAD);
        $this->defaultPageIterator = new PageIterator(
            $this->mockGraphClient,
            $this->defaultCollectionResponse,
            $this->defaultCallback,
        );
        $this->setupNextPageRequest();
    }

    private function setupNextPageRequest() {
        $this->mockGraphClient->method('createRequest')
                                ->willReturn($this->defaultGraphRequest);
    }

    private function setupCallback() {
        $this->callbackNumEntitiesProcessed = 0;
        $this->callbackIsEntityUser = false;
        $this->defaultCallback = function ($entity) {
            if (is_a($entity, User::class)) {
                $this->callbackIsEntityUser = true;
                $this->callbackNumEntitiesProcessed ++;
                return false;
            }

            $this->callbackNumEntitiesProcessed ++;
            return true;
        };
    }

    public function testConstructorCreatesPageIterator() {
        $pageIterator = new PageIterator(
            $this->mockGraphClient,
            $this->defaultCollectionResponse,
            $this->defaultCallback
        );
        $this->assertInstanceOf(PageIterator::class, $pageIterator);
    }

    public function testConstructorThrowsExceptionIfGraphResponseIsNotACollection() {
        $this->expectException(GraphClientException::class);
        $pageIterator = new PageIterator(
            $this->mockGraphClient,
            $this->createCollectionResponse(SampleGraphResponsePayload::ENTITY_PAYLOAD),
            $this->defaultCallback
        );
    }

    public function testIterateCallsCallbackForEachItemInCollection() {
        // Set response for call to get the next page
        MockHttpClientResponseConfig::configureWithLastPageCollectionPayload($this->mockHttpClient);

        $numEntitiesInCollection = sizeof(SampleGraphResponsePayload::COLLECTION_PAYLOAD["value"]) + sizeof(SampleGraphResponsePayload::LAST_PAGE_COLLECTION_PAYLOAD["value"]);
        $promise = $this->defaultPageIterator->iterate();
        $promise->wait();
        $this->assertEquals($numEntitiesInCollection, $this->callbackNumEntitiesProcessed);
    }

    public function testIteratePausesIfCallbackReturnsFalse() {
        MockHttpClientResponseConfig::configureWithLastPageCollectionPayload($this->mockHttpClient);

        // callback returns false if it encounters an entity of type User
        $iterator = new PageIterator(
            $this->mockGraphClient,
            $this->defaultCollectionResponse,
            $this->defaultCallback,
            User::class
        );
        $promise = $iterator->iterate();
        $promise->wait();
        $this->assertEquals(3, $this->callbackNumEntitiesProcessed);
    }

    public function testIterateCastsNextPageResultsToExpectedReturnType() {
        MockHttpClientResponseConfig::configureWithLastPageCollectionPayload($this->mockHttpClient);

        $pageIterator = new PageIterator(
            $this->mockGraphClient,
            $this->defaultCollectionResponse,
            $this->defaultCallback,
            User::class
        );
        $promise = $pageIterator->iterate();
        $promise->wait();
        $this->assertTrue($this->callbackIsEntityUser);
    }

    public function testIterateCompletesIfTheresNoNextLinkToFetch() {
        MockHttpClientResponseConfig::configureWithLastPageCollectionPayload($this->mockHttpClient);

        $promise = $this->defaultPageIterator->iterate();
        $promise->wait();
        $this->assertTrue($this->defaultPageIterator->isComplete());
    }

    public function testIterateCompletesIfNextPageIsEmpty() {
        MockHttpClientResponseConfig::configureWithEmptyPayload($this->mockHttpClient);
        $promise = $this->defaultPageIterator->iterate();
        $promise->wait();
        $this->assertTrue($this->defaultPageIterator->isComplete());
    }

    public function testIterateThrowsExceptionOnErrorGettingNextPage() {
        $this->expectException(ClientExceptionInterface::class);
        $this->mockHttpClient->method('sendRequest')
                                ->willThrowException($this->createMock(NetworkExceptionInterface::class));
        $promise = $this->defaultPageIterator->iterate();
        $promise->wait();
    }

    public function testIterateReturnsPromiseThatResolvesToTrueOnFulfilled() {
        MockHttpClientResponseConfig::configureWithEmptyPayload($this->mockHttpClient);
        $promise = $this->defaultPageIterator->iterate();
        $this->assertTrue($promise->wait());
    }

    public function testIterateUsingCallbackWithNoReturnValueIsOnlyCalledOnce() {
        $numEntitiesProcessed = 0;
        $callback = function ($entity) use (&$numEntitiesProcessed) {
            $numEntitiesProcessed ++;
        };

        $pageIterator = new PageIterator(
            $this->mockGraphClient,
            $this->defaultCollectionResponse,
            $callback
        );

        $promise = $pageIterator->iterate();
        $promise->wait();
        $this->assertEquals(1, $numEntitiesProcessed);
    }

    public function testIteratorGetsNextPageUsingRequestOptions() {
        MockHttpClientResponseConfig::configureWithLastPageCollectionPayload($this->mockHttpClient);
        $header = ["SampleHeader" => ["value"]];
        $requestOptions = new RequestOptions($header);
        $pageIterator = new PageIterator(
            $this->mockGraphClient,
            $this->defaultCollectionResponse,
            $this->defaultCallback,
            '',
            $requestOptions
        );
        $promise = $pageIterator->iterate();
        $promise->wait();
        $this->assertArrayHasKey("SampleHeader", $this->defaultGraphRequest->getHeaders());
        $this->assertEquals($header["SampleHeader"], $this->defaultGraphRequest->getHeaders()["SampleHeader"]);
    }

    public function testResumeContinuesIteration() {
        MockHttpClientResponseConfig::configureWithLastPageCollectionPayload($this->mockHttpClient);

        $pageIterator = new PageIterator(
            $this->mockGraphClient,
            $this->defaultCollectionResponse,
            $this->defaultCallback,
            User::class
        );

        $promise = $pageIterator->iterate();
        $promise->wait();
        // iterator pauses
        $this->assertEquals(3, $this->callbackNumEntitiesProcessed);
        $promise = $pageIterator->resume();
        $expectedNumEntities = sizeof(SampleGraphResponsePayload::COLLECTION_PAYLOAD["value"]) + sizeof(SampleGraphResponsePayload::LAST_PAGE_COLLECTION_PAYLOAD["value"]);
        $this->assertEquals($expectedNumEntities, $this->callbackNumEntitiesProcessed);
    }

    public function testGetNextLinkChangesAfterNextPageIsFetched() {
        $this->assertEquals($this->defaultCollectionResponse->getNextLink(), $this->defaultPageIterator->getNextLink());
        MockHttpClientResponseConfig::configureWithLastPageCollectionPayload($this->mockHttpClient);
        $promise = $this->defaultPageIterator->iterate();
        $promise->wait();
        $this->assertNull($this->defaultPageIterator->getNextLink());
    }

    public function testGetDeltaLinkChangesAfterNextPageIsFetched() {
        $this->assertEquals($this->defaultCollectionResponse->getDeltaLink(), $this->defaultPageIterator->getDeltaLink());
        MockHttpClientResponseConfig::configureWithLastPageCollectionPayload($this->mockHttpClient);
        $promise = $this->defaultPageIterator->iterate();
        $promise->wait();
        $this->assertNull($this->defaultPageIterator->getDeltaLink());
    }

    public function testSetAccessToken() {
        $token = "new token";
        $this->mockGraphClient->expects($this->once())
                                ->method("setAccessToken")
                                ->with($token);
        $instance = $this->defaultPageIterator->setAccessToken($token);
        $this->assertInstanceOf(PageIterator::class, $instance);
    }

    private function createCollectionResponse(array $payload): GraphResponse {
        return new GraphResponse(
            $this->createMock(GraphCollectionRequest::class),
            Utils::streamFor(json_encode($payload)),
            200
        );
    }
}
