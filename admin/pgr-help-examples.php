<?php
/** G-012 version-controlled, bilingual practical examples.
 *
 * @package PersianGravityForms
 */
defined( 'ABSPATH' ) || exit;
// phpcs:disable Generic.Files.LineLength.TooLong -- Copyable samples retain their complete text.
return array(
	'national-id'         => array(
		'fa' => array(
			'scenario'      => 'ثبت کد ملی متقاضی در فرم پذیرش؛ نمونه نامعتبر نباید ثبت شود.',
			'prerequisites' => 'Gravity Forms و ماژول کد ملی ایران در Persian Gravity ← Overview فعال باشد.',
			'steps'         => array(
				'Gravity Forms ← Forms ← Edit؛ از Advanced Fields فیلد Iranian National ID را اضافه کنید.',
				'Label را «کد ملی» بگذارید؛ در صورت نیاز Required و No Duplicates و گزینه Normalize Persian/Arabic digits while typing را تنظیم کنید؛ Save.',
				'در Preview یک بار نمونه معتبر و بار دیگر نمونه نامعتبر را ارسال کنید.',
			),
			'samples'       => array(
				array(
					'label' => 'کد ملی معتبر؛ ارقام فارسی و خط تیره',
					'value' => '۰۰۱-۳۵۴۶۲۴-۴',
				),
				array(
					'label' => 'مقدار نامعتبر با رقم کنترل غلط',
					'value' => '۰۰۱-۳۵۴۶۲۴-۵',
				),
				array(
					'label' => 'مقدار استاندارد Entry',
					'value' => '0013546244',
				),
			),
			'expected'      => 'معتبر پذیرفته و به ۱۰ رقم ASCII ذخیره می‌شود؛ نامعتبر با checksum غلط در سرور رد می‌شود.',
			'verification'  => 'Gravity Forms ← Entries؛ مقدار 0013546244 را در Entry موفق ببینید و برای ورودی غلط خطای Preview را بررسی کنید.',
			'notes'         => 'نمونه آزمایشی است نه هویت واقعی. حذف فاصله/خط تیره یا typing normalization اعتبارسنجی Mod-11 را حذف نمی‌کند؛ No Duplicates متعلق به Gravity Forms است.',
		),
		'en' => array(
			'scenario'      => 'Validate a signup National ID and reject a bad check digit.',
			'prerequisites' => 'Enable Iranian National ID in Persian Gravity → Overview; install Gravity Forms.',
			'steps'         => array(
				'Gravity Forms → Forms → Edit: add Iranian National ID from Advanced Fields.',
				'Set label National ID, optionally Required/No Duplicates and Normalize Persian/Arabic digits while typing; Save.',
				'Submit the valid value in Preview, then try the invalid value separately.',
			),
			'samples'       => array(
				array(
					'label' => 'Valid Persian-digit input',
					'value' => '۰۰۱-۳۵۴۶۲۴-۴',
				),
				array(
					'label' => 'Invalid checksum',
					'value' => '۰۰۱-۳۵۴۶۲۴-۵',
				),
				array(
					'label' => 'Canonical Entry value',
					'value' => '0013546244',
				),
			),
			'expected'      => 'Valid input is saved as exactly ten ASCII digits; the invalid checksum is rejected server-side.',
			'verification'  => 'Inspect the successful Gravity Forms → Entries value for 0013546244 and the validation error for the invalid attempt.',
			'notes'         => 'Algorithm fixture only, not a real identity. Separator/typing normalization never bypasses Mod-11; No Duplicates is Gravity Forms native.',
		),
	),
	'jalali-date'         => array(
		'fa' => array(
			'scenario'      => 'ثبت تاریخ یک رویداد با معنای تقویم جلالی.',
			'prerequisites' => 'ماژول تاریخ جلالی فعال باشد؛ فیلد اختصاصی را به‌جای Date بومی استفاده کنید.',
			'steps'         => array(
				'Gravity Forms ← Forms ← Edit؛ Jalali Date را از Advanced Fields اضافه کنید.',
				'در تنظیمات فیلد، Jalali date format را YYYY/MM/DD انتخاب و Save کنید.',
				'در Preview ورودی معتبر و سپس نامعتبر را جداگانه ارسال کنید.',
			),
			'samples'       => array(
				array(
					'label' => 'تاریخ معتبر',
					'value' => '۱۴۰۴/۰۲/۳۱',
				),
				array(
					'label' => 'تاریخ نامعتبر',
					'value' => '1404/07/31',
				),
				array(
					'label' => 'ذخیره استاندارد Entry',
					'value' => '1404-02-31',
				),
			),
			'expected'      => 'تاریخ معتبر با قالب انتخاب‌شده نمایش و به YYYY-MM-DD ASCII با معنای جلالی ذخیره می‌شود؛ روز ۳۱ ماه هفتم رد می‌شود.',
			'verification'  => 'Gravity Forms ← Entries را باز و 1404-02-31 را بررسی کنید؛ خطای ورودی 1404/07/31 را نیز ببینید.',
			'notes'         => 'این مقدار Gregorian نیست؛ Date بومی یا {pgr_jdate_field_2} نباید داده این فیلد را دوباره تبدیل کنند.',
		),
		'en' => array(
			'scenario'      => 'Collect an event date whose source calendar is Jalali.',
			'prerequisites' => 'Enable Jalali Date and use its dedicated field rather than native Gregorian Date.',
			'steps'         => array(
				'Gravity Forms → Forms → Edit: add Jalali Date from Advanced Fields.',
				'Set Jalali date format to YYYY/MM/DD and Save.',
				'Preview and submit the valid value; try the invalid date separately.',
			),
			'samples'       => array(
				array(
					'label' => 'Valid input',
					'value' => '۱۴۰۴/۰۲/۳۱',
				),
				array(
					'label' => 'Invalid input',
					'value' => '1404/07/31',
				),
				array(
					'label' => 'Stored Jalali ASCII value',
					'value' => '1404-02-31',
				),
			),
			'expected'      => 'The valid date stores YYYY-MM-DD ASCII with Jalali meaning; month seven day 31 fails validation.',
			'verification'  => 'Inspect 1404-02-31 in Gravity Forms → Entries and the invalid-date message in Preview.',
			'notes'         => 'No Gregorian reinterpretation or second conversion by {pgr_jdate_field_2} is supported.',
		),
	),
	'jalali-presentation' => array(
		'fa' => array(
			'scenario'      => 'نمایش تاریخ ثبت به جلالی در تأییدیه، اعلان و پارامتر نمایشی مقصد، بدون تغییر Entry.',
			'prerequisites' => 'در Persian Gravity ← Overview ماژول نمایش جلالی تاریخ‌های سیستمی را Enable کنید (پیش‌فرض OFF)؛ locale سایت fa_IR باشد.',
			'steps'         => array(
				'Gravity Forms ← Forms ← Edit؛ برای نمونه Date بومی میلادی با ID واقعی 2 بسازید؛ Jalali Date مجاز نیست.',
				'Settings ← Confirmations؛ Text Confirmation را انتخاب و متن نمونه را Save کنید.',
				'Settings ← Notifications؛ متن اعلان نمونه را وارد کنید؛ وجود تگ جایگزین‌شده به معنای اثبات ارسال ایمیل نیست.',
				'برای Page یا Redirect Confirmation تگ را در Query String پارامتر نمایشی قرار دهید؛ پردازش URL متعلق به Gravity Forms است.',
				'فرم را در fa_IR ارسال، متن/URL مقصد را بررسی و با مقادیر خام Entry مقایسه کنید.',
			),
			'samples'       => array(
				array(
					'label' => 'Text Confirmation',
					'value' => 'تاریخ ثبت: {pgr_jdate_created_date}
ساعت: {pgr_jdate_created_time}
تاریخ انتخابی: {pgr_jdate_field_2}',
				),
				array(
					'label' => 'Notification body',
					'value' => 'ثبت جدید: {pgr_jdate_created}',
				),
				array(
					'label' => 'Page/Redirect Query String',
					'value' => 'registered={pgr_jdate_created_date}&selected={pgr_jdate_field_2}',
				),
				array(
					'label' => 'فهرست کامل: ۱۲ تگ سیستمی و یک خانواده فیلد Date',
					'value' => '{pgr_jdate_created}
{pgr_jdate_created_date}
{pgr_jdate_created_time}
{pgr_jdate_updated}
{pgr_jdate_updated_date}
{pgr_jdate_updated_time}
{pgr_jdate_payment}
{pgr_jdate_payment_date}
{pgr_jdate_payment_time}
{pgr_jdate_today}
{pgr_jdate_today_date}
{pgr_jdate_today_time}
{pgr_jdate_field_2}',
				),
			),
			'expected'      => 'تگ صریحِ معتبر در fa_IR با ماژول فعال، تاریخ جلالی را نمایش می‌دهد؛ تاریخ‌های created/updated/payment زمان UTC Entry را در timezone سایت نشان می‌دهند و today زمان رندر را. Date بومی تاریخ تقویمی بدون جابه‌جایی timezone است. DB/Entry/API تغییر نمی‌کند.',
			'verification'  => 'Text Confirmation و URL decodeشدهٔ Page/Redirect را پس از Submit ببینید؛ Render اعلان را بدون ادعای تحویل ایمیل بررسی کنید؛ در Entries مقدار خام تاریخ را مقایسه کنید.',
			'notes'         => 'pgr_jalali_date فیلد دادهٔ جلالی است؛ jalali_presentation فقط نمایش تاریخ میلادی سیستم را تغییر می‌دهد؛ تگ‌های {pgr_jdate_*} درخواست صریح‌اند. عدد 2 را در {pgr_jdate_field_2} با شناسهٔ واقعی فیلد Date بومی عوض کنید، نه ID فرم یا فیلد Jalali. تگ‌های native خودکار تبدیل نمی‌شوند و {pgr_jdate:2} نامعتبر است. نبود payment_date خروجی خالی می‌دهد؛ تاریخ نامعتبر/ID نادرست/نوع دیگر یا ماژول/handler غایب ممکن است تگ را حل‌نشده باقی بگذارد. بازه میلادی 1800-01-01 تا 2124-03-19 و timezone سایت معتبر است. Page/Redirect فقط روی WordPress 6.8.3 / PHP 8.2 / GF 3.1.1.1 آزموده شده؛ GF جدیدتر، مصرف‌کنندگان ثالث، مقصد واقعی جدید و ایمیل خارجی تأیید نشده‌اند.',
		),
		'en' => array(
			'scenario'      => 'Show a Jalali registration date in confirmations, notification rendering and a human-facing URL parameter without rewriting an Entry.',
			'prerequisites' => 'Enable System-date Jalali Presentation in Overview (OFF by default); site locale must be fa_IR.',
			'steps'         => array(
				'Gravity Forms → Forms → Edit: use a native Gregorian Date field, with a real field ID such as 2, not Jalali Date.',
				'Settings → Confirmations: put the sample in a Text Confirmation; Save.',
				'Settings → Notifications: add the sample body; message rendering does not prove external mail delivery.',
				'For Page/Redirect Confirmations add the sample to the human-facing Query String; Gravity Forms handles URL encoding.',
				'Submit on a fa_IR site; inspect the rendered content and destination URL against the raw Entry.',
			),
			'samples'       => array(
				array(
					'label' => 'Text Confirmation',
					'value' => 'Registered: {pgr_jdate_created_date}
Time: {pgr_jdate_created_time}
Selected: {pgr_jdate_field_2}',
				),
				array(
					'label' => 'Notification body',
					'value' => 'Submitted at {pgr_jdate_created}',
				),
				array(
					'label' => 'Page/Redirect Query String',
					'value' => 'registered={pgr_jdate_created_date}&selected={pgr_jdate_field_2}',
				),
				array(
					'label' => 'Complete inventory: twelve system tags and a Date-field family',
					'value' => '{pgr_jdate_created}
{pgr_jdate_created_date}
{pgr_jdate_created_time}
{pgr_jdate_updated}
{pgr_jdate_updated_date}
{pgr_jdate_updated_time}
{pgr_jdate_payment}
{pgr_jdate_payment_date}
{pgr_jdate_payment_time}
{pgr_jdate_today}
{pgr_jdate_today_date}
{pgr_jdate_today_time}
{pgr_jdate_field_2}',
				),
			),
			'expected'      => 'Explicit supported tags render Jalali only while enabled in fa_IR. created/updated/payment use authoritative Entry UTC instants in WordPress site time; today is rendered now. Native Gregorian Date uses civil date without a timezone shift. Entry/DB/API values stay unchanged.',
			'verification'  => 'Submit and inspect Text Confirmation, decoded Page/Redirect parameters and original Entry. Test notification rendering without claiming actual external delivery.',
			'notes'         => 'pgr_jalali_date stores Jalali input; jalali_presentation displays known Gregorian/system sources; {pgr_jdate_*} requests that output. Replace 2 in {pgr_jdate_field_2} with the actual native Date field ID, not form ID or Jalali field ID. Native Gravity Forms tags remain native; {pgr_jdate:2} is unsupported. Missing payment renders empty; unsupported data/ID/type or disabled/missing handler may leave literal tokens. Requires fa_IR, site timezone and Gregorian 1800-01-01..2124-03-19. Page/Redirect qualified only on WP 6.8.3 / PHP 8.2 / GF 3.1.1.1; newer GF, third-party consumers, untested routes and outbound mail not proven.',
		),
	),
	'iranian-address'     => array(
		'fa' => array(
			'scenario'      => 'گرفتن نشانی تحویل با انتخاب استان ایران.',
			'prerequisites' => 'ماژول نشانی ایران در Overview فعال باشد.',
			'steps'         => array(
				'Gravity Forms ← Forms ← Edit؛ فیلد Address را اضافه کنید.',
				'Address Type را Iran تنظیم کنید؛ Label را «نشانی تحویل» بگذارید و Save کنید.',
				'در Preview خیابان و شهر نمونه را پر، Province را فارس انتخاب و ارسال کنید؛ سپس Entry را بررسی کنید.',
				'برای فرم فقط استان، Drop Down با Predefined Choices با نام Iranian Provinces نیز ممکن است.',
			),
			'samples'       => array(
				array(
					'label' => 'تنظیم نمونه',
					'value' => 'Address Type: Iran
Province: فارس',
				),
				array(
					'label' => 'ورودی آزمایشی',
					'value' => 'Street: Example Street 10
City: Shiraz
Postal Code: 1234567890',
				),
			),
			'expected'      => 'Address نوع iran با لیست ۳۱ استان و برچسب Province نمایش داده شده و انتخاب استان در Entry ذخیره می‌شود.',
			'verification'  => 'Gravity Forms ← Entries؛ استان انتخاب‌شده و زیرفیلدهای Address را بررسی کنید.',
			'notes'         => 'آدرس ساختگی است. این ماژول صحت کد پستی یا استعلام آدرس انجام نمی‌دهد.',
		),
		'en' => array(
			'scenario'      => 'Collect a delivery address with an Iranian province selector.',
			'prerequisites' => 'Enable Iranian Address in Overview.',
			'steps'         => array(
				'Gravity Forms → Forms → Edit: add Address.',
				'Set Address Type to Iran; label Delivery address and Save.',
				'Preview, enter sample address and choose فارس under Province; submit and inspect Entry.',
				'For province alone, use a Drop Down with Iranian Provinces Predefined Choices.',
			),
			'samples'       => array(
				array(
					'label' => 'Field configuration',
					'value' => 'Address Type: Iran
Province: فارس',
				),
				array(
					'label' => 'Fictional address',
					'value' => 'Street: Example Street 10
City: Shiraz
Postal Code: 1234567890',
				),
			),
			'expected'      => 'The Iran Address type exposes a 31-province selector; normal Address subfields are saved in the Entry.',
			'verification'  => 'Open Gravity Forms → Entries to inspect the selected province and address.',
			'notes'         => 'No postal-code verification or external address lookup is provided; example address is fictional.',
		),
	),
	'digit-normalization' => array(
		'fa' => array(
			'scenario'      => 'ذخیره شماره سفارش با ارقام مخلوط فارسی و عربی در فیلد متن.',
			'prerequisites' => 'ماژول نرمال‌سازی ارقام فعال باشد؛ گزینه مخصوص هر فرم جداست.',
			'steps'         => array(
				'Gravity Forms ← Forms ← Edit؛ Single Line Text با Label «شماره سفارش» بسازید.',
				'Settings ← Form Settings ← Form Options؛ Persian digit normalization را Enabled کرده و Save کنید.',
				'Preview؛ متن نمونه را ارسال کنید و نتیجه را در Entries بررسی کنید.',
			),
			'samples'       => array(
				array(
					'label' => 'ورودی',
					'value' => 'سفارش ۱۲٣۴۵',
				),
				array(
					'label' => 'مقدار Entry',
					'value' => 'سفارش 12345',
				),
			),
			'expected'      => 'gform_save_field_value فقط ارقام فارسی/عربیِ مقدار رشته‌ای را پیش از ذخیره به ASCII تبدیل می‌کند.',
			'verification'  => 'Gravity Forms ← Entries؛ مقدار «سفارش 12345» را بررسی کنید.',
			'notes'         => 'تنظیم در سطح Form است و مستقل از گزینه typing-time کد ملی؛ داده قدیمی و تمام انواع غیررشته‌ای را تبدیل نمی‌کند.',
		),
		'en' => array(
			'scenario'      => 'Normalize mixed Persian and Arabic-Indic digits in an order-reference text field.',
			'prerequisites' => 'Enable Digit Normalization; its setting is per form.',
			'steps'         => array(
				'Gravity Forms → Forms → Edit: add a Single Line Text field labeled Order reference.',
				'Settings → Form Settings → Form Options: set Persian digit normalization to Enabled; Save.',
				'Submit sample text in Preview and inspect Entries.',
			),
			'samples'       => array(
				array(
					'label' => 'Input',
					'value' => 'Order ۱۲٣۴۵',
				),
				array(
					'label' => 'Stored Entry value',
					'value' => 'Order 12345',
				),
			),
			'expected'      => 'The server-side gform_save_field_value hook converts Persian/Arabic digit characters in strings into ASCII before storage.',
			'verification'  => 'Open Gravity Forms → Entries and check Order 12345.',
			'notes'         => 'Not National ID typing normalization; does not rewrite old Entries or promise normalization of all non-string field types.',
		),
	),
	'iranian-currency'    => array(
		'fa' => array(
			'scenario'      => 'نمایش قیمت ثابت خدمات به ریال یا تومان، بدون درگاه پرداخت.',
			'prerequisites' => 'ماژول ریال/تومان فعال باشد؛ دسترسی تنظیمات عمومی Gravity Forms لازم است.',
			'steps'         => array(
				'Gravity Forms ← Settings ← Currency؛ Iranian Rial (IRR) را انتخاب و Save کنید.',
				'Forms ← Edit؛ یک Product با قیمت ثابت 125000 اضافه و Save کنید.',
				'Preview را برای قیمت بدون اعشار بررسی کنید؛ برای IRT تنظیم سراسری Currency را تغییر و دوباره Preview کنید.',
			),
			'samples'       => array(
				array(
					'label' => 'قیمت و واحد آزمایشی',
					'value' => 'Currency: IRR
Product price: 125000',
				),
				array(
					'label' => 'قالب مورد انتظار',
					'value' => '125,000 ریال
IRT display: 125,000 تومان',
				),
			),
			'expected'      => 'تعریف‌های IRR و IRT با علامت سمت راست، هزارگان و decimals=0 نمایش داده می‌شوند؛ هیچ تبدیل ریال به تومان انجام نمی‌شود.',
			'verification'  => 'Gravity Forms ← Settings: ارز انتخاب‌شده و Preview فیلد Product: بدون اعشار را بررسی کنید.',
			'notes'         => 'Currency در Gravity Forms سراسری است، نه مختص هر فرم؛ تغییر آن ممکن است فرم‌های دیگر را متأثر کند. پرداخت و تبدیل نرخ خارج از محدوده است.',
		),
		'en' => array(
			'scenario'      => 'Show a fixed Product price in Rial or Toman without payment processing.',
			'prerequisites' => 'Enable Iranian Rial / Toman; access global Gravity Forms settings.',
			'steps'         => array(
				'Gravity Forms → Settings → Currency: select Iranian Rial (IRR) and Save.',
				'Forms → Edit: add a Product with a fixed price 125000; Save.',
				'Preview no-decimal price; optionally choose IRT in the global Currency setting and preview again.',
			),
			'samples'       => array(
				array(
					'label' => 'Sample pricing',
					'value' => 'Currency: IRR
Product price: 125000',
				),
				array(
					'label' => 'Expected display shape',
					'value' => '125,000 ریال
IRT display: 125,000 تومان',
				),
			),
			'expected'      => 'IRR and IRT use right-side symbols, thousands grouping and decimals=0; amounts are not automatically converted.',
			'verification'  => 'Check chosen Currency in Gravity Forms Settings and no decimal fraction in Product preview.',
			'notes'         => 'Currency selection is GLOBAL to Gravity Forms, not per form. Changes can affect other forms; no gateway or exchange conversion is added.',
		),
	),
	'structured-scanner'  => array(
		'fa' => array(
			'scenario'      => 'خواندن دو خط متن اسکنر و پرکردن خودکار شناسه و شعبه در فیلدهای عادی.',
			'prerequisites' => 'ماژول Structured Scanner فعال باشد؛ Scanner Profiles بخش مدیریت مستقل است.',
			'steps'         => array(
				'Persian Gravity ← Scanner Profiles ← Create Scanner Profile؛ ID را ticket_two_lines، Label را Ticket two lines، Enabled و Parser را segments_v1 / newline با Trim و Normalize digits بگذارید؛ خروجی‌های مرتب reference و branch هر دو Required باشند؛ Save کنید.',
				'Gravity Forms ← Forms ← Edit؛ دو Single Line Text به عنوان مقصد با IDهای فرضی 1 و 2 بسازید.',
				'Structured Scanner را از Advanced Fields اضافه و Scanner profile را ticket_two_lines انتخاب کنید؛ در Output mapping، reference→1 و branch→2؛ Save.',
				'در Preview متن دوخطی را Paste کنید؛ سپس ورودی ناقص را جداگانه آزمون و Entry را بررسی کنید.',
			),
			'samples'       => array(
				array(
					'label' => 'تنظیم پروفایل و Mapping',
					'value' => 'Profile: ticket_two_lines
Parser: segments_v1 / newline
Outputs: reference [required], branch [required]
Mapping: reference -> Text ID 1; branch -> Text ID 2',
				),
				array(
					'label' => 'اسکن معتبر',
					'value' => 'REQ-۱۲۳۴
شیراز',
				),
				array(
					'label' => 'مقدار مقصدها',
					'value' => 'reference=REQ-1234
branch=شیراز',
				),
				array(
					'label' => 'اسکن ناقص',
					'value' => 'REQ-۱۲۳۴',
				),
			),
			'expected'      => 'اسکن معتبر هر دو فیلد مقصد را پر می‌کند؛ فقط مقادیر مقصد بعد از Submit به Entry می‌روند، نه raw Scanner. خطای Parse/Mapping تغییر جزئی در مقصدها نمی‌دهد.',
			'verification'  => 'پیش از Submit مقدار دو مقصد را ببینید؛ Entry ثبت‌شده را بررسی کنید؛ بعد از اسکن معتبر ورودی ناقص را بزنید و ثابت‌ماندن مقصدها را ببینید.',
			'notes'         => 'فقط Single Line Text/Hidden مقصد هستند؛ IDهای 1 و 2 باید با ID واقعی تعویض شوند. ساختار خروجی پروفایل پس از ایجاد immutable است؛ برای قرارداد جدید Profile ID جدید بسازید. این قابلیت OCR/دوربین یا اعتبارسنجی بانکی نیست.',
		),
		'en' => array(
			'scenario'      => 'Parse a two-line scanner payload into normal Reference and Branch fields.',
			'prerequisites' => 'Enable Structured Scanner; Scanner Profiles remains an independent admin screen.',
			'steps'         => array(
				'Persian Gravity → Scanner Profiles → Create Scanner Profile: ID ticket_two_lines, label Ticket two lines, Enabled, segments_v1/newline, Trim and Normalize digits; ordered required outputs reference and branch; Save.',
				'Gravity Forms → Forms → Edit: create two Single Line Text destination fields (example IDs 1 and 2).',
				'Add Structured Scanner from Advanced Fields; choose ticket_two_lines under Scanner profile; set Output mapping reference→1, branch→2; Save.',
				'Paste valid two-line input into Preview, then try the incomplete input and inspect the saved Entry.',
			),
			'samples'       => array(
				array(
					'label' => 'Profile and mapping',
					'value' => 'Profile: ticket_two_lines
Parser: segments_v1 / newline
Outputs: reference [required], branch [required]
Mapping: reference -> Text ID 1; branch -> Text ID 2',
				),
				array(
					'label' => 'Valid payload',
					'value' => 'REQ-۱۲۳۴
Shiraz',
				),
				array(
					'label' => 'Filled destinations',
					'value' => 'reference=REQ-1234
branch=Shiraz',
				),
				array(
					'label' => 'Invalid payload',
					'value' => 'REQ-۱۲۳۴',
				),
			),
			'expected'      => 'Valid scans fill both ordinary fields and their values are saved on submission, not the raw Scanner value. Invalid parse/mappings never partially update destinations.',
			'verification'  => 'Inspect both targets before Submit and in Entries; try one-line input after success and confirm both existing values remain unchanged.',
			'notes'         => 'Only Single Line Text/Hidden targets; replace IDs with real form field IDs. Profiles have immutable output contracts; use a new profile ID for changes. Not OCR/camera capture or bank validation.',
		),
	),
);
