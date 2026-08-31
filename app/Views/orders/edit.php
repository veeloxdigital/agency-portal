<header class="page-header compact"><div><a class="back-link" href="/orders/<?= (int)$order['id'] ?>">← Order details</a><div class="eyebrow">Order settings</div><h1>Edit <?= htmlspecialchars((string)$order['order_number']) ?></h1><p>Update pricing, dates, assignment and order details.</p></div></header>
<?php require BASE_PATH.'/app/Views/orders/_form.php'; ?>
