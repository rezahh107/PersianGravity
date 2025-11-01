<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PersianGravityForms\Services\NationalIdService;

class NationalIdServiceTest extends TestCase
{
    public function testImplementsHookable()
    {
        $service = new NationalIdService();
        $this->assertTrue(method_exists($service, 'register_hooks'));
    }

    public function testValidNationalId()
    {
        $service = new NationalIdService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('is_valid_national_id');
        $method->setAccessible(true);
        $this->assertTrue($method->invoke($service, '0010350829'));
        $this->assertFalse($method->invoke($service, '1234567890'));
    }
} 