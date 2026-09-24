# PersianGravity ↔ Gravity Presentation Profiles Responsibility Boundary

Status: **CURRENT ARCHITECTURE CONTRACT**

This document is the canonical PersianGravity-side responsibility contract for interaction with **Gravity Presentation Profiles (GPP)**. Capability-specific evidence remains in the existing G-007/G-008/G-009 documents; this document does not duplicate their implementation detail and does not widen any runtime authority.

Evidence snapshot at this closure:

- PersianGravity inspected merged `main`: `0e060e57a88357f16961d7e2da497f1daea1c8d3`.
- GPP inspected merged `main`: `4890d52ce6fad28539592324aa4a743960b359a2`.
- Latest public PersianGravity release: `v4.6.0`, release source `d134c9ac81b177a32a3138f074fca3d1c1ebfae4`.
- GPP's current qualified PersianGravity provider contract is exactly public release `v4.6.0`.

The repository capability map below describes what current PersianGravity source has actually admitted/verified. It is **not** a statement that post-`v4.6.0` `main` capabilities have already been publicly released, and it does not automatically widen GPP's provider contract.

## 1. Ownership model

A rendered state may pass through several layers without transferring ownership:

```text
Gravity Forms / Gravity Flow / Gravity Perks
        ↓
own data, lifecycle, workflow and component behavior

PersianGravity
        ↓
owns explicitly admitted reusable Persian / Iranian Gravity behavior
(localization, bounded Jalali presentation, bounded RTL/BiDi compatibility)

Gravity Presentation Profiles (GPP)
        ↓
owns deterministic visual composition and styling around host/PersianGravity states
```

Example:

```text
Gravity Flow owns a raw workflow date and its operational semantics
        ↓
PersianGravity may expose an admitted Gregorian → Jalali presentation capability
        ↓
GPP may consume that public capability only for a separately qualified host source
        ↓
GPP styles the rendered result
```

Storage, raw values, sorting, filtering, workflow transitions, assignments and permissions remain host-owned throughout.

## 2. Host-product responsibility

### Gravity Forms owns

- form/field lifecycle and native field behavior;
- validation and submission semantics;
- Entry data and stored values;
- raw query/sort/filter/export/API semantics;
- native markup/API contracts.

### Gravity Flow owns

- workflow state and transitions;
- assignments and authorization;
- Inbox, Status, Entry Detail, Timeline and Print host behavior;
- deadline, overdue, schedule and expiration semantics;
- operational getters, queries, sorting/filtering and exports.

### Gravity Perks products own

- their native component lifecycle and interaction behavior, including upload/select/search behavior;
- vendor markup, scripts, styles and supported host integration contracts.

Neither PersianGravity nor GPP may use presentation code to replace host-owned workflow, data, authorization or component semantics.

## 3. PersianGravity responsibility

PersianGravity owns only **explicitly admitted** reusable Persian/Iranian Gravity capabilities. Admission is bounded by the capability's source/version/evidence contract; product-family membership or a similar-looking surface is not enough.

### 3.1 Localization

PersianGravity may provide bounded generic `fa_IR` overlays for explicitly manifested exact products/domains. Provider entries win only inside the admitted contract; missing entries preserve upstream/vendor/TranslationsPress fallback.

The current NON-GravityView exact-product boundary includes:

- Gravity Forms `3.1.1.1` (`gravityforms`) — current source-backed provider authority is complete for the locked product census.
- Gravity Flow `3.1.0` (`gravityflow`) — current source-backed provider authority is complete for the locked product census.
- Gravity Perks `2.3.16` (`gravityperks`) — exact G-007 admitted family baseline.
- GP File Upload Pro `1.5.13` (`gp-file-upload-pro`) — exact G-007 admitted product.
- GP Advanced Select `1.1.21` (`gp-advanced-select`) — exact G-007 admitted product.

No other or future Gravity Perks product/version is implied. Cross-domain calls recorded during G-007 qualification do not silently extend provider authority.

### 3.2 Jalali system-date presentation

`jalali_presentation` is an opt-in, presentation-only capability exposed through the typed public `PGR_Jalali_Presentation` facade.

Its invariants are:

- no storage conversion;
- no API/raw-value rewrite;
- no query/sort/filter/workflow semantic change;
- no broad `wp_date` interception;
- no arbitrary date-looking-string parsing;
- no Gregorian reinterpretation of `pgr_jalali_date` values;
- unsupported, ambiguous, out-of-range or drifted cases retain native presentation.

Current NON-GravityView source/runtime admissions are bounded to:

- Gravity Forms Entries List: `date_created`;
- exact Gravity Flow `3.1.0` Inbox: `date_created`, `last_updated`, `due_date`;
- exact Gravity Flow `3.1.0` Status table: `date_created`, `workflow_timestamp`;
- exact Gravity Flow `3.1.0` Entry Detail workflow-info: Submitted, Last Updated, Due and Expiration.

Exact Gravity Flow `3.1.0` Status `due_date`, Entry Detail Scheduled, Timeline/history initial/stored dates, and an independent Print date seam are evidence-qualified final no-admission surfaces. A final no-admission record is exact-package evidence, not a permanent impossibility claim; a newly discovered supported bounded seam requires fresh qualification.

Most importantly:

```text
PersianGravity capability exists
!=
GPP is authorized to consume it
```

GPP must separately prove the host source semantics and consumer seam for each use.

### 3.3 RTL / BiDi compatibility

PersianGravity owns only exact, evidence-admitted reusable Persian/RTL compatibility. Vendor-native RTL remains preferred.

Current NON-GravityView exact-package browser dispositions include:

- Gravity Forms `3.1.1.1` authentic frontend form: `NATIVE_PASS` for the exercised RTL surfaces.
- Gravity Flow `3.1.0` authentic frontend Inbox/AG Grid: `NATIVE_PASS` for the exercised visible grid surface.
- Gravity Perks `2.3.16` family baseline, limited to GP File Upload Pro `1.5.13` plus GP Advanced Select `1.1.21`: `NATIVE_PASS` for that exact exercised combination.
- GP File Upload Pro `1.5.13`: `NATIVE_PASS`.
- GP Advanced Select `1.1.21`: `ADAPTER_REQUIRED_AND_VERIFIED` through the bounded PersianGravity adapter and exact vendor handle/state gate.
- Gravity Forms `gform_admin` reachability on the exact Flow frontend Inbox: source/runtime reachability and CSS direction causality are proven, but no material visible defect was demonstrated and no safe permanent repair seam was established; final production disposition is `NO_REPAIR_NO_ADMISSION`.

Diagnostic causality is not itself product ownership and does not justify a workaround.

## 4. GPP responsibility

GPP owns deterministic presentation of already-correct host/PersianGravity states, including:

- layout and composition;
- spacing and widths;
- visual hierarchy;
- responsive presentation and stacking;
- borders, radii and surfaces;
- visual treatment of focus/error/success/native states;
- profile-specific styling;
- bounded presentation adapters permitted by GPP's own governing architecture.

GPP does **not** own:

- PersianGravity translation/provider authority;
- Gregorian → Jalali conversion internals;
- PersianGravity field behavior or storage semantics;
- PersianGravity reusable RTL/BiDi compatibility logic;
- Gravity Forms validation/data lifecycle;
- Gravity Flow workflow/state/assignment/action/permission semantics;
- Gravity Perks upload/select/search lifecycle.

A GPP adapter may repair a presentation-only relationship around an existing correct state without becoming the state/behavior owner. For example, a scoped accessibility association may annotate an existing host validation node; it must not create validation truth or duplicate PersianGravity field behavior.

## 5. Allowed PersianGravity ↔ GPP interaction

GPP may consume a **public PersianGravity API/facade** only when all of the following are true:

1. the PersianGravity capability itself is admitted;
2. GPP independently qualifies the exact host source semantics and presentation seam;
3. GPP independently qualifies the exact PersianGravity provider release/capability it will consume;
4. failure, absence or drift retains native host presentation;
5. GPP does not load/copy PersianGravity internal files, converter implementation or private adapter logic;
6. the integration does not move host-owned storage, query, workflow, authorization or component behavior into either presentation layer.

The current GPP implementation follows this model for Jalali presentation: its `PersianGravityJalaliBridge` calls only the public `PGR_Jalali_Presentation::format_datetime()` facade after GPP has established a qualified typed source. GPP does not contain PersianGravity's Gregorian→Jalali converter.

PersianGravity-rendered fields/states may also be styled by GPP without transferring their behavior ownership.

### Adapter placement rule

Do not duplicate an adapter across both plugins.

- A reusable Persian/Iranian behavior correction that belongs across Gravity consumers goes in PersianGravity after its own admission evidence.
- A GPP-profile-specific composition/accessibility/presentation correction around already-correct behavior goes in GPP after its own qualification.
- A host semantic/workflow/data defect belongs to the host product or remains a qualification finding; do not hide it in either presentation layer.

## 6. Provider/version coupling and drift

PersianGravity publishing or merging a capability does not make GPP compatible with it.

The required sequence is:

```text
PersianGravity publishes an exact capability/release
        ↓
GPP independently qualifies that exact release + exact host source seam
        ↓
only then may GPP widen its admitted provider contract
```

At this closure, GPP's admitted provider version is exactly **PersianGravity `v4.6.0`**. PersianGravity `main` is already ahead of that release, but those post-release changes do not widen GPP compatibility.

If a newer PersianGravity provider is installed before GPP separately qualifies it, GPP must follow its own fail-safe contract and preserve native behavior rather than weakening the version gate. A future PersianGravity release likewise must not silently activate new GPP behavior.

Version drift checks are architecture safety, not inconvenience to remove.

## 7. Defect routing

When a Persian/RTL/Jalali/visual problem appears, route it by the owner of the broken semantics rather than by the screen where the symptom is visible.

### Fix PersianGravity when

The defect is inside an explicitly admitted reusable Persian/Iranian Gravity capability, for example:

- admitted localization content/provider runtime;
- `PGR_Jalali_Presentation` conversion/presentation contract;
- an admitted reusable RTL/BiDi compatibility adapter.

### Fix GPP when

The underlying host/PersianGravity behavior is correct and the defect is deterministic presentation inside an admitted GPP profile, for example:

- width, spacing or stacking;
- visual hierarchy;
- profile-specific responsive composition;
- visual treatment/association of an already-correct host or PersianGravity state;
- GPP's own exact consumer seam/version gate for an optional PersianGravity facade.

### Fix neither / investigate the host when

The defect originates in:

- Gravity Forms behavior, validation truth, storage or data lifecycle;
- Gravity Flow workflow/state/authorization/assignment/deadline/schedule semantics;
- Gravity Perks component lifecycle;
- vendor markup/API behavior outside either plugin's admitted responsibility.

Do not use CSS, formatting hooks or presentation adapters to compensate for a semantic/data/workflow defect.

## 8. Current evidence sources

PersianGravity authority/details:

- `AGENTS.md`
- `docs/ARCHITECTURE.md`
- `docs/G007_GRAVITY_PERKS_LOCALIZATION.md`
- `docs/G008_JALALI_PRESENTATION.md`
- `docs/G008_SYSTEM_DATE_EXPANSION.md`
- `docs/G009_RTL_BIDI_COMPATIBILITY.md`
- `docs/VALIDATION.md`

GPP read-only authority inspected for this contract:

- `AGENTS.md`
- `docs/architecture/MOTHER_ARCHITECTURE.md`
- `docs/architecture/PERSIANGRAVITY_JALALI_CONSUMER_V1.md`
- `src/Core/Presentation/PersianGravityJalaliBridge.php`
- `src/SRWF/GravityFlow/PersianDateFormatter.php`
- `src/SRWF/GravityFlow/BoundHostValueReader.php`
- `src/SRWF/GravityForms/JalaliValidationAssociation.php`

The GPP consumer document is authoritative for what GPP has separately qualified; PersianGravity documentation must not widen that consumer contract by assertion.

## 9. Release and change discipline

This contract does not release, tag or deploy PersianGravity and does not qualify a future GPP provider version.

When future work changes an ownership boundary, update this contract together with the capability-specific evidence that proves the change. Historical validation records may remain historical when they are clearly scoped to their original batch; do not rewrite them to look current.
