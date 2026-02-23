# EPREL API Client for PHP

[![CI](https://github.com/asm/eprel-api-client/actions/workflows/ci.yml/badge.svg)](https://github.com/asm/eprel-api-client/actions/workflows/ci.yml)
[![PHP Version](https://img.shields.io/badge/php-8.4+-8892BF.svg)](https://php.net/)

A modern, fluent, and strongly-typed PHP client to interact with the **EPREL** (European Product Database for Energy Labelling) Public API.

## What is EPREL?
The **Europäische Produktdatenbank für Energieverbrauchskennzeichnung** (European Product Database for Energy Labelling) is a database managed by the European Commission. It allows consumers to search for and verify the energy labels and product information sheets of appliances sold within the EU (e.g., refrigerators, washing machines, televisions, etc.).

This library allows you to seamlessly integrate the public search and retrieval capabilities of the EPREL database into your PHP applications.

## Requirements
- PHP 8.4 or higher
- A PSR-18 compatible HTTP Client (e.g., Guzzle)
- A PSR-17 compatible HTTP Factory
- *(Optional but recommended)* A PSR-6 compatible Cache Implementation (defaults to an in-memory array cache)

## Installation

You can install the package via composer:

```bash
composer require asm/eprel-api-client
```

## Usage

### Basic Initialization

You can initialize the client using an array of parameters or through a fluent configuration interface.

```php
use Asm\EprelApiClient\EprelClient;

// Using the Fluent API (Recommended)
$client = (new EprelClient())
    ->uri('https://eprel.ec.europa.eu/api')
    ->apiKey('your-api-key') // If required by the API
    ->version('1.6.3')
    ->cacheTtl(3600); // Cache responses for 1 hour

// Alternatively, using the constructor:
$client = new EprelClient([
    'uri' => 'https://eprel.ec.europa.eu/api',
    'cacheTtl' => 3600,
]);
```

### Searching for Products

You can retrieve a paginated list of products matching specific filter criteria (like the product group or energy class).

```php
$page = $client->getProducts([
    'productGroup' => 'REFRIGERATORS',
    'energyClass' => 'A',
    'size' => 20,
    'page' => 0
]);

echo "Total Elements found: " . $page->totalElements . "\n";

foreach ($page->content as $productSummary) {
    echo "Brand: " . $productSummary->brandName . "\n";
    echo "Model: " . $productSummary->modelIdentifier . "\n";
    echo "EPREL Reg Number: " . $productSummary->registrationNumber . "\n";
    echo "Energy Class: " . $productSummary->energyClass . "\n";
    echo "---\n";
}
```

### Fetching Product Details

To retrieve the complete details, technical parameters, and URLs for the energy labels of a specific product, use its EPREL registration number.

```php
use Asm\EprelApiClient\Exception\ResourceNotFoundException;

try {
    $productDetail = $client->getProduct('123456');
    
    echo "Brand: " . $productDetail->brandName . "\n";
    echo "Label URL: " . $productDetail->energyLabelUrl . "\n";
    echo "Product Sheet URL: " . $productDetail->productInformationSheetUrl . "\n";
    
    // Access arbitrary technical parameters provided by the API
    if (isset($productDetail->technicalParameters['volume'])) {
        echo "Volume: " . $productDetail->technicalParameters['volume'] . " L\n";
    }

} catch (ResourceNotFoundException $e) {
    echo "Product not found in the EPREL database.";
} catch (\Throwable $e) {
    echo "An API error occurred: " . $e->getMessage();
}
```

## Caching

To avoid hitting rate limits and to significantly improve your application's performance, it is highly recommended to inject a persistent PSR-6 Cache Item Pool.

By default, the client uses an in-memory `ArrayAdapter` which only persists data for the duration of the current PHP script execution.

```php
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Asm\EprelApiClient\EprelClient;

$redisConnection = RedisAdapter::createConnection('redis://localhost');
$cachePool = new RedisAdapter($redisConnection);

$client = (new EprelClient())
    ->setCache($cachePool)
    ->cacheTtl(86400); // Cache for 24 hours
```

## Development & Testing

This library comes with a Dockerized environment for easy development and testing.

```bash
# Start the docker environment and install composer dependencies
make setup

# Run PHPUnit tests
make test

# Run code style checks (PHPCS)
make cs

# Run static analysis (PHPStan Level max)
make stan

# Run Psalm
make psalm

# Run all tests and quality checks
make all
```

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
