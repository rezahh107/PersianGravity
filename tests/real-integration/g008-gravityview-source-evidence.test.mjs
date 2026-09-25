import assert from 'node:assert/strict';
import test from 'node:test';
import {
  assertMetadataOnlySourceEvidence,
  assertTargetFieldContracts,
  evaluateTargetFieldContracts,
  proveIndependentTargetFailClosed,
} from './g008-gravityview-source-evidence.mjs';

const dateCreatedFixture = `
class SyntheticDateCreated {
  var $name = 'date_created';
  var $is_searchable = true;
  var $search_operators = ['less_than', 'greater_than', 'is', 'isnot'];
  var $contexts = ['single', 'multiple', 'export'];
  function get_content() {
    return GVCommon::format_date( $field['value'], $format );
  }
}
`;

const dateUpdatedFixture = `
class DateUpdated extends \\GravityView_Field_Date_Created {
  var $name = 'date_updated';
  var $is_searchable = true;
  var $search_operators = ['less_than', 'greater_than', 'is', 'isnot'];
  var $contexts = ['single', 'multiple', 'export'];
}
`;

function metadataEvidence() {
  return {
    schema_version: '3.0.0',
    evidence_class: 'G008_GRAVITYVIEW_METADATA_ONLY_SOURCE_PROBE',
    program: 'G-008',
    product: 'GravityView',
    exact_version: '3.3.4',
    exact_package_sha256: 'a'.repeat(64),
    exact_gravityforms_version: '3.1.1.1',
    exact_gravityforms_package_sha256: 'b'.repeat(64),
    exact_persiangravity_head: 'c'.repeat(40),
    source_contract: evaluateTargetFieldContracts(dateCreatedFixture, dateUpdatedFixture),
    independent_fail_closed: {
      date_created: true,
      date_updated: true,
    },
    evidence_boundary: {
      metadata_only: true,
      raw_source_persisted: false,
    },
    provenance: {
      date_created_field: {
        file: 'src/Field/Types/DateCreated.php',
        start_line: 20,
        end_line: 40,
        file_sha256: 'd'.repeat(64),
        contract_keys: ['date_created.field_name'],
      },
    },
  };
}

test('both GravityView target fields are independently required', () => {
  const baseline = evaluateTargetFieldContracts(dateCreatedFixture, dateUpdatedFixture);
  assert.doesNotThrow(() => assertTargetFieldContracts(baseline));
  assert.deepEqual(proveIndependentTargetFailClosed(dateCreatedFixture, dateUpdatedFixture), {
    date_created: true,
    date_updated: true,
  });

  const missingCreated = evaluateTargetFieldContracts('', dateUpdatedFixture);
  assert.throws(
    () => assertTargetFieldContracts(missingCreated),
    /date_created/,
  );
  assert.equal(Object.values(missingCreated.date_updated).every(Boolean), true);

  const missingUpdated = evaluateTargetFieldContracts(dateCreatedFixture, '');
  assert.throws(
    () => assertTargetFieldContracts(missingUpdated),
    /date_updated/,
  );
  assert.equal(Object.values(missingUpdated.date_created).every(Boolean), true);
});

test('metadata-only GravityView source evidence accepts normalized provenance', () => {
  const evidence = metadataEvidence();
  assert.doesNotThrow(() => assertMetadataOnlySourceEvidence(evidence));
});

test('metadata-only GravityView source evidence rejects source windows and text-bearing fields', () => {
  for (const [key, value] of [
    ['lines', [{ line: 20, text: 'licensed source' }]],
    ['text', 'licensed source'],
    ['window', { start: 1, end: 2 }],
    ['excerpt', { lines: [] }],
  ]) {
    const evidence = metadataEvidence();
    evidence.provenance.date_created_field[key] = value;
    assert.throws(
      () => assertMetadataOnlySourceEvidence(evidence),
      /non-metadata fields/,
      key,
    );
  }

  const topLevelLeak = metadataEvidence();
  topLevelLeak.excerpt = { lines: [{ text: 'licensed source' }] };
  assert.throws(
    () => assertMetadataOnlySourceEvidence(topLevelLeak),
    /outside the metadata-only schema/,
  );
});

test('metadata-only GravityView source evidence rejects multi-line or malformed provenance values', () => {
  const multilinePath = metadataEvidence();
  multilinePath.provenance.date_created_field.file = 'src/Field/Types/DateCreated.php\nlicensed source';
  assert.throws(
    () => assertMetadataOnlySourceEvidence(multilinePath),
    /invalid relative PHP path/,
  );

  const rawContractId = metadataEvidence();
  rawContractId.provenance.date_created_field.contract_keys = ['date_created.field_name\nlicensed source'];
  assert.throws(
    () => assertMetadataOnlySourceEvidence(rawContractId),
    /invalid contract identifiers/,
  );
});
