# Persian Gravity Forms

افزونه‌ای متمرکز برای افزودن قابلیت‌های عمومی فارسی/ایرانی به Gravity Forms، بدون درگیر شدن با فونت، workflow، منطق پروژه‌های خاص یا ترجمه افزونه‌های دیگر.

## وضعیت فعلی

- Plugin version: `4.0.0`
- WordPress minimum: `6.7`
- PHP minimum: `8.2`
- Gravity Forms minimum: `3.0`
- Canonical runtime: `PGR_*`
- Text domain: `persian-gravityforms`
- Entrypoint: `persian-gravityforms.php`

نسخه 4 معماری‌های موازی و legacy قبلی را حذف کرده و فقط یک runtime اصلی نگه می‌دارد.

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

### قابلیت‌های عمومی دیگر

- form-level Persian/Arabic digit normalization قبل از ذخیره Entry
- Iranian address type و فهرست استان‌ها
- currencyهای `IRR` و `IRT`
- localization فقط برای stringهای خود PersianGravity

## معماری

```text
persian-gravityforms.php
        │
        └── gform_loaded
              │
              └── PGR_Core
                    ├── PGR_Admin
                    ├── PGR_Utils
                    ├── PGR_Address
                    ├── PGR_Currency
                    ├── PGR_Persian_Date
                    ├── PGR_GF_Field_National_ID
                    └── PGR_GF_Field_Jalali_Date
```

جزئیات بیشتر: [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md)

## مرز مسئولیت

PersianGravity عمداً این کارها را انجام **نمی‌دهد**:

- مدیریت Vazir/Vazirmatn یا سایر فونت‌ها
- ترجمه Gravity Forms، Gravity Flow یا GravityView
- payment gateway
- workflow/business logic
- منطق SRWF یا سایر پروژه‌های خاص
- custom database
- compatibility با field IDها و migrationهای legacy حذف‌شده

## نصب

1. پوشه افزونه را در `wp-content/plugins/` قرار دهید.
2. Gravity Forms 3.0+ باید نصب و فعال باشد.
3. افزونه **Persian Gravity Forms** را فعال کنید.
4. در Gravity Forms Form Editor، فیلدهای `Jalali Date` و `Iranian National ID` در Advanced Fields در دسترس هستند.

## تنظیمات

صفحه تنظیمات:

`Settings → Persian Gravity Forms`

در نسخه فعلی تنظیم global برای default رفتار typing-time normalization فیلد National ID وجود دارد.

همچنین هر فرم یک تنظیم `Persian digit normalization` دارد که در صورت فعال بودن، مقادیر string را قبل از ذخیره به ارقام ASCII تبدیل می‌کند.

## توسعه و تست

```bash
composer install
composer test
composer cs
composer compat
```

CI فعلی runtime واقعی shipped plugin را بررسی می‌کند و PHPUnit را روی PHP `8.2`, `8.3`, `8.4`, `8.5` اجرا می‌کند.

توجه: unit/runtime CI جای integration test روی یک WordPress + Gravity Forms licensed environment واقعی را نمی‌گیرد. وضعیت validation و gapهای شناخته‌شده در [`docs/VALIDATION.md`](docs/VALIDATION.md) ثبت می‌شوند.

## Translation

فقط text domain زیر متعلق به این افزونه است:

`persian-gravityforms`

POT را می‌توان با این دستور ساخت:

```bash
composer i18n:pot
```

ترجمه محصولات دیگر Gravity ecosystem باید از مکانیزم رسمی همان محصول انجام شود.

## Release

قبل از release باید حداقل این موارد هم‌راستا باشند:

- plugin header / `PGR_VERSION`
- `readme.txt` Stable tag
- Composer PHP baseline
- CI matrix
- changelog
- production package contents

مستندات contributor/agent: [`AGENTS.md`](AGENTS.md)
