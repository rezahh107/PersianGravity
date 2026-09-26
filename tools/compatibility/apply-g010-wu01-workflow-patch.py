#!/usr/bin/env python3
from pathlib import Path

path = Path('.github/workflows/wu008-real-integration.yml')
text = path.read_text(encoding='utf-8')

replacements = [
    (
        """      WU008_FLOW_FILE_ID: 1Y90nvrxEEfVZqpmxXkQvwJfw4pvKCoPf\n      WU008_FLOW_FILE: gravityflow-3.1.0-owner-supplied-source-package.zip\n      WU008_FLOW_SIZE: '2603034'\n      WU008_FLOW_SHA256: ac0573b75831380417a21a455176e25eb746d718bbbd0bb70d6da6f48cba5404\n      WU008_FLOW_VERSION: 3.1.0\n\n""",
        "",
    ),
    (
        """      - name: Validate harness and repository invariants\n""",
        """      - name: Load canonical Gravity Flow package authority\n        shell: bash\n        run: |\n          set -euo pipefail\n          php tools/compatibility/gravityflow-package.php env WU008_FLOW >> \"$GITHUB_ENV\"\n\n      - name: Validate harness and repository invariants\n""",
    ),
    (
        """          bash -n tests/real-integration/verify-package.sh\n""",
        """          bash -n tests/real-integration/verify-package.sh\n          php -l tools/compatibility/gravityflow-package.php\n""",
    ),
    (
        """          tests/real-integration/verify-package.sh \"Gravity Flow\" \"$WU008_FLOW_FILE_ID\" \"$WU008_PACKAGE_DIR/$WU008_FLOW_FILE\" \"$WU008_FLOW_FILE\" \"$WU008_FLOW_SIZE\" \"$WU008_FLOW_SHA256\" \"gravityflow/gravityflow.php\" \"$WU008_FLOW_VERSION\" \"$evidence\"\n""",
        """          tests/real-integration/verify-package.sh \"Gravity Flow\" \"$WU008_FLOW_FILE_ID\" \"$WU008_PACKAGE_DIR/$WU008_FLOW_FILE\" \"$WU008_FLOW_FILE\" \"$WU008_FLOW_SIZE\" \"$WU008_FLOW_SHA256\" \"$WU008_FLOW_MAIN_FILE\" \"$WU008_FLOW_VERSION\" \"$evidence\"\n""",
    ),
]

for old, new in replacements:
    count = text.count(old)
    if count != 1:
        raise SystemExit(f'expected exactly one bounded replacement; found {count}: {old.splitlines()[0]!r}')
    text = text.replace(old, new, 1)

for forbidden in (
    'WU008_FLOW_FILE_ID: 1Y90nvrxEEfVZqpmxXkQvwJfw4pvKCoPf',
    'WU008_FLOW_FILE: gravityflow-3.1.0-owner-supplied-source-package.zip',
    'WU008_FLOW_SIZE:',
    'WU008_FLOW_SHA256: ac0573b75831380417a21a455176e25eb746d718bbbd0bb70d6da6f48cba5404',
    'WU008_FLOW_VERSION: 3.1.0',
):
    if forbidden in text:
        raise SystemExit(f'legacy active authority remains: {forbidden}')

path.write_text(text, encoding='utf-8')
