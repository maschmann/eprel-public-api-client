<?php

declare(strict_types=1);

namespace Asm\EprelApiClient\Dto;

/**
 * @api
 */
final readonly class AddressResponse
{
    public function __construct(
        public ?string $address = null,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            isset($data['address']) && is_string($data['address']) ? $data['address'] : null
        );
    }
}
