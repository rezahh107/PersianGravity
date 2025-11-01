<?php
declare(strict_types=1);

namespace PersianGravityForms\Helpers;

/**
 * توابع کمکی برای پاک‌سازی داده‌ها
 */
class Sanitize
{
    public static function text(string $value): string
    {
        return sanitize_text_field($value);
    }
} 