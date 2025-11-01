<?php
declare(strict_types=1);

namespace PersianGravityForms\Core;

use PersianGravityForms\Contracts\Hookable;
use PersianGravityForms\Services\AddressService;
use PersianGravityForms\Services\NationalIdService;
use PersianGravityForms\Services\CurrencyService;

/**
 * Main plugin bootstrap and DI container.
 */
class Plugin
{
    /**
     * @var Hookable[]
     */
    private static array $services = [];

    /**
     * Initialize and register all services.
     */
    public static function init(): void
    {
        self::$services = [
            new AddressService(),
            new NationalIdService(),
            new CurrencyService(),
        ];
        foreach (self::$services as $service) {
            $service->register_hooks();
        }
    }
} 