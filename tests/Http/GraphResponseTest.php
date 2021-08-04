<?php

namespace Microsoft\Graph\Test\Http;

use GuzzleHttp\Psr7\Utils;
use Microsoft\Graph\Http\GraphRequest;
use Microsoft\Graph\Http\GraphResponse;
use PHPUnit\Framework\TestCase;

class GraphResponseTest extends TestCase
{
    private $defaultGraphResponse;
    private $defaultStatusCode = 200;
    private $defaultBody;
    private $defaultHeaders;
    private $mockGraphRequest;

    public $responseBody;

    public function setUp(): void
    {
        $this->mockGraphRequest = $this->createMock(GraphRequest::class);
        $this->defaultBody = SampleGraphResponsePayload::COLLECTION_PAYLOAD;
        $this->defaultHeaders = ['foo' => 'bar'];
        $this->defaultGraphResponse = new GraphResponse(
            $this->mockGraphRequest,
            Utils::streamFor(json_encode($this->defaultBody)),
            $this->defaultStatusCode,
            $this->defaultHeaders
        );
    }

    public function testGetResponseHeaders()
    {
        $this->assertEquals($this->defaultHeaders, $this->defaultGraphResponse->getHeaders());
    }

    public function testGetNextLink()
    {
        $nextLink = $this->defaultGraphResponse->getNextLink();
        $this->assertEquals($this->defaultBody['@odata.nextLink'], $nextLink);
    }

    public function testGetBodyReturnsDecodedBody()
    {
        $this->assertEquals($this->defaultBody, $this->defaultGraphResponse->getBody());
    }

    public function testGetBodyWithNullBodyReturnsEmptyArray()
    {
        $response = new GraphResponse($this->mockGraphRequest, null);
        $this->assertEquals(array(), $response->getBody());
    }

    public function testGetRawBody()
    {
        $rawBody = $this->defaultGraphResponse->getRawBody();
        $this->assertEquals(json_encode($this->defaultBody), $rawBody);
    }

    public function testGetStatus()
    {
        $this->assertEquals($this->defaultStatusCode, $this->defaultGraphResponse->getStatus());
    }

    public function testGetMultipleObjects()
    {
        $obj = $this->defaultGraphResponse->getResponseAsObject(TestModel::class);
        $this->assertIsArray($obj);
        $this->assertContainsOnlyInstancesOf(TestModel::class, $obj);
        $this->assertSameSize($this->defaultBody['value'], $obj);
        $this->assertEquals(1, $obj[0]->getId());
    }

    public function testGetValueObject()
    {
        $response = new GraphResponse(
            $this->mockGraphRequest,
            Utils::streamFor(json_encode(SampleGraphResponsePayload::ENTITY_PAYLOAD)),
            $this->defaultStatusCode,
            $this->defaultHeaders
        );

        $obj = $response->getResponseAsObject(TestModel::class);
        $this->assertInstanceOf(TestModel::class, $obj);
    }

    public function testGetZeroMultipleObjects()
    {
        $response = new GraphResponse(
            $this->mockGraphRequest,
            Utils::streamFor(json_encode(['value' => []])),
        );

        $obj = $response->getResponseAsObject(TestModel::class);
        $this->assertSame(array(), $obj);
    }
}
