<header class="page-header"><div><div class="eyebrow">Services</div><h1>Plans &amp; Packages</h1><p>Build one-off, monthly and yearly services for customer orders.</p></div><a class="primary-button" href="/packages/create">+ Create package</a></header>
<?php if ($flash): ?><div class="alert <?= htmlspecialchars($flash['type']) ?>"><?= htmlspecialchars($flash['message']) ?></div><?php endif; ?>
<section class="list-panel">
    <form class="toolbar package-toolbar" method="get" action="/packages">
        <div class="search-box"><span>⌕</span><input name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search plans and packages..."></div>
        <select name="billing" onchange="this.form.submit()"><option value="">All billing</option><option value="one_off" <?= $billing === 'one_off' ? 'selected' : '' ?>>One-off</option><option value="monthly" <?= $billing === 'monthly' ? 'selected' : '' ?>>Monthly</option><option value="yearly" <?= $billing === 'yearly' ? 'selected' : '' ?>>Yearly</option></select>
        <select name="status" onchange="this.form.submit()"><option value="">Current packages</option><option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option><option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive</option><option value="archived" <?= $status === 'archived' ? 'selected' : '' ?>>Archived</option></select>
        <button class="secondary-button" type="submit">Search</button>
    </form>
    <?php if ($packages === []): ?><div class="empty-state tall"><span>▦</span><h3>No packages found</h3><p>Create your first service or change the filters.</p><a class="primary-button" href="/packages/create">Create a package</a></div><?php else: ?>
    <div class="package-list">
        <?php foreach ($packages as $item): $billingLabel = $item['billing_type'] === 'one_off' ? 'One-off' : ucfirst((string) $item['billing_interval']); ?>
        <a class="package-row" href="/packages/<?= (int) $item['id'] ?>">
            <span class="package-icon">▦</span><span class="package-name"><strong><?= htmlspecialchars((string) $item['name']) ?></strong><small><?= htmlspecialchars($billingLabel) ?> · <?= $item['is_public'] ? 'Public' : 'Internal only' ?></small></span>
            <span class="package-price"><strong>£<?= number_format($item['price_amount']/100,2) ?></strong><small><?= $item['billing_type']==='recurring' ? 'per '.rtrim((string)$item['billing_interval'],'ly') : 'single payment' ?></small></span>
            <span class="package-stat"><strong><?= number_format((int) $item['order_count']) ?></strong><small>orders</small></span>
            <span class="package-stat"><strong>£<?= number_format($item['revenue_amount']/100,2) ?></strong><small>revenue</small></span>
            <span class="status <?= htmlspecialchars((string) $item['status']) ?>"><?= htmlspecialchars(ucfirst((string) $item['status'])) ?></span><b>›</b>
        </a>
        <?php endforeach; ?>
    </div><?php endif; ?>
</section>
