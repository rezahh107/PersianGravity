# WU-008 recovery evidence harness

This temporary branch exists only to execute WU-008 evidence on GitHub Actions.

The checkout/branch is **test machinery only**. The disposable WordPress installation fetches PersianGravity separately and detaches exactly at:

`d3d6460a07a2c38b483dac664603a443ce430da0`

The workflow fails closed unless `git -C <installed-plugin> rev-parse HEAD` equals that exact SHA.

No production PHP/runtime behavior, provider translation content, Content Admission authority, product manifest, generated production catalog, or licensed vendor package is modified by this harness.

The branch must not be merged as part of WU-008. The GitHub Actions artifact, not CI status alone, is the evidence corpus.
