<?php

declare(strict_types=1);

namespace Asm\EprelApiClient;

use Asm\EprelApiClient\Dto\AddressResponse;
use Asm\EprelApiClient\Dto\ProductDetail;
use Asm\EprelApiClient\Dto\ProductGroup;
use Asm\EprelApiClient\Dto\ProductGroupPage;
use Asm\EprelApiClient\Dto\ProductPage;
use Asm\EprelApiClient\Exception\EprelApiException;
use Asm\EprelApiClient\Exception\ResourceNotFoundException;
use Asm\EprelApiClient\Query\SearchQuery;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

/**
 * @api
 */
final class EprelClient
{
    private ?CacheItemPoolInterface $cache = null;
    private LoggerInterface $logger;
    private int $cacheTtl = 3600;
    private string $uri = 'https://eprel.ec.europa.eu/api';
    private string $assetsUri = 'https://ec.europa.eu/assets/move-ener/eprel/EPREL%20Public/Nested-labels%20thumbnails/';
    private ?string $apiKey = null;
    private string $version = 'latest';
    private ?ClientInterface $httpClient = null;

    /**
     * @param array<string, mixed> $params
     */
    public function __construct(array $params = [])
    {
        $this->logger = new NullLogger();

        if (isset($params['cache']) && $params['cache'] instanceof CacheItemPoolInterface) {
            $this->cache = $params['cache'];
        }

        if (isset($params['cacheTtl']) && is_int($params['cacheTtl'])) {
            $this->cacheTtl = $params['cacheTtl'];
        }

        if (isset($params['uri']) && is_string($params['uri'])) {
            $this->uri = $params['uri'];
        }

        if (isset($params['assetsUri']) && is_string($params['assetsUri'])) {
            $this->assetsUri = $params['assetsUri'];
        }

        if (isset($params['apiKey']) && is_string($params['apiKey'])) {
            $this->apiKey = $params['apiKey'];
        }

        if (isset($params['version']) && is_string($params['version'])) {
            $this->version = $params['version'];
        }

        if (isset($params['logger']) && $params['logger'] instanceof LoggerInterface) {
            $this->logger = $params['logger'];
        }

        if (isset($params['httpClient']) && $params['httpClient'] instanceof ClientInterface) {
            $this->httpClient = $params['httpClient'];
        }
    }

    public function cacheTtl(int $ttl): self
    {
        $this->cacheTtl = $ttl;
        $this->httpClient = null; // Reset client on config change
        return $this;
    }

    public function getCacheTtl(): int
    {
        return $this->cacheTtl;
    }

    public function uri(string $uri): self
    {
        $this->uri = $uri;
        $this->httpClient = null;
        return $this;
    }

    public function assetsUri(string $uri): self
    {
        $this->assetsUri = $uri;
        return $this;
    }

    public function apiKey(string $apiKey): self
    {
        $this->apiKey = $apiKey;
        $this->httpClient = null;
        return $this;
    }

    public function version(string $version): self
    {
        $this->version = $version;
        $this->httpClient = null;
        return $this;
    }

    public function setCache(CacheItemPoolInterface $cache): self
    {
        $this->cache = $cache;
        return $this;
    }

    private function initialize(): void
    {
        if ($this->cache === null) {
            $this->cache = new ArrayAdapter();
        }

        if ($this->httpClient !== null) {
            return;
        }

        $config = [
            'base_uri' => $this->uri,
            'headers' => [
                'Accept' => 'application/json',
                'User-Agent' => 'Asm-Eprel-SDK/1.0',
            ],
        ];

        if ($this->apiKey !== null) {
            $config['headers']['X-Api-Key'] = $this->apiKey;
        }

        // Apply versioning logic if needed in base_uri or headers
        if ($this->version !== 'latest') {
            $config['headers']['X-Api-Version'] = $this->version;
        }

        $this->httpClient = new GuzzleClient($config);
    }

    public function getHttpClient(): ClientInterface
    {
        $this->initialize();
        \assert($this->httpClient instanceof ClientInterface);
        return $this->httpClient;
    }

    public function ping(): bool
    {
        $this->initialize();
        \assert($this->httpClient instanceof ClientInterface);

        try {
            $response = $this->httpClient->request('GET', '/ping');
            return $response->getStatusCode() === 200;
        } catch (\Throwable $e) {
            $this->logger->error('Ping failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * @return ProductGroup[]
     * @throws EprelApiException
     */
    public function getProductGroups(): array
    {
        $this->initialize();
        \assert($this->httpClient instanceof ClientInterface);
        \assert($this->cache instanceof CacheItemPoolInterface);

        $cacheKey = 'eprel_product_groups';
        try {
            $item = $this->cache->getItem($cacheKey);
            if ($item->isHit()) {
                /** @var array<int, array<string, mixed>> $data */
                $data = $item->get();
                return array_map(fn (array $group) => ProductGroup::fromArray($group), $data);
            }

            $response = $this->httpClient->request('GET', '/product-groups');

            /** @var array<int, array<string, mixed>> $data */
            $data = json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);

            $item->set($data);
            $item->expiresAfter($this->cacheTtl);
            $this->cache->save($item);

            return array_map(fn (array $group) => ProductGroup::fromArray($group), $data);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to fetch product groups: ' . $e->getMessage());
            throw new EprelApiException('Failed to fetch product groups: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * @param array<string, mixed> $queryParams
     * @throws EprelApiException
     */
    public function getProducts(array $queryParams = []): ProductPage
    {
        $this->initialize();
        \assert($this->httpClient instanceof ClientInterface);
        \assert($this->cache instanceof CacheItemPoolInterface);

        $encoded = json_encode($queryParams);
        $cacheKey = 'eprel_products_' . md5($encoded === false ? '' : $encoded);
        try {
            $item = $this->cache->getItem($cacheKey);
            if ($item->isHit()) {
                /** @var array<string, mixed> $data */
                $data = $item->get();
                return ProductPage::fromArray($data);
            }

            $response = $this->httpClient->request('GET', '/products', [
                'query' => $queryParams,
            ]);

            /** @var array<string, mixed> $data */
            $data = json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);

            $item->set($data);
            $item->expiresAfter($this->cacheTtl);
            $this->cache->save($item);

            return ProductPage::fromArray($data);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to fetch products: ' . $e->getMessage());
            throw new EprelApiException('Failed to fetch products: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * @param array<string, mixed>|SearchQuery $queryParams
     * @throws EprelApiException
     */
    public function getProductsByGroup(string $productGroup, array|SearchQuery $queryParams = []): ProductGroupPage
    {
        $this->initialize();
        \assert($this->httpClient instanceof ClientInterface);
        \assert($this->cache instanceof CacheItemPoolInterface);

        if ($queryParams instanceof SearchQuery) {
            $queryParams = $queryParams->toArray();
        }

        $encoded = json_encode($queryParams);
        $cacheKey = 'eprel_group_products_' . md5($productGroup . ($encoded === false ? '' : $encoded));
        try {
            $item = $this->cache->getItem($cacheKey);
            if ($item->isHit()) {
                /** @var array<string, mixed> $data */
                $data = $item->get();
                return ProductGroupPage::fromArray($data);
            }

            $response = $this->httpClient->request('GET', '/products/' . urlencode($productGroup), [
                'query' => $queryParams,
            ]);

            /** @var array<string, mixed> $data */
            $data = json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);

            $item->set($data);
            $item->expiresAfter($this->cacheTtl);
            $this->cache->save($item);

            return ProductGroupPage::fromArray($data);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to fetch group products: ' . $e->getMessage());
            throw new EprelApiException('Failed to fetch group products: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * @throws ResourceNotFoundException
     * @throws EprelApiException
     */
    public function getProduct(string $registrationNumber, ?string $productGroup = null): ProductDetail
    {
        $this->initialize();
        \assert($this->httpClient instanceof ClientInterface);
        \assert($this->cache instanceof CacheItemPoolInterface);

        $cacheKey = 'eprel_product_' . md5(($productGroup ?? 'none') . '_' . $registrationNumber);
        try {
            $item = $this->cache->getItem($cacheKey);
            if ($item->isHit()) {
                /** @var array<string, mixed> $data */
                $data = $item->get();
                return ProductDetail::fromArray($data);
            }

            $path = $productGroup !== null
                ? '/products/' . urlencode($productGroup) . '/' . urlencode($registrationNumber)
                : '/product/' . urlencode($registrationNumber);

            $response = $this->httpClient->request('GET', $path);

            /** @var array<string, mixed> $data */
            $data = json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);

            $item->set($data);
            $item->expiresAfter($this->cacheTtl);
            $this->cache->save($item);

            return ProductDetail::fromArray($data);
        } catch (ClientException $e) {
            if ($e->getResponse()->getStatusCode() === 404) {
                throw new ResourceNotFoundException('Product not found: ' . $registrationNumber, 0, $e);
            }
            $this->logger->error('Client error fetching product: ' . $e->getMessage());
            throw new EprelApiException('Failed to fetch product: ' . $e->getMessage(), 0, $e);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to fetch product: ' . $e->getMessage());
            throw new EprelApiException('Failed to fetch product: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * @param array<string, mixed> $queryParams
     * @throws EprelApiException
     */
    public function getLabels(
        string $registrationNumber,
        ?string $productGroup = null,
        array $queryParams = []
    ): string|AddressResponse {
        $this->initialize();
        \assert($this->httpClient instanceof ClientInterface);

        try {
            $path = $productGroup !== null
                ? '/products/' . urlencode($productGroup) . '/' . urlencode($registrationNumber) . '/labels'
                : '/product/' . urlencode($registrationNumber) . '/labels';

            $response = $this->httpClient->request('GET', $path, [
                'query' => $queryParams,
            ]);

            $contentType = $response->getHeaderLine('Content-Type');

            if (str_contains($contentType, 'application/json')) {
                /** @var array<string, mixed> $data */
                $data = json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);
                return AddressResponse::fromArray($data);
            }

            return $response->getBody()->getContents();
        } catch (ClientException $e) {
            if ($e->getResponse()->getStatusCode() === 404) {
                throw new ResourceNotFoundException('Labels not found for product: ' . $registrationNumber, 0, $e);
            }
            throw new EprelApiException('Failed to fetch labels: ' . $e->getMessage(), 0, $e);
        } catch (\Throwable $e) {
            throw new EprelApiException('Failed to fetch labels: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * @param array<string, mixed> $queryParams
     * @throws EprelApiException
     */
    public function getFiches(
        string $registrationNumber,
        ?string $productGroup = null,
        array $queryParams = []
    ): string|AddressResponse {
        $this->initialize();
        \assert($this->httpClient instanceof ClientInterface);

        try {
            $path = $productGroup !== null
                ? '/products/' . urlencode($productGroup) . '/' . urlencode($registrationNumber) . '/fiches'
                : '/product/' . urlencode($registrationNumber) . '/fiches';

            $response = $this->httpClient->request('GET', $path, [
                'query' => $queryParams,
            ]);

            $contentType = $response->getHeaderLine('Content-Type');

            if (str_contains($contentType, 'application/json')) {
                /** @var array<string, mixed> $data */
                $data = json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);
                return AddressResponse::fromArray($data);
            }

            return $response->getBody()->getContents();
        } catch (ClientException $e) {
            if ($e->getResponse()->getStatusCode() === 404) {
                throw new ResourceNotFoundException('Fiches not found for product: ' . $registrationNumber, 0, $e);
            }
            throw new EprelApiException('Failed to fetch fiches: ' . $e->getMessage(), 0, $e);
        } catch (\Throwable $e) {
            throw new EprelApiException('Failed to fetch fiches: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * @throws ResourceNotFoundException
     * @throws EprelApiException
     */
    public function getNestedLabel(string $registrationNumber): string
    {
        $this->initialize();
        \assert($this->httpClient instanceof ClientInterface);

        try {
            $response = $this->httpClient->request('GET', '/product/' . urlencode($registrationNumber) . '/nested-label');

            return $response->getBody()->getContents();
        } catch (ClientException $e) {
            if ($e->getResponse()->getStatusCode() === 404) {
                throw new ResourceNotFoundException('Nested label not found for product: ' . $registrationNumber, 0, $e);
            }
            throw new EprelApiException('Client error fetching nested label: ' . $e->getMessage(), $e->getResponse()->getStatusCode(), $e);
        } catch (\Throwable $e) {
            throw new EprelApiException('Server error or network issue fetching nested label: ' . $e->getMessage(), 500, $e);
        }
    }

    /**
     * @throws ResourceNotFoundException
     * @throws EprelApiException
     */
    public function getClassArrowWithScale(string $registrationNumber): string
    {
        $this->initialize();
        \assert($this->httpClient instanceof ClientInterface);

        try {
            $response = $this->httpClient->request('GET', '/product/' . urlencode($registrationNumber) . '/class-arrow-with-scale');

            return $response->getBody()->getContents();
        } catch (ClientException $e) {
            if ($e->getResponse()->getStatusCode() === 404) {
                throw new ResourceNotFoundException('Class arrow with scale not found for product: ' . $registrationNumber, 0, $e);
            }
            throw new EprelApiException('Client error fetching class arrow with scale: ' . $e->getMessage(), $e->getResponse()->getStatusCode(), $e);
        } catch (\Throwable $e) {
            throw new EprelApiException('Server error or network issue fetching class arrow with scale: ' . $e->getMessage(), 500, $e);
        }
    }

    /**
     * @return ProductDetail|ProductDetail[]
     * @throws ResourceNotFoundException
     * @throws EprelApiException
     */
    public function getProductByGtin(string $gtin): ProductDetail|array
    {
        $this->initialize();
        \assert($this->httpClient instanceof ClientInterface);

        try {
            $response = $this->httpClient->request('GET', '/product/gtin/' . urlencode($gtin));
            /** @psalm-suppress MixedAssignment */
            $data = json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);

            if (is_array($data) && isset($data[0]) && is_array($data[0])) {
                /** @var array<int, array<string, mixed>> $data */
                return array_map(fn (array $item) => ProductDetail::fromArray($item), $data);
            }

            if (is_array($data)) {
                /** @var array<string, mixed> $data */
                return ProductDetail::fromArray($data);
            }

            throw new EprelApiException('Invalid response format from GTIN search.');
        } catch (ClientException $e) {
            if ($e->getResponse()->getStatusCode() === 404) {
                throw new ResourceNotFoundException('Product not found for GTIN: ' . $gtin, 0, $e);
            }
            throw new EprelApiException('Client error fetching product by GTIN: ' . $e->getMessage(), $e->getResponse()->getStatusCode(), $e);
        } catch (\Throwable $e) {
            throw new EprelApiException('Server error or network issue fetching product by GTIN: ' . $e->getMessage(), 500, $e);
        }
    }

    /**
     * @throws EprelApiException
     */
    public function exportProducts(string $productGroup): string
    {
        $this->initialize();
        \assert($this->httpClient instanceof ClientInterface);

        try {
            $response = $this->httpClient->request('GET', '/exportProducts/' . urlencode($productGroup));

            return $response->getBody()->getContents();
        } catch (ClientException $e) {
            throw new EprelApiException('Client error exporting products: ' . $e->getMessage(), $e->getResponse()->getStatusCode(), $e);
        } catch (\Throwable $e) {
            throw new EprelApiException('Server error or network issue exporting products: ' . $e->getMessage(), 500, $e);
        }
    }

    /**
     * @throws EprelApiException
     */
    public function getEnergyClassImage(string $fileName): string
    {
        $this->initialize();
        \assert($this->httpClient instanceof ClientInterface);
        \assert($this->cache instanceof CacheItemPoolInterface);

        $cacheKey = 'eprel_energy_arrow_' . md5($fileName);
        try {
            $item = $this->cache->getItem($cacheKey);
            if ($item->isHit()) {
                /** @psalm-suppress MixedAssignment */
                $cached = $item->get();
                return is_string($cached) ? $cached : '';
            }

            $response = $this->httpClient->request('GET', $this->assetsUri . $fileName);
            $content = $response->getBody()->getContents();

            $item->set($content);
            $item->expiresAfter($this->cacheTtl);
            $this->cache->save($item);

            return $content;
        } catch (\Throwable $e) {
            throw new EprelApiException('Failed to fetch energy class image: ' . $e->getMessage(), 0, $e);
        }
    }
}
