#!/usr/bin/env bash
set -euo pipefail

BASE=407f14fb805ab9f12fad94971def9900aee76b3e
PAYLOAD_B64=/tmp/pgr_payload.b64
PAYLOAD_TGZ=/tmp/pgr_payload.tgz

cat \
  .pgr-transfer/part01.txt \
  .pgr-transfer/part02.txt \
  .pgr-transfer/part03.txt \
  .pgr-transfer/chunk{00..24}.txt \
  .pgr-transfer/part5.txt > "$PAYLOAD_B64"

test "$(wc -c < "$PAYLOAD_B64")" -eq 80480
echo 'e4c108456590444fc08f82c27ada832f088f049a86ee1409ddaf75c9c176c3ca  /tmp/pgr_payload.b64' | sha256sum -c -
base64 -d "$PAYLOAD_B64" > "$PAYLOAD_TGZ"
echo '9cd173158e7022c3086aee04e2726cbd7ddc4c3ba1b69ffaf73cbf8e20fbec1f  /tmp/pgr_payload.tgz' | sha256sum -c -
tar -xzf "$PAYLOAD_TGZ" -C .

echo 'bff3f53338558fd9c62e19a6dc8161f39c61a8cdb42d807acc1fe6693868fa1a  languages/providers/gravityflow/source/fa_IR.po' | sha256sum -c -
echo '02e3f5631b5a9ffbe308531c6da7d2e2668af4378e84c7824f46b10e1f609c3d  languages/providers/gravityflow/source/records/admin-builder-fa_IR.po' | sha256sum -c -
echo 'c66a42a95c7ec08e9f2481c36f6710a7e53011f5d53b607727752f300a2b9d54  tools/i18n/admission/content.json' | sha256sum -c -
echo '1c3e610a8ee5c4e507b85ec89aff71158ac36921fd2515ee5c6339a470af7cdc  languages/providers/gravityflow/source/provenance.json' | sha256sum -c -

python3 <<'PY'
from pathlib import Path
import re


def replace_once(path, pattern, replacement, flags=re.S):
    p = Path(path)
    text = p.read_text()
    new, count = re.subn(pattern, replacement, text, count=1, flags=flags)
    if count != 1:
        raise SystemExit(f'{path}: expected exactly one replacement for {pattern!r}, got {count}')
    p.write_text(new)

p = Path('tools/i18n/content-admission.php')
text = p.read_text()
old = "! is_int( $record['admitted_message_count'] ?? null ) || 0 >= $record['admitted_message_count'] ||"
new = "! is_int( $record['admitted_message_count'] ?? null ) || 0 > $record['admitted_message_count'] ||"
if text.count(old) != 1:
    raise SystemExit('validator: expected positive-only admitted_message_count guard exactly once')
p.write_text(text.replace(old, new, 1))

replace_once(
    'README.md',
    r'Gravity Forms اکنون دقیقاً یک content admission محدود.*?این وضعیت ترجمهٔ کامل Gravity Forms نیست\.',
    'Gravity Forms پس از PR #19 دقیقاً شش content-admission مستقل و پذیرفته‌شده با union قطعی `1759` identity از source census مستقل `4207` دارد؛ script map خالی است و هیچ translation JSON برای Gravity Forms تولید نمی‌شود. این وضعیت همچنان `CONTENT_ADMITTED_PARTIAL` است و ادعای ترجمهٔ کامل محصول نیست.'
)
replace_once(
    'README.md',
    r'برای Gravity Flow، runtime content authority دقیقاً دو surface مرورشده دارد:.*?source census مستقل `1098` است\.',
    'برای Gravity Flow، runtime content authority اکنون هر هفت surface پذیرفته‌شدهٔ registry را به‌صورت مستقل پوشش می‌دهد: shortcode (`15`)، Inbox (`255`)، Status (`43`)، Reports (`20`)، Form Settings / Admin Builder (`391`)، Settings & Integrations (`44`) و Entry Detail Sidebar (`0`). مجموع occurrenceها `768` است و overlapهای یکسان به union قطعی `732` identity از source census مستقل `1098` می‌رسند؛ `366` identity همچنان non-admitted هستند و fallback موجود را حفظ می‌کنند.'
)

replace_once(
    'readme.txt',
    r'Gravity Forms has exactly one bounded production content-admission record.*?seven explicit PR #15 semantic corrections define the final admitted Persian content\.',
    'Gravity Forms production content authority from PR #19 remains unchanged: six independently admitted records with a deterministic union of 1759 unique identities from the separate 4207-message source census. Its script map remains empty and no Gravity Forms translation JSON is generated; this remains partial content authority, not full-product localization.'
)
replace_once(
    'readme.txt',
    r'Gravity Flow retains exactly two bounded production content-admission records:.*?source census\.',
    'Gravity Flow now has all seven accepted registry surfaces independently admitted: shortcode (15), Inbox (255), Status (43), Reports (20), Form Settings / Admin Builder (391), Settings & Integrations (44), and Entry Detail Sidebar (0). The 768 surface occurrences deterministically deduplicate to 732 unique admitted identities from the separate 1098-message source census; 366 identities remain non-admitted and keep the existing fallback chain.'
)
p = Path('readme.txt')
text = p.read_text()
marker = '= Unreleased =\n'
bullet = '* Completed Gravity Flow 3.1.0 Content Admission v2 across all seven accepted surfaces: 732 unique admitted identities, 366 non-admitted source identities, an auditable zero-identity Entry Detail Sidebar record, zero JS handles/translation JSON, and unchanged fallback semantics.\n'
if bullet not in text:
    if text.count(marker) != 1:
        raise SystemExit('readme.txt: Unreleased marker not unique')
    p.write_text(text.replace(marker, marker + bullet, 1))

replace_once(
    'docs/ARCHITECTURE.md',
    r'Gravity Forms production content authority is revision 2 with exactly one independently.*?no Gravity Forms translation JSON is generated\.',
    'Gravity Forms production content authority is revision 2 with six independently validated records and a deterministic aggregate of 1759 unique identities from the separately admitted 4207-message source census. The existing PR #19 identity/path/content authority is preserved unchanged. Gravity Forms script handles remain zero and no Gravity Forms translation JSON is generated.'
)
replace_once(
    'docs/ARCHITECTURE.md',
    r'Gravity Flow production content authority remains revision 2 with exactly two independently.*?no Gravity Flow translation JSON is generated\.',
    'Gravity Flow production content authority is revision 2 with all seven accepted Surface Registry records independently validated: shortcode 15, Inbox 255, Status 43, Reports 20, Form Settings / Admin Builder 391, Settings & Integrations 44, and Entry Detail Sidebar 0. The records contain 768 surface occurrences with identical overlaps deduplicated to a deterministic 732-identity aggregate from the separate 1098-message source census; 366 source identities remain non-admitted. Inbox and Status locked fingerprints remain unchanged. The zero-identity Entry Detail Sidebar record is still explicit and auditable. Gravity Flow script handles remain zero and no Gravity Flow translation JSON is generated.'
)

replace_once(
    'docs/LOCALIZATION.md',
    r'Gravity Forms now has revision-2 production content authority.*?same identity/keyset/path scope and is not represented as an untouched byte-for-byte subset of that baseline\.',
    'Gravity Forms has revision-2 production content authority for six independently accepted records with a deterministic 1759-identity aggregate from the separate 4207-message source census. The exact PR #19 records and generated authority remain unchanged by this Gravity Flow work.'
)
replace_once(
    'docs/LOCALIZATION.md',
    r'Gravity Flow retains revision-2 authority for exactly Inbox and Status:.*?1098-message source census\.',
    'Gravity Flow has revision-2 authority for all seven accepted registry surfaces: shortcode 15, Inbox 255, Status 43, Reports 20, Form Settings / Admin Builder 391, Settings & Integrations 44, and Entry Detail Sidebar 0. The 768 surface occurrences contain only identical overlaps and deterministically union to 732 admitted identities from the separate 1098-message source census; 366 source identities remain non-admitted. Native JS handles remain zero and no Gravity Flow translation JSON is generated.'
)

p = Path('docs/VALIDATION.md')
text = p.read_text()
current = '''## Current content-admission snapshot — 2026-09-14\n\n- Gravity Forms: revision 2, 6 admitted records, 1759 unique admitted identities from a 4207-identity source census; unchanged from merged PR #19.\n- Gravity Flow: revision 2, 7 admitted records with surface counts 15 / 255 / 43 / 20 / 391 / 44 / 0; 768 occurrences deduplicate to 732 unique admitted identities from a 1098-identity source census; 366 remain non-admitted.\n- Gravity Flow native JS handles: 0; generated translation JSON: 0.\n- Real licensed browser/UI behavior is not inferred from this repository state and remains separately evidenced.\n\n'''
if current not in text:
    heading = '# Persian Gravity Forms Validation — 4.2.0\n\n'
    if text.count(heading) != 1:
        raise SystemExit('VALIDATION.md: heading not unique')
    p.write_text(text.replace(heading, heading + current, 1))

p = Path('docs/ARCHITECTURE_CONTENT_ADMISSION_V2.md')
text = p.read_text()
text, n1 = re.subn(
    r'Current production extensions:\n- `WU-005.*?`WU-007[^\n]*\n',
    'Current production state (2026-09-14): Gravity Forms has six accepted records / 1759 unique identities; Gravity Flow has seven accepted records / 732 unique identities. Historical originating Work Units below remain provenance for earlier increments.\n',
    text,
    count=1,
    flags=re.S,
)
if n1 != 1:
    raise SystemExit('ARCHITECTURE_CONTENT_ADMISSION_V2.md: production extensions block not found')
text, n2 = re.subn(
    r'Production currently contains exactly three bounded records:.*?No other Gravity Forms or Gravity Flow surface is authorized by those Work Units\.',
    'Production now contains independently validated records across products. Gravity Forms contributes six accepted records and Gravity Flow contributes all seven accepted Surface Registry records; GravityView retains its separately bounded existing record. Record authority is still granted only by explicit revision-2 content records, never by product identity or source admission alone.',
    text,
    count=1,
    flags=re.S,
)
if n2 != 1:
    raise SystemExit('ARCHITECTURE_CONTENT_ADMISSION_V2.md: old record-count block not found')
text, n3 = re.subn(
    r'Current aggregates:\n\n- Gravity Forms frontend shortcode:.*?independent 1098-message source census\.',
    'Current aggregates:\n\n- Gravity Forms: six independently admitted records deterministically union to 1759 identities from the independent 4207-message source census.\n- Gravity Flow: seven independently admitted records contain 768 surface occurrences and deterministically union to 732 identities from the independent 1098-message source census; 366 source identities remain non-admitted.',
    text,
    count=1,
    flags=re.S,
)
if n3 != 1:
    raise SystemExit('ARCHITECTURE_CONTENT_ADMISSION_V2.md: old aggregate block not found')
p.write_text(text)

p = Path('docs/VALIDATION_CONTENT_ADMISSION_V2.md')
text = p.read_text()
current2 = '''## Current production snapshot — 2026-09-14\n\nGravity Forms remains at the merged PR #19 authority: 6 records and 1759 unique admitted identities. Gravity Flow now validates all 7 accepted surfaces with counts 15 / 255 / 43 / 20 / 391 / 44 / 0, 768 surface occurrences, 30 multi-surface identities, 36 duplicate overlap occurrences, and a deterministic 732-identity union; 366 of the 1098 source identities remain non-admitted. Inbox and Status fingerprints are preserved exactly. The accepted Entry Detail Sidebar surface is represented by an explicit zero-identity record, and negative admitted counts remain invalid.\n\nThe older WU-005 and WU-007 sections below are retained as historical incremental evidence rather than current aggregate authority.\n\n'''
heading = '# Content Admission v2 validation record\n\n'
if current2 not in text:
    if text.count(heading) != 1:
        raise SystemExit('VALIDATION_CONTENT_ADMISSION_V2.md: heading not unique')
    text = text.replace(heading, heading + current2, 1)
text = text.replace('Current Gravity Flow values remain unchanged:', 'Historical WU-005 Gravity Flow values at the two-surface checkpoint:', 1)
text = text.replace('Gravity Forms production authority is exactly one record:', 'Historical WU-007 / PR #15 scope admitted exactly one Gravity Forms frontend record; current merged PR #19 authority contains six records / 1759 unique identities. The historical record was:', 1)
p.write_text(text)
PY

composer validate --strict
composer install --no-interaction --no-progress --prefer-dist
composer i18n:build

echo '247224d3385df3fbbeda0bb2177d649858b808d10586b7ecac8da90a5424615c  languages/providers/gravityflow/gravityflow-fa_IR.mo' | sha256sum -c -
echo '580e658d61be7190fb53db1fe7c9f1cf15ba5210ef8aa7fe8c4bf5432aa262d0  languages/providers/gravityflow/gravityflow-fa_IR.l10n.php' | sha256sum -c -
echo '263590c8abd09c9c18d09755e160b28c0c26c58e0b29c2f02814a9359a8e2b74  languages/providers/gravityflow/metadata.json' | sha256sum -c -

composer i18n:check
composer test
composer cs
composer compat
node --test tests/js/structured-scanner.test.js

git diff --exit-code "$BASE" -- languages/providers/gravityforms
git diff --exit-code "$BASE" -- tools/i18n/admission/surfaces.json includes/localization/products.php
git diff --exit-code "$BASE" -- .github/workflows/wu008-real-integration.yml tests/real-integration

test -z "$(find languages/providers/gravityflow -maxdepth 1 -type f -name 'gravityflow-fa_IR-*.json' -print -quit)"

rm -rf .pgr-transfer
rm -f .github/workflows/pgr-transfer-finalize.yml

git add -A
! git diff --cached --name-only | grep -E '(^|/)\.pgr-transfer/|pgr-transfer-finalize\.yml'

git config user.name 'github-actions[bot]'
git config user.email '41898282+github-actions[bot]@users.noreply.github.com'
git commit -m 'feat: complete Gravity Flow classified content admission v2'
git push origin "HEAD:${GITHUB_REF_NAME}"
