<?php
/** Manager-only held submission decisions. Builder: Shashi. */
if (!defined('ABSPATH')) { exit; }
$idlm_rows = $context['rows'];
$idlm_status = $context['status'];
?>
<div class="wrap idlm-wrap idlm-identity-reviews">
    <h1>Identity reviews</h1>
<?php // phpcs:disable WordPress.Security.NonceVerification.Recommended -- REL007-BOUNDARY-0135: (guarded template): the template is rendered only from the manager-gated review controller; resolved is a strict string flag selecting a literal success notice, not authority for a resolution.
?>    <?php if (isset($_GET['resolved']) && '1' === $_GET['resolved']) : ?><div class="notice notice-success" role="status"><p>Review resolved.</p></div><?php endif; ?>
<?php // phpcs:enable WordPress.Security.NonceVerification.Recommended
?>    <p>Choose a canonical candidate, create a separate unassigned lead, or reject the submission. Every decision requires a reason. Reviews expire under the touchpoint retention policy.</p>
    <p class="idlm-identity-filters"><?php foreach (array('pending','matched','separate','rejected') as $idlm_filter) : ?><a class="idlm-btn idlm-btn-secondary idlm-btn-sm" <?php echo $idlm_filter === $idlm_status ? 'aria-current="page" ' : ''; ?>href="<?php echo esc_url(add_query_arg('status', $idlm_filter, admin_url('admin.php?page=idlm_identity_reviews'))); ?>"><?php echo esc_html(ucfirst($idlm_filter)); ?></a> <?php endforeach; ?></p>
    <?php if (!$idlm_rows) : ?><p>No reviews in this status.</p><?php endif; ?>
    <?php foreach ($idlm_rows as $idlm_review) : ?>
        <section class="idlm-identity-card">
            <h2><?php echo esc_html('Submission #' . $idlm_review->id); ?> <span class="idlm-badge <?php echo esc_attr(array('pending' => 'is-warning', 'matched' => 'is-success', 'separate' => 'is-info')[$idlm_review->status] ?? 'is-neutral'); ?>"><?php echo esc_html(ucfirst($idlm_review->status)); ?></span></h2>
            <p><?php echo esc_html($idlm_review->source . ' · ' . $idlm_review->email . ' · ' . $idlm_review->phone . ' · ' . $idlm_review->received_at); ?></p>
            <dl><?php foreach (IDLM_Identity_Review::data($idlm_review) as $idlm_field => $idlm_value) : ?>
                <?php if (in_array($idlm_field, array('first_name', 'last_name', 'project_name', 'property_location', 'budget', 'project_timeline'), true) && $idlm_value !== '') : ?>
                    <dt><?php echo esc_html(ucwords(str_replace('_', ' ', $idlm_field))); ?></dt><dd><?php echo esc_html($idlm_value); ?></dd>
                <?php endif; ?>
            <?php endforeach; ?></dl>
            <?php if ($idlm_review->status !== 'pending') : ?>
                <p><?php echo esc_html(sprintf('%s · lead #%d · decided by user #%d · %s', $idlm_review->status, $idlm_review->resolved_lead_id, $idlm_review->resolved_by, $idlm_review->resolved_at)); ?></p>
                <p><?php echo esc_html($idlm_review->resolution_note); ?></p>
            <?php else : ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="idlm_resolve_identity_review">
                <input type="hidden" name="review_id" value="<?php echo esc_attr($idlm_review->id); ?>">
                <?php wp_nonce_field(IDLM_Identity_Review::nonce_action($idlm_review->id)); ?>
                <div class="idlm-field-group">
                <p class="idlm-field"><label class="idlm-form-label" for="decision-<?php echo esc_attr($idlm_review->id); ?>">Decision</label>
                    <select class="idlm-select" required name="decision" id="decision-<?php echo esc_attr($idlm_review->id); ?>"><option value="matched">Use canonical candidate</option><option value="separate">Create separate unassigned lead</option><option value="rejected">Reject submission</option></select></p>
                <p class="idlm-field"><label class="idlm-form-label" for="candidate-<?php echo esc_attr($idlm_review->id); ?>">Canonical candidate (for use canonical candidate)</label>
                    <select class="idlm-select" name="lead_id" id="candidate-<?php echo esc_attr($idlm_review->id); ?>"><option value="0">Select candidate</option>
                    <?php foreach (IDLM_Identity_Review::candidate_ids($idlm_review) as $idlm_id) : $idlm_lead = IDLM_Identity_Review::candidate_lead($idlm_id); ?>
                        <?php if ($idlm_lead && empty($idlm_lead->is_duplicate)) : ?><option value="<?php echo esc_attr($idlm_id); ?>"><?php echo esc_html('#' . $idlm_id . ' · ' . $idlm_lead->first_name . ' ' . $idlm_lead->last_name . ' · ' . $idlm_lead->email . ' · ' . $idlm_lead->phone); ?></option><?php endif; ?>
                    <?php endforeach; ?></select></p>
                <p class="idlm-field"><label class="idlm-form-label" for="note-<?php echo esc_attr($idlm_review->id); ?>">Reason</label><textarea class="idlm-input" required maxlength="1000" rows="3" name="note" id="note-<?php echo esc_attr($idlm_review->id); ?>"></textarea></p>
                </div>
                <button class="idlm-btn idlm-btn-primary" type="submit">Resolve submission</button>
            </form>
            <?php endif; ?>
        </section>
    <?php endforeach; ?>
    <p class="idlm-identity-filters"><a class="idlm-btn idlm-btn-secondary idlm-btn-sm" href="<?php echo esc_url(add_query_arg('status', $idlm_status, admin_url('admin.php?page=idlm_identity_reviews'))); ?>">First page</a>
    <?php if (count($idlm_rows) === 25) : $idlm_last = end($idlm_rows); ?><a class="idlm-btn idlm-btn-secondary idlm-btn-sm" href="<?php echo esc_url(add_query_arg(array('after' => $idlm_last->id, 'status' => $idlm_status), admin_url('admin.php?page=idlm_identity_reviews'))); ?>">Next page</a><?php endif; ?></p>
</div>
