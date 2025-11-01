<?php
declare(strict_types=1);

namespace PersianGravityForms\Contracts;

/**
 * Interface for services that register WordPress hooks.
 */
interface Hookable
{
    public function register_hooks(): void;
} 