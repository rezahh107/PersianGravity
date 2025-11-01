<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class PluginTest extends TestCase
{
    public function testPluginInitLoadsWithoutError()
    {
        $this->expectNotToPerformAssertions();
        \PersianGravityForms\Core\Plugin::init();
    }
} 