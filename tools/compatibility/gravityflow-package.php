#!/usr/bin/env php
<?php

declare(strict_types=1);

final class GravityFlowPackageAuthority {
    private const SCHEMA_VERSION = 1;
    private const PRODUCT = 'gravityflow';
    private const AUTHORITY = 'OWNER_SUPPLIED_GOOGLE_DRIVE';
    private const PLUGIN_MAIN_FILE = 'gravityflow/gravityflow.php';

    private const REQUIRED_KEYS = [
        'schema_version',
        'product',
        'version',
        'authority',
        'google_drive_file_id',
        'expected_filename',
        'expected_bytes',
        'sha256',
        'plugin_main_file',
    ];

    public static function defaultPath(): string {
        return __DIR__ . '/gravityflow-package.json';
    }

    public static function load(?string $path = null): array {
        $path ??= self::defaultPath();
        $content = @file_get_contents($path);
        if ($content === false) {
            throw new RuntimeException("cannot read package authority: {$path}");
        }

        try {
            $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('package authority is not valid JSON: ' . $exception->getMessage(), 0, $exception);
        }

        if (!is_array($data) || array_is_list($data)) {
            throw new RuntimeException('package authority root must be a JSON object');
        }

        $keys = array_keys($data);
        $expectedKeys = self::REQUIRED_KEYS;
        sort($keys);
        sort($expectedKeys);
        if ($keys !== $expectedKeys) {
            $missing = array_values(array_diff(self::REQUIRED_KEYS, array_keys($data)));
            $extra = array_values(array_diff(array_keys($data), self::REQUIRED_KEYS));
            $parts = [];
            if ($missing !== []) {
                $parts[] = 'missing=' . implode(',', $missing);
            }
            if ($extra !== []) {
                $parts[] = 'unexpected=' . implode(',', $extra);
            }
            throw new RuntimeException('package authority fields invalid' . ($parts === [] ? '' : ': ' . implode(' ', $parts)));
        }

        if ($data['schema_version'] !== self::SCHEMA_VERSION) {
            throw new RuntimeException('unsupported package authority schema_version');
        }
        if ($data['product'] !== self::PRODUCT) {
            throw new RuntimeException('package authority product must be gravityflow');
        }
        if (!is_string($data['version']) || preg_match('/^(0|[1-9][0-9]*)(?:\.(0|[1-9][0-9]*)){2,3}$/', $data['version']) !== 1) {
            throw new RuntimeException('package authority version is invalid');
        }
        if ($data['authority'] !== self::AUTHORITY) {
            throw new RuntimeException('package authority must be OWNER_SUPPLIED_GOOGLE_DRIVE');
        }
        if (!is_string($data['google_drive_file_id']) || preg_match('/^[A-Za-z0-9_-]{10,128}$/', $data['google_drive_file_id']) !== 1) {
            throw new RuntimeException('package authority google_drive_file_id is invalid');
        }
        if (
            !is_string($data['expected_filename']) ||
            preg_match('/^[A-Za-z0-9._-]+\.zip$/', $data['expected_filename']) !== 1 ||
            basename($data['expected_filename']) !== $data['expected_filename']
        ) {
            throw new RuntimeException('package authority expected_filename is invalid');
        }
        if (!is_int($data['expected_bytes']) || $data['expected_bytes'] <= 0) {
            throw new RuntimeException('package authority expected_bytes must be a positive integer');
        }
        if (!is_string($data['sha256']) || preg_match('/^[a-f0-9]{64}$/', $data['sha256']) !== 1) {
            throw new RuntimeException('package authority sha256 is invalid');
        }
        if ($data['plugin_main_file'] !== self::PLUGIN_MAIN_FILE) {
            throw new RuntimeException('package authority plugin_main_file must be gravityflow/gravityflow.php');
        }

        return $data;
    }

    public static function envLines(string $prefix, array $package): string {
        if (preg_match('/^[A-Z][A-Z0-9_]*$/', $prefix) !== 1) {
            throw new RuntimeException('environment prefix is invalid');
        }

        $values = [
            'PRODUCT' => $package['product'],
            'VERSION' => $package['version'],
            'AUTHORITY' => $package['authority'],
            'FILE_ID' => $package['google_drive_file_id'],
            'FILE' => $package['expected_filename'],
            'SIZE' => (string) $package['expected_bytes'],
            'SHA256' => $package['sha256'],
            'MAIN_FILE' => $package['plugin_main_file'],
        ];

        $lines = [];
        foreach ($values as $suffix => $value) {
            if (!is_string($value) || str_contains($value, "\n") || str_contains($value, "\r")) {
                throw new RuntimeException("package authority {$suffix} cannot be exported to environment");
            }
            $lines[] = $prefix . '_' . $suffix . '=' . $value;
        }

        return implode("\n", $lines) . "\n";
    }
}

function gravityflowPackageAuthorityMain(array $argv): int {
    $command = $argv[1] ?? 'validate';

    try {
        $package = GravityFlowPackageAuthority::load();
        if ($command === 'validate') {
            fwrite(STDOUT, "Gravity Flow package authority OK\n");
            return 0;
        }
        if ($command === 'env') {
            $prefix = $argv[2] ?? '';
            fwrite(STDOUT, GravityFlowPackageAuthority::envLines($prefix, $package));
            return 0;
        }

        throw new RuntimeException("unsupported command: {$command}");
    } catch (Throwable $exception) {
        fwrite(STDERR, 'gravityflow-package: ' . $exception->getMessage() . "\n");
        return 1;
    }
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    exit(gravityflowPackageAuthorityMain($argv));
}
