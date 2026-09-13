#!/usr/bin/env bash
set -euo pipefail

python .github/wu004-semantic-repair.py apply | tee /tmp/semantic-review.json
php -l tools/i18n/content-admission.php
php -l tests/ContentAdmissionTokenSemanticsTest.php
php -l tests/GravityFormsSemanticAdmissionTest.php

composer validate --strict
composer install --no-interaction --no-progress --prefer-dist

php .github/wu004-fingerprint.php > /tmp/pr19-fingerprints.json
cat /tmp/pr19-fingerprints.json
python .github/wu004-semantic-repair.py sync /tmp/pr19-fingerprints.json
new_aggregate_translation="$(php -r '$j=json_decode(file_get_contents("/tmp/pr19-fingerprints.json"), true); echo $j["aggregate"]["admitted_translation_content_sha256"];')"
python - "$new_aggregate_translation" <<'PY'
import sys
from pathlib import Path
path = Path('tests/GravityFormsFrontendContentAdmissionTest.php')
text = path.read_text(encoding='utf-8')
old = '3054209761a3513fd34f3f038ccc9e07b2167661820c4a7c31cde300c1d7ea44'
new = sys.argv[1]
if text.count(old) != 1:
    raise SystemExit('stale aggregate fingerprint expectation not found exactly once')
path.write_text(text.replace(old, new), encoding='utf-8')
PY
rm .github/wu004-fingerprint.php

composer i18n:build
composer i18n:check

php -r '
require "vendor/autoload.php";
require "tools/i18n/admission.php";
require "tools/i18n/content-admission.php";
$products = require "includes/localization/products.php";
$content = pgr_validate_content_admission(__DIR__, pgr_validate_admission(__DIR__), $products);
$g = $content["gravityforms"];
$occ = 0; $freq = array();
foreach ($g["admissions"] as $record) {
    $p = pgr_content_load_sparse_po(__DIR__ . "/" . $record["provider_source_path"], "gravityforms", "fa_IR");
    $occ += count($p["ids"]);
    foreach ($p["ids"] as $id) { $freq[$id] = ($freq[$id] ?? 0) + 1; }
}
$multi = count(array_filter($freq, static fn($n) => $n > 1));
if (count($g["admissions"]) !== 6 || $occ !== 1826 || count($freq) !== 1759 || $multi !== 55 || $occ - count($freq) !== 67 || 4207 - count($freq) !== 2448 || $g["aggregate"]["native_js_handles_activated"] !== 0 || $g["aggregate"]["js_translation_json_generated"] !== 0) { throw new RuntimeException("GF admission invariants drift"); }
echo "GF admission invariants: PASS\n";
'

sha256sum \
  languages/providers/gravityforms/source/fa_IR.po \
  languages/providers/gravityforms/gravityforms-fa_IR.mo \
  languages/providers/gravityforms/gravityforms-fa_IR.l10n.php \
  languages/providers/gravityforms/metadata.json | tee /tmp/pr19-derived-hashes.txt

vendor/bin/phpunit \
  tests/ContentAdmissionTokenSemanticsTest.php \
  tests/GravityFormsSemanticAdmissionTest.php \
  tests/GravityFormsFrontendContentAdmissionTest.php \
  tests/GravityFormsClassifiedContentAdmissionTest.php
composer test
composer cs
composer compat
node --test tests/js/structured-scanner.test.js
composer i18n:check
git diff --check

test ! -e .wu004-transfer/part-00
test ! -e .wu004-transfer/part-01
test "$(find languages/providers/gravityforms/source/records -maxdepth 1 -name '*-fa_IR.po' -type f | wc -l)" -eq 6
test -z "$(find languages/providers/gravityforms -maxdepth 1 -name 'gravityforms-fa_IR-*.json' -print -quit)"
! git diff --name-only origin/main...HEAD | grep -Ei 'wu008|real-integration'
grep -A1 'msgid "Colors"' languages/providers/gravityforms/source/records/frontend-block-fa_IR.po | grep -F 'msgstr "رنگ‌ها"'
grep -A1 'msgid "Accent"' languages/providers/gravityforms/source/records/frontend-block-fa_IR.po | grep -F 'msgstr "رنگ تاکیدی"'

rm -rf .wordpress-core
git init -q .wordpress-core
git -C .wordpress-core remote add origin https://github.com/WordPress/WordPress.git
git -C .wordpress-core fetch -q --depth=1 origin b998fef9238af183f9523b3df71618e6e57498b6
git -C .wordpress-core checkout -q --detach FETCH_HEAD
PGR_WP_CORE="$PWD/.wordpress-core" composer i18n:test
rm -rf .wordpress-core

composer i18n:check
git diff --check

rm .github/wu004-semantic-repair.py .github/wu004-run-semantic-repair.sh .github/workflows/pr19-semantic-repair.yml
git add -A
git diff --cached --check

git config user.name 'github-actions[bot]'
git config user.email '41898282+github-actions[bot]@users.noreply.github.com'
git commit -m 'fix(i18n): close Gravity Forms semantic admission QA'
git push origin HEAD:feat/wu004-gravityforms-classified-admission
printf 'RESULTING_HEAD=%s\n' "$(git rev-parse HEAD)"
