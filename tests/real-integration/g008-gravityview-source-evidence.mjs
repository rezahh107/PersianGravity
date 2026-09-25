import { createHash } from 'node:crypto';

const targetContractShape = {
  date_created: [
    'field_name',
    'searchable',
    'operators',
    'contexts',
    'get_content_reads_raw_field_value',
  ],
  date_updated: [
    'extends_date_created',
    'field_name',
    'searchable',
    'operators',
    'contexts',
  ],
};

const sourceEvidenceTopLevelKeys = new Set([
  'schema_version',
  'evidence_class',
  'program',
  'product',
  'exact_version',
  'exact_package_sha256',
  'exact_gravityforms_version',
  'exact_gravityforms_package_sha256',
  'exact_persiangravity_head',
  'source_contract',
  'independent_fail_closed',
  'evidence_boundary',
  'provenance',
]);

const provenanceKeys = new Set([
  'file',
  'start_line',
  'end_line',
  'file_sha256',
  'contract_keys',
]);

function allTrue(value) {
  if (typeof value === 'boolean') return value;
  if (value && typeof value === 'object' && !Array.isArray(value)) {
    const values = Object.values(value);
    return values.length > 0 && values.every(allTrue);
  }
  return false;
}

export function evaluateTargetFieldContracts(dateCreatedContent, dateUpdatedContent) {
  return {
    date_created: {
      field_name: /var\s+\$name\s*=\s*['"]date_created['"]/.test(dateCreatedContent),
      searchable: /var\s+\$is_searchable\s*=\s*true/.test(dateCreatedContent),
      operators: /var\s+\$search_operators\s*=\s*\[['"]less_than['"],\s*['"]greater_than['"],\s*['"]is['"],\s*['"]isnot['"]\]/.test(dateCreatedContent),
      contexts: /var\s+\$contexts\s*=\s*\[['"]single['"],\s*['"]multiple['"],\s*['"]export['"]\]/.test(dateCreatedContent),
      get_content_reads_raw_field_value: /get_content[\s\S]{0,700}GVCommon::format_date\(\s*\$field\['value'\]/.test(dateCreatedContent),
    },
    date_updated: {
      extends_date_created: /class\s+DateUpdated\s+extends\s+\\GravityView_Field_Date_Created/.test(dateUpdatedContent),
      field_name: /var\s+\$name\s*=\s*['"]date_updated['"]/.test(dateUpdatedContent),
      searchable: /var\s+\$is_searchable\s*=\s*true/.test(dateUpdatedContent),
      operators: /var\s+\$search_operators\s*=\s*\[['"]less_than['"],\s*['"]greater_than['"],\s*['"]is['"],\s*['"]isnot['"]\]/.test(dateUpdatedContent),
      contexts: /var\s+\$contexts\s*=\s*\[['"]single['"],\s*['"]multiple['"],\s*['"]export['"]\]/.test(dateUpdatedContent),
    },
  };
}

export function assertTargetFieldContracts(targetContracts) {
  for (const [field, requiredKeys] of Object.entries(targetContractShape)) {
    const contract = targetContracts?.[field];
    if (!contract || !requiredKeys.every((key) => contract[key] === true)) {
      throw new Error(`Exact GravityView target field contract drifted independently: ${field}`);
    }
  }
}

export function proveIndependentTargetFailClosed(dateCreatedContent, dateUpdatedContent) {
  const baseline = evaluateTargetFieldContracts(dateCreatedContent, dateUpdatedContent);
  assertTargetFieldContracts(baseline);

  const createdDrift = evaluateTargetFieldContracts(
    dateCreatedContent.replace(/date_created/g, 'date_created__qualification_drift'),
    dateUpdatedContent,
  );
  let createdFailedClosed = false;
  try {
    assertTargetFieldContracts(createdDrift);
  } catch (error) {
    createdFailedClosed = /date_created/.test(String(error?.message));
  }
  if (!createdFailedClosed || !allTrue(createdDrift.date_updated)) {
    throw new Error('Independent date_created contract drift did not fail closed without contaminating date_updated.');
  }

  const updatedDrift = evaluateTargetFieldContracts(
    dateCreatedContent,
    dateUpdatedContent.replace(/date_updated/g, 'date_updated__qualification_drift'),
  );
  let updatedFailedClosed = false;
  try {
    assertTargetFieldContracts(updatedDrift);
  } catch (error) {
    updatedFailedClosed = /date_updated/.test(String(error?.message));
  }
  if (!updatedFailedClosed || !allTrue(updatedDrift.date_created)) {
    throw new Error('Independent date_updated contract drift did not fail closed without contaminating date_created.');
  }

  return {
    date_created: true,
    date_updated: true,
  };
}

export function sha256(content) {
  return createHash('sha256').update(content, 'utf8').digest('hex');
}

export function sourceLocation(source, startNeedle, contractKeys, endNeedle = null) {
  const lines = source.content.split(/\r?\n/);
  const startIndex = lines.findIndex((line) => line.includes(startNeedle));
  if (startIndex < 0) {
    throw new Error(`Required provenance anchor not found in ${source.relative}`);
  }

  let endIndex = startIndex;
  if (endNeedle) {
    endIndex = lines.findIndex((line, index) => index >= startIndex && line.includes(endNeedle));
    if (endIndex < 0) {
      throw new Error(`Required provenance end anchor not found in ${source.relative}`);
    }
  }

  return {
    file: source.relative.replaceAll('\\', '/'),
    start_line: startIndex + 1,
    end_line: endIndex + 1,
    file_sha256: sha256(source.content),
    contract_keys: [...contractKeys],
  };
}

function assertPlainBooleanTree(value, path) {
  if (typeof value === 'boolean') return;
  if (!value || typeof value !== 'object' || Array.isArray(value)) {
    throw new Error(`Source evidence contract must contain booleans only at ${path}.`);
  }
  const entries = Object.entries(value);
  if (entries.length === 0) {
    throw new Error(`Source evidence contract object is empty at ${path}.`);
  }
  for (const [key, child] of entries) {
    if (!/^[a-z0-9_]+$/.test(key)) {
      throw new Error(`Invalid source evidence contract key at ${path}.`);
    }
    assertPlainBooleanTree(child, `${path}.${key}`);
  }
}

export function assertSanitizedProvenance(provenance) {
  if (!provenance || typeof provenance !== 'object' || Array.isArray(provenance)) {
    throw new Error('GravityView source provenance must be a metadata object.');
  }
  const entries = Object.entries(provenance);
  if (entries.length === 0) {
    throw new Error('GravityView source provenance must not be empty.');
  }

  for (const [id, record] of entries) {
    if (!/^[a-z0-9_]+$/.test(id)) {
      throw new Error(`Invalid source provenance identifier: ${id}`);
    }
    if (!record || typeof record !== 'object' || Array.isArray(record)) {
      throw new Error(`Source provenance record ${id} must be an object.`);
    }
    const keys = Object.keys(record);
    if (keys.some((key) => !provenanceKeys.has(key)) || keys.length !== provenanceKeys.size) {
      throw new Error(`Source provenance record ${id} contains non-metadata fields.`);
    }
    if (!/^(?:[A-Za-z0-9._-]+\/)*[A-Za-z0-9._-]+\.php$/.test(record.file)) {
      throw new Error(`Source provenance record ${id} has an invalid relative PHP path.`);
    }
    if (
      !Number.isInteger(record.start_line)
      || record.start_line < 1
      || !Number.isInteger(record.end_line)
      || record.end_line < record.start_line
    ) {
      throw new Error(`Source provenance record ${id} has an invalid line range.`);
    }
    if (!/^[a-f0-9]{64}$/.test(record.file_sha256)) {
      throw new Error(`Source provenance record ${id} has an invalid file fingerprint.`);
    }
    if (
      !Array.isArray(record.contract_keys)
      || record.contract_keys.length === 0
      || record.contract_keys.some((key) => typeof key !== 'string' || !/^[a-z0-9_.-]+$/.test(key))
    ) {
      throw new Error(`Source provenance record ${id} has invalid contract identifiers.`);
    }
  }
}

export function assertMetadataOnlySourceEvidence(evidence) {
  if (!evidence || typeof evidence !== 'object' || Array.isArray(evidence)) {
    throw new Error('GravityView source evidence must be an object.');
  }

  const actualKeys = Object.keys(evidence);
  if (actualKeys.some((key) => !sourceEvidenceTopLevelKeys.has(key)) || actualKeys.length !== sourceEvidenceTopLevelKeys.size) {
    throw new Error('GravityView source evidence contains fields outside the metadata-only schema.');
  }

  if (evidence.schema_version !== '3.0.0') {
    throw new Error('GravityView source evidence metadata schema drifted.');
  }
  if (evidence.evidence_class !== 'G008_GRAVITYVIEW_METADATA_ONLY_SOURCE_PROBE') {
    throw new Error('GravityView source evidence class drifted.');
  }
  if (evidence.program !== 'G-008' || evidence.product !== 'GravityView') {
    throw new Error('GravityView source evidence identity drifted.');
  }
  if (evidence.evidence_boundary?.metadata_only !== true || evidence.evidence_boundary?.raw_source_persisted !== false) {
    throw new Error('GravityView source evidence boundary is not metadata-only.');
  }
  if (
    evidence.independent_fail_closed?.date_created !== true
    || evidence.independent_fail_closed?.date_updated !== true
  ) {
    throw new Error('GravityView target-field independent fail-closed proof is incomplete.');
  }

  assertPlainBooleanTree(evidence.source_contract, 'source_contract');
  assertTargetFieldContracts(evidence.source_contract);
  assertSanitizedProvenance(evidence.provenance);

  const serialized = JSON.stringify(evidence);
  if (serialized.includes('\\n') || serialized.includes('\\r')) {
    throw new Error('GravityView source evidence contains serialized multi-line content.');
  }
}
