<?php
/**
 * Static bilingual Help & Documentation catalog.
 *
 * @package PersianGravityForms
 */

defined( 'ABSPATH' ) || exit;

final class PGR_Help_Catalog {
	/** Return all version-controlled help topics. */
	public static function all() {
		return array(
			'quick-start'         => self::topic(
				'شروع سریع',
				'<p>PersianGravity مجموعه‌ای محدود از قابلیت‌های فارسی/ایرانی برای Gravity Forms است. منوی <strong>Persian Gravity</strong> شامل Overview، Scanner Profiles، Settings، System Status و Help & Documentation است. نیازمندی فعلی WordPress 6.7+، PHP 8.2+ و Gravity Forms 3.0+ است.</p><p>همه شش ماژول هنگام ارتقا از 4.1.0 پیش‌فرض فعال‌اند. غیرفعال‌سازی فقط runtime قابلیت را متوقف می‌کند و داده، تنظیمات یا فرم موجود را حذف نمی‌کند.</p>',
				'Quick start',
				'<p>PersianGravity is a bounded set of Persian/Iranian capabilities for Gravity Forms. The <strong>Persian Gravity</strong> menu contains Overview, Scanner Profiles, Settings, System Status, and Help & Documentation. Current requirements are WordPress 6.7+, PHP 8.2+, and Gravity Forms 3.0+.</p><p>All six modules remain enabled by default when upgrading from 4.1.0. Disabling stops that capability runtime; it does not delete existing forms, settings, profiles, or entries.</p>'
			),
			'module-manager'      => self::topic(
				'مدیریت ماژول‌ها',
				'<p>کارت‌های Overview نام و توضیح فارسی و English، وضعیت Enabled/Disabled، کنترل تغییر وضعیت و لینک Help را نشان می‌دهند. تغییر وضعیت با POST، nonce و مجوز مدیریت انجام می‌شود.</p><p>قبل از Disable، فقط metadata فرم‌ها بررسی می‌شود. اگر قابلیت در فرم استفاده شود عملیات مسدود است. UNKNOWN یعنی استفاده فعلی با قرارداد عمومی قابل اثبات نیست؛ در این حالت تأیید دوم لازم است. بررسی usage در بارگذاری عادی صفحه اجرا نمی‌شود.</p>',
				'Module Manager',
				'<p>Overview cards show Persian and English names/descriptions, Enabled/Disabled state, a state-change control, and Help. State changes use POST, a nonce, and the canonical admin capability.</p><p>Before Disable, only form configuration metadata is inspected. USED blocks disable. UNKNOWN means public contracts cannot establish current use confidently, so a second explicit confirmation is required. Usage scans do not run on ordinary page loads.</p>'
			),
			'national-id'         => self::topic(
				'کد ملی ایران',
				'<p>Field type <code dir="ltr">pgr_national_id</code> در Advanced Fields قرار دارد. ارقام فارسی/عربی در سمت سرور به ASCII تبدیل می‌شوند، checksum کد ملی بررسی می‌شود و مقدار معتبر به صورت دقیقاً ۱۰ رقم ASCII ذخیره می‌شود. No Duplicates از مکانیزم Gravity Forms استفاده می‌کند.</p><p>گزینه typing-time normalization فقط UX است؛ اعتبار و ذخیره نهایی server-authoritative است. جداکننده‌های فاصله/خط تیره در normalization پذیرفته می‌شوند، اما داده نامعتبر یا checksum نادرست رد می‌شود.</p>',
				'Iranian National ID',
				'<p>Field type <code>pgr_national_id</code> appears under Advanced Fields. Persian/Arabic digits are normalized server-side, the Iranian checksum is validated, and valid storage is exactly ten ASCII digits. No Duplicates uses Gravity Forms native behavior.</p><p>Typing-time normalization is UX only; validation and persistence remain server-authoritative. Presentation spaces/dashes can normalize, while malformed values or a bad checksum are rejected.</p>'
			),
			'jalali-date'         => self::topic(
				'تاریخ جلالی',
				'<p>Field type <code dir="ltr">pgr_jalali_date</code> یک فیلد اختصاصی است و Date بومی Gravity Forms را جایگزین نمی‌کند. قالب‌های نمایشی YYYY/MM/DD، YYYY-MM-DD، YYYY.MM.DD، DD/MM/YYYY، DD-MM-YYYY، DD.MM.YYYY و MM/DD/YYYY پشتیبانی می‌شوند.</p><p>اعتبار تقویم جلالی در سرور بررسی و مقدار به صورت ASCII <code dir="ltr">YYYY-MM-DD</code> ذخیره می‌شود؛ معنای تقویم ذخیره‌شده همچنان جلالی است و تبدیل ضمنی Gregorian انجام نمی‌شود.</p>',
				'Jalali Date',
				'<p>Field type <code>pgr_jalali_date</code> is dedicated and does not replace the native Gravity Forms Date field. Supported presentation formats include YYYY/MM/DD, YYYY-MM-DD, YYYY.MM.DD, DD/MM/YYYY, DD-MM-YYYY, DD.MM.YYYY, and MM/DD/YYYY.</p><p>Jalali calendar validity is checked server-side and storage is canonical ASCII <code>YYYY-MM-DD</code>. The stored calendar semantics remain Jalali; no implicit Gregorian conversion occurs.</p>'
			),
			'iranian-address'     => self::topic(
				'نشانی ایران',
				'<p>این ماژول Address type ایران با <code dir="ltr">addressType=iran</code> و فهرست ۳۱ استان را به Gravity Forms اضافه می‌کند؛ همچنین Iranian Provinces را در predefined choices ارائه می‌دهد.</p><p>با Disable، hookهای address type و province choices ثبت نمی‌شوند. فرم‌ها و metadata موجود حذف یا بازنویسی نمی‌شوند؛ اگر استفاده از addressType ایران تشخیص داده شود Disable مسدود می‌شود.</p>',
				'Iranian Address',
				'<p>This module adds the Iran Address type using <code>addressType=iran</code>, the 31-province list, and an Iranian Provinces predefined choice set.</p><p>When disabled, its address/province hooks are not registered. Existing forms and metadata are not deleted or rewritten; detected Iran address-type use blocks disable.</p>'
			),
			'digit-normalization' => self::topic(
				'نرمال‌سازی ارقام',
				'<p>این قابلیت مستقل از کد ملی و در سطح فرم است. setting با شناسه <code dir="ltr">pgr_normalize_digits</code> تعیین می‌کند ارقام فارسی و عربی قبل از ذخیره Entry به ASCII تبدیل شوند. authority در فیلتر server-side <code dir="ltr">gform_save_field_value</code> است.</p><p>اگر هر فرم این setting را فعال داشته باشد Disable مسدود می‌شود، زیرا خاموش‌کردن ناگهانی semantics ذخیره داده را تغییر می‌دهد.</p>',
				'Digit Normalization',
				'<p>This is separate from National ID and works at form level. Setting <code>pgr_normalize_digits</code> converts Persian/Arabic digits to ASCII before Entry persistence; the authority is the server-side <code>gform_save_field_value</code> path.</p><p>If any form has this setting enabled, disabling the module is blocked because doing so would silently change persistence semantics.</p>'
			),
			'iranian-currency'    => self::topic(
				'ریال و تومان ایران',
				'<p>این ماژول currencyهای <code dir="ltr">IRR</code> (Iranian Rial) و <code dir="ltr">IRT</code> (Iranian Toman) را با صفر رقم اعشار به Gravity Forms اضافه می‌کند.</p><p>این قابلیت فقط currency definition است و payment gateway یا منطق پرداخت ایجاد نمی‌کند. تشخیص مطمئن عدم استفاده جاری از currency با form metadata عمومی تضمین نشده است؛ بنابراین Disable ممکن است UNKNOWN و نیازمند تأیید دوم باشد.</p>',
				'Iranian Rial / Toman',
				'<p>This module adds <code>IRR</code> (Iranian Rial) and <code>IRT</code> (Iranian Toman) Gravity Forms currency definitions with zero decimal places.</p><p>It is a currency-definition feature only; it provides no payment gateway or payment workflow. Reliable proof of current non-use is not guaranteed by public form metadata, so Disable can return UNKNOWN and require second confirmation.</p>'
			),
			'structured-scanner'  => self::topic(
				'اسکنر ساختاریافته',
				'<p>Field type <code dir="ltr">pgr_structured_scanner</code> یک controller گذرا و <code dir="ltr">displayOnly</code> است: Scanner Profile ورودی را parse می‌کند، Output Mapping خروجی‌ها را به فیلدهای عادی <code dir="ltr">text</code>/<code dir="ltr">hidden</code> می‌فرستد و همان فیلدهای مقصد در Entry ذخیره می‌شوند. raw scan عمداً مقدار persisted این فیلد نیست.</p><p>update plan قبل از mutation کامل validate و به‌صورت atomic اعمال می‌شود. scan معتبر بعدی می‌تواند مقادیر mapped را جایگزین کند؛ scan نامعتبر نباید partial update ایجاد کند. Enter/Tab/idle/paste تابع parser موجود است. با Disable، field/editor/assets Scanner حذف می‌شوند ولی Scanner Profiles باقی می‌ماند.</p>',
				'Structured Scanner',
				'<p>Field type <code>pgr_structured_scanner</code> is a transient <code>displayOnly</code> controller: a Scanner Profile parses input, Output Mapping targets ordinary <code>text</code>/<code>hidden</code> fields, and those target fields persist to the Entry. Raw scanner input is intentionally not the persisted value of the Scanner field.</p><p>The full update plan is validated before atomic mutation. A later valid scan can replace mapped values; an invalid scan must not partially mutate them. Enter/Tab/idle/paste completion remains parser-driven. Disabling removes Scanner field/editor/assets while Scanner Profiles administration remains available.</p>'
			),
			'sayad-v01'           => self::topic(
				'Sayad v0.1',
				'<p>پروفایل built-in <code dir="ltr">sayad_v01</code> دقیقاً هفت خروجی مرتب دارد: <code>qr_version</code>، <code>owner_type</code>، <code>owner_identifier</code>، <code>iban</code>، <code>bank_branch</code>، <code>cheque_serial</code> و <code>sayad_id</code>.</p><p>این parser فقط ساختاری است: authority برای checksum صیاد، اعتبار بانکی، سازگاری بین‌بانکی، استعلام آنلاین یا پرداخت ندارد.</p>',
				'Sayad v0.1',
				'<p>Built-in profile <code>sayad_v01</code> has exactly seven ordered outputs: <code>qr_version</code>, <code>owner_type</code>, <code>owner_identifier</code>, <code>iban</code>, <code>bank_branch</code>, <code>cheque_serial</code>, and <code>sayad_id</code>.</p><p>It is a structural parser only: it is not authority for Sayad checksum validity, bank validity, cross-bank compatibility, online inquiry, or payment behavior.</p>'
			),
			'scanner-profiles'    => self::topic(
				'پروفایل‌های اسکنر',
				'<p>Profile می‌تواند built-in یا custom، active/disabled و read-only باشد. parser نسخه فعلی <code dir="ltr">segments_v1</code> با newline segments، trim و digit normalization است. outputs مرتب‌اند و هر output می‌تواند required باشد.</p><p>Profile ID و executable parser/output contract پس از ایجاد immutable هستند؛ تغییر ساختار نیازمند Profile ID جدید است. Duplicate/clone یک custom profile جدید می‌سازد. حذف custom profile فرم‌ها را بازنویسی نمی‌کند و ممکن است mapping موجود را unavailable کند.</p>',
				'Scanner Profiles',
				'<p>A Profile can be built-in or custom, active/disabled, and read-only. Current parser contract is <code>segments_v1</code> with newline segments, trim, digit normalization, ordered outputs, and required-output flags.</p><p>Profile ID and executable parser/output contract are immutable after creation; a changed structure needs a new Profile ID. Duplicate creates a new custom profile. Deleting a custom profile does not rewrite forms and can leave existing mappings unavailable.</p>'
			),
			'settings'            => self::topic(
				'تنظیمات',
				'<p>option فعلی <code dir="ltr">pgr_settings</code> تنظیم <code dir="ltr">default_force_english</code> را نگه می‌دارد: پیش‌فرض typing-time digit normalization برای فیلدهای جدید National ID. این مقدار با Disable کد ملی حذف نمی‌شود و تا Enable بعدی غیرفعال می‌ماند.</p>',
				'Settings',
				'<p>Current option <code>pgr_settings</code> stores <code>default_force_english</code>, the default typing-time digit-normalization choice for new National ID fields. Disabling National ID does not delete the saved value; it remains inactive until the module is enabled again.</p>'
			),
			'system-status'       => self::topic(
				'وضعیت سیستم',
				'<p>System Status نسخه PersianGravity، WordPress، PHP، دسترس‌پذیری/version Gravity Forms، وضعیت هر شش ماژول و availability Profile Registry را نشان می‌دهد. WARNING به معنی نیاز به بررسی است و عملیات مخرب در این صفحه وجود ندارد.</p>',
				'System Status',
				'<p>System Status reports PersianGravity, WordPress, PHP, Gravity Forms availability/version, all six module states, and Profile Registry availability. WARNING means the condition needs review; the page contains no destructive action.</p>'
			),
			'data-behavior'       => self::topic(
				'داده و حریم خصوصی',
				'<p>state ماژول‌ها فقط در <code dir="ltr">pgr_modules</code>، تنظیمات فعلی در <code dir="ltr">pgr_settings</code> و custom Scanner Profiles در <code dir="ltr">pgr_scanner_profiles</code> ذخیره می‌شوند. تنظیمات field/form در Gravity Forms form metadata باقی می‌ماند.</p><p>Disable هیچ Entry، field definition، setting، profile یا mapping را حذف نمی‌کند. Structured Scanner raw payload عمداً به‌عنوان مقدار Scanner field ذخیره نمی‌شود. این توضیح فقط مرز ذخیره‌سازی خود افزونه است و ادعای عمومی درباره سایر افزونه‌ها نیست.</p>',
				'Data & Privacy',
				'<p>Module state is stored only in <code>pgr_modules</code>, current settings in <code>pgr_settings</code>, and custom Scanner Profiles in <code>pgr_scanner_profiles</code>. Gravity Forms field/form configuration remains form metadata.</p><p>Disable deletes no Entries, field definitions, settings, profiles, or mappings. Structured Scanner raw payload is intentionally not persisted as the Scanner field value. This describes plugin-owned storage boundaries only, not broad privacy behavior of other plugins.</p>'
			),
			'troubleshooting'     => self::topic(
				'عیب‌یابی',
				'<p>اگر capability در Form Editor نیست، ابتدا module state و سپس Gravity Forms availability را در System Status بررسی کنید. اگر Disable مسدود است، فرم‌های استفاده‌کننده باید پیش از خاموش‌کردن اصلاح شوند. UNKNOWN نیازمند بررسی و تأیید دوم است.</p><p>برای Scanner، availability پروفایل، Profile selection، Output Mapping و نوع مقصد text/hidden را بررسی کنید؛ mapping نامعتبر fail-closed است. اگر ترجمه فارسی ظاهر نمی‌شود user locale، فایل‌های fa_IR و cache مرورگر/admin را بررسی کنید. Scanner Profiles حتی هنگام خاموش بودن Scanner باید باز شود.</p>',
				'Troubleshooting',
				'<p>If a capability is missing from the Form Editor, check its module state and Gravity Forms availability in System Status. If Disable is blocked, remove current form usage first. UNKNOWN requires review and a second confirmation.</p><p>For Scanner problems, check profile availability, Profile selection, Output Mapping, and text/hidden target types; invalid mappings fail closed. If Persian translation is missing, verify user locale, fa_IR assets, and relevant browser/admin caches. Scanner Profiles should remain accessible even while Scanner runtime is disabled.</p>'
			),
			'scope'               => self::topic(
				'دامنه و محدودیت‌ها',
				'<p>PersianGravity مالک Gravity Flow workflow، GravityView، منطق SRWF، font delivery، payment gateway، online Sayad inquiry، OCR/camera scanner، custom database یا ترجمه افزونه‌های دیگر نیست. Module Manager نیز extension marketplace یا third-party runtime registry نیست.</p>',
				'Scope & Limits',
				'<p>PersianGravity does not own Gravity Flow workflow, GravityView, SRWF business logic, font delivery, payment gateways, online Sayad inquiry, OCR/camera scanning, custom databases, or translations for other plugins. Module Manager is not an extension marketplace or third-party runtime registry.</p>'
			),
		);
	}

	/** Return one topic/language or null. */
	public static function get( $id, $lang ) {
		$all = self::all();
		return isset( $all[ $id ][ $lang ] ) ? $all[ $id ][ $lang ] : null;
	}

	/**
	 * Build one bilingual topic payload.
	 *
	 * @param string $fa_title Persian title.
	 * @param string $fa_body  Persian body.
	 * @param string $en_title English title.
	 * @param string $en_body  English body.
	 * @return array<string,array<string,string>>
	 */
	private static function topic( $fa_title, $fa_body, $en_title, $en_body ) {
		return array(
			'fa' => array(
				'title'   => $fa_title,
				'body'    => $fa_body,
				'summary' => wp_strip_all_tags( $fa_body ),
			),
			'en' => array(
				'title'   => $en_title,
				'body'    => $en_body,
				'summary' => wp_strip_all_tags( $en_body ),
			),
		);
	}
}
