# WU-008 recovery execution

Temporary evidence-only harness for GitHub Actions. The branch is test machinery and must not be merged as part of WU-008.

The disposable WordPress runtime installs PersianGravity separately by fetching and detaching exactly `d3d6460a07a2c38b483dac664603a443ce430da0`, then fails closed unless `git rev-parse HEAD` matches it.

The workflow artifact is the evidence corpus; CI status alone is not acceptance evidence.
