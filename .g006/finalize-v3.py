import json, hashlib, re
from pathlib import Path
from importlib.machinery import SourceFileLoader

root = Path.cwd()
po = SourceFileLoader('gf_po', str(root / '.g006/gf_po.py')).load_module()
STALE = '2b64a903d2065499fa0248c6199db150bb7d1a43025d2908e8a66e4af9d4ab92'
POT = Path('/tmp/gf-pot-path').read_text().strip()
LEGACY = Path('/tmp/legacy-gf.po')
new = json.loads((root / '.g006/gf-new-translations.json').read_text())
corrections = json.loads((root / '.g006/gf-corrections.json').read_text())
corrected = {x.strip() for x in (root / '.g006/gf-second-pass-corrected.txt').read_text().splitlines() if x.strip()}


def hash_lines(lines):
    rows = sorted(lines)
    return hashlib.sha256(('\n'.join(rows) + ('\n' if rows else '')).encode()).hexdigest()


def source_hash(ids):
    return hashlib.sha256('\n'.join(sorted(ids)).encode()).hexdigest()


def trans_hash(vals):
    return hashlib.sha256('\x00'.join(vals).encode()).hexdigest()


def q(s):
    return json.dumps(s, ensure_ascii=False)


def po_entry(e, vals):
    lines = []
    refs = []
    for r in e.get('refs', []):
        if r not in refs:
            refs.append(r)
    if refs:
        lines.append('#: ' + ' '.join(refs))
    if e.get('ctx') is not None:
        lines.append('msgctxt ' + q(e['ctx']))
    lines.append('msgid ' + q(e['id']))
    if e.get('plural') is not None:
        assert len(vals) == 2 and vals[0] and vals[1]
        lines.append('msgid_plural ' + q(e['plural']))
        lines.append('msgstr[0] ' + q(vals[0]))
        lines.append('msgstr[1] ' + q(vals[1]))
    else:
        assert len(vals) == 1 and vals[0]
        lines.append('msgstr ' + q(vals[0]))
    return '\n'.join(lines) + '\n\n'

pot_entries = [e for e in po.parse_po(POT) if e['id'] not in ('', None) and not e['obsolete']]
assert len(pot_entries) == 4208
stale = [e for e in pot_entries if po.ihash(e) == STALE]
assert len(stale) == 1 and stale[0]['id'] == "I'm a Button!"
canonical_entries = [e for e in pot_entries if po.ihash(e) != STALE]
canonical = {po.ihash(e): dict(e, identity=po.ihash(e)) for e in canonical_entries}
canon_ids = sorted(canonical)
assert len(canon_ids) == 4207
assert source_hash(canon_ids) == '1b92edc87f2d152cb98a026dde815ab93e3e8303eef2954e95b3a816a85801fb'

agg = root / 'languages/providers/gravityforms/source/fa_IR.po'
old_entries = [e for e in po.parse_po(agg) if e['id'] not in ('', None) and not e['obsolete']]
oldmap = {po.ihash(e): e for e in old_entries}
assert len(oldmap) == 1759 and set(oldmap) <= set(canonical)
baseline_keyset = hash_lines(oldmap.keys())
baseline_trans = hash_lines([po.trans_row(e) for e in old_entries])
assert baseline_keyset == 'e15f8e80cc711ea7b862be66d3a8a5827f85e0312caae391fcdecbf1a34fdec7'
assert baseline_trans == 'a0b631dfcb88eb497be26086799a8816b7f85f99fd42c62afe6488ee508f9c27'

residual_ids = sorted(set(canonical) - set(oldmap))
assert len(residual_ids) == 2448 and STALE not in residual_ids
assert len(new) == 922 and len(corrections) == 741 and set(new).isdisjoint(corrections)
assert set(new) <= set(residual_ids) and set(corrections) <= set(residual_ids)
assert len(corrected) == 1604 and corrected <= set(residual_ids)

legacy_entries = [e for e in po.parse_po(LEGACY) if e['id'] not in ('', None) and not e['obsolete'] and 'fuzzy' not in e['flags']]
legacy = {po.ihash(e): po.trans_values(e) for e in legacy_entries if all(po.trans_values(e))}
final = {}
origins = {'new': 0, 'corrected_legacy': 0, 'unchanged_legacy': 0}
for identity in residual_ids:
    e = canonical[identity]
    expected_forms = 2 if e.get('plural') is not None else 1
    if identity in new:
        vals = new[identity]
        origins['new'] += 1
    elif identity in corrections:
        vals = corrections[identity]
        origins['corrected_legacy'] += 1
    else:
        assert identity in legacy, 'Missing reviewed legacy fallback for ' + identity
        vals = legacy[identity]
        origins['unchanged_legacy'] += 1
    assert isinstance(vals, list) and len(vals) == expected_forms and all(isinstance(v, str) and v for v in vals)
    final[identity] = vals
assert origins == {'new': 922, 'corrected_legacy': 741, 'unchanged_legacy': 785}
assert len(final) == 2448

header = (
    '# Gravity Forms 3.1.1.1 Persian catalog — G-006 reviewed product remainder.\n'
    '# Exactly the 2448 canonical source-backed identities outside the previously accepted six-surface union.\n'
    'msgid ""\nmsgstr ""\n'
    '"Project-Id-Version: Gravity Forms 3.1.1.1\\n"\n'
    '"Language: fa_IR\\n"\n'
    '"MIME-Version: 1.0\\n"\n'
    '"Content-Type: text/plain; charset=UTF-8\\n"\n'
    '"Content-Transfer-Encoding: 8bit\\n"\n'
    '"Plural-Forms: nplurals=2; plural=(n > 1);\\n"\n'
    '"X-Domain: gravityforms\\n"\n\n'
)
rempo = root / 'languages/providers/gravityforms/source/records/remainder-fa_IR.po'
rempo.parent.mkdir(parents=True, exist_ok=True)
rempo.write_text(header + ''.join(po_entry(canonical[i], final[i]) for i in residual_ids), encoding='utf-8')

rem_keyset = hash_lines(residual_ids)
rem_trans = hash_lines([i + '\x1f' + '\x00'.join(final[i]) for i in residual_ids])
assert set(oldmap).isdisjoint(residual_ids) and set(oldmap) | set(residual_ids) == set(canon_ids)

oldtext = agg.read_text(encoding='utf-8')
old_header = '# Gravity Forms 3.1.1.1 Persian sparse admission aggregate — six classified surfaces.\n# Exact deterministic union of Content Admission v2 records; do not broaden by hand.\n'
new_header = '# Gravity Forms 3.1.1.1 Persian full accepted source-backed catalog.\n# Exact deterministic union of the six historical surfaces plus the G-006 product remainder.\n'
assert old_header in oldtext
agg.write_text(oldtext.replace(old_header, new_header, 1).rstrip() + '\n\n' + ''.join(po_entry(canonical[i], final[i]) for i in residual_ids), encoding='utf-8')

printf_re = re.compile(r"(?<!\d)%(?:\d+\$)?[-+0#']*(?:\d+|\*)?(?:\.(?:\d+|\*))?[bcdeEfFgGosuxX]")
markup_re = re.compile(r'</?[A-Za-z][^>]*>')
url_re = re.compile(r'https?://[^\s<>"\']+')
brace_re = re.compile(r'\{[^{}\r\n]+\}')
entity_re = re.compile(r'&#(?:\d+|x[0-9A-Fa-f]+);')
protected_re = re.compile(r'Gravity Forms|Gravity Flow|GravityView|WordPress|\bAPI\b|\bURL\b|\bPHP\b|\bCSS\b|\bHTML\b|\bJSON\b|\bREST\b|\bAJAX\b|JavaScript|reCAPTCHA|OAuth1|SMTP|Mailgun|GravityFlow\.io|display_all|allow_anonymous|page_id|\[gravityflow\]|Client Key|Client Secret|Consumer Key|Form ID|Lead ID')

def risk(e):
    value = e['id'] + '\n' + (e.get('plural') or '')
    flags = []
    if e.get('plural') is not None: flags.append('PLURAL')
    if printf_re.search(value): flags.append('PRINTF')
    if markup_re.search(value): flags.append('MARKUP')
    if url_re.search(value): flags.append('URL')
    if brace_re.search(value): flags.append('TEMPLATE_TOKEN')
    if entity_re.search(value): flags.append('NUMERIC_ENTITY')
    if protected_re.search(value): flags.append('PROTECTED_LITERAL')
    if len(e['id'].strip().encode()) <= 16: flags.append('SHORT_AMBIGUOUS')
    return flags

review_entries = [{'identity': i, 'status': 'ACCEPTED', 'translation_sha256': trans_hash(final[i]), 'risk_flags': risk(canonical[i]), 'second_pass_corrected': i in corrected} for i in residual_ids]
review = {
    'schema_version': 1, 'review_scope': 'ACCEPTED_TRANSLATION_REVIEW',
    'product': 'gravityforms', 'domain': 'gravityforms', 'locale': 'fa_IR', 'target_version': '3.1.1.1',
    'accepted_count': 2448, 'unreviewed_count': 0, 'rejected_count': 0,
    'semantic_review_passes': 2, 'second_pass_corrections': 1604,
    'review_method': 'ENTRY_BY_ENTRY_SEMANTIC_REVIEW_PLUS_SECOND_PASS_RISK_AND_TOKEN_REVIEW',
    'translation_hash_method': 'SHA256_UTF8_NUL_JOINED_MSGSTR', 'entries': review_entries,
}
review_path = root / 'tools/i18n/admission/reviews/gravityforms-remainder.json'
review_path.parent.mkdir(parents=True, exist_ok=True)
review_path.write_text(json.dumps(review, ensure_ascii=False, indent=2) + '\n')

evidence = {
    'schema_version': 1, 'scope': 'PRODUCT_REMAINDER', 'product': 'gravityforms', 'domain': 'gravityforms', 'locale': 'fa_IR', 'target_version': '3.1.1.1',
    'source_package_sha256': '542f56ae0747f3661d1474996527298027db3fb8ed3e6469a6391aaabf61069b',
    'vendor_pot_sha256': 'a4eb120ee9513552004400548c26a568612ace6accdf9fdcd60b8aa4b163bccf',
    'canonical_message_count': 4207, 'canonical_keyset_sha256': '1b92edc87f2d152cb98a026dde815ab93e3e8303eef2954e95b3a816a85801fb',
    'canonical_keyset_hash_method': 'SHA256_UTF8_NEWLINE_JOIN_SORTED_IDENTITY_SHA256_NO_TRAILING_NEWLINE',
    'preexisting_accepted_message_count': 1759, 'preexisting_accepted_keyset_sha256': baseline_keyset,
    'preexisting_accepted_translation_content_sha256': baseline_trans, 'remainder_message_count': 2448,
    'remainder_keyset_sha256': rem_keyset, 'canonical_identities': canon_ids,
    'entries': [{'identity': i, 'msgctxt': canonical[i].get('ctx'), 'msgid': canonical[i]['id'], 'msgid_plural': canonical[i].get('plural'), 'references': list(dict.fromkeys(canonical[i].get('refs', [])))} for i in residual_ids],
}
ev_path = root / 'tools/i18n/admission/remainders/gravityforms.json'
ev_path.parent.mkdir(parents=True, exist_ok=True)
ev_path.write_text(json.dumps(evidence, ensure_ascii=False, indent=2) + '\n')

manifest_path = root / 'tools/i18n/admission/content.json'
manifest = json.loads(manifest_path.read_text())
assert manifest['content_admission_revision'] == 3 and len(manifest['admissions']) == 20
assert not any(r.get('product') == 'gravityforms' and r.get('authority_scope') == 'PRODUCT_REMAINDER' for r in manifest['admissions'])
record = {
    'product': 'gravityforms', 'domain': 'gravityforms', 'locale': 'fa_IR', 'target_version': '3.1.1.1', 'content_state': 'CONTENT_ADMITTED_REMAINDER',
    'reviewed_source_po_sha256': '2c7960c895216da61ff7d6b8f6a248c2ac130fdf7927efc5c3d09c5e58bc79b1', 'reviewed_source_message_count': 4207,
    'source_package_sha256': '542f56ae0747f3661d1474996527298027db3fb8ed3e6469a6391aaabf61069b', 'vendor_pot_sha256': 'a4eb120ee9513552004400548c26a568612ace6accdf9fdcd60b8aa4b163bccf',
    'authority_scope': 'PRODUCT_REMAINDER', 'preexisting_accepted_message_count': 1759,
    'preexisting_accepted_keyset_sha256': baseline_keyset, 'preexisting_accepted_translation_content_sha256': baseline_trans,
    'canonical_message_count': 4207, 'canonical_keyset_sha256': '1b92edc87f2d152cb98a026dde815ab93e3e8303eef2954e95b3a816a85801fb',
    'admitted_message_count': 2448, 'admitted_keyset_sha256': rem_keyset, 'admitted_translation_content_sha256': rem_trans,
    'remainder_evidence_index_path': 'tools/i18n/admission/remainders/gravityforms.json', 'remainder_evidence_index_sha256': hashlib.sha256(ev_path.read_bytes()).hexdigest(),
    'review_index_path': 'tools/i18n/admission/reviews/gravityforms-remainder.json', 'review_index_sha256': hashlib.sha256(review_path.read_bytes()).hexdigest(),
    'provider_source_path': 'languages/providers/gravityforms/source/records/remainder-fa_IR.po', 'provider_source_sha256': hashlib.sha256(rempo.read_bytes()).hexdigest(),
    'native_js_handles_activated': 0, 'js_translation_json_generated': 0,
}
manifest['admissions'].append(record)
manifest_path.write_text(json.dumps(manifest, ensure_ascii=False, indent=2) + '\n')

all_entries = [e for e in po.parse_po(agg) if e['id'] not in ('', None) and not e['obsolete']]
amap = {po.ihash(e): e for e in all_entries}
assert len(amap) == 4207 and set(amap) == set(canon_ids)
for i, e in oldmap.items(): assert po.trans_values(amap[i]) == po.trans_values(e)
agg_key = hash_lines(amap.keys())
agg_trans = hash_lines([po.trans_row(e) for e in all_entries])
agg_sha = hashlib.sha256(agg.read_bytes()).hexdigest()
fields = ['product','domain','locale','target_version','content_state','reviewed_source_po_sha256','reviewed_source_message_count','surface_id','authority_scope','preexisting_accepted_message_count','preexisting_accepted_keyset_sha256','preexisting_accepted_translation_content_sha256','canonical_message_count','canonical_keyset_sha256','admitted_message_count','admitted_keyset_sha256','admitted_surface_path_index_sha256','admitted_translation_content_sha256','surface_evidence_index_path','surface_evidence_index_sha256','remainder_evidence_index_path','remainder_evidence_index_sha256','review_index_path','review_index_sha256','provider_source_path','provider_source_sha256']
def proj(r): return {k: r[k] for k in fields if k in r}
gf_records = [r for r in manifest['admissions'] if r['product'] == 'gravityforms']
gf_records.sort(key=lambda r: '\x1f'.join([r['product'], r['domain'], r['locale'], r['target_version'], r.get('surface_id', r.get('authority_scope', ''))]))
prov_path = root / 'languages/providers/gravityforms/source/provenance.json'
prov = json.loads(prov_path.read_text())
prov['content_admission_revision'] = 3
prov['content_admission_state'] = 'CONTENT_ADMITTED_FULL'
prov['content_admissions'] = [proj(r) for r in gf_records]
prov['content_aggregate'] = {'admitted_message_count': 4207, 'admitted_keyset_sha256': agg_key, 'admitted_translation_content_sha256': agg_trans, 'provider_source_path': 'languages/providers/gravityforms/source/fa_IR.po', 'provider_source_sha256': agg_sha}
prov['catalog_revision'] = 3
prov['translation_review'] = 'G006_GRAVITYFORMS_FULL_SOURCE_CENSUS_REVIEWED_ACCEPTED'
prov_path.write_text(json.dumps(prov, ensure_ascii=False, indent=2) + '\n')

print(json.dumps({'canonical': 4207, 'preexisting': 1759, 'remainder': 2448, 'stale_excluded': True, 'origins': origins, 'second_pass_corrections': 1604, 'baseline_keyset': baseline_keyset, 'baseline_translation': baseline_trans, 'remainder_keyset': rem_keyset, 'remainder_translation': rem_trans, 'remainder_po_sha': record['provider_source_sha256'], 'evidence_sha': record['remainder_evidence_index_sha256'], 'review_sha': record['review_index_sha256'], 'aggregate_sha': agg_sha, 'aggregate_keyset': agg_key, 'aggregate_translation': agg_trans}, ensure_ascii=False, indent=2))
