<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PersianGravityForms\Services\AddressService;

class AddressServiceTest extends TestCase
{
    public function testImplementsHookable()
    {
        $service = new AddressService();
        $this->assertTrue(method_exists($service, 'register_hooks'));
    }
} 