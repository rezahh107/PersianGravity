<?php
declare(strict_types=1);

namespace PersianGravityForms\Services;

use PersianGravityForms\Contracts\Hookable;

/**
 * افزودن و قالب‌بندی ارزهای ریال و تومان برای Gravity Forms
 */
class CurrencyService implements Hookable
{
    public function register_hooks(): void
    {
        add_filter('gform_currencies', [$this, 'add_iranian_currencies']);
        add_filter('gform_currency_format', [$this, 'format_currency'], 10, 3);
    }

    /**
     * افزودن ارزهای IRR و IRT به لیست ارزها
     */
    public function add_iranian_currencies(array $currencies): array
    {
        $currencies['IRR'] = [
            'name'               => 'ریال ایران',
            'symbol_left'        => '',
            'symbol_right'       => 'ریال',
            'symbol_padding'     => ' ',
            'decimal_separator'  => '.',
            'thousand_separator' => ',',
            'decimals'           => 0,
            'code'               => 'IRR',
        ];
        $currencies['IRT'] = [
            'name'               => 'تومان ایران',
            'symbol_left'        => '',
            'symbol_right'       => 'تومان',
            'symbol_padding'     => ' ',
            'decimal_separator'  => '.',
            'thousand_separator' => ',',
            'decimals'           => 0,
            'code'               => 'IRT',
        ];
        return $currencies;
    }

    /**
     * قالب‌بندی مبلغ برای نمایش RTL و جداکننده هزارگان
     */
    public function format_currency(string $formatted, float $amount, string $currency): string
    {
        if (in_array($currency, ['IRR', 'IRT'], true)) {
            $number = number_format($amount, 0, '.', ',');
            $unit = $currency === 'IRR' ? 'ریال' : 'تومان';
            // نمایش راست‌به‌چپ و جداکننده هزارگان
            return '<span dir="rtl">' . $number . ' ' . $unit . '</span>';
        }
        return $formatted;
    }
} 