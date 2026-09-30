# UI implementation batch: quotation action hierarchy

Author: Shashi

- Audit issues addressed: CI-03 and DS-05. The previous foundation pilot result file was absent; the existing `idlm-btn` contract was already usable, so this batch migrates consumers instead of repeating foundation work.
- Area changed: quotation list, quotation editor/footer, quotation detail header, quotation setup save action, and catalogue header/editor actions.
- Canonical pattern: `idlm-btn idlm-btn-primary` for the single committing/creating action and `idlm-btn idlm-btn-secondary` for navigation, filters, cancellation, and non-destructive utilities. Links retain link semantics.
- Material files: `includes/class-quotation-renderer.php`, `assets/js/quotations.js`, and `assets/css/quotations.css`.
- Legacy removed: migrated actions no longer depend on quotation-local `.button`/`.button-primary` styling; live list replacement markup uses the same shared classes as initial server markup.
- Legacy remaining: specialist quotation row controls (`.button-link`, `.button-link-delete`), work-surface close controls, and un-migrated low-frequency catalogue/settings actions remain intentionally scoped for a later action-family pass.
- Verification: PHP syntax check, focused UI regression contracts, and a structural search for old primary action classes in the migrated list/detail/editor/header scope.
- Deferred related finding: CI-04 visual pagination/table shell alignment is the recommended next batch; it can reuse this action contract without changing quote pagination semantics.
