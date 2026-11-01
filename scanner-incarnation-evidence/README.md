# Same-ID Scanner Profile recreation — independent WU008 runtime qualification

Reviewed production Head: `c7c5377e93f5f4f704acc797dc67ea8554b5dfd7`
Isolated test-only qualification Head: `7b0c3ff6f00d378b389168af3a1231618fdf6b1b`

Genuine logged-in WordPress/Chromium; original A nonce remains in the same session.
Independent Registry API deletion/recreation; original mapped GF field checked through GFAPI.
Source candidate tested only on disposable WordPress; no live data migration.

| Scenario | Original confirmation POST | Registry after POST |
|---|---|---|
| unchanged | deleted | absent |
| missing | delete_error | absent |
| changed_label | delete_error | Beta / replacement |
| same_label | delete_error | Alpha / replacement |
| enabled_only | delete_error | Alpha / original |
| output_label_only | delete_error | Alpha / original |
| fresh_b | delete_error | Alpha / replacement |
| identical_content | delete_error | Alpha / original |

Fresh B confirmation outcome: deleted

Deterministic pre-CAS interleaving: delete_error; old A confirmation deleted replacement B=false; mutation elapsed ms=166.507; true parallel workers=NOT_PROVEN

**Observed disposition:** IDENTICAL_CONTENT_AND_PRE_CAS_INTERLEAVING_REJECTED

Raw nonce and authenticated cookies are never saved.

## unchanged

[unchanged-original-confirmation.jpg](unchanged-original-confirmation.jpg)

[unchanged-after-old-POST.jpg](unchanged-after-old-POST.jpg)

## missing

[missing-original-confirmation.jpg](missing-original-confirmation.jpg)

[missing-after-old-POST.jpg](missing-after-old-POST.jpg)

## changed_label

[changed_label-original-confirmation.jpg](changed_label-original-confirmation.jpg)

[changed_label-recreated-B-before-old-POST.jpg](changed_label-recreated-B-before-old-POST.jpg)

[changed_label-after-old-POST.jpg](changed_label-after-old-POST.jpg)

## same_label

[same_label-original-confirmation.jpg](same_label-original-confirmation.jpg)

[same_label-recreated-B-before-old-POST.jpg](same_label-recreated-B-before-old-POST.jpg)

[same_label-after-old-POST.jpg](same_label-after-old-POST.jpg)

## enabled_only

[enabled_only-original-confirmation.jpg](enabled_only-original-confirmation.jpg)

[enabled_only-recreated-B-before-old-POST.jpg](enabled_only-recreated-B-before-old-POST.jpg)

[enabled_only-after-old-POST.jpg](enabled_only-after-old-POST.jpg)

## output_label_only

[output_label_only-original-confirmation.jpg](output_label_only-original-confirmation.jpg)

[output_label_only-recreated-B-before-old-POST.jpg](output_label_only-recreated-B-before-old-POST.jpg)

[output_label_only-after-old-POST.jpg](output_label_only-after-old-POST.jpg)

## fresh_b

[fresh_b-original-confirmation.jpg](fresh_b-original-confirmation.jpg)

[fresh_b-recreated-B-before-old-POST.jpg](fresh_b-recreated-B-before-old-POST.jpg)

[fresh_b-after-new-POST.jpg](fresh_b-after-new-POST.jpg)

## identical_content

[identical_content-original-confirmation.jpg](identical_content-original-confirmation.jpg)

[identical_content-recreated-B-before-old-POST.jpg](identical_content-recreated-B-before-old-POST.jpg)

[identical_content-after-old-POST.jpg](identical_content-after-old-POST.jpg)

## interleaved_replace (single-request test-only hook; NOT a two-worker concurrency test)

[interleaved_replace-original-confirmation.jpg](interleaved_replace-original-confirmation.jpg)

[interleaved_replace-after-old-POST.jpg](interleaved_replace-after-old-POST.jpg)

Exact hook marker: [interleaving-marker.json](interleaving-marker.json)
