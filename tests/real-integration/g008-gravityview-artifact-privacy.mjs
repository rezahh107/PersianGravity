import fs from 'node:fs';
import path from 'node:path';
import {
  assertMetadataOnlySourceEvidence,
  assertSanitizedProvenance,
} from './g008-gravityview-source-evidence.mjs';

const artifactDir = process.env.WU008_ARTIFACT_DIR;
if (!artifactDir) {
  throw new Error('WU008_ARTIFACT_DIR is required for GravityView artifact privacy verification.');
}

const sourceFile = 'g008-gravityview-source-probe.json';
const sourcePath = path.join(artifactDir, sourceFile);
if (!fs.existsSync(sourcePath)) {
  throw new Error('GravityView metadata-only source evidence is missing.');
}

const source = JSON.parse(fs.readFileSync(sourcePath, 'utf8'));
assertMetadataOnlySourceEvidence(source);

const sourceDiscoveryFiles = fs.readdirSync(artifactDir)
  .filter((name) => /^g008-gravityview-.*source.*\.json$/i.test(name))
  .sort();
if (JSON.stringify(sourceDiscoveryFiles) !== JSON.stringify([sourceFile])) {
  throw new Error(`Unexpected GravityView source-discovery artifact set: ${JSON.stringify(sourceDiscoveryFiles)}`);
}

const qualificationPath = path.join(artifactDir, 'g008-gravityview-date-qualification.json');
let qualificationChecked = false;
if (fs.existsSync(qualificationPath)) {
  const qualification = JSON.parse(fs.readFileSync(qualificationPath, 'utf8'));
  assertSanitizedProvenance(qualification.source_provenance);
  if (
    qualification.source_evidence_boundary?.metadata_only !== true
    || qualification.source_evidence_boundary?.raw_source_persisted !== false
    || qualification.independent_source_fail_closed?.date_created !== true
    || qualification.independent_source_fail_closed?.date_updated !== true
  ) {
    throw new Error('GravityView qualification artifact does not preserve the metadata-only source boundary.');
  }
  qualificationChecked = true;
}

const admissionPath = path.join(artifactDir, 'g008-gravityview-admission.json');
let admissionChecked = false;
if (fs.existsSync(admissionPath)) {
  const admission = JSON.parse(fs.readFileSync(admissionPath, 'utf8'));
  assertSanitizedProvenance(admission.source_provenance);
  if (
    admission.source_evidence_boundary?.metadata_only !== true
    || admission.source_evidence_boundary?.raw_source_persisted !== false
    || admission.independent_source_fail_closed?.date_created !== true
    || admission.independent_source_fail_closed?.date_updated !== true
  ) {
    throw new Error('GravityView production admission artifact does not preserve the metadata-only source boundary.');
  }
  admissionChecked = true;
}

console.log(
  `PASS GravityView artifact privacy: metadata-only source evidence; qualification_checked=${qualificationChecked}; admission_checked=${admissionChecked}`,
);
