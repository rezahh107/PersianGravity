# Same-ID Scanner Profile recreation — independent WU008 runtime qualification

Reviewed production Head: `11e2c5974d37878ec6f2398aa99d5dc147629e11`
Isolated test-only qualification Head: `af728482a992d9615f0142609b1f18fcbfc1fb05`

Genuine logged-in WordPress/Chromium; original A nonce remains in the same session.
Independent Registry API deletion/recreation; original mapped GF field checked through GFAPI.
No source production code modified. All scenarios are disposable.

| Scenario | Original confirmation POST | Registry after POST |
|---|---|---|
| unchanged | deleted | absent |
| missing | delete_error | absent |
| changed_label | delete_error | Beta / replacement |
| same_label | deleted | absent |

**Observed disposition:** CONFIRMED_FINDING

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
