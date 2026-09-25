#!/usr/bin/env php
<?php

declare(strict_types=1);

function fail_release(string $message): never {
    fwrite(STDERR, "release-tool: {$message}\n");
    exit(1);
}

function parse_options(array $args): array {
    $options = [];
    $positionals = [];
    foreach ($args as $arg) {
        if (str_starts_with($arg, '--') && str_contains($arg, '=')) {
            [$key, $value] = explode('=', substr($arg, 2), 2);
            $options[$key] = $value;
        } else {
            $positionals[] = $arg;
        }
    }
    return [$options, $positionals];
}

function stable_semver(string $version): bool {
    return preg_match('/^(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)$/', $version) === 1;
}

function read_required(string $path): string {
    $content = @file_get_contents($path);
    if ($content === false) {
        fail_release("cannot read required file: {$path}");
    }
    return $content;
}

function one_match(string $pattern, string $content, string $label): string {
    $count = preg_match_all($pattern, $content, $matches);
    if ($count !== 1) {
        fail_release("expected exactly one {$label}; found {$count}");
    }
    return trim((string) $matches[1][0]);
}

function replace_one(string $pattern, string $replacement, string $content, string $label): string {
    $count = 0;
    $updated = preg_replace($pattern, $replacement, $content, 1, $count);
    if ($updated === null || $count !== 1) {
        fail_release("expected exactly one replaceable {$label}; changed {$count}");
    }
    return $updated;
}

function replace_literal_one(string $old, string $new, string $content, string $label): string {
    $count = substr_count($content, $old);
    if ($count !== 1) {
        fail_release("expected exactly one replaceable {$label}; found {$count}");
    }
    return str_replace($old, $new, $content);
}

function gettext_project_version(string $content, string $label): string {
    $prefix = '"Project-Id-Version: Persian Gravity Forms ';
    $suffix = '\\n"';
    $versions = [];
    foreach (preg_split('/\R/', $content) ?: [] as $line) {
        if (str_starts_with($line, $prefix) && str_ends_with($line, $suffix)) {
            $versions[] = substr($line, strlen($prefix), -strlen($suffix));
        }
    }
    if (count($versions) !== 1) {
        fail_release("expected exactly one {$label}; found " . count($versions));
    }
    return $versions[0];
}

function release_files(string $root): array {
    return [
        'plugin' => $root . '/persian-gravityforms.php',
        'readme' => $root . '/readme.txt',
        'github' => $root . '/README.md',
        'agents' => $root . '/AGENTS.md',
        'languages_readme' => $root . '/languages/README.md',
        'architecture' => $root . '/docs/ARCHITECTURE.md',
        'localization' => $root . '/docs/LOCALIZATION.md',
        'pot' => $root . '/languages/persian-gravityforms.pot',
        'po' => $root . '/languages/persian-gravityforms-fa_IR.po',
    ];
}

function metadata(string $root): array {
    $f = release_files($root);
    $plugin = read_required($f['plugin']);
    $readme = read_required($f['readme']);
    $github = read_required($f['github']);
    $agents = read_required($f['agents']);
    $languagesReadme = read_required($f['languages_readme']);
    $architecture = read_required($f['architecture']);
    $localization = read_required($f['localization']);
    $pot = read_required($f['pot']);
    $po = read_required($f['po']);

    return [
        'plugin_header' => one_match('/^ \* Version:\s*([^\r\n]+)$/m', $plugin, 'plugin header Version'),
        'pgr_version' => one_match("/define\\(\\s*'PGR_VERSION',\\s*'([^']+)'\\s*\\);/", $plugin, 'PGR_VERSION'),
        'stable_tag' => one_match('/^Stable tag:\s*([^\r\n]+)$/m', $readme, 'readme Stable tag'),
        'github_active' => one_match('/^- Plugin version:\s*`([^`]+)`$/m', $github, 'README active plugin version'),
        'agents_active' => one_match('/^- Version:\s*`([^`]+)`$/m', $agents, 'AGENTS active version'),
        'languages_readme_active' => one_match('/own-plugin text domain in\s+([0-9]+\.[0-9]+\.[0-9]+)/', $languagesReadme, 'languages README active version'),
        'languages_readme_ships' => one_match('/^Version\s+([0-9]+\.[0-9]+\.[0-9]+)\s+ships:/m', $languagesReadme, 'languages README ships version'),
        'architecture_title' => one_match('/^# Persian Gravity Forms Architecture —\s+([0-9]+\.[0-9]+\.[0-9]+)$/m', $architecture, 'architecture active version'),
        'localization_active' => one_match('/active repository identity is \*\*([0-9]+\.[0-9]+\.[0-9]+)\*\*/', $localization, 'localization active repository identity'),
        'pot_project_version' => gettext_project_version($pot, 'POT Project-Id-Version'),
        'po_project_version' => gettext_project_version($po, 'PO Project-Id-Version'),
    ];
}

function canonical_version(string $root, ?string $expected = null): string {
    $values = metadata($root);
    $canonical = $values['plugin_header'];
    if (!stable_semver($canonical)) {
        fail_release("canonical source version is not stable SemVer: {$canonical}");
    }
    foreach ($values as $label => $value) {
        if ($value !== $canonical) {
            fail_release("version mismatch: {$label}={$value}, canonical={$canonical}");
        }
    }
    if ($expected !== null && $expected !== '' && $expected !== $canonical) {
        fail_release("expected version {$expected}, source declares {$canonical}");
    }
    return $canonical;
}

function next_version(string $current, string $bump): string {
    if (!stable_semver($current)) {
        fail_release("cannot bump non-stable SemVer: {$current}");
    }
    if (!in_array($bump, ['patch', 'minor', 'major'], true)) {
        fail_release("unsupported bump '{$bump}'; expected patch, minor, or major");
    }
    [$major, $minor, $patch] = array_map('intval', explode('.', $current));
    if ($bump === 'patch') {
        $patch++;
    } elseif ($bump === 'minor') {
        $minor++;
        $patch = 0;
    } else {
        $major++;
        $minor = 0;
        $patch = 0;
    }
    return "{$major}.{$minor}.{$patch}";
}

function unreleased_notes(string $readme): string {
    if (preg_match_all('/^= Unreleased =$/m', $readme) !== 1) {
        fail_release('readme.txt must contain exactly one Unreleased changelog section');
    }
    $pattern = '/^= Unreleased =\R(.*?)(?=^= [0-9]+\.[0-9]+\.[0-9]+ =\R)/ms';
    if (preg_match($pattern, $readme, $matches) !== 1) {
        fail_release('Unreleased must be followed by a released SemVer section');
    }
    $notes = trim((string) $matches[1]);
    foreach (preg_split('/\R/', $notes) ?: [] as $line) {
        $line = trim($line);
        if (str_starts_with($line, '* ') && strlen(trim(substr($line, 2))) >= 8) {
            return $notes;
        }
    }
    fail_release('Unreleased changelog is empty or does not contain meaningful bullet content');
}

function write_release_file(string $path, string $content): void {
    if (@file_put_contents($path, $content) === false) {
        fail_release("cannot write release file: {$path}");
    }
}

function prepare_release(string $root, string $bump): array {
    $previous = canonical_version($root);
    $candidate = next_version($previous, $bump);
    $f = release_files($root);
    $plugin = read_required($f['plugin']);
    $readme = read_required($f['readme']);
    $github = read_required($f['github']);
    $agents = read_required($f['agents']);
    $languagesReadme = read_required($f['languages_readme']);
    $architecture = read_required($f['architecture']);
    $localization = read_required($f['localization']);
    $pot = read_required($f['pot']);
    $po = read_required($f['po']);
    $notes = unreleased_notes($readme);

    if (preg_match('/^= ' . preg_quote($candidate, '/') . ' =$/m', $readme) === 1) {
        fail_release("candidate changelog section {$candidate} already exists");
    }

    $plugin = replace_one('/^ \* Version:\s*' . preg_quote($previous, '/') . '$/m', ' * Version: ' . $candidate, $plugin, 'plugin header Version');
    $plugin = replace_one("/define\\(\\s*'PGR_VERSION',\\s*'" . preg_quote($previous, '/') . "'\\s*\\);/", "define( 'PGR_VERSION', '{$candidate}' );", $plugin, 'PGR_VERSION');
    $readme = replace_one('/^Stable tag:\s*' . preg_quote($previous, '/') . '$/m', 'Stable tag: ' . $candidate, $readme, 'readme Stable tag');
    $github = replace_one('/^- Plugin version:\s*`' . preg_quote($previous, '/') . '`$/m', '- Plugin version: `' . $candidate . '`', $github, 'README active plugin version');
    $agents = replace_one('/^- Version:\s*`' . preg_quote($previous, '/') . '`$/m', '- Version: `' . $candidate . '`', $agents, 'AGENTS active version');
    $languagesReadme = replace_one('/own-plugin text domain in\s+' . preg_quote($previous, '/') . '/', 'own-plugin text domain in ' . $candidate, $languagesReadme, 'languages README active version');
    $languagesReadme = replace_one('/^Version\s+' . preg_quote($previous, '/') . '\s+ships:/m', 'Version ' . $candidate . ' ships:', $languagesReadme, 'languages README ships version');
    $architecture = replace_one('/^# Persian Gravity Forms Architecture —\s+' . preg_quote($previous, '/') . '$/m', '# Persian Gravity Forms Architecture — ' . $candidate, $architecture, 'architecture active version');
    $localization = replace_one('/active repository identity is \*\*' . preg_quote($previous, '/') . '\*\*/', 'active repository identity is **' . $candidate . '**', $localization, 'localization active repository identity');
    $pot = replace_literal_one('"Project-Id-Version: Persian Gravity Forms ' . $previous . '\\n"', '"Project-Id-Version: Persian Gravity Forms ' . $candidate . '\\n"', $pot, 'POT Project-Id-Version');
    $po = replace_literal_one('"Project-Id-Version: Persian Gravity Forms ' . $previous . '\\n"', '"Project-Id-Version: Persian Gravity Forms ' . $candidate . '\\n"', $po, 'PO Project-Id-Version');

    $readme = replace_one(
        '/^= Unreleased =\R.*?(?=^= [0-9]+\.[0-9]+\.[0-9]+ =\R)/ms',
        "= Unreleased =\n\n= {$candidate} =\n{$notes}\n\n",
        $readme,
        'Unreleased changelog section'
    );

    foreach ([
        'plugin' => $plugin,
        'readme' => $readme,
        'github' => $github,
        'agents' => $agents,
        'languages_readme' => $languagesReadme,
        'architecture' => $architecture,
        'localization' => $localization,
        'pot' => $pot,
        'po' => $po,
    ] as $key => $content) {
        write_release_file($f[$key], $content);
    }

    canonical_version($root, $candidate);
    return [
        'previous_version' => $previous,
        'candidate_version' => $candidate,
        'bump' => $bump,
        'notes' => $notes,
    ];
}

function require_option(array $options, string $key): string {
    if (!isset($options[$key]) || $options[$key] === '') {
        fail_release("missing --{$key}=...");
    }
    return (string) $options[$key];
}

function bool_option(array $options, string $key): bool {
    $value = require_option($options, $key);
    if (!in_array($value, ['0', '1'], true)) {
        fail_release("--{$key} must be 0 or 1");
    }
    return $value === '1';
}

function tag_state_option(array $options): string {
    $state = require_option($options, 'tag-state');
    if (!in_array($state, ['ABSENT', 'EXACT_SOURCE', 'CONFLICT'], true)) {
        fail_release('--tag-state must be ABSENT, EXACT_SOURCE, or CONFLICT');
    }
    return $state;
}

function validate_publish(array $options): void {
    $sourceVersion = require_option($options, 'source-version');
    $candidateVersion = require_option($options, 'candidate-version');
    $sourceSha = require_option($options, 'source-sha');
    $candidateSha = require_option($options, 'candidate-sha');
    $integratedSha = require_option($options, 'integrated-sha');
    $sourceTree = require_option($options, 'source-tree');
    $candidateTree = require_option($options, 'candidate-tree');
    $candidateBranch = require_option($options, 'candidate-branch');
    $tag = require_option($options, 'tag');
    $merged = bool_option($options, 'candidate-merged');
    $tagState = tag_state_option($options);
    $releaseExists = bool_option($options, 'release-exists');

    foreach ([$sourceVersion, $candidateVersion] as $version) {
        if (!stable_semver($version)) {
            fail_release("publish version is not stable SemVer: {$version}");
        }
    }
    foreach ([
        'source-sha' => $sourceSha,
        'candidate-sha' => $candidateSha,
        'integrated-sha' => $integratedSha,
        'source-tree' => $sourceTree,
        'candidate-tree' => $candidateTree,
    ] as $label => $value) {
        if (preg_match('/^[0-9a-f]{40}$/', $value) !== 1) {
            fail_release("{$label} is not a 40-character lowercase Git object id");
        }
    }
    if (!$merged) {
        fail_release('release candidate PR is not merged');
    }
    if ($sourceVersion !== $candidateVersion) {
        fail_release("candidate/source version mismatch: candidate={$candidateVersion} source={$sourceVersion}");
    }
    if ($sourceSha !== $integratedSha) {
        fail_release("main is not the exact integrated Release PR result: main={$sourceSha} integrated={$integratedSha}");
    }
    if ($candidateBranch !== 'release/v' . $sourceVersion) {
        fail_release("candidate branch mismatch: expected=release/v{$sourceVersion} actual={$candidateBranch}");
    }
    if ($tag !== 'v' . $sourceVersion) {
        fail_release("candidate/tag version mismatch: expected=v{$sourceVersion} actual={$tag}");
    }
    if ($sourceTree !== $candidateTree) {
        fail_release("release-source identity mismatch: main tree={$sourceTree} candidate tree={$candidateTree}");
    }
    if ($tagState === 'CONFLICT') {
        fail_release("tag {$tag} exists but does not identify the exact qualified source; refusing to overwrite or move it");
    }
    if ($releaseExists) {
        fail_release("GitHub Release {$tag} already exists; refusing to mutate it");
    }
    fwrite(STDOUT, "PUBLISH_CONTRACT=PASS version={$sourceVersion} source={$sourceSha} candidate={$candidateSha} tree={$sourceTree} tag_state={$tagState}\n");
}

[$options, $positionals] = parse_options(array_slice($argv, 1));
$command = array_shift($positionals) ?? '';
$root = rtrim((string) ($options['root'] ?? getenv('PGR_RELEASE_ROOT') ?: getcwd()), '/');

switch ($command) {
    case 'verify':
        fwrite(STDOUT, 'RELEASE_METADATA=PASS version=' . canonical_version($root, $options['expected'] ?? null) . "\n");
        break;
    case 'current':
        fwrite(STDOUT, canonical_version($root) . "\n");
        break;
    case 'next':
        fwrite(STDOUT, next_version(canonical_version($root), $positionals[0] ?? '') . "\n");
        break;
    case 'prepare':
        $result = prepare_release($root, $positionals[0] ?? '');
        if (($out = getenv('GITHUB_OUTPUT')) !== false && $out !== '') {
            file_put_contents($out, "previous_version={$result['previous_version']}\n", FILE_APPEND);
            file_put_contents($out, "candidate_version={$result['candidate_version']}\n", FILE_APPEND);
            file_put_contents($out, "bump={$result['bump']}\n", FILE_APPEND);
        }
        fwrite(STDOUT, "RELEASE_PREPARE=PASS previous={$result['previous_version']} candidate={$result['candidate_version']} bump={$result['bump']}\n");
        break;
    case 'validate-publish':
        validate_publish($options);
        break;
    default:
        fail_release('usage: release-tool.php verify|current|next <patch|minor|major>|prepare <patch|minor|major>|validate-publish [options]');
}
