# WU-007 — Gravity Forms frontend shortcode bounded content admission

Work Unit: `WU-007 — PGR-GRAVITYFORMS-FRONTEND-FIRST-BOUNDED-CONTENT-ADMISSION-01`

This change admits exactly one Gravity Forms 3.1.1.1 surface through Content Admission v2:

`gravityforms::frontend_runtime::shortcode:gravityform`

The registered Surface Registry rule is exactly `form_display.php`. The exact owner-supplied Gravity Forms package SHA-256 is
`542f56ae0747f3661d1474996527298027db3fb8ed3e6469a6391aaabf61069b`; the reviewed WU-003 Persian baseline SHA-256 is
`2c7960c895216da61ff7d6b8f6a248c2ac130fdf7927efc5c3d09c5e58bc79b1`.

## Derived authority

A PHP-tokenizer pass over the exact `form_display.php` found 48 gettext calls and 41 unique canonical identities. The same 41 identities are the vendor-POT identities carrying current `form_display.php` references; the two sets are equal with no source-only or POT-only member.

The admitted record is therefore:

- admitted identities: `41`;
- keyset SHA-256: `827255f0e88f86eac6f25217e801100fa8597a2ea28ab98236cb87cd9aa45ecb`;
- surface-path index SHA-256: `5ced80516bff2df13f4c8e4a3fd46446548d90fa6ea4beec9b31f1ada7b0d177`;
- translation-content SHA-256: `e6fd34b1a2670a4ac14309296dd718e46394ae5627bc32efc20a5e45a38d0b05`;
- evidence-index SHA-256: `0e9fb3c7a44f3bc2ae14d25f694a2301be59d7e75fc1d1f0740fb3be94283d22`;
- sparse record PO SHA-256: `2c90a0be260f15bb366065896dd67b723d4e223e01a9e488a9b872900e917971`;
- aggregate provider PO SHA-256: `2c90a0be260f15bb366065896dd67b723d4e223e01a9e488a9b872900e917971`.

Three admitted identities (`Medium`, `Submit`, `Strong`) also have references matching another registered Gravity Forms surface. Content Admission v2 keeps canonical identity semantics: identical translations deduplicate in a future multi-record union and conflicting translations fail closed.

## Runtime boundary

This is partial surface content, not a full-product translation admission. The aggregate contains 41 identities out of the independently admitted 4207-identity Gravity Forms source census.

The Gravity Forms runtime script map remains empty. No Gravity Forms translation JSON is generated or activated. The deterministic PHP artifacts are derived only from the 41-identity aggregate:

- `gravityforms-fa_IR.mo`: `756eacdc638326ab7aa0fe7aadfaa2b0ad920c0bf1a3705e76d309852df1df7c`;
- `gravityforms-fa_IR.l10n.php`: `26ca94067251b06b377dd23f9f16e74a423afc60e9cafaf20c5ea0f57851fbbe`.

Provider entries can win only for these admitted identities. A current identity such as `Form`, which is not in the `form_display.php` record, is absent from the provider catalog and therefore remains subject to the existing upstream/vendor/TranslationsPress/source-English fallback chain.

No vendor package/source is committed. No browser validation is claimed by this Work Unit.
