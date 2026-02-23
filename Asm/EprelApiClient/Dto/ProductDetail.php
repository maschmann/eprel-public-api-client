<?php

declare(strict_types=1);

namespace Asm\EprelApiClient\Dto;

/**
 * @api
 */
final readonly class ProductDetail
{
    /**
     * @param array<string, mixed>|null $technicalParameters
     */
    public function __construct(
        public ?string $registrationNumber,
        public ?string $brandName,
        public ?string $modelIdentifier,
        public ?string $energyClass,
        public ?string $productGroup,
        public ?string $energyLabelUrl,
        public ?string $productInformationSheetUrl,
        public ?array $technicalParameters
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        /** @var array<string, mixed>|null $technicalParameters */
        $technicalParameters = isset($data['technicalParameters']) && is_array($data['technicalParameters']) ? $data['technicalParameters'] : null;

        return new self(
            isset($data['registrationNumber']) && is_string($data['registrationNumber']) ? $data['registrationNumber'] : null,
            isset($data['brandName']) && is_string($data['brandName']) ? $data['brandName'] : null,
            isset($data['modelIdentifier']) && is_string($data['modelIdentifier']) ? $data['modelIdentifier'] : null,
            isset($data['energyClass']) && is_string($data['energyClass']) ? $data['energyClass'] : null,
            isset($data['productGroup']) && is_string($data['productGroup']) ? $data['productGroup'] : null,
            isset($data['energyLabelUrl']) && is_string($data['energyLabelUrl']) ? $data['energyLabelUrl'] : null,
            isset($data['productInformationSheetUrl']) && is_string($data['productInformationSheetUrl']) ? $data['productInformationSheetUrl'] : null,
            $technicalParameters
        );
    }
}
