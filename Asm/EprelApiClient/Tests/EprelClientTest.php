<?php

declare(strict_types=1);

namespace Asm\EprelApiClient\Tests;

use Asm\EprelApiClient\Dto\AddressResponse;
use Asm\EprelApiClient\Dto\ProductDetail;
use Asm\EprelApiClient\Dto\ProductGroup;
use Asm\EprelApiClient\Dto\ProductGroupPage;
use Asm\EprelApiClient\EprelClient;
use Asm\EprelApiClient\Exception\ResourceNotFoundException;
use Asm\EprelApiClient\Query\SearchQuery;
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
                'eprelRegistrationNumber' => '12345',
                'supplierOrTrademark' => 'Acme Corp',
                'energyClass' => 'A',
                'powerSupplyType' => 'INTERNAL',
                'contactDetails' => [
                    'city' => 'Berlin'
                ]
            ])),
            new Response(200, [], (string) json_encode([
                'registrationNumber' => '999',
                'brandName' => 'Brand Y',
            ]))
        ]);

        $handlerStack = HandlerStack::create($mock);
        $httpClient = new GuzzleClient(['handler' => $handlerStack]);

        $client = new EprelClient(['httpClient' => $httpClient]);

        // First test: Using the /product/{id} endpoint with alternative fields
        $product = $client->getProduct('12345');

        $this->assertSame('12345', $product->registrationNumber);
        $this->assertSame('Acme Corp', $product->brandName);
        $this->assertSame('A', $product->energyClass);
        $this->assertFalse($product->blocked);
        $this->assertIsArray($product->technicalParameters);
        $this->assertSame('INTERNAL', $product->technicalParameters['powerSupplyType']);
        $this->assertIsArray($product->technicalParameters['contactDetails']);
        $this->assertSame('Berlin', $product->technicalParameters['contactDetails']['city']);

        // Second test: Using the /products/{group}/{id} endpoint
        $product2 = $client->getProduct('999', 'REFRIGERATORS');
        $this->assertSame('999', $product2->registrationNumber);
        $this->assertSame('Brand Y', $product2->brandName);
    }

    public function testGetOvenWithCavities(): void
    {
        $mock = new MockHandler([
            new Response(200, [], (string) json_encode([
                'eprelRegistrationNumber' => '332',
                'productGroup' => 'ovens',
                'blocked' => true,
                'orgVerificationStatus' => 'UNVERIFIED',
                'cavities' => [
                    [
                        'id' => 555,
                        'energyClass' => 'A',
                        'volume' => 2
                    ]
                ]
            ]))
        ]);

        $handlerStack = HandlerStack::create($mock);
        $httpClient = new GuzzleClient(['handler' => $handlerStack]);

        $client = new EprelClient(['httpClient' => $httpClient]);

        $product = $client->getProduct('332');

        $this->assertTrue($product->blocked);
        $this->assertSame('UNVERIFIED', $product->orgVerificationStatus);
        $this->assertFalse($product->isVerified());
        $this->assertIsArray($product->technicalParameters);
        $this->assertIsArray($product->technicalParameters['cavities']);
        $this->assertCount(1, $product->technicalParameters['cavities']);
        $this->assertIsArray($product->technicalParameters['cavities'][0]);
        $this->assertSame('A', $product->technicalParameters['cavities'][0]['energyClass']);
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

    public function testGetProductGroups(): void
    {
        $mock = new MockHandler([
            new Response(200, [], (string) json_encode([
                [
                    'code' => 'AIR_CONDITIONER',
                    'url_code' => 'airconditioners',
                    'name' => 'Air conditioners',
                    'regulation' => 'Regulation (EU) 626/2011'
                ],
                [
                    'code' => 'DOMESTIC_OVEN',
                    'url_code' => 'ovens',
                    'name' => 'Domestic Ovens',
                    'regulation' => 'Regulation (EU) 65/2014'
                ]
            ]))
        ]);

        $handlerStack = HandlerStack::create($mock);
        $httpClient = new GuzzleClient(['handler' => $handlerStack]);

        $client = new EprelClient(['httpClient' => $httpClient]);

        $groups = $client->getProductGroups();

        $this->assertCount(2, $groups);
        $this->assertSame('AIR_CONDITIONER', $groups[0]->code);
        $this->assertSame('airconditioners', $groups[0]->urlCode);
        $this->assertSame('Air conditioners', $groups[0]->name);
        $this->assertSame('Regulation (EU) 626/2011', $groups[0]->regulation);
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

    public function testGetProductsByGroup(): void
    {
        $mock = new MockHandler([
            new Response(200, [], (string) json_encode([
                'size' => 25,
                'offset' => 0,
                'hits' => [
                    [
                        'registrationNumber' => 'ABC',
                        'brandName' => 'Brand X'
                    ]
                ]
            ]))
        ]);

        $handlerStack = HandlerStack::create($mock);
        $httpClient = new GuzzleClient(['handler' => $handlerStack]);

        $client = new EprelClient(['httpClient' => $httpClient]);

        $page = $client->getProductsByGroup('REFRIGERATORS', ['_page' => 1, '_limit' => 10]);

        $this->assertCount(1, $page->hits);
        $this->assertSame('ABC', $page->hits[0]->registrationNumber);
        $this->assertSame(25, $page->size);
    }

    public function testGetProductsByGroupWithSearchQuery(): void
    {
        $mock = new MockHandler([
            new Response(200, [], (string) json_encode([
                'size' => 10,
                'offset' => 0,
                'hits' => []
            ]))
        ]);

        $container = [];
        $history = \GuzzleHttp\Middleware::history($container);
        $handlerStack = HandlerStack::create($mock);
        $handlerStack->push($history);

        $httpClient = new GuzzleClient(['handler' => $handlerStack]);

        $client = new EprelClient(['httpClient' => $httpClient]);

        $query = (new SearchQuery())
            ->page(2)
            ->limit(10)
            ->modelIdentifier('test-id')
            ->includeOldProducts()
            ->sort(0, 'energyClass', 'DESC')
            ->withFilter('heatSourceGas', true);

        $client->getProductsByGroup('ovens', $query);

        $this->assertIsArray($container);
        $this->assertCount(1, $container);
        /** @var array{request: \Psr\Http\Message\RequestInterface} $firstEntry */
        $firstEntry = $container[0];
        $request = $firstEntry['request'];
        $queryString = $request->getUri()->getQuery();

        $this->assertStringContainsString('_page=2', $queryString);
        $this->assertStringContainsString('_limit=10', $queryString);
        $this->assertStringContainsString('modelIdentifier=test-id', $queryString);
        $this->assertStringContainsString('includeOldProducts=true', $queryString);
        $this->assertStringContainsString('sort0=energyClass', $queryString);
        $this->assertStringContainsString('order0=DESC', $queryString);
        $this->assertStringContainsString('heatSourceGas=true', $queryString);
    }

    public function testGetLabelsBinary(): void
    {
        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'image/png'], 'fake-binary-data')
        ]);

        $handlerStack = HandlerStack::create($mock);
        $httpClient = new GuzzleClient(['handler' => $handlerStack]);

        $client = new EprelClient(['httpClient' => $httpClient]);

        $result = $client->getLabels('12345');

        $this->assertSame('fake-binary-data', $result);
    }

    public function testGetLabelsJson(): void
    {
        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], (string) json_encode([
                'address' => 'https://eprel.ec.europa.eu/label/12345.png'
            ]))
        ]);

        $handlerStack = HandlerStack::create($mock);
        $httpClient = new GuzzleClient(['handler' => $handlerStack]);

        $client = new EprelClient(['httpClient' => $httpClient]);

        $result = $client->getLabels('12345', null, ['noRedirect' => true]);

        $this->assertInstanceOf(AddressResponse::class, $result);
        $this->assertSame('https://eprel.ec.europa.eu/label/12345.png', $result->address);
    }

    public function testGetFichesBinary(): void
    {
        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/pdf'], 'fake-pdf-data')
        ]);

        $handlerStack = HandlerStack::create($mock);
        $httpClient = new GuzzleClient(['handler' => $handlerStack]);

        $client = new EprelClient(['httpClient' => $httpClient]);

        $result = $client->getFiches('12345', 'REFRIGERATORS', ['language' => 'EN']);

        $this->assertSame('fake-pdf-data', $result);
    }

    public function testGetFichesJson(): void
    {
        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], (string) json_encode([
                'address' => 'https://eprel.ec.europa.eu/fiche/12345.pdf'
            ]))
        ]);

        $handlerStack = HandlerStack::create($mock);
        $httpClient = new GuzzleClient(['handler' => $handlerStack]);

        $client = new EprelClient(['httpClient' => $httpClient]);

        $result = $client->getFiches('12345', null, ['noRedirect' => true]);

        $this->assertInstanceOf(AddressResponse::class, $result);
        $this->assertSame('https://eprel.ec.europa.eu/fiche/12345.pdf', $result->address);
    }

    public function testGetNestedLabelSuccess(): void
    {
        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'image/svg+xml'], '<svg>label</svg>')
        ]);

        $handlerStack = HandlerStack::create($mock);
        $httpClient = new GuzzleClient(['handler' => $handlerStack]);

        $client = new EprelClient(['httpClient' => $httpClient]);

        $result = $client->getNestedLabel('12345');

        $this->assertSame('<svg>label</svg>', $result);
    }

    public function testGetClassArrowWithScaleSuccess(): void
    {
        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'image/svg+xml'], '<svg>arrow_with_scale</svg>')
        ]);

        $handlerStack = HandlerStack::create($mock);
        $httpClient = new GuzzleClient(['handler' => $handlerStack]);

        $client = new EprelClient(['httpClient' => $httpClient]);

        $result = $client->getClassArrowWithScale('12345');

        $this->assertSame('<svg>arrow_with_scale</svg>', $result);
    }

    public function testGetProductByGtinSingle(): void
    {
        $mock = new MockHandler([
            new Response(200, [], (string) json_encode([
                'eprelRegistrationNumber' => 'GTIN1',
                'supplierOrTrademark' => 'Brand GTIN',
                'modelIdentifier' => 'MOD1'
            ]))
        ]);

        $handlerStack = HandlerStack::create($mock);
        $httpClient = new GuzzleClient(['handler' => $handlerStack]);

        $client = new EprelClient(['httpClient' => $httpClient]);

        $result = $client->getProductByGtin('12345678901234');

        $this->assertInstanceOf(ProductDetail::class, $result);
        $this->assertSame('GTIN1', $result->registrationNumber);
    }

    public function testGetProductByGtinMultiple(): void
    {
        $mock = new MockHandler([
            new Response(200, [], (string) json_encode([
                [
                    'eprelRegistrationNumber' => 'GTIN_MULTI_1',
                    'supplierOrTrademark' => 'Brand GTIN'
                ],
                [
                    'eprelRegistrationNumber' => 'GTIN_MULTI_2',
                    'supplierOrTrademark' => 'Brand GTIN'
                ]
            ]))
        ]);

        $handlerStack = HandlerStack::create($mock);
        $httpClient = new GuzzleClient(['handler' => $handlerStack]);

        $client = new EprelClient(['httpClient' => $httpClient]);

        $result = $client->getProductByGtin('12345678901234');

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertInstanceOf(ProductDetail::class, $result[0]);
        $this->assertSame('GTIN_MULTI_1', $result[0]->registrationNumber);
    }

    public function testExportProductsSuccess(): void
    {
        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/zip'], 'fake-zip-binary-data')
        ]);

        $handlerStack = HandlerStack::create($mock);
        $httpClient = new GuzzleClient(['handler' => $handlerStack]);

        $client = new EprelClient(['httpClient' => $httpClient]);

        $result = $client->exportProducts('ovens');

        $this->assertSame('fake-zip-binary-data', $result);
    }

    public function testGetEnergyClassImageSuccess(): void
    {
        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'image/svg+xml'], '<svg>arrow</svg>')
        ]);

        $handlerStack = HandlerStack::create($mock);
        $httpClient = new GuzzleClient(['handler' => $handlerStack]);

        $client = new EprelClient(['httpClient' => $httpClient]);

        $result = $client->getEnergyClassImage('G-Left-Red-WithAGScale.svg');

        $this->assertSame('<svg>arrow</svg>', $result);
    }
}
