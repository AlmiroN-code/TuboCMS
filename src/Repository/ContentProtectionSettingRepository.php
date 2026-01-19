<?php

namespace App\Repository;

use App\Entity\ContentProtectionSetting;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ContentProtectionSetting>
 */
class ContentProtectionSettingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ContentProtectionSetting::class);
    }

    public function findByKey(string $key): ?ContentProtectionSetting
    {
        return $this->findOneBy(['settingKey' => $key]);
    }

    public function getValue(string $key, mixed $default = null): mixed
    {
        $setting = $this->findByKey($key);
        
        if (!$setting) {
            return $default;
        }

        $value = $setting->getSettingValue();
        
        // Try to decode JSON
        $decoded = json_decode($value, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }
        
        return $value;
    }

    public function setValue(string $key, mixed $value): void
    {
        $setting = $this->findByKey($key);
        
        if (!$setting) {
            $setting = new ContentProtectionSetting();
            $setting->setSettingKey($key);
        }

        // Encode arrays/objects as JSON
        if (is_array($value) || is_object($value)) {
            $value = json_encode($value);
        }

        $setting->setSettingValue($value);
        $setting->setUpdatedAt(new \DateTime());

        $this->getEntityManager()->persist($setting);
        $this->getEntityManager()->flush();
    }
}
