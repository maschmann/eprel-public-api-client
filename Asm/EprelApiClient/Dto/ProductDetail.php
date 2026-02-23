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
        public ?string $energyClassImage,
        public ?string $productGroup,
        public ?string $status,
        public bool $blocked,
        public ?string $orgVerificationStatus,
        public ?string $trademarkVerificationStatus,
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
        $regNum = null;
        if (isset($data['registrationNumber']) && is_string($data['registrationNumber'])) {
            $regNum = $data['registrationNumber'];
        } elseif (isset($data['eprelRegistrationNumber']) && is_string($data['eprelRegistrationNumber'])) {
            $regNum = $data['eprelRegistrationNumber'];
        }

        $brand = null;
        if (isset($data['brandName']) && is_string($data['brandName'])) {
            $brand = $data['brandName'];
        } elseif (isset($data['supplierOrTrademark']) && is_string($data['supplierOrTrademark'])) {
            $brand = $data['supplierOrTrademark'];
        }

        $knownKeys = [
            'registrationNumber' => true,
            'eprelRegistrationNumber' => true,
            'brandName' => true,
            'supplierOrTrademark' => true,
            'modelIdentifier' => true,
            'energyClass' => true,
            'energyClassImage' => true,
            'productGroup' => true,
            'status' => true,
            'blocked' => true,
            'orgVerificationStatus' => true,
            'trademarkVerificationStatus' => true,
            'energyLabelUrl' => true,
            'productInformationSheetUrl' => true,
            'technicalParameters' => true,
        ];

        /** @var array<string, mixed> $technicalParameters */
        $technicalParameters = isset($data['technicalParameters']) && is_array($data['technicalParameters'])
            ? $data['technicalParameters']
            : [];

        // Put any unknown root properties into technicalParameters
        /** @psalm-suppress MixedAssignment */
        foreach ($data as $key => $value) {
            if (!isset($knownKeys[$key])) {
                $technicalParameters[$key] = $value;
            }
        }

        return new self(
            $regNum,
            $brand,
            isset($data['modelIdentifier']) && is_string($data['modelIdentifier']) ? $data['modelIdentifier'] : null,
            isset($data['energyClass']) && is_string($data['energyClass']) ? $data['energyClass'] : null,
            isset($data['energyClassImage']) && is_string($data['energyClassImage']) ? $data['energyClassImage'] : null,
            isset($data['productGroup']) && is_string($data['productGroup']) ? $data['productGroup'] : null,
            isset($data['status']) && is_string($data['status']) ? $data['status'] : null,
            isset($data['blocked']) && (bool) $data['blocked'],
            isset($data['orgVerificationStatus']) && is_string($data['orgVerificationStatus']) ? $data['orgVerificationStatus'] : null,
            isset($data['trademarkVerificationStatus']) && is_string($data['trademarkVerificationStatus']) ? $data['trademarkVerificationStatus'] : null,
            isset($data['energyLabelUrl']) && is_string($data['energyLabelUrl']) ? $data['energyLabelUrl'] : null,
            isset($data['productInformationSheetUrl']) && is_string($data['productInformationSheetUrl']) ? $data['productInformationSheetUrl'] : null,
            $technicalParameters !== [] ? $technicalParameters : null
        );
    }

    public function isVerified(): bool
    {
        return $this->orgVerificationStatus === 'VERIFIED';
    }

    public function isTrademarkVerified(): bool
    {
        return $this->trademarkVerificationStatus === 'VERIFIED';
    }
}
