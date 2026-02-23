<?php

declare(strict_types=1);

namespace Asm\EprelApiClient;

use Asm\EprelApiClient\Dto\ProductDetail;
use Asm\EprelApiClient\Dto\ProductPage;
use Asm\EprelApiClient\Exception\EprelApiException;
use Asm\EprelApiClient\Exception\ResourceNotFoundException;
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
     * @throws ResourceNotFoundException
     * @throws EprelApiException
     */
    public function getProduct(string $registrationNumber): ProductDetail
    {
        $this->initialize();
        \assert($this->httpClient instanceof ClientInterface);
        \assert($this->cache instanceof CacheItemPoolInterface);

        $cacheKey = 'eprel_product_' . md5($registrationNumber);
        try {
            $item = $this->cache->getItem($cacheKey);
            if ($item->isHit()) {
                /** @var array<string, mixed> $data */
                $data = $item->get();
                return ProductDetail::fromArray($data);
            }

            $response = $this->httpClient->request('GET', '/products/' . urlencode($registrationNumber));

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
}
