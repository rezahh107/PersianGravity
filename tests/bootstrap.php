<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

// راه‌اندازی Brain Monkey برای تست هوک‌های وردپرس
if (class_exists('Brain\Monkey')) {
    \Brain\Monkey\setUp();
} 