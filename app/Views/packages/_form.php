<?php

use App\Core\Csrf;

$editing = !empty($package['id']);
$features = $features ?: [''];
?>
<?php if (!empty($errors)): ?><div class="alert error"><?= implode('<br>', array_map(static fn ($error) => htmlspecialchars((string) $error), $errors)) ?></div><?php endif; ?>
<form class="record-form package-form" action="<?= $editing ? '/packages/' . (int) $package['id'] : '/packages' ?>" method="post">
    <input type="hidden" name="_token" value="<?= htmlspecialchars(Csrf::token()) ?>">
    <section class="form-card">
        <div class="form-heading"><span>01</span><div><h2>Package details</h2><p>Name and describe the service customers will purchase.</p></div></div>
        <div class="form-grid">
            <label class="wide">Package name *<input name="name" maxlength="150" value="<?= htmlspecialchars((string) ($package['name'] ?? '')) ?>" placeholder="e.g. Business Website" required></label>
            <label class="wide">Description<textarea name="description" rows="5" placeholder="Explain what this package provides..."><?= htmlspecialchars((string) ($package['description'] ?? '')) ?></textarea></label>
        </div>
    </section>
    <section class="form-card">
        <div class="form-heading"><span>02</span><div><h2>Pricing and billing</h2><p>Choose whether this is a one-time or recurring charge.</p></div></div>
        <div class="form-grid">
            <label>Billing type<select id="billing-type" name="billing_type"><option value="one_off" <?= ($package['billing_type'] ?? '') === 'one_off' ? 'selected' : '' ?>>One-off payment</option><option value="recurring" <?= ($package['billing_type'] ?? '') === 'recurring' ? 'selected' : '' ?>>Recurring payment</option></select></label>
            <label id="billing-interval">Billing interval<select name="billing_interval"><option value="monthly" <?= ($package['billing_interval'] ?? '') === 'monthly' ? 'selected' : '' ?>>Monthly</option><option value="yearly" <?= ($package['billing_interval'] ?? '') === 'yearly' ? 'selected' : '' ?>>Yearly</option></select></label>
            <label>Price (£) *<input inputmode="decimal" name="price" value="<?= htmlspecialchars((string) ($package['price'] ?? '')) ?>" placeholder="0.00" required></label>
            <label>Setup fee (£)<input inputmode="decimal" name="setup_fee" value="<?= htmlspecialchars((string) ($package['setup_fee'] ?? '0.00')) ?>" placeholder="0.00"></label>
        </div>
    </section>
    <section class="form-card">
        <div class="form-heading"><span>03</span><div><h2>Included features</h2><p>List the main deliverables or benefits included in this package.</p></div></div>
        <div id="feature-list" class="feature-editor">
            <?php foreach ($features as $feature): ?><div class="feature-row"><span>✓</span><input name="features[]" maxlength="255" value="<?= htmlspecialchars((string) $feature) ?>" placeholder="e.g. Five-page responsive website"><button type="button" class="remove-feature" aria-label="Remove feature">×</button></div><?php endforeach; ?>
        </div>
        <button id="add-feature" class="text-button" type="button">+ Add another feature</button>
    </section>
    <section class="form-card">
        <div class="form-heading"><span>04</span><div><h2>Availability</h2><p>Control whether staff and customers can use this package.</p></div></div>
        <div class="form-grid">
            <label>Status<select name="status"><option value="active" <?= ($package['status'] ?? '') === 'active' ? 'selected' : '' ?>>Active</option><option value="inactive" <?= ($package['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option><option value="archived" <?= ($package['status'] ?? '') === 'archived' ? 'selected' : '' ?>>Archived</option></select></label>
            <label class="toggle-field"><input type="checkbox" name="is_public" value="1" <?= !empty($package['is_public']) ? 'checked' : '' ?>><span><strong>Public package</strong><small>Allow this package to be offered to customers.</small></span></label>
        </div>
    </section>
    <div class="form-actions"><a class="secondary-button" href="<?= $editing ? '/packages/' . (int) $package['id'] : '/packages' ?>">Cancel</a><button class="primary-button" type="submit"><?= $editing ? 'Save changes' : 'Create package' ?></button></div>
</form>
<template id="feature-template"><div class="feature-row"><span>✓</span><input name="features[]" maxlength="255" placeholder="Enter an included feature"><button type="button" class="remove-feature" aria-label="Remove feature">×</button></div></template>
<script>
const billingType=document.getElementById('billing-type'),interval=document.getElementById('billing-interval');
function billingVisibility(){interval.hidden=billingType.value!=='recurring'}billingType.addEventListener('change',billingVisibility);billingVisibility();
const featureList=document.getElementById('feature-list');
document.getElementById('add-feature').addEventListener('click',()=>{featureList.append(document.getElementById('feature-template').content.cloneNode(true));featureList.lastElementChild.querySelector('input').focus()});
featureList.addEventListener('click',event=>{if(event.target.classList.contains('remove-feature')&&featureList.children.length>1)event.target.closest('.feature-row').remove()});
</script>
