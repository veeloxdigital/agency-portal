<?php

use App\Core\Csrf;

$editing = !empty($customer['id']);
?>
<?php if (!empty($errors)): ?><div class="alert error"><?= implode('<br>', array_map(static fn ($error) => htmlspecialchars((string) $error), $errors)) ?></div><?php endif; ?>
<form class="record-form" action="<?= $editing ? '/customers/' . (int) $customer['id'] : '/customers' ?>" method="post">
    <input type="hidden" name="_token" value="<?= htmlspecialchars(Csrf::token()) ?>">
    <section class="form-card">
        <div class="form-heading"><span>01</span><div><h2>Customer details</h2><p>The main person or business attached to this account.</p></div></div>
        <div class="form-grid">
            <label>Customer type<select name="type"><option value="business" <?= ($customer['type'] ?? '') === 'business' ? 'selected' : '' ?>>Business</option><option value="individual" <?= ($customer['type'] ?? '') === 'individual' ? 'selected' : '' ?>>Individual</option></select></label>
            <label>Account status<select name="status"><option value="lead" <?= ($customer['status'] ?? '') === 'lead' ? 'selected' : '' ?>>Lead</option><option value="active" <?= ($customer['status'] ?? '') === 'active' ? 'selected' : '' ?>>Active</option><option value="inactive" <?= ($customer['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option><option value="archived" <?= ($customer['status'] ?? '') === 'archived' ? 'selected' : '' ?>>Archived</option></select></label>
            <label class="wide">Company name<input name="company_name" value="<?= htmlspecialchars((string) ($customer['company_name'] ?? '')) ?>" placeholder="Required for business customers"></label>
            <label>Contact name *<input name="contact_name" value="<?= htmlspecialchars((string) ($customer['contact_name'] ?? '')) ?>" required></label>
            <label>Email address *<input type="email" name="email" value="<?= htmlspecialchars((string) ($customer['email'] ?? '')) ?>" required></label>
            <label>Telephone<input name="phone" value="<?= htmlspecialchars((string) ($customer['phone'] ?? '')) ?>"></label>
        </div>
    </section>
    <section class="form-card">
        <div class="form-heading"><span>02</span><div><h2>Billing address</h2><p>This address will be used on invoices.</p></div></div>
        <div class="form-grid">
            <label class="wide">Address line 1<input name="address_line_1" value="<?= htmlspecialchars((string) ($customer['address_line_1'] ?? '')) ?>"></label>
            <label class="wide">Address line 2<input name="address_line_2" value="<?= htmlspecialchars((string) ($customer['address_line_2'] ?? '')) ?>"></label>
            <label>Town or city<input name="town_city" value="<?= htmlspecialchars((string) ($customer['town_city'] ?? '')) ?>"></label>
            <label>County<input name="county" value="<?= htmlspecialchars((string) ($customer['county'] ?? '')) ?>"></label>
            <label>Postcode<input name="postcode" value="<?= htmlspecialchars((string) ($customer['postcode'] ?? '')) ?>"></label>
            <label>Country code<input name="country_code" maxlength="2" value="<?= htmlspecialchars((string) ($customer['country_code'] ?? 'GB')) ?>"></label>
        </div>
    </section>
    <section class="form-card">
        <div class="form-heading"><span>03</span><div><h2>Internal notes</h2><p>Only administrators and staff can see these notes.</p></div></div>
        <label>Notes<textarea name="internal_notes" rows="6" placeholder="Add useful context about this customer..."><?= htmlspecialchars((string) ($customer['internal_notes'] ?? '')) ?></textarea></label>
    </section>
    <div class="form-actions"><a class="secondary-button" href="<?= $editing ? '/customers/' . (int) $customer['id'] : '/customers' ?>">Cancel</a><button class="primary-button" type="submit"><?= $editing ? 'Save changes' : 'Create customer' ?></button></div>
</form>
