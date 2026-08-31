<header class="page-header"><div><div class="eyebrow">Relationships</div><h1>Customers</h1><p>Manage every lead, customer and portal account in one place.</p></div><a class="primary-button" href="/customers/create">+ Add customer</a></header>

<?php if ($flash): ?><div class="alert <?= htmlspecialchars($flash['type']) ?>"><?= htmlspecialchars($flash['message']) ?></div><?php endif; ?>

<section class="list-panel">
    <form class="toolbar" method="get" action="/customers">
        <div class="search-box"><span>⌕</span><input name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search name, company, email or account..."></div>
        <select name="status" onchange="this.form.submit()"><option value="">Current customers</option><?php foreach (['lead' => 'Leads', 'active' => 'Active', 'inactive' => 'Inactive', 'archived' => 'Archived'] as $value => $label): ?><option value="<?= $value ?>" <?= $status === $value ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select>
        <button class="secondary-button" type="submit">Search</button>
    </form>
    <div class="list-summary"><strong><?= number_format($total) ?></strong> customer<?= $total === 1 ? '' : 's' ?></div>

    <?php if ($customers === []): ?>
        <div class="empty-state tall"><span>◎</span><h3>No customers found</h3><p>Add your first customer or change the current filters.</p><a class="primary-button" href="/customers/create">Add a customer</a></div>
    <?php else: ?>
        <div class="table-wrap"><table class="data-table"><thead><tr><th>Customer</th><th>Account</th><th>Status</th><th>Portal</th><th>Added</th><th></th></tr></thead><tbody>
        <?php foreach ($customers as $customer): ?>
            <tr>
                <td><a class="customer-cell" href="/customers/<?= (int) $customer['id'] ?>"><span class="table-avatar"><?= htmlspecialchars(strtoupper(substr((string) ($customer['company_name'] ?: $customer['contact_name']), 0, 1))) ?></span><span><strong><?= htmlspecialchars((string) ($customer['company_name'] ?: $customer['contact_name'])) ?></strong><small><?= htmlspecialchars((string) $customer['email']) ?></small></span></a></td>
                <td><span class="mono"><?= htmlspecialchars((string) $customer['account_number']) ?></span></td>
                <td><span class="status <?= htmlspecialchars((string) $customer['status']) ?>"><?= htmlspecialchars(ucfirst((string) $customer['status'])) ?></span></td>
                <td><?= $customer['user_id'] ? '<span class="portal-on">Enabled</span>' : '<span class="muted">Not enabled</span>' ?></td>
                <td><?= htmlspecialchars(date('j M Y', strtotime((string) $customer['created_at']))) ?></td>
                <td><a class="row-action" href="/customers/<?= (int) $customer['id'] ?>">View ›</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody></table></div>
        <?php if ($pages > 1): ?><nav class="pagination"><?php for ($i = 1; $i <= $pages; $i++): ?><a class="<?= $i === $page ? 'active' : '' ?>" href="?search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>&page=<?= $i ?>"><?= $i ?></a><?php endfor; ?></nav><?php endif; ?>
    <?php endif; ?>
</section>
