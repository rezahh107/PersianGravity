#!/usr/bin/env python3
import hashlib, json, pathlib, shutil, subprocess, sys, tempfile

ROOT = pathlib.Path.cwd()
SOURCE_ROOT = pathlib.Path(sys.argv[1]).resolve()
REVIEWED_PO = pathlib.Path(sys.argv[2]).resolve()
EXPECTED_SOURCE_ZIP_SHA = 'af5959fb6bf0cfcb4d07d14b1933cf9ea0a9d0f994b9991f27aed47edbcb9829'
EXPECTED_REVIEWED_SHA = '2f57d0e1878801f30f6f9c1c8d354c68a739e8364cea063cdb7a56a8f81a2fca'
EXPECTED_SOURCE_KEYSET = '3b533294de818bd7e772533512424571c85e5aa7bccb78cfe06b8b6820645c95'
EXPECTED_SOURCE_REFHASH = '4332466a4f585fd66110b7158d0bbfbf1947ef9e4a97bb924e9c0800fbf64c34'
EXPECTED_AUTHORITY_KEYSET = 'f5451db1aad8e945dd50c18bdb703825d4f2a57b36d5feb6a4dba23e65f7d643'
EXPECTED_AUTHORITY_TRANSLATION = '2aad82335f66963db669cd32e675c7286c5c57b233a45e946b92bdfac82a03fb'
AUTHORITY_ARCHIVE_SHA = 'c193a49bc896d0aa9f7e4c4204778dc2c7cd1fbec974237a01292e8dfafd233d'

SURFACES = [
    dict(key='shortcode', surface_id='gravityview::frontend_runtime::shortcode:gravityview', rules=['src/Shortcode/GravityViewShortcode.php'], count=2,
         keyset='061ddb2324a950ef0e64f9c252cec438403031c7a07a5c235dee5e71594729db', pathhash='881e5750f77fbd1fedb4677d2e0775488ee9ac19ee42679e46ab6c045fe2668c', transhash='293073a2b4d1a49b8c5becb59fe1f00f5c83e5b578b8b27de57749792368d13d',
         index='tools/i18n/admission/gravityview-frontend-shortcode-index.json', po='languages/providers/gravityview/source/records/frontend-shortcode-fa_IR.po', preserve=True),
    dict(key='gutenberg-view-block', surface_id='gravityview::frontend_runtime::block:gk-gravityview-blocks/view', rules=['src/PageBuilder/Gutenberg/blocks/view/'], count=1,
         keyset='b8a1fc110661dbfb20cc833ad4fa9344652a9d30c07a67d9dbb7867b303ebda9', pathhash='c62346581df17952f35c606a426a2f828ccc990347f0d364974fdf9473f4eace', transhash='bb7314bc368e16031302a3ca4ccb49a5c0e2e900742549619d4b78ed40c793e6',
         index='tools/i18n/admission/gravityview-gutenberg-view-block-index.json', po='languages/providers/gravityview/source/records/gutenberg-view-block-fa_IR.po'),
    dict(key='admin-builder', surface_id='gravityview::admin_builder::post_type:gravityview', rules=['src/Admin/AdminViews.php','src/Admin/Metaboxes/','src/Admin/Rendering/','src/Settings/ViewSettings.php'], count=340,
         keyset='096a6c4d534f103ab93b7d5ff39d4163d80241eeee87c8418296c74138e04bf6', pathhash='2b5bb2e2147e8893447b6d515435d728cf668f272825a83c6daa5cbf05baea23', transhash='c01a1527fb72acb883bb66947926405602e37d39b5197c4157428d872d1ebfa0',
         index='tools/i18n/admission/gravityview-admin-builder-index.json', po='languages/providers/gravityview/source/records/admin-builder-fa_IR.po'),
    dict(key='foundation-settings', surface_id='gravityview::settings_integrations::foundation_settings:gravityview', rules=['src/Settings/PluginSettings.php'], count=41,
         keyset='ab0c4e24c80a7c4f82847953994a87f344f4d71ab9effce686ca86949ddcaa93', pathhash='e5e5b2230a92eedb9c528cfe4c757ecc11e287f2e6852594fb1188e35a5f9f6b', transhash='4b4bcfdf4926fd9a286a365690e671cce956c2ce5f0b55534d0447e77c5fdb2c',
         index='tools/i18n/admission/gravityview-foundation-settings-index.json', po='languages/providers/gravityview/source/records/foundation-settings-fa_IR.po'),
    dict(key='entry-approval', surface_id='gravityview::entry_management_runtime::gravityforms_entry_list:approval', rules=['src/Entry/Approval/'], count=30,
         keyset='f3c4f0b31e54a7ed5aa394564ca2adc95e73a1e83f534255852b2499826f14fd', pathhash='b65f045623216d99908a615582f163dea443d00d31b8a74f66bf84f388f2609f', transhash='150d51ca872546983882f3da7977e5ad0071172d3ef589a1cffa73ea9ad8aa3b',
         index='tools/i18n/admission/gravityview-entry-approval-index.json', po='languages/providers/gravityview/source/records/entry-approval-fa_IR.po'),
    dict(key='search-widget', surface_id='gravityview::frontend_runtime::widget:gravityview_widget_search', rules=['src/Widget/Types/SearchWidget.php'], count=53,
         keyset='6bdc2d973e5b6d4f20e17de809a8c7f196f69d321f95f3ce73a3ae413ccc794f', pathhash='c42fed0d28ee873ebb3f6c594449b42ceea812a40a8a5550af948c3df2854fae', transhash='77fc0aec83d84be30c10d218655d3d0ce2f69e10924242817b2e569370d3e015',
         index='tools/i18n/admission/gravityview-search-widget-index.json', po='languages/providers/gravityview/source/records/search-widget-fa_IR.po'),
]

def fail(msg):
    raise SystemExit('WU006 FAIL: ' + msg)

def sha256(path):
    h=hashlib.sha256()
    with open(path,'rb') as f:
        for chunk in iter(lambda:f.read(1024*1024), b''): h.update(chunk)
    return h.hexdigest()

def hash_lines(lines):
    rows=sorted(lines)
    data='\n'.join(rows) + ('\n' if rows else '')
    return hashlib.sha256(data.encode()).hexdigest()

def run(*args, capture=False):
    p=subprocess.run(args, cwd=ROOT, text=True, stdout=subprocess.PIPE if capture else None, stderr=subprocess.PIPE if capture else None, check=True)
    return p.stdout if capture else ''

def path_match(path, rules):
    return any(path.startswith(r) if r.endswith('/') else path==r for r in rules)

def split_ref(ref):
    p, line = ref.rsplit(':',1)
    return p, int(line)

def poq(s):
    return json.dumps(s, ensure_ascii=False)

def write_pot(path, records, refs_by_id):
    lines=[
        'msgid ""', 'msgstr ""',
        '"Project-Id-Version: GravityView 3.3.4\\n"',
        '"MIME-Version: 1.0\\n"',
        '"Content-Type: text/plain; charset=UTF-8\\n"',
        '"Content-Transfer-Encoding: 8bit\\n"',
        '"X-Domain: gk-gravityview\\n"', '',
    ]
    for ident in sorted(records):
        rec=records[ident]
        if rec['msgid_plural']:
            fail('authority unexpectedly contains a plural target: '+ident)
        refs=refs_by_id.get(ident, [])
        if refs:
            lines.append('#: ' + ' '.join(refs))
        if rec['msgctxt']:
            lines.append('msgctxt ' + poq(rec['msgctxt']))
        lines.append('msgid ' + poq(rec['msgid']))
        lines.append('msgstr ""')
        lines.append('')
    path.write_text('\n'.join(lines), encoding='utf-8')

def normalize_header(path):
    text=path.read_text(encoding='utf-8')
    head_end=text.find('\n\n')
    if head_end < 0: fail('PO header separator missing: '+str(path))
    header=text[:head_end]
    if '"X-Domain: gk-gravityview\\n"' not in header:
        marker='"Content-Transfer-Encoding: 8bit\\n"'
        if marker not in header: fail('PO transfer-encoding header missing: '+str(path))
        header=header.replace(marker, marker+'\n"X-Domain: gk-gravityview\\n"',1)
        text=header+text[head_end:]
    path.write_text(text, encoding='utf-8')

def generate_provider(target_records, refs_by_id, out_path):
    out_path.parent.mkdir(parents=True, exist_ok=True)
    with tempfile.TemporaryDirectory(prefix='wu006-po-') as td:
        td=pathlib.Path(td)
        pot=td/'target.pot'; merged=td/'merged.po'; clean=td/'clean.po'
        write_pot(pot,target_records,refs_by_id)
        run('msgmerge','--no-fuzzy-matching','--no-wrap','--quiet',str(REVIEWED_PO),str(pot),'-o',str(merged))
        run('msgattrib','--no-obsolete','--no-fuzzy','--translated','--no-wrap',str(merged),'-o',str(clean))
        normalize_header(clean)
        run('msgfmt','--check','-o','/dev/null',str(clean))
        shutil.copyfile(clean,out_path)

def inspect_po(path):
    raw=run('php','.github/scripts/wu006-inspect-po.php',str(path),capture=True)
    return json.loads(raw)

def index_fingerprints(index):
    ids=[]; rows=[]
    for ident, idxs in index['entries']:
        ids.append(ident)
        for i in idxs:
            rows.append(ident+'\x1f'+index['paths'][i])
    return hash_lines(ids), hash_lines(rows)

if sha256(REVIEWED_PO) != EXPECTED_REVIEWED_SHA: fail('reviewed Persian PO hash mismatch')
extract_raw=run('php','.github/scripts/wu006-extract-source.php',str(SOURCE_ROOT),'gk-gravityview',capture=True)
source=json.loads(extract_raw)
if source['canonical_message_count'] != 3127 or source['context_message_count'] != 127 or source['plural_message_count'] != 42 or source['source_reference_count'] != 3931: fail('source census mismatch')
if source['canonical_keyset_sha256'] != EXPECTED_SOURCE_KEYSET or source['source_reference_index_sha256'] != EXPECTED_SOURCE_REFHASH: fail('source fingerprints mismatch')
if len(source['dynamic_non_literal_calls']) != 1: fail('dynamic source-call accounting mismatch')
identities=source['identities']

all_surface_ids=[]; frequency={}; computed={}
for s in SURFACES:
    selected={}; matched_by_id={}
    for ident,rec in identities.items():
        matched=[]
        for ref in rec['references']:
            p,_=split_ref(ref)
            if path_match(p,s['rules']): matched.append(p)
        if matched:
            selected[ident]=rec
            matched_by_id[ident]=sorted(set(matched))
    paths=sorted({p for ps in matched_by_id.values() for p in ps})
    path_index={p:i for i,p in enumerate(paths)}
    entries=[[ident,[path_index[p] for p in matched_by_id[ident]]] for ident in sorted(selected)]
    index={'surface_id':s['surface_id'],'source_path_rules':s['rules'],'paths':paths,'entries':entries}
    k,p=index_fingerprints(index)
    if len(selected)!=s['count'] or k!=s['keyset'] or p!=s['pathhash']:
        fail(f"surface reproduction mismatch: {s['surface_id']} count={len(selected)} key={k} path={p}")
    computed[s['surface_id']]={'selected':selected,'index':index,'matched_by_id':matched_by_id}
    for ident in selected:
        all_surface_ids.append(ident); frequency[ident]=frequency.get(ident,0)+1

union_ids=sorted(frequency)
if len(all_surface_ids)!=467 or len(union_ids)!=461: fail('surface occurrence/union mismatch')
if sum(1 for n in frequency.values() if n>1)!=6 or sum(max(0,n-1) for n in frequency.values())!=6: fail('overlap accounting mismatch')
if hash_lines(union_ids)!=EXPECTED_AUTHORITY_KEYSET: fail('aggregate authority keyset mismatch')
if 3127-len(union_ids)!=2666: fail('unclassified boundary mismatch')
if any(identities[i]['msgid_plural'] for i in union_ids): fail('WU-003 target plural invariant drifted')

tmp_root=pathlib.Path(tempfile.mkdtemp(prefix='wu006-generated-'))
try:
    generated_inspect={}
    for s in SURFACES:
        c=computed[s['surface_id']]
        refs={i:[ref for ref in identities[i]['references'] if path_match(split_ref(ref)[0],s['rules'])] for i in c['selected']}
        tmp=tmp_root/(s['key']+'.po')
        generate_provider(c['selected'],refs,tmp)
        ins=inspect_po(tmp)
        if len(ins['ids'])!=s['count'] or hash_lines(ins['ids'])!=s['keyset'] or hash_lines(list(ins['translation_rows'].values()))!=s['transhash']:
            fail('generated surface translation fingerprint mismatch: '+s['surface_id'])
        generated_inspect[s['surface_id']]=(tmp,ins)

    shortcode=SURFACES[0]
    existing_short=inspect_po(ROOT/shortcode['po'])
    generated_short=generated_inspect[shortcode['surface_id']][1]
    if existing_short['translation_rows'] != generated_short['translation_rows']:
        fail('existing two-identity shortcode translations differ from WU-003 authority')
    existing_index=json.loads((ROOT/shortcode['index']).read_text(encoding='utf-8'))
    if existing_index != computed[shortcode['surface_id']]['index']:
        fail('existing shortcode source-evidence index drifted')

    for s in SURFACES[1:]:
        idx_path=ROOT/s['index']; idx_path.parent.mkdir(parents=True,exist_ok=True)
        idx_path.write_text(json.dumps(computed[s['surface_id']]['index'],ensure_ascii=False,separators=(',',':'))+'\n',encoding='utf-8')
        po_tmp,_=generated_inspect[s['surface_id']]
        po_path=ROOT/s['po']; po_path.parent.mkdir(parents=True,exist_ok=True); shutil.copyfile(po_tmp,po_path)

    union_records={i:identities[i] for i in union_ids}
    union_refs={i:identities[i]['references'] for i in union_ids}
    aggregate_path=ROOT/'languages/providers/gravityview/source/fa_IR.po'
    generate_provider(union_records,union_refs,aggregate_path)
    aggregate=inspect_po(aggregate_path)
    if len(aggregate['ids'])!=461 or hash_lines(aggregate['ids'])!=EXPECTED_AUTHORITY_KEYSET or hash_lines(list(aggregate['translation_rows'].values()))!=EXPECTED_AUTHORITY_TRANSLATION:
        fail('aggregate provider source does not equal the WU-003 461 authority')

    manifest_path=ROOT/'tools/i18n/admission/content.json'
    manifest=json.loads(manifest_path.read_text(encoding='utf-8'))
    old_other=[r for r in manifest['admissions'] if r['product']!='gravityview']
    old_gv=[r for r in manifest['admissions'] if r['product']=='gravityview']
    if len(old_gv)!=1 or old_gv[0]['surface_id']!=shortcode['surface_id']:
        fail('expected exactly one existing GravityView shortcode admission')
    old_short=old_gv[0]
    for field,expected in [('admitted_message_count',2),('admitted_keyset_sha256',shortcode['keyset']),('admitted_surface_path_index_sha256',shortcode['pathhash']),('admitted_translation_content_sha256',shortcode['transhash'])]:
        if old_short.get(field)!=expected: fail('existing shortcode admission drift: '+field)

    records=[old_short]
    for s in SURFACES[1:]:
        records.append({
            'product':'gravityview','domain':'gk-gravityview','locale':'fa_IR','target_version':'3.3.4','content_state':'CONTENT_ADMITTED_PARTIAL',
            'reviewed_source_po_sha256':EXPECTED_REVIEWED_SHA,'reviewed_source_message_count':3127,
            'source_package_sha256':EXPECTED_SOURCE_ZIP_SHA,'vendor_pot_sha256':None,
            'surface_id':s['surface_id'],'admitted_message_count':s['count'],'admitted_keyset_sha256':s['keyset'],
            'admitted_surface_path_index_sha256':s['pathhash'],'admitted_translation_content_sha256':s['transhash'],
            'surface_evidence_index_path':s['index'],'surface_evidence_index_sha256':sha256(ROOT/s['index']),
            'provider_source_path':s['po'],'provider_source_sha256':sha256(ROOT/s['po']),
            'native_js_handles_activated':0,'js_translation_json_generated':0,
        })
    manifest['admissions']=old_other+records
    manifest['admissions'].sort(key=lambda r:'\x1f'.join([r['product'],r['domain'],r['locale'],r['target_version'],r['surface_id']]))
    manifest_path.write_text(json.dumps(manifest,ensure_ascii=False,indent=4)+'\n',encoding='utf-8')

    validate_php="""<?php
require getcwd() . '/tools/i18n/admission.php';
require getcwd() . '/vendor/autoload.php';
require getcwd() . '/tools/i18n/content-admission.php';
define('ABSPATH', getcwd() . '/');
$products=require ABSPATH.'includes/localization/products.php';
$source=pgr_validate_admission(ABSPATH);
$content=pgr_validate_content_admission(ABSPATH,$source,$products);
echo json_encode($content['gravityview'], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),"\n";
"""
    check_file=tmp_root/'validate.php'; check_file.write_text(validate_php,encoding='utf-8')
    gv=json.loads(run('php',str(check_file),capture=True))
    if len(gv['admissions'])!=6 or gv['aggregate']['admitted_message_count']!=461 or gv['aggregate']['admitted_keyset_sha256']!=EXPECTED_AUTHORITY_KEYSET or gv['aggregate']['admitted_translation_content_sha256']!=EXPECTED_AUTHORITY_TRANSLATION:
        fail('repository-native content admission aggregate mismatch')

    prov_path=ROOT/'languages/providers/gravityview/source/provenance.json'
    prov=json.loads(prov_path.read_text(encoding='utf-8'))
    if prov.get('source_package_sha256')!=EXPECTED_SOURCE_ZIP_SHA or prov.get('vendor_pot_sha256', 'missing') is not None:
        fail('GravityView source provenance drift')
    projected=[]
    projection_fields=['product','domain','locale','target_version','content_state','reviewed_source_po_sha256','reviewed_source_message_count','surface_id','admitted_message_count','admitted_keyset_sha256','admitted_surface_path_index_sha256','admitted_translation_content_sha256','surface_evidence_index_path','surface_evidence_index_sha256','provider_source_path','provider_source_sha256']
    for r in gv['admissions']:
        projected.append({k:r[k] for k in projection_fields})
    prov['content_admission_revision']=2
    prov['content_admission_state']='CONTENT_ADMITTED_PARTIAL'
    prov['content_admissions']=projected
    prov['content_aggregate']=gv['aggregate']
    prov['catalog_revision']=3
    prov['translation_review']='WU003_CLASSIFIED_AUTHORITY_EXACT_ACCEPTED_SIX_SURFACE_UNION'
    prov['native_js_handles_activated']=0
    prov['js_translation_json_generated']=0
    prov['wu003_authority_archive_sha256']=AUTHORITY_ARCHIVE_SHA
    prov['upstream_fallback_validation']='SYNTHETIC_CORE_SPARSE_PRECEDENCE'
    prov_path.write_text(json.dumps(prov,ensure_ascii=False,separators=(',',':'))+'\n',encoding='utf-8')
finally:
    shutil.rmtree(tmp_root,ignore_errors=True)

print('WU006 source admission materialization: PASS')
