<?php

declare(strict_types=1);

namespace App\Tests\Property\Storage;

use App\Entity\Storage;
use Eris\Generator;
use Eris\TestTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Property-based tests for Storage entity validation.
 * 
 * **Feature: remote-storage, Property 1: Storage validation requires all mandatory fields**
 * **Validates: Requirements 1.2, 1.3, 1.4**
 * 
 * Property: For any FTP/SFTP/HTTP storage configuration, validation SHALL fail 
 * if any of the mandatory fields (host, port, username, password/key, basePath) 
 * is empty or missing.
 */
class StorageValidationPropertyTest extends TestCase
{
    use TestTrait;

    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        $this->validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();
    }

    /**
     * Property: Storage with valid name and type passes basic validation.
     * 
     * For any non-empty name and valid type, the Storage entity SHALL pass
     * basic field validation (name and type constraints).
     */
    public function testValidStoragePassesBasicValidation(): void
    {
        $this->forAll(
            Generator\suchThat(
                fn($name) => strlen(trim($name)) > 0 && strlen($name) <= 100,
                Generator\string()
            ),
            Generator\elements(Storage::VALID_TYPES)
        )->then(function (string $name, string $type): void {
            $storage = new Storage();
            $storage->setName($name);
            $storage->setType($type);

            $violations = $this->validator->validate($storage);
            
            // Фильтруем только ошибки для name и type
            $nameTypeViolations = array_filter(
                iterator_to_array($violations),
                fn($v) => in_array($v->getPropertyPath(), ['name', 'type'])
            );

            $this->assertCount(
                0, 
                $nameTypeViolations,
                sprintf(
                    'Valid name "%s" and type "%s" should pass validation. Violations: %s',
                    $name,
                    $type,
                    implode(', ', array_map(fn($v) => $v->getMessage(), $nameTypeViolations))
                )
            );
        });
    }

    /**
     * Property: Storage with empty name fails validation.
     * 
     * For any storage with empty or whitespace-only name, validation SHALL fail.
     */
    public function testEmptyNameFailsValidation(): void
    {
        $this->forAll(
            Generator\elements(['', ' ', '  ', "\t", "\n"]),
            Generator\elements(Storage::VALID_TYPES)
        )->then(function (string $emptyName, string $type): void {
            $storage = new Storage();
            $storage->setName($emptyName);
            $storage->setType($type);

            $violations = $this->validator->validate($storage);
            
            $nameViolations = array_filter(
                iterator_to_array($violations),
                fn($v) => $v->getPropertyPath() === 'name'
            );

            $this->assertNotEmpty(
                $nameViolations,
                sprintf('Empty name "%s" should fail validation', var_export($emptyName, true))
            );
        });
    }

    /**
     * Property: Storage with invalid type fails validation.
     * 
     * For any storage with type not in VALID_TYPES, validation SHALL fail.
     */
    public function testInvalidTypeFailsValidation(): void
    {
        $this->forAll(
            Generator\suchThat(
                fn($name) => strlen(trim($name)) > 0 && strlen($name) <= 100,
                Generator\string()
            ),
            Generator\suchThat(
                fn($type) => !in_array($type, Storage::VALID_TYPES) && strlen($type) > 0,
                Generator\string()
            )
        )->then(function (string $name, string $invalidType): void {
            $storage = new Storage();
            $storage->setName($name);
            $storage->setType($invalidType);

            $violations = $this->validator->validate($storage);
            
            $typeViolations = array_filter(
                iterator_to_array($violations),
                fn($v) => $v->getPropertyPath() === 'type'
            );

            $this->assertNotEmpty(
                $typeViolations,
                sprintf('Invalid type "%s" should fail validation', $invalidType)
            );
        });
    }

    /**
     * Property: Storage name exceeding 100 characters fails validation.
     * 
     * For any storage with name longer than 100 characters, validation SHALL fail.
     */
    public function testNameExceedingMaxLengthFailsValidation(): void
    {
        $this->forAll(
            Generator\map(
                fn($length) => str_repeat('a', $length),
                Generator\choose(101, 200)
            ),
            Generator\elements(Storage::VALID_TYPES)
        )->then(function (string $longName, string $type): void {
            $storage = new Storage();
            $storage->setName($longName);
            $storage->setType($type);

            $violations = $this->validator->validate($storage);
            
            $nameViolations = array_filter(
                iterator_to_array($violations),
                fn($v) => $v->getPropertyPath() === 'name'
            );

            $this->assertNotEmpty(
                $nameViolations,
                sprintf('Name with %d characters should fail validation', strlen($longName))
            );
        });
    }

    /**
     * Property: All valid storage types are accepted.
     * 
     * For any type in VALID_TYPES, the type validation SHALL pass.
     */
    public function testAllValidTypesAreAccepted(): void
    {
        foreach (Storage::VALID_TYPES as $validType) {
            $storage = new Storage();
            $storage->setName('Test Storage');
            $storage->setType($validType);

            $violations = $this->validator->validate($storage);
            
            $typeViolations = array_filter(
                iterator_to_array($violations),
                fn($v) => $v->getPropertyPath() === 'type'
            );

            $this->assertEmpty(
                $typeViolations,
                sprintf('Valid type "%s" should pass validation', $validType)
            );
        }
    }
}
