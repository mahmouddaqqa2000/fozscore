<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

if (!isset($_GET['id'])) {
    header('Location: news.php');
    exit;
}

$id = (int)$_GET['id'];
$stmt = $pdo->prepare("SELECT * FROM news WHERE id = ?");
$stmt->execute([$id]);
$news = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$news) {
    die('الخبر غير موجود');
}

$settings = get_site_settings($pdo);
$site_name = $settings['site_name'];
$favicon = $settings['favicon'];

// --- إعدادات SEO ---
$seo_title = htmlspecialchars($news['title']) . ' | ' . $site_name;
$summary_text = !empty($news['summary']) ? $news['summary'] : mb_substr(strip_tags($news['content']), 0, 160) . '...';
$seo_desc = htmlspecialchars($summary_text);
$seo_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
$seo_image = !empty($news['image_url']) ? $news['image_url'] : ((isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]/assets/default-news.jpg");
$pub_date = date('c', strtotime($news['created_at']));
// -------------------

// --- جلب أخبار ذات صلة بناءً على الكلمات المفتاحية ---
$keywords = [];
$words = explode(' ', $news['title']);
foreach ($words as $word) {
    $word = trim($word);
    // استبعاد الكلمات القصيرة (حروف الجر وغيرها) والرموز
    if (mb_strlen($word, 'UTF-8') >= 4) {
        $keywords[] = $word;
    }
}

$related_news = [];
$exclude_ids = [$id];

if (!empty($keywords)) {
    $sql = "SELECT id, title, image_url, created_at FROM news WHERE id != ?";
    $params = [$id];
    $conditions = [];
    foreach ($keywords as $word) {
        $conditions[] = "title LIKE ?";
        $params[] = "%$word%";
    }
    if (!empty($conditions)) {
        $sql .= " AND (" . implode(' OR ', $conditions) . ")";
    }
    $sql .= " ORDER BY created_at DESC LIMIT 3";
    $stmt_related = $pdo->prepare($sql);
    $stmt_related->execute($params);
    $related_news = $stmt_related->fetchAll(PDO::FETCH_ASSOC);
}

// إذا لم نجد ما يكفي من الأخبار ذات الصلة، نكمل العدد من أحدث الأخبار
if (count($related_news) < 3) {
    foreach ($related_news as $r) $exclude_ids[] = $r['id'];
    $limit = 3 - count($related_news);
    $placeholders = implode(',', array_fill(0, count($exclude_ids), '?'));
    $stmt_latest = $pdo->prepare("SELECT id, title, image_url, created_at FROM news WHERE id NOT IN ($placeholders) ORDER BY created_at DESC LIMIT $limit");
    $stmt_latest->execute($exclude_ids);
    $latest_fallback = $stmt_latest->fetchAll(PDO::FETCH_ASSOC);
    $related_news = array_merge($related_news, $latest_fallback);
}
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $seo_title; ?></title>
    <?php
    // تحديد رابط Base بشكل ديناميكي صحيح
    $base_href = '/';
    if (!empty($settings['site_url'])) {
        $base_href = rtrim($settings['site_url'], '/') . '/';
    } else {
        $base_href = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/';
    }
    ?>
    <base href="<?php echo htmlspecialchars($base_href); ?>">
    <?php if ($favicon): ?><link rel="icon" href="<?php echo htmlspecialchars($favicon); ?>"><?php endif; ?>
    <meta name="description" content="<?php echo $seo_desc; ?>">
    <link rel="canonical" href="<?php echo $seo_url; ?>">

    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="article">
    <meta property="og:url" content="<?php echo $seo_url; ?>">
    <meta property="og:title" content="<?php echo $seo_title; ?>">
    <meta property="og:description" content="<?php echo $seo_desc; ?>">
    <meta property="og:image" content="<?php echo $seo_image; ?>">
    <meta property="article:published_time" content="<?php echo $pub_date; ?>">

    <!-- Twitter -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?php echo $seo_title; ?>">
    <meta name="twitter:description" content="<?php echo $seo_desc; ?>">
    <meta name="twitter:image" content="<?php echo $seo_image; ?>">

    <!-- Schema.org NewsArticle -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "NewsArticle",
      "headline": "<?php echo htmlspecialchars($news['title']); ?>",
      "image": ["<?php echo $seo_image; ?>"],
      "datePublished": "<?php echo $pub_date; ?>",
      "dateModified": "<?php echo $pub_date; ?>",
      "author": { "@type": "Organization", "name": "FozScore" }
    }
    </script>

    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Tajawal', sans-serif; background:#f7f7f7; margin:0; color:#333 }
        .container { max-width:800px; margin:2rem auto; padding:0 1rem; }
        .news-card { background:#fff; border-radius:16px; overflow:hidden; box-shadow:0 4px 15px rgba(0,0,0,0.05); }
        .news-image { width:100%; height:400px; object-fit:cover; }
        .news-content { padding:2rem; }
        .news-title { font-size:2rem; font-weight:800; color:#1e293b; margin-bottom:1rem; }
        .news-date { color:#64748b; font-size:0.9rem; margin-bottom:1.5rem; display:block; }
        .news-body { font-size:1.1rem; line-height:1.8; color:#334155; white-space: pre-line; }
        .back-btn { display:inline-block; margin-top:2rem; color:#2563eb; text-decoration:none; font-weight:700; }
        .back-btn:hover { text-decoration: underline; }
        
        @media (max-width: 600px) { 
            .news-image { height: 250px; } 
            .news-title { font-size: 1.5rem; } 
            .news-content { padding: 1.5rem; }
        }

        /* News Section for Single News Page */
        .news-section-container {
            margin-top: 2rem;
            padding: 1.5rem;
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            border: 1px solid #e2e8f0;
        }
        .section-title-news {
            font-size: 1.25rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-bottom: 10px;
            border-bottom: 1px solid #e2e8f0;
        }
        .news-grid-news-page {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 1.5rem;
        }
        .news-card-news-page {
            background: #f8fafc; /* Lighter background for cards within the section */
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid #e2e8f0;
            text-decoration: none;
            color: inherit;
            display: block;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .news-card-news-page:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .news-img-news-page {
            width: 100%;
            height: 140px;
            object-fit: cover;
        }
        .news-img-placeholder-news-page {
            width: 100%;
            height: 140px;
            background: #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #64748b;
            font-size: 0.9rem;
        }
        .news-body-news-page {
            padding: 1rem;
        }
        .news-title-news-page {
            font-size: 0.95rem;
            font-weight: 700;
            margin: 0 0 0.5rem;
            line-height: 1.4;
            color: #1e293b;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .news-date-news-page {
            font-size: 0.8rem;
            color: #64748b;
        }
        .view-all-btn {
            font-size: 0.9rem;
            color: #2563eb;
            text-decoration: none;
            font-weight: 600;
        }
        .view-all-btn:hover {
            text-decoration: underline;
        }
        @media (max-width: 768px) {
            .news-grid-news-page {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/header.php'; ?>
    <div class="container">
        <div class="news-card">
            <?php if (!empty($news['image_url'])): ?>
            <img src="<?php echo htmlspecialchars($news['image_url']); ?>" class="news-image" alt="<?php echo htmlspecialchars($news['title']); ?>">
            <?php endif; ?>
            <div class="news-content">
                <h1 class="news-title"><?php echo htmlspecialchars($news['title']); ?></h1>
                <span class="news-date">📅 <?php echo htmlspecialchars($news['created_at']); ?></span>
                <div class="news-body"><?php echo htmlspecialchars($news['content']); ?></div>
                <a href="news.php" class="back-btn">← العودة للأخبار</a>
            </div>
        </div>
    </div>

    <?php if (!empty($related_news)): ?>
    <div class="container">
        <div class="news-section-container">
            <div class="section-title-news">
                <span>أخبار ذات صلة</span>
                <a href="news.php" class="view-all-btn">عرض الكل &larr;</a>
            </div>
            <div class="news-grid-news-page">
                <?php foreach ($related_news as $news_item): ?>
                    <a href="view_news.php?id=<?php echo $news_item['id']; ?>" class="news-card-news-page">
                        <?php if ($news_item['image_url']): ?>
                            <img src="<?php echo htmlspecialchars($news_item['image_url']); ?>" alt="<?php echo htmlspecialchars($news_item['title']); ?>" class="news-img-news-page">
                        <?php else: ?>
                            <div class="news-img-placeholder-news-page">لا توجد صورة</div>
                        <?php endif; ?>
                        <div class="news-body-news-page">
                            <h3 class="news-title-news-page"><?php echo htmlspecialchars($news_item['title']); ?></h3>
                            <div class="news-date-news-page"><?php echo date('Y/m/d', strtotime($news_item['created_at'])); ?></div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <?php include __DIR__ . '/footer.php'; ?>
</body>
</html>