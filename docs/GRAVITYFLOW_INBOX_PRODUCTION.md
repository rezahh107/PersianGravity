# Gravity Flow Inbox production admission

PersianGravity now admits one reviewed production localization surface for Gravity Flow 3.1.0: `gravityflow::workflow_runtime::admin_page:gravityflow-inbox`.

The runtime catalog is sparse: 255 reviewed `fa_IR` messages selected from the final-v2 baseline (SHA-256 `c1dbd59c8364b5fbe9e0b3aaad4c20363642d80a8b7869592993c127cb7c84d9`). Missing Gravity Flow keys continue to fall through to upstream/vendor translations and then source English.

Generated production artifacts are `gravityflow-fa_IR.mo` and `gravityflow-fa_IR.l10n.php`. No native Gravity Flow JS translation handle or JSON catalog is activated in this work unit. No RTL patch is included.

This is not full Gravity Flow translation admission. Remaining Gravity Flow surfaces stay unadmitted until separately approved.
