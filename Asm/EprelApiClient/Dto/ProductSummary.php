<?php

declare(strict_types=1);

namespace Asm\EprelApiClient\Dto;

/**
 * @api
 */
final readonly class ProductSummary
{
    public function __construct(
        public ?string $registrationNumber,
        public ?string $brandName,
        public ?string $modelIdentifier,
        public ?string $energyClass,
        public ?string $productGroup
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            isset($data['registrationNumber']) && is_string($data['registrationNumber']) ? $data['registrationNumber'] : null,
            isset($data['brandName']) && is_string($data['brandName']) ? $data['brandName'] : null,
            isset($data['modelIdentifier']) && is_string($data['modelIdentifier']) ? $data['modelIdentifier'] : null,
            isset($data['energyClass']) && is_string($data['energyClass']) ? $data['energyClass'] : null,
            isset($data['productGroup']) && is_string($data['productGroup']) ? $data['productGroup'] : null
        );
    }
}
