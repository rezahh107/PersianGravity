#!/usr/bin/env python3
import ast
import json
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
RECORD_ROOT = ROOT / "languages/providers/gravityforms/source/records"
AGGREGATE = ROOT / "languages/providers/gravityforms/source/fa_IR.po"
SHORTCODE = RECORD_ROOT / "frontend-shortcode-fa_IR.po"
BLOCK = RECORD_ROOT / "frontend-block-fa_IR.po"

RECORDS = {
    "admin-builder": RECORD_ROOT / "admin-builder-fa_IR.po",
    "entry-management": RECORD_ROOT / "entry-management-fa_IR.po",
    "settings-integrations": RECORD_ROOT / "settings-integrations-fa_IR.po",
    "developer-diagnostics": RECORD_ROOT / "developer-diagnostics-fa_IR.po",
    "frontend-block": BLOCK,
}

# Only confirmed semantic defects: meaning inversions, wrong actions/statuses,
# materially unrelated UI instructions, or clearly corrupted Persian text.
PRIMARY = {
    "admin-builder": {
        (None, "Enable Submit Button Conditional Logic"): "فعال‌سازی منطق شرطی دکمهٔ ارسال",
        (None, "Allow field to be populated dynamically"): "اجازه دهید فیلد به‌صورت پویا مقداردهی شود",
        (None, "Default Province"): "استان پیش‌فرض",
        (None, "Select Exact Number"): "انتخاب تعداد دقیق",
        (None, "Product Field Mapping"): "نگاشت فیلد محصول",
        (None, "Job Type"): "نوع شغل",
        (None, "Descending"): "نزولی",
        (None, "Ascending"): "صعودی",
        (None, "Selections"): "انتخاب‌ها",
        (None, "Default State"): "ایالت پیش‌فرض",
        (None, "Multiple Files"): "چند فایل",
        (None, "Last page options"): "گزینه‌های صفحهٔ آخر",
        (None, "Manage last page options"): "مدیریت گزینه‌های صفحهٔ آخر",
        (None, "Maximum"): "حداکثر",
        (None, "Select One"): "انتخاب یکی",
        (None, "End of the last row"): "پایان آخرین ردیف",
        (None, "Enable Autocomplete"): "فعال‌سازی تکمیل خودکار",
        (None, "Option Label"): "برچسب گزینه",
        (None, "Submit Input Type"): "نوع ورودی دکمهٔ ارسال",
        (None, "Input Mask"): "الگوی ورودی",
        (None, "Do not round"): "گرد نکن",
        (None, "Persian"): "فارسی",
        (None, "Minimum"): "حداقل",
        (None, "Not employed but looking for work"): "شاغل نیستم و دنبال کار می‌گردم",
        (None, "Horizontal"): "افقی",
        (None, "Filter the entries by adding conditions."): "با افزودن شرط‌ها، ورودی‌ها را فیلتر کنید.",
        (None, "Select Multiple"): "انتخاب چند مورد",
        (None, "Hide Province Field"): "پنهان‌کردن فیلد استان",
        (None, "Submit Button Text"): "متن دکمهٔ ارسال",
        (None, "Gradient: Blues"): "گرادیان: آبی‌ها",
        (None, "Laothian"): "لائوسی",
        (None, "Serbian"): "صربی",
        (None, "Male, Female, Non-binary, Agender, My gender is not listed, Prefer not to answer"): "مرد، زن، غیردودویی، بی‌جنسیت، جنسیت من در فهرست نیست، ترجیح می‌دهم پاسخ ندهم",
        (None, "Select the default validation message placement.  Validation messages can be placed above the field inputs or below the field inputs."): "محل پیش‌فرض پیام اعتبارسنجی را انتخاب کنید. پیام‌های اعتبارسنجی می‌توانند بالای ورودی‌های فیلد یا پایین آن‌ها قرار گیرند.",
        (None, "Hide State/Province/Region"): "پنهان‌کردن ایالت/استان/منطقه",
        (None, "Check this box to prevent the State/Province/Region from being displayed in the form."): "برای جلوگیری از نمایش ایالت/استان/منطقه در فرم، این گزینه را علامت بزنید.",
        (None, "Validation Summary"): "خلاصهٔ اعتبارسنجی",
        (None, "Custom Validation Message"): "پیام اعتبارسنجی سفارشی",
        (None, "Schedule Form"): "زمان‌بندی فرم",
        (None, "Date Picker Icon"): "آیکون انتخابگر تاریخ",
        (None, "Placeholder text is not supported when using the Rich Text Editor."): "هنگام استفاده از ویرایشگر متن غنی، متن نگه‌دارنده پشتیبانی نمی‌شود.",
        (None, "Limit Number of Entries"): "محدود کردن تعداد ورودی‌ها",
        (None, "Create rules to dynamically display or hide this page based on values from another field."): "برای نمایش یا پنهان‌کردن پویای این صفحه بر اساس مقادیر فیلدی دیگر، قانون ایجاد کنید.",
        (None, "Submit Button Image URL"): "URL تصویر دکمهٔ ارسال",
        (None, "Insert Choices"): "درج گزینه‌ها",
        (None, "Czech"): "چکی",
        (None, "Select the province you would like to be selected by default when the form gets displayed."): "استانی را که می‌خواهید هنگام نمایش فرم به‌طور پیش‌فرض انتخاب شود، انتخاب کنید.",
        (None, "Date Input Type"): "نوع ورودی تاریخ",
    },
    "entry-management": {
        (None, "Resend"): "ارسال مجدد",
        (None, "Take Over"): "به دست گرفتن کنترل",
        (None, "This form does not have any entries in the trash matching the search criteria."): "این فرم هیچ ورودی در زباله‌دان مطابق با معیارهای جستجو ندارد.",
        (None, "Display Mode"): "حالت نمایش",
        (None, "%s moved to Trash."): "%s به زباله‌دان منتقل شدند.",
        (None, "Resend Notifications"): "ارسال مجدد اعلان‌ها",
        (None, "%s has taken over and is currently editing."): "%s کنترل را به دست گرفته و در حال ویرایش است.",
        (None, "Resending..."): "در حال ارسال مجدد...",
    },
    "developer-diagnostics": {
        (None, "The database is currently being upgraded to version %s. %s"): "پایگاه داده در حال ارتقا به نسخهٔ %s است. %s",
        (None, "Database Server"): "سرور پایگاه داده",
        (None, "Site Locale"): "زبان سایت",
        (None, "User (ID: %d) Locale"): "زبان کاربر (ID: %d)",
        (None, "WordPress (Local) Timezone"): "منطقهٔ زمانی محلی WordPress",
        (None, "Database Management System"): "سامانهٔ مدیریت پایگاه داده",
        (None, "Parent"): "والد",
    },
    "settings-integrations": {
        (None, "Not implemented"): "پیاده‌سازی نشده",
        (None, "Click to upload"): "برای بارگذاری کلیک کنید",
        (None, "Authorization has been voided. Transaction Id: %s"): "مجوز باطل شد. شماره تراکنش: %s",
        (None, "The Form ID for the entry."): "شناسهٔ فرم برای ورودی.",
        (None, "Enable Data Collection"): "فعال‌سازی گردآوری داده",
        (None, "Invisible"): "نامرئی",
        (None, "API Keys"): "کلیدهای API",
        (None, "Consumer Key"): "کلید مصرف‌کننده",
        (None, "Unable to render form settings."): "نمایش تنظیمات فرم ممکن نیست.",
        (None, "A valid license key is required for access to automatic plugin upgrades and product support."): "برای دسترسی به ارتقای خودکار افزونه و پشتیبانی محصول، کلید مجوز معتبر لازم است.",
        (None, "swatch"): "نمونهٔ رنگ",
        (None, "Products and Services"): "محصولات و خدمات",
        (None, "You must select a valid minute."): "باید یک دقیقهٔ معتبر انتخاب کنید.",
        (None, "You must select either am or pm."): "باید یکی از گزینه‌های قبل‌ازظهر یا بعدازظهر را انتخاب کنید.",
        (None, "Whether the entry has been read."): "آیا ورودی خوانده شده است.",
        (None, "Error retrieving notes."): "خطا در دریافت یادداشت‌ها.",
        (None, "Address (State / Province)"): "آدرس (ایالت / استان)",
        (None, "Select a Value"): "یک مقدار انتخاب کنید",
        (None, "Purchase Date"): "تاریخ خرید",
        (None, "Gravity Forms has been successfully uninstalled. It can be re-activated from the %splugins page%s."): "Gravity Forms با موفقیت حذف شد. می‌توان دوباره آن را از %sصفحهٔ افزونه‌ها%s فعال کرد.",
        (None, "Post Payment Actions"): "اقدامات پس از پرداخت",
        (None, "Subscription"): "اشتراک",
        (None, "Select which actions should only occur after payment has been received."): "انتخاب کنید کدام اقدامات فقط پس از دریافت پرداخت انجام شوند.",
        ("regarding a payment method", "Any"): "هرکدام",
    },
}

ALL = {}
for record, corrections in PRIMARY.items():
    for key, value in corrections.items():
        if key in ALL and ALL[key] != value:
            raise SystemExit(f"conflicting repair map for {key!r}")
        ALL[key] = value


def po_unquote(value: str) -> str:
    return ast.literal_eval(value)


def field_value(block: str, field: str):
    lines = block.splitlines()
    prefix = field + " "
    for index, line in enumerate(lines):
        if not line.startswith(prefix):
            continue
        value = po_unquote(line[len(prefix):])
        index += 1
        while index < len(lines) and lines[index].startswith('"'):
            value += po_unquote(lines[index])
            index += 1
        return value
    return None


def identity(block: str):
    msgid = field_value(block, "msgid")
    if msgid is None or msgid == "":
        return None
    return (field_value(block, "msgctxt"), msgid)


def translation(block: str):
    lines = block.splitlines()
    for line in lines:
        if line.startswith("msgstr "):
            return po_unquote(line[len("msgstr "):])
    return None


def replace_translation(block: str, value: str) -> str:
    lines = block.splitlines()
    for index, line in enumerate(lines):
        if line.startswith("msgstr "):
            lines[index] = "msgstr " + json.dumps(value, ensure_ascii=False)
            return "\n".join(lines)
    raise RuntimeError("singular msgstr not found")


def load_blocks(path: Path):
    text = path.read_text(encoding="utf-8")
    trailing = text.endswith("\n")
    return text.split("\n\n"), trailing


def write_blocks(path: Path, blocks, trailing: bool):
    text = "\n\n".join(blocks)
    if trailing and not text.endswith("\n"):
        text += "\n"
    path.write_text(text, encoding="utf-8")


def index_blocks(path: Path):
    blocks, _ = load_blocks(path)
    result = {}
    for block in blocks:
        key = identity(block)
        if key is not None:
            if key in result:
                raise RuntimeError(f"duplicate PO identity in {path}: {key!r}")
            result[key] = block
    return result


def patch_po(path: Path, corrections, required=None):
    blocks, trailing = load_blocks(path)
    seen = set()
    out = []
    for block in blocks:
        key = identity(block)
        if key in corrections:
            out.append(replace_translation(block, corrections[key]))
            seen.add(key)
        else:
            out.append(block)
    if required is not None:
        missing = set(required) - seen
        if missing:
            raise RuntimeError(f"missing correction identities in {path}: {sorted(missing)!r}")
    write_blocks(path, out, trailing)
    return seen


def assert_translation(path: Path, key, expected):
    block = index_blocks(path).get(key)
    if block is None:
        raise RuntimeError(f"missing expected identity in {path}: {key!r}")
    actual = translation(block)
    if actual != expected:
        raise RuntimeError(f"unexpected translation in {path}: {key!r}: {actual!r}")


def php_array_string(value):
    return value.replace("\\", "\\\\").replace("'", "\\'")


def generate_semantic_test():
    rows = []
    for record, corrections in PRIMARY.items():
        rel = f"languages/providers/gravityforms/source/records/{record}-fa_IR.po"
        for (context, msgid), expected in corrections.items():
            ctx = "null" if context is None else "'" + php_array_string(context) + "'"
            rows.append(
                "\t\t\tarray( '" + php_array_string(rel) + "', " + ctx + ", '" +
                php_array_string(msgid) + "', '" + php_array_string(expected) + "' ),"
            )
    test = """<?php

use PHPUnit\\Framework\\TestCase;

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

final class GravityFormsSemanticAdmissionTest extends TestCase {
    public function test_confirmed_semantic_repairs_propagate_from_records_to_generated_provider(): void {
        $root = dirname( __DIR__ );
        $aggregate = ( new Gettext\\Loader\\StrictPoLoader() )->loadFile( $root . '/languages/providers/gravityforms/source/fa_IR.po' );
        $generated = require $root . '/languages/providers/gravityforms/gravityforms-fa_IR.l10n.php';
        $corrections = array(
""" + "\n".join(rows) + """
        );

        foreach ( $corrections as $correction ) {
            list( $path, $context, $msgid, $expected ) = $correction;
            $record = ( new Gettext\\Loader\\StrictPoLoader() )->loadFile( $root . '/' . $path );
            $this->assertSame( $expected, $this->translation( $record, $msgid, $context ), $path . ': ' . $msgid );
            $this->assertSame( $expected, $this->translation( $aggregate, $msgid, $context ), 'aggregate: ' . $msgid );
            $runtime_key = null === $context ? $msgid : $context . "\\x04" . $msgid;
            $this->assertArrayHasKey( $runtime_key, $generated['messages'], 'generated provider: ' . $msgid );
            $this->assertSame( $expected, $generated['messages'][ $runtime_key ], 'generated provider: ' . $msgid );
        }
    }

    public function test_previously_locked_block_repairs_remain_exact(): void {
        $root = dirname( __DIR__ );
        $block = ( new Gettext\\Loader\\StrictPoLoader() )->loadFile(
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
"""
    (ROOT / "tests/GravityFormsSemanticAdmissionTest.php").write_text(test, encoding="utf-8")


def patch_tokenizer_and_tests():
    path = ROOT / "tools/i18n/content-admission.php"
    text = path.read_text(encoding="utf-8")
    old = "\t\t'printf' => '/%(?:\\d+\\$)?[-+0#\\']*(?:\\d+|\\*)?(?:\\.(?:\\d+|\\*))?[bcdeEfFgGosuxX]/',"
    new = old + "\n\t\t'printf_space' => '/%(?:\\d+\\$)?[-+0#\\']* +[-+0#\\']*(?:\\d+|\\*)?(?:\\.(?:\\d+|\\*))?[bcdeEfFgGosuxX](?![A-Za-z])/',"
    if old not in text or "'printf_space'" in text:
        raise RuntimeError("printf tokenizer source is not at the expected starting state")
    path.write_text(text.replace(old, new, 1), encoding="utf-8")

    token_test = r'''<?php

use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__ ) . '/vendor/autoload.php';
require_once dirname( __DIR__ ) . '/tools/i18n/content-admission.php';

final class ContentAdmissionTokenSemanticsTest extends TestCase {
    public function test_literal_percent_prose_is_not_treated_as_printf(): void {
        $this->assertSame( array(), pgr_content_tokens( 'Do not close this page until the upgrade is 100% complete.' ) );
        $this->assertSame( array(), pgr_content_tokens( 'Save 20% off today.' ) );
        $this->assertSame( array(), pgr_content_tokens( 'Apply a 5% discount.' ) );
        $this->assertSame( array( '%s' ), pgr_content_tokens( '%s%% complete.' ) );
        $this->assertSame( array( '%02d', '%1$s' ), pgr_content_tokens( '%1$s / %02d' ) );
    }

    public function test_valid_printf_space_flag_formats_are_protected(): void {
        $this->assertSame(
            array( '% 5.2f', '% 5d', '% d', '%1$ d' ),
            pgr_content_tokens( '% d / % 5d / % 5.2f / %1$ d' )
        );
    }

    public function test_url_sentence_punctuation_is_not_part_of_the_protected_token(): void {
        $this->assertSame( array( 'https://' ), pgr_content_tokens( 'See https://).' ) );
        $this->assertSame( array( 'https://' ), pgr_content_tokens( 'ببینید https://)،' ) );
        $this->assertSame( array( 'https://example.test/path_(value)' ), pgr_content_tokens( 'See https://example.test/path_(value).' ) );
    }

    public function test_named_entities_can_be_localized_but_numeric_entities_remain_protected(): void {
        $this->assertSame( array(), pgr_content_tokens( 'Products &amp; Services Settings' ) );
        $this->assertSame( array( '&#160;', '&#x202F;' ), pgr_content_tokens( 'Keep &#160; and &#x202F;' ) );
        $this->assertSame( array( '</a>', '<a href="?x=1&amp;y=2">' ), pgr_content_tokens( '<a href="?x=1&amp;y=2">Link</a>' ) );
    }

    public function test_printf_space_placeholder_loss_fails_sparse_admission_validation(): void {
        $path = tempnam( sys_get_temp_dir(), 'pgr-space-printf-' );
        $po = <<<'PO'
msgid ""
msgstr ""
"Language: fa_IR\n"
"Plural-Forms: nplurals=2; plural=(n > 1);\n"
"X-Domain: gravityforms\n"

msgid "% 5d items"
msgstr "مورد"
PO;
        file_put_contents( $path, $po . "\n" );
        $this->expectException( RuntimeException::class );
        $this->expectExceptionMessage( 'Placeholder/markup/literal drift in admitted entry' );
        try {
            pgr_content_load_sparse_po( $path, 'gravityforms', 'fa_IR' );
        } finally {
            unlink( $path );
        }
    }
}
'''
    (ROOT / "tests/ContentAdmissionTokenSemanticsTest.php").write_text(token_test, encoding="utf-8")


def apply():
    # Every primary finding must exist before any file is changed.
    for record, corrections in PRIMARY.items():
        idx = index_blocks(RECORDS[record])
        missing = set(corrections) - set(idx)
        if missing:
            raise RuntimeError(f"semantic QA target drift in {record}: {sorted(missing)!r}")

    assert_translation(BLOCK, (None, "Colors"), "رنگ‌ها")
    assert_translation(BLOCK, (None, "Accent"), "رنگ تاکیدی")

    shortcode_keys = set(index_blocks(SHORTCODE))
    overlap = shortcode_keys.intersection(ALL)
    if overlap:
        raise RuntimeError(f"semantic repair would touch locked shortcode identities: {sorted(overlap)!r}")

    changed_records = []
    for record, path in RECORDS.items():
        present = set(index_blocks(path)).intersection(ALL)
        if present:
            patch_po(path, ALL, required=present)
            changed_records.append(record)

    # The aggregate is a deterministic identity union; identity membership is unchanged,
    # so synchronize only translations for the exact corrected identities. Full admission
    # validation later proves byte/hash binding and exact union equality.
    aggregate_keys = set(index_blocks(AGGREGATE))
    missing_aggregate = set(ALL) - aggregate_keys
    if missing_aggregate:
        raise RuntimeError(f"aggregate missing repaired identities: {sorted(missing_aggregate)!r}")
    patch_po(AGGREGATE, ALL, required=set(ALL))

    patch_tokenizer_and_tests()
    generate_semantic_test()

    print(json.dumps({
        "records_inspected": list(RECORDS),
        "primary_correction_count": sum(len(v) for v in PRIMARY.values()),
        "changed_records": changed_records,
    }, ensure_ascii=False, indent=2))


def detect_indent(text: str) -> int:
    for line in text.splitlines()[1:]:
        stripped = line.lstrip(' ')
        if stripped and stripped != line:
            return len(line) - len(stripped)
    return 2


def write_json(path: Path, data):
    old = path.read_text(encoding="utf-8")
    indent = detect_indent(old)
    path.write_text(json.dumps(data, ensure_ascii=False, indent=indent) + "\n", encoding="utf-8")


def sync(fp_path: Path):
    fp = json.loads(fp_path.read_text(encoding="utf-8"))
    content_path = ROOT / "tools/i18n/admission/content.json"
    content = json.loads(content_path.read_text(encoding="utf-8"))
    by_path = fp["records"]
    for record in content["admissions"]:
        if record.get("product") != "gravityforms":
            continue
        path = record["provider_source_path"]
        values = by_path[path]
        record["provider_source_sha256"] = values["provider_source_sha256"]
        record["admitted_translation_content_sha256"] = values["admitted_translation_content_sha256"]
    write_json(content_path, content)

    provenance_path = ROOT / "languages/providers/gravityforms/source/provenance.json"
    provenance = json.loads(provenance_path.read_text(encoding="utf-8"))
    for record in provenance["content_admissions"]:
        path = record["provider_source_path"]
        values = by_path[path]
        record["provider_source_sha256"] = values["provider_source_sha256"]
        record["admitted_translation_content_sha256"] = values["admitted_translation_content_sha256"]
    aggregate = fp["aggregate"]
    if aggregate["admitted_message_count"] != 1759 or aggregate["admitted_keyset_sha256"] != "e15f8e80cc711ea7b862be66d3a8a5827f85e0312caae391fcdecbf1a34fdec7":
        raise RuntimeError("semantic repair changed aggregate identity boundary")
    provenance["content_aggregate"]["admitted_translation_content_sha256"] = aggregate["admitted_translation_content_sha256"]
    provenance["content_aggregate"]["provider_source_sha256"] = aggregate["provider_source_sha256"]
    provenance["translation_review"] = "WU001_CLASSIFIED_AUTHORITY_WITH_PR15_SEVEN_SEMANTIC_CORRECTIONS_AND_PR19_SEMANTIC_QA_REPAIRS"
    write_json(provenance_path, provenance)


if __name__ == "__main__":
    if len(sys.argv) < 2:
        raise SystemExit("usage: apply|sync <fingerprints.json>")
    if sys.argv[1] == "apply":
        apply()
    elif sys.argv[1] == "sync" and len(sys.argv) == 3:
        sync(Path(sys.argv[2]))
    else:
        raise SystemExit("invalid command")
