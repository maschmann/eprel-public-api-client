<?php

declare(strict_types=1);

namespace Asm\EprelApiClient\Query;

/**
 * @api
 */
final class SearchQuery
{
    /** @var array<string, mixed> */
    private array $params = [];

    public function page(int $page): self
    {
        $this->params['_page'] = $page;
        return $this;
    }

    public function limit(int $limit): self
    {
        $this->params['_limit'] = $limit;
        return $this;
    }

    public function modelIdentifier(string $identifier): self
    {
        $this->params['modelIdentifier'] = $identifier;
        return $this;
    }

    public function supplierOrTrademark(string $supplier): self
    {
        $this->params['supplierOrTrademark'] = $supplier;
        return $this;
    }

    public function gtinIdentifier(string $gtin): self
    {
        $this->params['gtinIdentifier'] = $gtin;
        return $this;
    }

    public function includeOldProducts(bool $include = true): self
    {
        $this->params['includeOldProducts'] = $include ? 'true' : 'false';
        return $this;
    }

    public function sort(int $index, string $field, string $order = 'ASC'): self
    {
        if ($index < 0 || $index > 2) {
            throw new \InvalidArgumentException('Sort index must be between 0 and 2.');
        }

        $this->params["sort{$index}"] = $field;
        $this->params["order{$index}"] = strtoupper($order);

        return $this;
    }

    public function withFilter(string $name, string|int|float|bool $value): self
    {
        if (is_bool($value)) {
            $value = $value ? 'true' : 'false';
        }

        $this->params[$name] = $value;
        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->params;
    }
}
