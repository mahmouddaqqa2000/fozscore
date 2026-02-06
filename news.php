<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

$stmt = $pdo->query("SELECT * FROM news ORDER BY created_at DESC");
$news_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

$settings = get_site_settings($pdo);
$site_name = $settings['site_name'];
$favicon = $settings['favicon'];

// --- إعدادات SEO ---
$seo_title = "أخبار الرياضة وكرة القدم - " . $site_name;
$seo_desc = "تابع أحدث أخبار كرة القدم العالمية والمحلية، تغطية حصرية للدوريات الأوروبية والعربية، انتقالات اللاعبين، وتحليلات المباريات على FozScore.";
$seo_keywords = "أخبار رياضة, كرة قدم, أخبار الدوري الإنجليزي, أخبار الدوري الإسباني, انتقالات, FozScore, أخبار اليوم";
$seo_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
// -------------------
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $seo_title; ?></title>
    <?php if ($favicon): ?><link rel="icon" href="<?php echo htmlspecialchars($favicon); ?>"><?php endif; ?>
    <meta name="description" content="<?php echo htmlspecialchars($seo_desc); ?>">
    <meta name="keywords" content="<?php echo htmlspecialchars($seo_keywords); ?>">
    <link rel="canonical" href="<?php echo $seo_url; ?>">

    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?php echo $seo_url; ?>">
    <meta property="og:title" content="<?php echo $seo_title; ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($seo_desc); ?>">
    
    <!-- Twitter -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?php echo $seo_title; ?>">
    <meta name="twitter:description" content="<?php echo htmlspecialchars($seo_desc); ?>">

    <!-- Schema.org JSON-LD -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "CollectionPage",
      "headline": "<?php echo $seo_title; ?>",
      "description": "<?php echo htmlspecialchars($seo_desc); ?>",
      "url": "<?php echo $seo_url; ?>",
      "mainEntity": {
        "@type": "ItemList",
        "itemListElement": [
          <?php 
          $list_items = [];
          $pos = 1;
          foreach (array_slice($news_list, 0, 10) as $news) {
              $news_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]/view_news.php?id=" . $news['id'];
              $list_items[] = '{ "@type": "ListItem", "position": ' . $pos++ . ', "url": "' . $news_url . '", "name": "' . htmlspecialchars($news['title']) . '" }';
          }
          echo implode(',', $list_items);
          ?>
        ]
      }
    }
    </script>

    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #1e293b;
            --secondary: #2563eb;
            --bg: #f8fafc;
            --card: #ffffff;
            --text: #0f172a;
            --text-light: #64748b;
            --border: #e2e8f0;
        }
        body { font-family: 'Tajawal', sans-serif; background-color: var(--bg); margin:0; color: var(--text); }
        .container { max-width:1000px; margin:2rem auto; padding:0 1rem; }
        .page-title { text-align:center; font-size:1.8rem; color: var(--primary); margin-bottom:2rem; font-weight: 800; }
        
        .news-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; }
        .news-card { 
            background: var(--card); 
            border-radius: 16px; 
            overflow: hidden; 
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); 
            border: 1px solid var(--border); 
            display: flex; 
            flex-direction: column; 
            transition: transform 0.2s, box-shadow 0.2s;
            text-decoration: none;
            color: inherit;
        }
        .news-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1);
        }
        .news-img { width: 100%; height: 200px; object-fit: cover; }
        .news-body { padding: 1.5rem; flex: 1; display: flex; flex-direction: column; }
        .news-title { font-size: 1.1rem; font-weight: 700; margin: 0 0 10px 0; color: var(--primary); line-height: 1.5; }
        .news-summary { font-size: 0.95rem; color: var(--text-light); margin-bottom: 15px; line-height: 1.6; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
        .news-footer { margin-top: auto; display: flex; justify-content: space-between; align-items: center; font-size: 0.85rem; color: #94a3b8; }
        .read-more { color: var(--secondary); font-weight: 700; font-size: 0.9rem; }
        
        @media (max-width: 600px) {
            .news-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/header.php'; ?>
    <div class="container">
        <h1 class="page-title">الأخبار الرياضية</h1>
        
        <?php if (empty($news_list)): ?>
            <div style="text-align:center; padding:3rem; color:#64748b;">لا توجد أخبار حالياً.</div>
        <?php else: ?>
            <div class="news-grid">
                <?php foreach ($news_list as $news): ?>
                    <a href="view_news.php?id=<?php echo $news['id']; ?>" class="news-card">
                        <?php if ($news['image_url']): ?>
                            <img src="<?php echo htmlspecialchars($news['image_url']); ?>" alt="صورة الخبر" class="news-img">
                        <?php else: ?>
                            <div style="height:200px; background:#f1f5f9; display:flex; align-items:center; justify-content:center; color:#94a3b8;">لا توجد صورة</div>
                        <?php endif; ?>
                        <div class="news-body">
                            <h3 class="news-title"><?php echo htmlspecialchars($news['title']); ?></h3>
                            <?php if (!empty($news['summary']) && $news['summary'] !== $news['title']): ?>
                                <div class="news-summary"><?php echo htmlspecialchars($news['summary']); ?></div>
                            <?php endif; ?>
                            <div class="news-footer">
                                <span>📅 <?php echo date('Y-m-d', strtotime($news['created_at'])); ?></span>
                                <span class="read-more">اقرأ المزيد &larr;</span>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    <?php include __DIR__ . '/footer.php'; ?>
</body>
</html>
