# Persian Gravity Forms

افزونه‌ای متمرکز برای افزودن قابلیت‌های عمومی فارسی/ایرانی به Gravity Forms، بدون درگیر شدن با فونت، workflow، منطق پروژه‌های خاص یا ترجمه افزونه‌های دیگر.

## وضعیت فعلی

- Plugin version: `4.1.0`
- WordPress minimum: `6.7`
- PHP minimum: `8.2`
- Gravity Forms minimum: `3.0`
- Canonical runtime: `PGR_*`
- Text domain: `persian-gravityforms`
- Entrypoint: `persian-gravityforms.php`

نسخه 4 معماری‌های موازی و legacy قبلی را حذف کرده و فقط یک runtime اصلی نگه می‌دارد. شناسهٔ فعلی سورس `4.1.0` است. این تغییر زیرساخت ترجمه، نسخهٔ جدید یا انتشار ایجاد نمی‌کند.

## قابلیت‌ها

### Iranian National ID

فیلد اختصاصی Gravity Forms با type زیر:

`pgr_national_id`

قابلیت‌ها:

- ورودی ۱۰ رقمی کد ملی ایران
- تبدیل ارقام فارسی و عربی به ASCII
- اعتبارسنجی server-side checksum
- ذخیره مقدار canonical به‌صورت ۱۰ رقم ASCII
- پشتیبانی از Gravity Forms conditional logic
- استفاده از سازوکار native `No Duplicates` خود Gravity Forms
- گزینه اختیاری برای normalize کردن ارقام هنگام تایپ

### Jalali Date

فیلد اختصاصی Gravity Forms با type زیر:

`pgr_jalali_date`

قابلیت‌ها:

- اعتبارسنجی server-side تاریخ جلالی
- چند presentation format قابل انتخاب در Form Editor
- ذخیره canonical به شکل `YYYY-MM-DD` با ارقام ASCII
- مقدار ذخیره‌شده همچنان **Jalali** است و به Gregorian تبدیل نمی‌شود
- پشتیبانی از conditional logic و merge-tag / entry display

این افزونه Date field عادی Gravity Forms را override نمی‌کند.

### Structured Scanner v0.1

فیلد کنترل‌گر غیرذخیره‌ای Gravity Forms با type زیر:

`pgr_structured_scanner`

پروفایل ساختاری فعلی:

`sayad_v01`

خروجی‌های این پروفایل دقیقاً به این ترتیب هستند:

1. `qr_version`
2. `owner_type`
3. `owner_identifier`
4. `iban`
5. `bank_branch`
6. `cheque_serial`
7. `sayad_id`

رفتار فعلی:

- Scanner یک `textarea` چندخطی transient و بدون `name="input_<id>"` رندر می‌کند؛ raw payload خودش Entry value نیست.
- LF و CRLF در مرز capture حفظ می‌شوند و parser موجود آن‌ها را normalize می‌کند.
- ارقام فارسی/عربی به ASCII به‌صورت string تبدیل می‌شوند و leading zero از بین نمی‌رود.
- Enterهای جداکننده تا وقتی parser payload را کامل و معتبر ندانسته‌اند scan را زودهنگام finalize نمی‌کنند.
- Enter روی payload کامل، Tab به‌عنوان finalization صریح، paste صریح، و idle روی payload کامل از همان parser موجود برای تصمیم completion استفاده می‌کنند.
- mapping فقط به Gravity Forms Single Line Text (`text`) و Hidden (`hidden`) مجاز است.
- `scanner_profile` و `scanner_mappings` configuration ذخیره می‌شوند؛ raw scan ذخیره نمی‌شود.
- mapping نامعتبر، target حذف‌شده/نامعتبر، duplicate target و self-target به‌صورت fail-closed رد می‌شوند.
- mapped-field update اتمیک است: ابتدا کل plan معتبر می‌شود و سپس همه مقصدها به‌روزرسانی می‌شوند؛ scan ناموفق نباید بخشی از state موفق قبلی را تغییر دهد.
- assetهای Scanner فقط روی فرم‌هایی load می‌شوند که `pgr_structured_scanner` دارند و lifecycle بعد از render از `gform/post_render` پشتیبانی می‌کند.

این پروفایل فقط **ساختار هفت‌بخشی** را تفسیر می‌کند. این قابلیت authority برای checksum صیادی، اعتبار بانکی، cross-bank compatibility یا business validation مالی ایجاد نمی‌کند.

### قابلیت‌های عمومی دیگر

- form-level Persian/Arabic digit normalization قبل از ذخیره Entry
- Iranian address type و فهرست استان‌ها
- currencyهای `IRR` و `IRT`
- ترجمهٔ متن‌های خود PersianGravity و زیرساخت overlay عمومی `fa_IR` برای محصولات صریحاً ثبت‌شده

## معماری

```text
persian-gravityforms.php
        │
        ├── PGR_Localization (ثبت فوری resolverها)
        └── gform_loaded
              │
              └── PGR_Core
                    ├── PGR_Admin
                    ├── PGR_Utils
                    ├── PGR_Address
                    ├── PGR_Currency
                    ├── PGR_Persian_Date
                    ├── PGR_Scanner_Profile_Registry
                    ├── PGR_GF_Field_National_ID
                    ├── PGR_GF_Field_Jalali_Date
                    └── PGR_GF_Field_Structured_Scanner
```

جزئیات بیشتر: [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md)

## مرز مسئولیت

PersianGravity عمداً این کارها را انجام **نمی‌دهد**:

- مدیریت Vazir/Vazirmatn یا سایر فونت‌ها
- ترجمهٔ دامنه‌های دلخواه یا تغییر اصطلاحات برای پروژه‌ای خاص
- payment gateway
- workflow/business logic
- منطق SRWF یا سایر پروژه‌های خاص
- custom database
- bank/checksum authority برای Structured Scanner
- compatibility با field IDها و migrationهای legacy حذف‌شده

## نصب

1. پوشه افزونه را در `wp-content/plugins/` قرار دهید.
2. Gravity Forms 3.0+ باید نصب و فعال باشد.
3. افزونه **Persian Gravity Forms** را فعال کنید.
4. در Gravity Forms Form Editor، فیلدهای `Jalali Date`، `Iranian National ID` و در source فعلی PR، `Structured Scanner` در Advanced Fields در دسترس هستند.

## تنظیمات

صفحه تنظیمات:

`Settings → Persian Gravity Forms`

در نسخه فعلی تنظیم global برای default رفتار typing-time normalization فیلد National ID وجود دارد.

همچنین هر فرم یک تنظیم `Persian digit normalization` دارد که در صورت فعال بودن، مقادیر string را قبل از ذخیره به ارقام ASCII تبدیل می‌کند.

Structured Scanner تنظیم global جدیدی اضافه نمی‌کند. configuration آن field-owned است و در `scanner_profile` و `scanner_mappings` نگه‌داری می‌شود.

## توسعه و تست

```bash
composer install
composer test
composer cs
composer compat
node --test tests/js/structured-scanner.test.js
```

CI فعلی runtime واقعی shipped plugin را بررسی می‌کند، Scanner pure-JavaScript tests را اجرا می‌کند و PHPUnit را روی PHP `8.2`, `8.3`, `8.4`, `8.5` اجرا می‌کند.

توجه: unit/runtime CI جای integration test روی یک WordPress + Gravity Forms licensed environment واقعی را نمی‌گیرد. برای Structured Scanner، مرورگر/Gravity Forms واقعی تا وقتی طبق `docs/VALIDATION.md` اجرا نشده باشد `NOT_PROVEN` باقی می‌ماند.

## Translation

برای دامنه‌های `gravityforms`، `gravityflow` و `gk-gravityview` زیرساخت مشترک ترجمهٔ فارسی اضافه شده است. اگر ترجمهٔ ارائه‌دهنده موجود باشد اولویت دارد؛ در غیر این صورت ترجمهٔ upstream/TranslationsPress باقی می‌ماند و بعد از آن متن اصلی نمایش داده می‌شود. هیچ فایل فروشنده یا updater تغییر نمی‌کند.

**وضعیت این PR ناقص است:** بسته/POT دقیق GF `3.1.1.1`، Flow `3.1.1` و GravityView `3.3.3` در محیط اجرای کار در دسترس نبود. بنابراین POها فعلاً scaffold خالی هستند، هیچ ترجمهٔ تولیدی و هیچ handle تأییدنشده‌ای فعال نشده است. هسته و ساخت کاتالوگ قابل بررسی‌اند؛ این PR ادعای فارسی‌سازی آمادهٔ استفاده ندارد. پوشش کل محصولات نامعلوم است، نه ۱۰۰٪ و نه حتی یک شمارش کامل با صفر ترجمه.

`composer i18n:check` سازگاری source و metadata و نبود خروجی یتیم را کنترل می‌کند. برای تولید آگاهانه `composer i18n:build` و برای آزمون Core، `PGR_WP_CORE=/path/to/wordpress composer i18n:test` را اجرا کنید. PO هنگام ساخت دست‌نخورده می‌ماند. فایل‌های `.mo`، `.l10n.php` و JSON فقط برای محتوای معتبر و ثبت‌شده ساخته می‌شوند.

ثبت resolverها فوری و بدون بارگذاری ترجمه است. درخواست‌هایی که پیش از بارگذاری خود PersianGravity رخ داده‌اند خارج از این مرز هستند. جزئیات، شواهد و مراحل تکمیل در [`docs/LOCALIZATION.md`](docs/LOCALIZATION.md) ثبت شده‌اند.

## Release

قبل از release باید حداقل این موارد هم‌راستا باشند:

- plugin header / `PGR_VERSION`
- `readme.txt` Stable tag
- Composer PHP baseline
- CI matrix
- changelog
- production package contents

Structured Scanner در این PR موجب version bump یا release نمی‌شود.

مستندات contributor/agent: [`AGENTS.md`](AGENTS.md)
