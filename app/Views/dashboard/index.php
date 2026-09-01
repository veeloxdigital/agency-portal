<?php $outstanding = '£' . number_format($metrics['outstanding'] / 100, 2); $isCustomer = ($user['role'] ?? '') === 'customer'; ?>
<header class="page-header">
    <div>
        <div class="eyebrow">Overview</div>
        <h1>Good <?= (int) date('G') < 12 ? 'morning' : ((int) date('G') < 18 ? 'afternoon' : 'evening') ?>, <?= htmlspecialchars(explode(' ', (string) $user['name'])[0]) ?>.</h1>
        <p><?= $isCustomer ? 'Here’s an overview of your Veelox Digital account.' : 'Here’s what’s happening across Veelox Digital today.' ?></p>
    </div>
    <?php if (($user['role'] ?? '') !== 'customer'): ?><a class="primary-button" href="/customers/create">+ Add customer</a><?php endif; ?>
</header>

<?php if ($isCustomer): ?>
<section class="metric-grid customer-metrics">
    <article class="metric-card"><span class="metric-icon blue">◇</span><div><small>Your active services</small><strong><?= number_format($metrics['orders']) ?></strong><em>Services on your account</em></div></article>
    <article class="metric-card"><span class="metric-icon amber">£</span><div><small>Your balance</small><strong><?= htmlspecialchars($outstanding) ?></strong><em>Currently awaiting payment</em></div></article>
    <article class="metric-card"><span class="metric-icon green">✦</span><div><small>Your support tickets</small><strong><?= number_format($metrics['tickets']) ?></strong><em>Currently open</em></div></article>
</section>
<section class="dashboard-grid customer-dashboard">
    <article class="panel recent-services"><div class="panel-heading"><div><span class="eyebrow">Your account</span><h2>Recent services</h2></div></div>
    <?php if ($recentOrders === []): ?><div class="empty-state"><span>◇</span><h3>No services yet</h3><p>Your services will appear here after they are added to your account.</p></div><?php else: ?><div class="portal-order-list"><?php foreach ($recentOrders as $item): ?><div><span class="metric-icon blue">◇</span><span><strong><?= htmlspecialchars((string)$item['description']) ?></strong><small><?= htmlspecialchars((string)$item['order_number']) ?> · <?= $item['billing_type']==='one_off'?'One-off':htmlspecialchars(ucfirst((string)$item['billing_interval'])) ?></small></span><span class="status <?= htmlspecialchars((string)$item['status']) ?>"><?= htmlspecialchars(ucwords(str_replace('_',' ',(string)$item['status']))) ?></span></div><?php endforeach; ?></div><?php endif; ?></article>
    <article class="panel account-help"><div class="panel-heading"><div><span class="eyebrow">Support</span><h2>Need some help?</h2></div></div><p>Contact the Veelox Digital team and follow replies securely in your account.</p><a class="primary-button" href="/portal/tickets/create">Open a support ticket</a></article>
</section>
<?php else: ?>
<section class="metric-grid">
    <article class="metric-card"><span class="metric-icon lilac">◎</span><div><small>Active customers</small><strong><?= number_format($metrics['customers']) ?></strong><em>Customer accounts</em></div></article>
    <article class="metric-card"><span class="metric-icon blue">◇</span><div><small>Active orders</small><strong><?= number_format($metrics['orders']) ?></strong><em>Pending, awaiting payment and active</em></div></article>
    <article class="metric-card"><span class="metric-icon amber">£</span><div><small>Outstanding</small><strong><?= htmlspecialchars($outstanding) ?></strong><em>Awaiting payment</em></div></article>
    <article class="metric-card"><span class="metric-icon green">✦</span><div><small>Open tickets</small><strong><?= number_format($metrics['tickets']) ?></strong><em>Need attention</em></div></article>
</section>

<section class="dashboard-grid">
    <article class="panel">
        <div class="panel-heading"><div><span class="eyebrow">Revenue</span><h2>Performance overview</h2></div><span class="pill">This month</span></div>
        <div class="empty-state"><span>↗</span><h3>Revenue reporting is ready</h3><p>Charts will populate as invoices and payments are recorded.</p></div>
    </article>
    <article class="panel quick-panel">
        <div class="panel-heading"><div><span class="eyebrow">Shortcuts</span><h2>Quick actions</h2></div></div>
        <?php if (($user['role'] ?? '') !== 'customer'): ?><a href="/customers/create"><span>◎</span><div><strong>Add a customer</strong><small>Create a new customer account</small></div><b>›</b></a><?php endif; ?>
        <?php if (($user['role'] ?? '') !== 'customer'): ?><a href="/packages/create"><span>▦</span><div><strong>Create a package</strong><small>Add a one-off or recurring service</small></div><b>›</b></a><?php endif; ?>
        <?php if (($user['role'] ?? '') !== 'customer'): ?><a href="/orders/create"><span>◇</span><div><strong>Create an order</strong><small>Assign a service to a customer</small></div><b>›</b></a><?php endif; ?>
        <a href="/invoices/create"><span>▤</span><div><strong>Raise an invoice</strong><small>Create a customer invoice</small></div><b>›</b></a>
    </article>
</section>
<?php endif; ?>
