<?php

declare(strict_types=1);

namespace Asm\EprelApiClient\Dto;

/**
 * @api
 */
final readonly class ProductGroupPage
{
    /**
     * @param ProductSummary[] $hits
     */
    public function __construct(
        public array $hits,
        public ?int $size,
        public ?int $offset
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $hits = [];
        if (isset($data['hits']) && is_array($data['hits'])) {
            /** @var mixed $item */
            foreach ($data['hits'] as $item) {
                if (is_array($item)) {
                    /** @var array<string, mixed> $itemData */
                    $itemData = $item;
                    $hits[] = ProductSummary::fromArray($itemData);
                }
            }
        }

        return new self(
            $hits,
            isset($data['size']) && is_int($data['size']) ? $data['size'] : null,
            isset($data['offset']) && is_int($data['offset']) ? $data['offset'] : null
        );
    }
}
