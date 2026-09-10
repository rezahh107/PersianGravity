# WU-007 — Gravity Forms frontend shortcode bounded content admission

Work Unit: `WU-007 — PGR-GRAVITYFORMS-FRONTEND-FIRST-BOUNDED-CONTENT-ADMISSION-01`

This change admits exactly one Gravity Forms 3.1.1.1 surface through Content Admission v2:

`gravityforms::frontend_runtime::shortcode:gravityform`

The registered Surface Registry rule is exactly `form_display.php`. The exact owner-supplied Gravity Forms package SHA-256 is
`542f56ae0747f3661d1474996527298027db3fb8ed3e6469a6391aaabf61069b`; the reviewed WU-003 Persian baseline SHA-256 is
`2c7960c895216da61ff7d6b8f6a248c2ac130fdf7927efc5c3d09c5e58bc79b1`.

The WU-003 full baseline remains historical reviewed-input provenance. PR #15 applies exactly seven locked semantic corrections to the admitted sparse source; the final admitted translations are therefore not claimed to be an unmodified exact subset of the WU-003 baseline.

## Derived authority

A PHP-tokenizer pass over the exact `form_display.php` found 48 gettext calls and 41 unique canonical identities. The same 41 identities are the vendor-POT identities carrying current `form_display.php` references; the two sets are equal with no source-only or POT-only member.

The final repaired admitted record is:

- admitted identities: `41`;
- keyset SHA-256: `827255f0e88f86eac6f25217e801100fa8597a2ea28ab98236cb87cd9aa45ecb`;
- surface-path index SHA-256: `5ced80516bff2df13f4c8e4a3fd46446548d90fa6ea4beec9b31f1ada7b0d177`;
- translation-content SHA-256: `1fc2c6ceb203c48d757d53a0892b11b417ad6c956350e3414b9588cf80afb3b6`;
- evidence-index SHA-256: `0e9fb3c7a44f3bc2ae14d25f694a2301be59d7e75fc1d1f0740fb3be94283d22`;
- sparse record PO SHA-256: `ba4337ab4a7df342c59aec346464e67065ea48a7fa4045aa161b7f0162d9cf5a`;
- aggregate provider PO SHA-256: `ba4337ab4a7df342c59aec346464e67065ea48a7fa4045aa161b7f0162d9cf5a`.

The seven PR #15 corrections are limited to the locked messages for submission availability/retry, uniqueness errors, `Reason: %s`, Save and Continue expiry, and Spam Filter. No msgid, context, plural identity, surface membership, package/POT evidence, or source-path rule changes.

Three admitted identities (`Medium`, `Submit`, `Strong`) also have references matching another registered Gravity Forms surface. Content Admission v2 keeps canonical identity semantics: identical translations deduplicate in a future multi-record union and conflicting translations fail closed.

## Runtime boundary

This is partial surface content, not a full-product translation admission. The aggregate contains 41 identities out of the independently admitted 4207-identity Gravity Forms source census.

The Gravity Forms runtime script map remains empty. No Gravity Forms translation JSON is generated or activated. The deterministic PHP artifacts derived from the final repaired 41-identity aggregate are:

- `gravityforms-fa_IR.mo`: `de4ceac4c44892cdaa75a918a3f9e3b2c4a8b864700f1662f59e5073e118fff2`;
- `gravityforms-fa_IR.l10n.php`: `a8d1bee39d503e1a99c296f02f64708e57c7ef731db059778b575e20e0b11ca3`.

Provider entries can win only for these admitted identities. A current identity such as `Form`, which is not in the `form_display.php` record, is absent from the provider catalog and therefore remains subject to the existing upstream/vendor/TranslationsPress/source-English fallback chain.

No vendor package/source is committed. Real licensed/browser validation remains `NOT_PROVEN` / `NOT_EXECUTED` unless separately recorded against an actual environment.
