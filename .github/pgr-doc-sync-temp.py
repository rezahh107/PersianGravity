from pathlib import Path
import re


def replace_section(path, pattern, replacement):
    p = Path(path)
    text = p.read_text()
    new, count = re.subn(pattern, replacement, text, flags=re.S)
    if count != 1:
        raise SystemExit(f"{path}: expected one replacement, got {count}")
    p.write_text(new)


replace_section(
    "docs/LOCALIZATION.md",
    r"## Source admission and bounded content admission\n.*?(?=\n## )",
    """## Source admission and bounded content admission

Source admission does not itself populate provider translations. Current revision-2 production content authority is deliberately partial and record-scoped:

- Gravity Forms retains the exact merged PR #19 state: six independently validated records with a deterministic aggregate of 1759 unique identities from the separate 4207-message source census. Its script map remains empty and no Gravity Forms translation JSON is generated.
- Gravity Flow admits all seven accepted Surface Registry records: shortcode 15, Inbox 255, Status 43, Reports 20, Form Settings / Admin Builder 391, Settings & Integrations 44, and Entry Detail Sidebar 0. The 768 surface occurrences contain only identical overlaps and deterministically union to 732 unique identities from the separate 1098-message source census; 366 source identities remain non-admitted. Inbox and Status locked fingerprints are preserved. The zero-identity Entry Detail Sidebar record is explicit authority and is valid because admitted counts are non-negative; negative counts still fail closed.
- GravityView retains its separately bounded existing authority; this Gravity Flow change does not broaden GravityView content or script activation.

For every product, validated content authority is the exact union of its revision-2 records. Product/source identity alone is never authority. All product script maps remain empty, Gravity Flow activates zero native JS translation handles, and no Gravity Flow translation JSON is generated. Missing identities retain upstream/vendor/TranslationsPress fallback and then source English. These are partial surface authorities only, not full-product localization or licensed browser proof.
""",
)

replace_section(
    "docs/ARCHITECTURE_CONTENT_ADMISSION_V2.md",
    r"## Build and runtime boundary\n.*\Z",
    """## Build and runtime boundary

`tools/i18n/build.php` grants partial runtime catalog generation only when a validated product aggregate exists and the committed product PO matches the aggregate message count and SHA-256. A product with valid source admission but zero validated content records has no content authority; metadata-only products remain runtime dormant.

Current product aggregates are independent of their historical first-admission checkpoints. Gravity Forms remains exactly the merged PR #19 authority: six records / 1759 unique identities. Gravity Flow is the seven-record classified union: 732 unique identities from 768 surface occurrences, leaving 366 of 1098 source identities non-admitted.

Gravity Flow Inbox fingerprints remain:

- admitted count: `255`;
- keyset: `446d961de3a5572fb8ff7ebce8a24ce3ed7d7372efcce7022967d319a20a147a`;
- path fingerprint: `6e0459570b6b10dab7385cb712d00b7b80d1550c0d3f4f2d8c80f81d2a38aa09`;
- translation fingerprint: `6fda7b2d1c75a2441eb6f0fc2447cc39af76312283f08a57819f1cc4998ffa1f`.

Gravity Flow Status fingerprints remain:

- admitted count: `43`;
- keyset: `08f03c79014c427b13d0f8cb37fd2f3d873b7f60b63db9bb7bc06e1f0640ba6d`;
- path fingerprint: `0fc86212261393e443a9c07954fb7286204ae5f33b25ee13e8b7a8dad23603ed`;
- translation fingerprint: `32ff81b876f917c75741c137b2b3513d740159b386613a13fb12db6600244aa7`.

The current Gravity Flow aggregate is 732 identities. Its aggregate provider PO SHA-256 is `bff3f53338558fd9c62e19a6dc8161f39c61a8cdb42d807acc1fe6693868fa1a`, generated MO SHA-256 is `247224d3385df3fbbeda0bb2177d649858b808d10586b7ecac8da90a5424615c`, and generated `.l10n.php` SHA-256 is `580e658d61be7190fb53db1fe7c9f1cf15ba5210ef8aa7fe8c4bf5432aa262d0`.

All product script maps remain empty. No native JavaScript translation handle is activated by these content-admission records, and no Gravity Forms or Gravity Flow translation JSON is generated. Non-admitted identities retain upstream/vendor/TranslationsPress and then source-English fallback.
""",
)

replace_section(
    "docs/VALIDATION_CONTENT_ADMISSION_V2.md",
    r"Production-focused Gravity Flow tests are product-scoped.*?(?=\n\nHistorical WU-005 Gravity Flow values at the two-surface checkpoint:)",
    """Production-focused Gravity Flow tests are product-scoped against the global manifest and now assert the exact seven-record production subset while allowing unrelated product records. They preserve the locked Inbox and Status fingerprints, independently validate the accepted zero-identity Entry Detail Sidebar record, and keep historical two-surface behavior as local checkpoint evidence rather than current aggregate authority. Existing validator-level fail-closed coverage for duplicate tuples, unknown/unowned surfaces, out-of-surface evidence, negative counts and fingerprint drift remains active.

The current Gravity Flow coverage additionally verifies:

- exact seven-surface counts `15 / 255 / 43 / 20 / 391 / 44 / 0`;
- 768 surface occurrences, 732 unique admitted identities, 30 multi-surface identities, 36 duplicate overlap occurrences, and 366 non-admitted source identities;
- every multi-surface overlap has identical translation content, so union order cannot select a winner;
- Inbox and Status retain their exact locked count/keyset/path/content/provider/evidence fingerprints and their local 10-identity overlap / 288-identity two-record union;
- Entry Detail Sidebar is represented by an explicit empty evidence set and sparse PO, while negative admitted counts remain invalid;
- a known non-admitted source identity remains absent from production provider content;
- Gravity Forms remains exactly six records / 1759 unique identities;
- every Gravity Flow product script map remains empty and no Gravity Flow translation JSON exists; and
- generated Gravity Flow PO/MO/`.l10n.php`/metadata bytes are reproduced deterministically by `composer i18n:build` / `composer i18n:check`.
""",
)

replace_section(
    "languages/README.md",
    r"Content authority is separately bounded\..*?(?=\n\n`composer i18n:check`)",
    """Content authority is separately bounded. Gravity Forms retains the merged PR #19 production authority: six independently admitted records with a deterministic union of 1759 identities from the independent 4207-message source census. Gravity Flow now admits all seven accepted registry surfaces with counts 15 / 255 / 43 / 20 / 391 / 44 / 0; 768 surface occurrences deterministically union to 732 unique identities from the independent 1098-message source census, leaving 366 non-admitted identities. Its native JS handle count remains zero and no Gravity Flow translation JSON is generated. GravityView retains its separately bounded existing content authority and dependency/domain boundary. Full vendor packages/source/POT corpora and the full reviewed Persian baselines are not committed; non-expressive admission evidence lives under `tools/i18n/admission/` and is excluded from distribution.""",
)

assert "Gravity Flow is the seven-record classified union: 732" in Path("docs/ARCHITECTURE_CONTENT_ADMISSION_V2.md").read_text()
assert "exact seven-record production subset" in Path("docs/VALIDATION_CONTENT_ADMISSION_V2.md").read_text()
assert "all seven accepted Surface Registry records" in Path("docs/LOCALIZATION.md").read_text()
assert "Gravity Flow now admits all seven accepted registry surfaces" in Path("languages/README.md").read_text()
assert "The current Gravity Flow aggregate remains 288" not in Path("docs/ARCHITECTURE_CONTENT_ADMISSION_V2.md").read_text()
