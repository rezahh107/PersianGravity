=== Persian Gravity Forms (Refactor) ===
Contributors: pgr
Requires at least: 6.0
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 2.0.0
Tags: gravity forms, persian, iran, national id

Adds an Iranian National ID field to Gravity Forms, with per-form Persian digit normalization and global defaults. The "separator" option is removed.

== Description ==
- Custom field: National ID (Iran), with checksum validation.
- Global defaults (Settings → Persian GF).
- Per-form toggle to normalize Persian/Arabic digits to English on submission.
- Live client-side validation and accessibility hints.
- Lightweight (no external frameworks).

== Installation ==
1. Upload the ZIP via Plugins → Add New → Upload Plugin.
2. Activate the plugin.
3. See Settings → Persian GF to set defaults.

== Changelog ==
= 2.0.0 =
* New: Settings page with global defaults and tools.
* New: Per-form Persian digit normalization.
* Change: Removed separator display option in National ID field.
* Fix: PHP 8+ compatible (no deprecated vendor packages).
