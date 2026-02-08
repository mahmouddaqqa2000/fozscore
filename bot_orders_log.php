<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/db.php';

// إعدادات التصفح (Pagination)
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 50;
$offset = ($page - 1) * $limit;

// جلب الطلبات مع اسم المستخدم
$stmt = $pdo->prepare("SELECT o.*, u.username 
                       FROM bot_orders o 
                       LEFT JOIN bot_users u ON o.chat_id = u.chat_id 
                       ORDER BY o.id DESC LIMIT ? OFFSET ?");
$stmt->execute([$limit, $offset]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// حساب عدد الصفحات
$total_orders = $pdo->query("SELECT COUNT(*) FROM bot_orders")->fetchColumn();
$total_pages = ceil($total_orders / $limit);
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>سجل طلبات البوت - FozScore</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Tajawal', sans-serif; background-color: #f8fafc; color: #1e293b; margin: 0; padding: 20px; }
        .container { max-width: 1200px; margin: 0 auto; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .header h1 { margin: 0; color: #0f172a; }
        .back-btn { text-decoration: none; background: #e2e8f0; color: #475569; padding: 10px 20px; border-radius: 8px; font-weight: bold; transition: 0.2s; }
        .back-btn:hover { background: #cbd5e1; color: #1e293b; }
        
        .card { background: white; padding: 25px; border-radius: 16px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); margin-bottom: 25px; border: 1px solid #e2e8f0; overflow-x: auto; }
        
        table { width: 100%; border-collapse: collapse; font-size: 0.95rem; }
        th { background: #f1f5f9; padding: 12px; text-align: right; font-weight: 700; color: #475569; border-bottom: 2px solid #e2e8f0; white-space: nowrap; }
        td { padding: 12px; border-bottom: 1px solid #e2e8f0; vertical-align: middle; }
        tr:last-child td { border-bottom: none; }
        tr:hover { background-color: #f8fafc; }
        
        .status-badge { padding: 4px 10px; border-radius: 20px; font-size: 0.8rem; font-weight: 700; }
        .status-pending { background: #fef3c7; color: #d97706; }
        .status-completed { background: #dcfce7; color: #166534; }
        .status-cancelled { background: #fee2e2; color: #991b1b; }
        
        .pagination { display: flex; justify-content: center; gap: 5px; margin-top: 20px; }
        .page-link { padding: 8px 12px; background: white; border: 1px solid #e2e8f0; border-radius: 6px; text-decoration: none; color: #1e293b; }
        .page-link.active { background: #2563eb; color: white; border-color: #2563eb; }
        
        .link-cell { max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; direction: ltr; text-align: left; }
        .link-cell a { color: #2563eb; text-decoration: none; }
        .link-cell a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📜 سجل طلبات البوت</h1>
            <a href="telegram_bot_panel.php" class="back-btn">العودة للوحة البوت</a>
        </div>

        <div class="card">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>المستخدم</th>
                        <th>الخدمة</th>
                        <th>الكمية</th>
                        <th>التكلفة</th>
                        <th>الرابط</th>
                        <th>الحالة</th>
                        <th>رقم خارجي</th>
                        <th>التاريخ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($orders)): ?>
                        <tr><td colspan="9" style="text-align:center; padding: 20px; color: #64748b;">لا توجد طلبات حتى الآن.</td></tr>
                    <?php else: ?>
                        <?php foreach ($orders as $order): ?>
                            <tr>
                                <td><?php echo $order['id']; ?></td>
                                <td>
                                    <?php echo htmlspecialchars($order['username'] ?? 'غير معروف'); ?>
                                    <br><span style="font-size:0.8em; color:#64748b;"><?php echo $order['chat_id']; ?></span>
                                </td>
                                <td><?php echo htmlspecialchars($order['service_name']); ?></td>
                                <td><?php echo number_format($order['qty']); ?></td>
                                <td style="color:#16a34a; font-weight:bold;">$<?php echo number_format($order['cost'], 2); ?></td>
                                <td class="link-cell">
                                    <a href="<?php echo htmlspecialchars($order['link']); ?>" target="_blank"><?php echo htmlspecialchars($order['link']); ?></a>
                                </td>
                                <td>
                                    <?php 
                                    $statusClass = 'status-pending';
                                    $statusText = $order['status'];
                                    if ($order['status'] == 'completed') { $statusClass = 'status-completed'; $statusText = 'مكتمل'; }
                                    elseif ($order['status'] == 'cancelled') { $statusClass = 'status-cancelled'; $statusText = 'ملغي'; }
                                    elseif ($order['status'] == 'pending') { $statusText = 'قيد التنفيذ'; }
                                    ?>
                                    <span class="status-badge <?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
                                </td>
                                <td><?php echo $order['external_id'] ? '#' . $order['external_id'] : '-'; ?></td>
                                <td style="color:#64748b; font-size:0.85rem;"><?php echo date('Y-m-d H:i', $order['created_at']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="?page=<?php echo $i; ?>" class="page-link <?php echo $i === $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>