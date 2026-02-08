<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/db.php';

// جلب إعدادات البوت (للوصول لـ API Key)
$stmt_s = $pdo->query("SELECT key_name, value FROM secondary_bot_settings");
$bot_settings = $stmt_s->fetchAll(PDO::FETCH_KEY_PAIR);

// معالجة تحديث حالة الطلب
if (isset($_POST['update_status'])) {
    $order_id = $_POST['order_id'];
    $external_id = $_POST['external_id'];
    
    if ($external_id && !empty($bot_settings['smm_api_url']) && !empty($bot_settings['smm_api_key'])) {
        $status = getSMMStatus($bot_settings['smm_api_url'], $bot_settings['smm_api_key'], $external_id);
        if ($status) {
            // توحيد الحالة (lowercase)
            $new_status = strtolower($status);
            $pdo->prepare("UPDATE bot_orders SET status = ? WHERE id = ?")->execute([$new_status, $order_id]);
            $message = "✅ تم تحديث حالة الطلب #$order_id إلى: <b>$new_status</b>";
        } else {
            $error = "❌ فشل جلب الحالة من الموقع (تأكد من الإعدادات أو رقم الطلب).";
        }
    } else {
        $error = "❌ لا يوجد رقم طلب خارجي أو إعدادات API ناقصة.";
    }
}

// معالجة تحديث جميع الطلبات المعلقة
if (isset($_POST['update_all_pending'])) {
    $stmtPending = $pdo->prepare("SELECT id, external_id FROM bot_orders WHERE status IN ('pending', 'in_progress', 'processing') AND external_id IS NOT NULL");
    $stmtPending->execute();
    $pendingOrders = $stmtPending->fetchAll(PDO::FETCH_ASSOC);
    $updatedCount = 0;
    foreach ($pendingOrders as $pOrder) {
        $status = getSMMStatus($bot_settings['smm_api_url'], $bot_settings['smm_api_key'], $pOrder['external_id']);
        if ($status) {
            $pdo->prepare("UPDATE bot_orders SET status = ? WHERE id = ?")->execute([strtolower($status), $pOrder['id']]);
            $updatedCount++;
        }
    }
    $message = "✅ تم تحديث حالة $updatedCount طلب.";
}

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
        
        .btn-sm { padding: 4px 8px; font-size: 0.8rem; border-radius: 4px; border: none; cursor: pointer; background: #3b82f6; color: white; }
        .btn-sm:hover { background: #2563eb; }
        .alert { padding: 10px; border-radius: 8px; margin-bottom: 20px; font-weight: bold; }
        .alert-success { background: #dcfce7; color: #166534; }
        .alert-danger { background: #fee2e2; color: #991b1b; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📜 سجل طلبات البوت</h1>
            <a href="telegram_bot_panel.php" class="back-btn">العودة للوحة البوت</a>
        </div>

        <?php if (isset($message)): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
        <?php endif; ?>
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="post" style="margin-bottom: 20px;">
            <button type="submit" name="update_all_pending" class="back-btn" style="background: #0f172a; color: white; border: none; cursor: pointer;">🔄 تحديث حالة جميع الطلبات المعلقة</button>
        </form>

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
                        <th>إجراءات</th>
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
                                    elseif ($order['status'] == 'pending') { $statusText = 'قيد الانتظار'; }
                                    elseif ($order['status'] == 'in_progress') { $statusText = 'جاري التنفيذ'; }
                                    elseif ($order['status'] == 'processing') { $statusText = 'معالجة'; }
                                    elseif ($order['status'] == 'partial') { $statusText = 'مكتمل جزئياً'; }
                                    ?>
                                    <span class="status-badge <?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
                                </td>
                                <td><?php echo $order['external_id'] ? '#' . $order['external_id'] : '-'; ?></td>
                                <td style="color:#64748b; font-size:0.85rem;"><?php echo date('Y-m-d H:i', $order['created_at']); ?></td>
                                <td>
                                    <?php if ($order['external_id']): ?>
                                    <form method="post" style="display:inline;">
                                        <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                        <input type="hidden" name="external_id" value="<?php echo $order['external_id']; ?>">
                                        <button type="submit" name="update_status" class="btn-sm" title="تحديث الحالة من الموقع">🔄</button>
                                    </form>
                                    <?php endif; ?>
                                </td>
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
<?php
function getSMMStatus($url, $key, $order_id) {
    $post = [
        'key' => $key,
        'action' => 'status',
        'order' => $order_id
    ];
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
    $result = curl_exec($ch);
    curl_close($ch);
    $json = json_decode($result, true);
    return $json['status'] ?? null;
}
?>