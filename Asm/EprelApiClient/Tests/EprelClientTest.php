<?php

declare(strict_types=1);

namespace Asm\EprelApiClient\Tests;

use Asm\EprelApiClient\EprelClient;
use Asm\EprelApiClient\Exception\ResourceNotFoundException;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

/**
 * @internal
 * @psalm-suppress UnusedClass
 */
#[\PHPUnit\Framework\Attributes\CoversClass(EprelClient::class)]
final class EprelClientTest extends TestCase
{
    public function testFluentConfiguration(): void
    {
        $client = new EprelClient();

        $client->uri('https://example.com/api')
               ->apiKey('secret-key')
               ->version('1.6.3')
               ->cacheTtl(7200);

        $httpClient = $client->getHttpClient();

        $this->assertInstanceOf(\GuzzleHttp\ClientInterface::class, $httpClient);
        $this->assertSame(7200, $client->getCacheTtl());
    }

    public function testConstructorParams(): void
    {
        $cache = new ArrayAdapter();

        $params = [
            'uri' => 'https://test.local',
            'apiKey' => 'test-key',
            'version' => '2.0',
            'cache' => $cache,
            'cacheTtl' => 100,
        ];

        $client = new EprelClient($params);
        $this->assertInstanceOf(EprelClient::class, $client);
    }

    public function testGetProductSuccess(): void
    {
        $mock = new MockHandler([
            new Response(200, [], (string) json_encode([
                'registrationNumber' => '12345',
                'brandName' => 'Acme Corp',
                'energyClass' => 'A'
            ]))
        ]);

        $handlerStack = HandlerStack::create($mock);
        $httpClient = new GuzzleClient(['handler' => $handlerStack]);

        $client = new EprelClient(['httpClient' => $httpClient]);

        $product = $client->getProduct('12345');

        $this->assertSame('12345', $product->registrationNumber);
        $this->assertSame('Acme Corp', $product->brandName);
        $this->assertSame('A', $product->energyClass);
    }

    public function testGetProductNotFound(): void
    {
        $mock = new MockHandler([
            new Response(404, [], '')
        ]);

        $handlerStack = HandlerStack::create($mock);
        $httpClient = new GuzzleClient(['handler' => $handlerStack]);

        $client = new EprelClient(['httpClient' => $httpClient]);

        $this->expectException(ResourceNotFoundException::class);
        $client->getProduct('99999');
    }

    public function testGetProducts(): void
    {
        $mock = new MockHandler([
            new Response(200, [], (string) json_encode([
                'content' => [
                    [
                        'registrationNumber' => '111',
                        'brandName' => 'Brand A'
                    ],
                    [
                        'registrationNumber' => '222',
                        'brandName' => 'Brand B'
                    ]
                ],
                'totalElements' => 2,
                'totalPages' => 1,
                'size' => 20,
                'number' => 0
            ]))
        ]);

        $handlerStack = HandlerStack::create($mock);
        $httpClient = new GuzzleClient(['handler' => $handlerStack]);

        $client = new EprelClient(['httpClient' => $httpClient]);

        $page = $client->getProducts(['productGroup' => 'REFRIGERATORS']);

        $this->assertCount(2, $page->content);
        $this->assertSame('111', $page->content[0]->registrationNumber);
        $this->assertSame('Brand A', $page->content[0]->brandName);
        $this->assertSame(2, $page->totalElements);
    }
}
