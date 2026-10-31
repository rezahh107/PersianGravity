# Same-ID Scanner Profile recreation — independent WU008 runtime qualification

Reviewed production Head: `11e2c5974d37878ec6f2398aa99d5dc147629e11`
Isolated test-only qualification Head: `b0b865268530314591b81b9a5d56e5f71d826877`

Genuine logged-in WordPress/Chromium; original A nonce remains in the same session.
Independent Registry API deletion/recreation; original mapped GF field checked through GFAPI.
No source production code modified. All scenarios are disposable.

| Scenario | Original confirmation POST | Registry after POST |
|---|---|---|
| unchanged | deleted | absent |
| missing | delete_error | absent |
| changed_label | delete_error | Beta / replacement |
| same_label | delete_error | Alpha / replacement |
| enabled_only | delete_error | Alpha / original |
| output_label_only | delete_error | Alpha / original |
| fresh_b | delete_error | Alpha / replacement |
| identical_content | deleted | absent |

Fresh B confirmation outcome: deleted

**Observed disposition:** BOUNDED_SNAPSHOT_REPAIR_VERIFIED

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
