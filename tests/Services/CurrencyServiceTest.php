<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PersianGravityForms\Services\CurrencyService;

class CurrencyServiceTest extends TestCase
{
    public function testAddIranianCurrencies()
    {
        $service = new CurrencyService();
        $currencies = $service->add_iranian_currencies([]);
        $this->assertArrayHasKey('IRR', $currencies);
        $this->assertArrayHasKey('IRT', $currencies);
        $this->assertEquals('ریال ایران', $currencies['IRR']['name']);
        $this->assertEquals('تومان ایران', $currencies['IRT']['name']);
    }

    public function testFormatCurrencyIRR()
    {
        $service = new CurrencyService();
        $formatted = $service->format_currency('', 1234567, 'IRR');
        $this->assertStringContainsString('1,234,567', $formatted);
        $this->assertStringContainsString('ریال', $formatted);
    }

    public function testFormatCurrencyIRT()
    {
        $service = new CurrencyService();
        $formatted = $service->format_currency('', 89000, 'IRT');
        $this->assertStringContainsString('89,000', $formatted);
        $this->assertStringContainsString('تومان', $formatted);
    }
} 