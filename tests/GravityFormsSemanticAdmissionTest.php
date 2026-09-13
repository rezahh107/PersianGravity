<?php

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

final class GravityFormsSemanticAdmissionTest extends TestCase {
    public function test_confirmed_semantic_repairs_propagate_from_records_to_generated_provider(): void {
        $root = dirname( __DIR__ );
        $aggregate = ( new Gettext\Loader\StrictPoLoader() )->loadFile( $root . '/languages/providers/gravityforms/source/fa_IR.po' );
        $generated = require $root . '/languages/providers/gravityforms/gravityforms-fa_IR.l10n.php';
        $corrections = array(
			array( 'languages/providers/gravityforms/source/records/admin-builder-fa_IR.po', null, 'Enable Submit Button Conditional Logic', 'فعال‌سازی منطق شرطی دکمهٔ ارسال' ),
			array( 'languages/providers/gravityforms/source/records/admin-builder-fa_IR.po', null, 'Allow field to be populated dynamically', 'اجازه دهید فیلد به‌صورت پویا مقداردهی شود' ),
			array( 'languages/providers/gravityforms/source/records/admin-builder-fa_IR.po', null, 'Default Province', 'استان پیش‌فرض' ),
			array( 'languages/providers/gravityforms/source/records/admin-builder-fa_IR.po', null, 'Select Exact Number', 'انتخاب تعداد دقیق' ),
			array( 'languages/providers/gravityforms/source/records/admin-builder-fa_IR.po', null, 'Product Field Mapping', 'نگاشت فیلد محصول' ),
			array( 'languages/providers/gravityforms/source/records/admin-builder-fa_IR.po', null, 'Job Type', 'نوع شغل' ),
			array( 'languages/providers/gravityforms/source/records/admin-builder-fa_IR.po', null, 'Descending', 'نزولی' ),
			array( 'languages/providers/gravityforms/source/records/admin-builder-fa_IR.po', null, 'Ascending', 'صعودی' ),
			array( 'languages/providers/gravityforms/source/records/admin-builder-fa_IR.po', null, 'Selections', 'انتخاب‌ها' ),
			array( 'languages/providers/gravityforms/source/records/admin-builder-fa_IR.po', null, 'Default State', 'ایالت پیش‌فرض' ),
			array( 'languages/providers/gravityforms/source/records/admin-builder-fa_IR.po', null, 'Multiple Files', 'چند فایل' ),
			array( 'languages/providers/gravityforms/source/records/admin-builder-fa_IR.po', null, 'Last page options', 'گزینه‌های صفحهٔ آخر' ),
			array( 'languages/providers/gravityforms/source/records/admin-builder-fa_IR.po', null, 'Manage last page options', 'مدیریت گزینه‌های صفحهٔ آخر' ),
			array( 'languages/providers/gravityforms/source/records/admin-builder-fa_IR.po', null, 'Maximum', 'حداکثر' ),
			array( 'languages/providers/gravityforms/source/records/admin-builder-fa_IR.po', null, 'Select One', 'انتخاب یکی' ),
			array( 'languages/providers/gravityforms/source/records/admin-builder-fa_IR.po', null, 'End of the last row', 'پایان آخرین ردیف' ),
			array( 'languages/providers/gravityforms/source/records/admin-builder-fa_IR.po', null, 'Enable Autocomplete', 'فعال‌سازی تکمیل خودکار' ),
			array( 'languages/providers/gravityforms/source/records/admin-builder-fa_IR.po', null, 'Option Label', 'برچسب گزینه' ),
			array( 'languages/providers/gravityforms/source/records/admin-builder-fa_IR.po', null, 'Submit Input Type', 'نوع ورودی دکمهٔ ارسال' ),
			array( 'languages/providers/gravityforms/source/records/admin-builder-fa_IR.po', null, 'Input Mask', 'الگوی ورودی' ),
			array( 'languages/providers/gravityforms/source/records/admin-builder-fa_IR.po', null, 'Do not round', 'گرد نکن' ),
			array( 'languages/providers/gravityforms/source/records/admin-builder-fa_IR.po', null, 'Persian', 'فارسی' ),
			array( 'languages/providers/gravityforms/source/records/admin-builder-fa_IR.po', null, 'Minimum', 'حداقل' ),
			array( 'languages/providers/gravityforms/source/records/admin-builder-fa_IR.po', null, 'Not employed but looking for work', 'شاغل نیستم و دنبال کار می‌گردم' ),
			array( 'languages/providers/gravityforms/source/records/admin-builder-fa_IR.po', null, 'Horizontal', 'افقی' ),
			array( 'languages/providers/gravityforms/source/records/admin-builder-fa_IR.po', null, 'Filter the entries by adding conditions.', 'با افزودن شرط‌ها، ورودی‌ها را فیلتر کنید.' ),
			array( 'languages/providers/gravityforms/source/records/admin-builder-fa_IR.po', null, 'Select Multiple', 'انتخاب چند مورد' ),
			array( 'languages/providers/gravityforms/source/records/admin-builder-fa_IR.po', null, 'Hide Province Field', 'پنهان‌کردن فیلد استان' ),
			array( 'languages/providers/gravityforms/source/records/admin-builder-fa_IR.po', null, 'Submit Button Text', 'متن دکمهٔ ارسال' ),
			array( 'languages/providers/gravityforms/source/records/admin-builder-fa_IR.po', null, 'Gradient: Blues', 'گرادیان: آبی‌ها' ),
			array( 'languages/providers/gravityforms/source/records/admin-builder-fa_IR.po', null, 'Laothian', 'لائوسی' ),
			array( 'languages/providers/gravityforms/source/records/admin-builder-fa_IR.po', null, 'Serbian', 'صربی' ),
			array( 'languages/providers/gravityforms/source/records/admin-builder-fa_IR.po', null, 'Male, Female, Non-binary, Agender, My gender is not listed, Prefer not to answer', 'مرد، زن، غیردودویی، بی‌جنسیت، جنسیت من در فهرست نیست، ترجیح می‌دهم پاسخ ندهم' ),
			array( 'languages/providers/gravityforms/source/records/admin-builder-fa_IR.po', null, 'Select the default validation message placement.  Validation messages can be placed above the field inputs or below the field inputs.', 'محل پیش‌فرض پیام اعتبارسنجی را انتخاب کنید. پیام‌های اعتبارسنجی می‌توانند بالای ورودی‌های فیلد یا پایین آن‌ها قرار گیرند.' ),
			array( 'languages/providers/gravityforms/source/records/admin-builder-fa_IR.po', null, 'Hide State/Province/Region', 'پنهان‌کردن ایالت/استان/منطقه' ),
			array( 'languages/providers/gravityforms/source/records/admin-builder-fa_IR.po', null, 'Check this box to prevent the State/Province/Region from being displayed in the form.', 'برای جلوگیری از نمایش ایالت/استان/منطقه در فرم، این گزینه را علامت بزنید.' ),
			array( 'languages/providers/gravityforms/source/records/admin-builder-fa_IR.po', null, 'Validation Summary', 'خلاصهٔ اعتبارسنجی' ),
			array( 'languages/providers/gravityforms/source/records/admin-builder-fa_IR.po', null, 'Custom Validation Message', 'پیام اعتبارسنجی سفارشی' ),
			array( 'languages/providers/gravityforms/source/records/admin-builder-fa_IR.po', null, 'Schedule Form', 'زمان‌بندی فرم' ),
			array( 'languages/providers/gravityforms/source/records/admin-builder-fa_IR.po', null, 'Date Picker Icon', 'آیکون انتخابگر تاریخ' ),
			array( 'languages/providers/gravityforms/source/records/admin-builder-fa_IR.po', null, 'Placeholder text is not supported when using the Rich Text Editor.', 'هنگام استفاده از ویرایشگر متن غنی، متن نگه‌دارنده پشتیبانی نمی‌شود.' ),
			array( 'languages/providers/gravityforms/source/records/admin-builder-fa_IR.po', null, 'Limit Number of Entries', 'محدود کردن تعداد ورودی‌ها' ),
			array( 'languages/providers/gravityforms/source/records/admin-builder-fa_IR.po', null, 'Create rules to dynamically display or hide this page based on values from another field.', 'برای نمایش یا پنهان‌کردن پویای این صفحه بر اساس مقادیر فیلدی دیگر، قانون ایجاد کنید.' ),
			array( 'languages/providers/gravityforms/source/records/admin-builder-fa_IR.po', null, 'Submit Button Image URL', 'URL تصویر دکمهٔ ارسال' ),
			array( 'languages/providers/gravityforms/source/records/admin-builder-fa_IR.po', null, 'Insert Choices', 'درج گزینه‌ها' ),
			array( 'languages/providers/gravityforms/source/records/admin-builder-fa_IR.po', null, 'Czech', 'چکی' ),
			array( 'languages/providers/gravityforms/source/records/admin-builder-fa_IR.po', null, 'Select the province you would like to be selected by default when the form gets displayed.', 'استانی را که می‌خواهید هنگام نمایش فرم به‌طور پیش‌فرض انتخاب شود، انتخاب کنید.' ),
			array( 'languages/providers/gravityforms/source/records/admin-builder-fa_IR.po', null, 'Date Input Type', 'نوع ورودی تاریخ' ),
			array( 'languages/providers/gravityforms/source/records/entry-management-fa_IR.po', null, 'Resend', 'ارسال مجدد' ),
			array( 'languages/providers/gravityforms/source/records/entry-management-fa_IR.po', null, 'Take Over', 'به دست گرفتن کنترل' ),
			array( 'languages/providers/gravityforms/source/records/entry-management-fa_IR.po', null, 'This form does not have any entries in the trash matching the search criteria.', 'این فرم هیچ ورودی در زباله‌دان مطابق با معیارهای جستجو ندارد.' ),
			array( 'languages/providers/gravityforms/source/records/entry-management-fa_IR.po', null, 'Display Mode', 'حالت نمایش' ),
			array( 'languages/providers/gravityforms/source/records/entry-management-fa_IR.po', null, '%s moved to Trash.', '%s به زباله‌دان منتقل شدند.' ),
			array( 'languages/providers/gravityforms/source/records/entry-management-fa_IR.po', null, 'Resend Notifications', 'ارسال مجدد اعلان‌ها' ),
			array( 'languages/providers/gravityforms/source/records/entry-management-fa_IR.po', null, '%s has taken over and is currently editing.', '%s کنترل را به دست گرفته و در حال ویرایش است.' ),
			array( 'languages/providers/gravityforms/source/records/entry-management-fa_IR.po', null, 'Resending...', 'در حال ارسال مجدد...' ),
			array( 'languages/providers/gravityforms/source/records/developer-diagnostics-fa_IR.po', null, 'The database is currently being upgraded to version %s. %s', 'پایگاه داده در حال ارتقا به نسخهٔ %s است. %s' ),
			array( 'languages/providers/gravityforms/source/records/developer-diagnostics-fa_IR.po', null, 'Database Server', 'سرور پایگاه داده' ),
			array( 'languages/providers/gravityforms/source/records/developer-diagnostics-fa_IR.po', null, 'Site Locale', 'زبان سایت' ),
			array( 'languages/providers/gravityforms/source/records/developer-diagnostics-fa_IR.po', null, 'User (ID: %d) Locale', 'زبان کاربر (ID: %d)' ),
			array( 'languages/providers/gravityforms/source/records/developer-diagnostics-fa_IR.po', null, 'WordPress (Local) Timezone', 'منطقهٔ زمانی محلی WordPress' ),
			array( 'languages/providers/gravityforms/source/records/developer-diagnostics-fa_IR.po', null, 'Database Management System', 'سامانهٔ مدیریت پایگاه داده' ),
			array( 'languages/providers/gravityforms/source/records/developer-diagnostics-fa_IR.po', null, 'Parent', 'والد' ),
			array( 'languages/providers/gravityforms/source/records/settings-integrations-fa_IR.po', null, 'Not implemented', 'پیاده‌سازی نشده' ),
			array( 'languages/providers/gravityforms/source/records/settings-integrations-fa_IR.po', null, 'Click to upload', 'برای بارگذاری کلیک کنید' ),
			array( 'languages/providers/gravityforms/source/records/settings-integrations-fa_IR.po', null, 'Authorization has been voided. Transaction Id: %s', 'مجوز باطل شد. شماره تراکنش: %s' ),
			array( 'languages/providers/gravityforms/source/records/settings-integrations-fa_IR.po', null, 'The Form ID for the entry.', 'شناسهٔ فرم برای ورودی.' ),
			array( 'languages/providers/gravityforms/source/records/settings-integrations-fa_IR.po', null, 'Enable Data Collection', 'فعال‌سازی گردآوری داده' ),
			array( 'languages/providers/gravityforms/source/records/settings-integrations-fa_IR.po', null, 'Invisible', 'نامرئی' ),
			array( 'languages/providers/gravityforms/source/records/settings-integrations-fa_IR.po', null, 'API Keys', 'کلیدهای API' ),
			array( 'languages/providers/gravityforms/source/records/settings-integrations-fa_IR.po', null, 'Consumer Key', 'کلید مصرف‌کننده' ),
			array( 'languages/providers/gravityforms/source/records/settings-integrations-fa_IR.po', null, 'Unable to render form settings.', 'نمایش تنظیمات فرم ممکن نیست.' ),
			array( 'languages/providers/gravityforms/source/records/settings-integrations-fa_IR.po', null, 'A valid license key is required for access to automatic plugin upgrades and product support.', 'برای دسترسی به ارتقای خودکار افزونه و پشتیبانی محصول، کلید مجوز معتبر لازم است.' ),
			array( 'languages/providers/gravityforms/source/records/settings-integrations-fa_IR.po', null, 'swatch', 'نمونهٔ رنگ' ),
			array( 'languages/providers/gravityforms/source/records/settings-integrations-fa_IR.po', null, 'Products and Services', 'محصولات و خدمات' ),
			array( 'languages/providers/gravityforms/source/records/settings-integrations-fa_IR.po', null, 'You must select a valid minute.', 'باید یک دقیقهٔ معتبر انتخاب کنید.' ),
			array( 'languages/providers/gravityforms/source/records/settings-integrations-fa_IR.po', null, 'You must select either am or pm.', 'باید یکی از گزینه‌های قبل‌ازظهر یا بعدازظهر را انتخاب کنید.' ),
			array( 'languages/providers/gravityforms/source/records/settings-integrations-fa_IR.po', null, 'Whether the entry has been read.', 'آیا ورودی خوانده شده است.' ),
			array( 'languages/providers/gravityforms/source/records/settings-integrations-fa_IR.po', null, 'Error retrieving notes.', 'خطا در دریافت یادداشت‌ها.' ),
			array( 'languages/providers/gravityforms/source/records/settings-integrations-fa_IR.po', null, 'Address (State / Province)', 'آدرس (ایالت / استان)' ),
			array( 'languages/providers/gravityforms/source/records/settings-integrations-fa_IR.po', null, 'Select a Value', 'یک مقدار انتخاب کنید' ),
			array( 'languages/providers/gravityforms/source/records/settings-integrations-fa_IR.po', null, 'Purchase Date', 'تاریخ خرید' ),
			array( 'languages/providers/gravityforms/source/records/settings-integrations-fa_IR.po', null, 'Gravity Forms has been successfully uninstalled. It can be re-activated from the %splugins page%s.', 'Gravity Forms با موفقیت حذف شد. می‌توان دوباره آن را از %sصفحهٔ افزونه‌ها%s فعال کرد.' ),
			array( 'languages/providers/gravityforms/source/records/settings-integrations-fa_IR.po', null, 'Post Payment Actions', 'اقدامات پس از پرداخت' ),
			array( 'languages/providers/gravityforms/source/records/settings-integrations-fa_IR.po', null, 'Subscription', 'اشتراک' ),
			array( 'languages/providers/gravityforms/source/records/settings-integrations-fa_IR.po', null, 'Select which actions should only occur after payment has been received.', 'انتخاب کنید کدام اقدامات فقط پس از دریافت پرداخت انجام شوند.' ),
			array( 'languages/providers/gravityforms/source/records/settings-integrations-fa_IR.po', 'regarding a payment method', 'Any', 'هرکدام' ),
        );

        foreach ( $corrections as $correction ) {
            list( $path, $context, $msgid, $expected ) = $correction;
            $record = ( new Gettext\Loader\StrictPoLoader() )->loadFile( $root . '/' . $path );
            $this->assertSame( $expected, $this->translation( $record, $msgid, $context ), $path . ': ' . $msgid );
            $this->assertSame( $expected, $this->translation( $aggregate, $msgid, $context ), 'aggregate: ' . $msgid );
            $runtime_key = null === $context ? $msgid : $context . "\x04" . $msgid;
            $this->assertArrayHasKey( $runtime_key, $generated['messages'], 'generated provider: ' . $msgid );
            $this->assertSame( $expected, $generated['messages'][ $runtime_key ], 'generated provider: ' . $msgid );
        }
    }

    public function test_previously_locked_block_repairs_remain_exact(): void {
        $root = dirname( __DIR__ );
        $block = ( new Gettext\Loader\StrictPoLoader() )->loadFile(
            $root . '/languages/providers/gravityforms/source/records/frontend-block-fa_IR.po'
        );
        $this->assertSame( 'رنگ‌ها', $this->translation( $block, 'Colors', null ) );
        $this->assertSame( 'رنگ تاکیدی', $this->translation( $block, 'Accent', null ) );
    }

    private function translation( $catalog, string $msgid, ?string $context ): ?string {
        foreach ( $catalog as $entry ) {
            if ( $entry->getOriginal() === $msgid && (string) ( $entry->getContext() ?? '' ) === (string) ( $context ?? '' ) ) {
                return $entry->getTranslation();
            }
        }
        return null;
    }
}
