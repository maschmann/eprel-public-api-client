<?php

declare(strict_types=1);

namespace Asm\EprelApiClient\Dto;

/**
 * @api
 */
final readonly class ProductGroup
{
    public function __construct(
        public ?string $code,
        public ?string $urlCode,
        public ?string $name,
        public ?string $regulation
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            isset($data['code']) && is_string($data['code']) ? $data['code'] : null,
            isset($data['url_code']) && is_string($data['url_code']) ? $data['url_code'] : null,
            isset($data['name']) && is_string($data['name']) ? $data['name'] : null,
            isset($data['regulation']) && is_string($data['regulation']) ? $data['regulation'] : null
        );
    }
}
