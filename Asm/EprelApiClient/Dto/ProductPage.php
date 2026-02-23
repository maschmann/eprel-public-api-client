<?php

declare(strict_types=1);

namespace Asm\EprelApiClient\Dto;

/**
 * @api
 */
final readonly class ProductPage
{
    /**
     * @param ProductSummary[] $content
     */
    public function __construct(
        public array $content,
        public ?int $totalElements,
        public ?int $totalPages,
        public ?int $size,
        public ?int $number
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $content = [];
        if (isset($data['content']) && is_array($data['content'])) {
            /** @var mixed $item */
            foreach ($data['content'] as $item) {
                if (is_array($item)) {
                    /** @var array<string, mixed> $itemData */
                    $itemData = $item;
                    $content[] = ProductSummary::fromArray($itemData);
                }
            }
        }

        return new self(
            $content,
            isset($data['totalElements']) && is_int($data['totalElements']) ? $data['totalElements'] : null,
            isset($data['totalPages']) && is_int($data['totalPages']) ? $data['totalPages'] : null,
            isset($data['size']) && is_int($data['size']) ? $data['size'] : null,
            isset($data['number']) && is_int($data['number']) ? $data['number'] : null
        );
    }
}
