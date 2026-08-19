<?php
declare(strict_types=1);

namespace App;

use App\Exception\AvailabilityDomainRequiredException;
use App\Exception\BootVolumeSizeException;

class Config
{
    public string $region = '';
    public string $userId = '';
    public string $tenancyId = '';
    public string $keyFingerPrint = '';
    public string $privateKeyFilename = '';

    /**
     * @var array|string|null
     */
    public $availabilityDomains;
    public string $subnetId = '';
    public string $imageId = '';
    public ?float $ocpus;
    public ?float $memoryInGBs;

    public string $sourceDetails;
    public string $bootVolumeId;
    public string $bootVolumeSizeInGBs;

    /**
     * @param string $region
     * @param string $userId
     * @param string $tenancyId
     * @param string $keyFingerPrint
     * @param string $privateKeyFilename
     * @param string|array|null $availabilityDomains
     * @param string $subnetId
     * @param string $imageId
     * @param int $ocups
     * @param int $memoryInGBs
     */
    public function __construct(
        string $region,
        string $userId,
        string $tenancyId,
        string $keyFingerPrint,
        string $privateKeyFilename,
        $availabilityDomains,
        string $subnetId,
        string $imageId,
        float $ocups = 4.0,
float $memoryInGBs = 24.0
    ) {
        $this->region = $region;
        $this->userId = $userId;
        $this->tenancyId = $tenancyId;
        $this->keyFingerPrint = $keyFingerPrint;
        $this->privateKeyFilename = $privateKeyFilename;
        $this->availabilityDomains = $availabilityDomains;
        $this->subnetId = $subnetId;
        $this->imageId = $imageId;
        $this->ocpus = $ocups;
        $this->memoryInGBs = $memoryInGBs;
        $this->imageId = $imageId;
    }

    /**
     * @param string $bootVolumeId
     */
    public function setBootVolumeId(string $bootVolumeId): void
    {
        $this->bootVolumeId = $bootVolumeId;
    }

    /**
     * @return string
     * @throws AvailabilityDomainRequiredException|BootVolumeSizeException
     */
    public function getSourceDetails(): string
    {
        if (isset($this->sourceDetails)) {
            return $this->sourceDetails;
        }

        $sourceDetails = [
            'sourceType' => 'image',
            'imageId' => $this->imageId,
        ];

        if (!empty($this->bootVolumeId) && !empty($this->bootVolumeSizeInGBs)) {
            throw new BootVolumeSizeException('BOOT_VOLUME_ID and BOOT_VOLUME_SIZE_IN_GBS cannot be used together');
        }

        if (!empty($this->bootVolumeSizeInGBs)) {
            if (!is_numeric($this->bootVolumeSizeInGBs)) {
                throw new BootVolumeSizeException('BOOT_VOLUME_SIZE_IN_GBS must be numeric');
            }
            $sourceDetails['bootVolumeSizeInGBs'] = $this->bootVolumeSizeInGBs;
        } elseif (!empty($this->bootVolumeId)) {
            if (!is_string($this->availabilityDomains) || empty($this->availabilityDomains)) {
                throw new AvailabilityDomainRequiredException('AVAILABILITY_DOMAIN must be specified as string if using BOOT_VOLUME_ID');
            }

            $sourceDetails = [
                'sourceType' => 'bootVolume',
                'bootVolumeId' => $this->bootVolumeId,
            ];
        }

        return json_encode($sourceDetails);
    }

    /**
     * @param string $bootVolumeSizeInGBs
     */
    public function setBootVolumeSizeInGBs(string $bootVolumeSizeInGBs): void
    {
        $this->bootVolumeSizeInGBs = $bootVolumeSizeInGBs;
    }

    /**
     * @param string $sourceDetails
     */
    public function setSourceDetails(string $sourceDetails): void
    {
        $this->sourceDetails = $sourceDetails;
    }
}
