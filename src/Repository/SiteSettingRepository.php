<?php

namespace App\Repository;

use App\Entity\SiteSetting;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class SiteSettingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SiteSetting::class);
    }

    public function findByKey(string $key): ?SiteSetting
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
        
        return match ($setting->getSettingType()) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $value,
            'float' => (float) $value,
            'json' => json_decode($value, true),
            default => $value,
        };
    }

    public function setValue(string $key, mixed $value, string $type = 'string', ?string $description = null): void
    {
        $setting = $this->findByKey($key);
        
        if (!$setting) {
            $setting = new SiteSetting();
            $setting->setSettingKey($key);
        }

        $stringValue = match ($type) {
            'boolean' => $value ? '1' : '0',
            'json' => json_encode($value),
            default => (string) $value,
        };

        $setting->setSettingValue($stringValue);
        $setting->setSettingType($type);
        
        if ($description) {
            $setting->setDescription($description);
        }

        $this->getEntityManager()->persist($setting);
        $this->getEntityManager()->flush();
    }
}
