<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

$settings = get_site_settings($pdo);
$favicon = $settings['favicon'];

$query = isset($_GET['q']) ? trim($_GET['q']) : '';
$matches = [];
$news_results = [];

if (!empty($query)) {
    $term = '%' . $query . '%';
    
    // البحث في المباريات (الفرق أو البطولة)
    $stmt = $pdo->prepare("SELECT * FROM matches WHERE team_home LIKE ? OR team_away LIKE ? OR championship LIKE ? ORDER BY match_date DESC LIMIT 30");
    $stmt->execute([$term, $term, $term]);
    $matches = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // البحث في الأخبار (العنوان أو الملخص)
    $stmt = $pdo->prepare("SELECT * FROM news WHERE title LIKE ? OR summary LIKE ? ORDER BY created_at DESC LIMIT 10");
    $stmt->execute([$term, $term]);
    $news_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>بحث <?php echo $query ? '- ' . htmlspecialchars($query) : ''; ?> - FozScore</title>
    <?php if ($favicon): ?><link rel="icon" href="<?php echo htmlspecialchars($favicon); ?>"><?php endif; ?>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #1e293b;
            --secondary: #2563eb;
            --bg: #f8fafc;
            --card: #ffffff;
            --text: #0f172a;
            --text-light: #64748b;
            --border: #e2e8f0;
            --accent: #ef4444;
        }
        body {
            font-family: 'Tajawal', sans-serif;
            background-color: var(--bg);
            color: var(--text);
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 1000px;
            margin: 2rem auto;
            padding: 0 1rem;
            min-height: 60vh;
        }
        
        /* Search Box */
        .search-box-container {
            background: var(--card);
            padding: 2rem;
            border-radius: 16px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            margin-bottom: 2rem;
            text-align: center;
            border: 1px solid var(--border);
        }
        .search-form {
            display: flex;
            gap: 10px;
            max-width: 600px;
            margin: 0 auto;
        }
        .search-input {
            flex: 1;
            padding: 12px 20px;
            border-radius: 50px;
            border: 2px solid var(--border);
            font-family: inherit;
            font-size: 1rem;
            outline: none;
            transition: border-color 0.2s;
        }
        .search-input:focus {
            border-color: var(--secondary);
        }
        .search-btn {
            background: var(--secondary);
            color: white;
            border: none;
            padding: 0 25px;
            border-radius: 50px;
            font-family: inherit;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.2s;
        }
        .search-btn:hover {
            background: #1d4ed8;
        }

        /* Section Titles */
        .section-title {
            font-size: 1.4rem;
            font-weight: 800;
            color: var(--primary);
            margin: 2rem 0 1rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        /* Matches Styles (Reused) */
        .match-card {
            background: var(--card);
            border-radius: 16px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            overflow: hidden;
            border: 1px solid var(--border);
            margin-bottom: 2rem;
        }
        .match-item {
            border-bottom: 1px solid var(--border);
            transition: background-color 0.2s;
        }
        .match-item:last-child { border-bottom: none; }
        .match-item:hover { background-color: #f8fafc; }
        .match-link {
            display: flex;
            align-items: center;
            padding: 1rem 1.5rem;
            text-decoration: none;
            color: inherit;
            gap: 1rem;
        }
        .match-info {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 15px;
        }
        .team {
            flex: 1;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.95rem;
        }
        .team.home { justify-content: flex-start; text-align: right; }
        .team.away { justify-content: flex-end; text-align: left; }
        .score-box {
            background: var(--primary);
            color: #fff;
            padding: 4px 12px;
            border-radius: 8px;
            font-weight: 700;
            min-width: 60px;
            text-align: center;
            font-size: 1rem;
        }
        .score-box.vs { background: #e2e8f0; color: var(--text); }
        .match-date-small {
            font-size: 0.8rem;
            color: var(--text-light);
            margin-top: 4px;
            text-align: center;
        }

        /* News Styles (Reused) */
        .news-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
        }
        .news-card {
            background: var(--card);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            border: 1px solid var(--border);
            text-decoration: none;
            color: inherit;
            display: flex;
            flex-direction: column;
            transition: transform 0.2s;
        }
        .news-card:hover { transform: translateY(-5px); }
        .news-img { width: 100%; height: 160px; object-fit: cover; }
        .news-body { padding: 1rem; flex: 1; display: flex; flex-direction: column; }
        .news-title { font-size: 1rem; font-weight: 700; margin: 0 0 0.5rem; line-height: 1.5; color: var(--primary); }
        .news-date { font-size: 0.8rem; color: var(--text-light); margin-top: auto; }

        .no-results {
            text-align: center;
            padding: 3rem;
            color: var(--text-light);
            font-size: 1.1rem;
        }

        /* Dark Mode */
        body.dark-mode {
            --primary: #f1f5f9;
            --secondary: #60a5fa;
            --bg: #0f172a;
            --card: #1e293b;
            --text: #f1f5f9;
            --text-light: #94a3b8;
            --border: #334155;
        }
        body.dark-mode .search-box-container { background: var(--card); border-color: var(--border); }
        body.dark-mode .search-input { background: #0f172a; border-color: var(--border); color: white; }
        body.dark-mode .match-card, body.dark-mode .news-card { background: var(--card); border-color: var(--border); }
        body.dark-mode .match-item:hover { background-color: #2d3748; }
        body.dark-mode .score-box { background: #334155; color: #fff; }
        body.dark-mode .score-box.vs { background: #334155; color: #cbd5e1; }
        body.dark-mode .news-title { color: var(--text); }
        
        /* Toggle Button */
        .theme-toggle { position: fixed; bottom: 20px; left: 20px; width: 50px; height: 50px; border-radius: 50%; background: #1e293b; color: #fff; border: none; font-size: 24px; cursor: pointer; box-shadow: 0 4px 12px rgba(0,0,0,0.3); z-index: 1000; transition: all 0.3s ease; display: flex; align-items: center; justify-content: center; }
        .theme-toggle:hover { transform: scale(1.1); }
        body.dark-mode .theme-toggle { background: var(--secondary); color: #fff; }
        
        /* Mobile Responsive */
        @media (max-width: 768px) {
            .match-link { flex-direction: column; gap: 10px; }
            .match-info { width: 100%; justify-content: space-between; }
            .team { font-size: 0.9rem; flex-direction: column; gap: 5px; }
            .team.away { flex-direction: column-reverse; }
            .team.home { justify-content: center; text-align: center; order: 1; }
            .team.away { justify-content: center; text-align: center; order: 3; }
            .match-center-info { order: 2; }
        }

        /* Autocomplete Styles */
        .search-wrapper { position: relative; flex: 1; }
        .search-input { width: 100%; box-sizing: border-box; }
        .suggestions-list {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 12px;
            margin-top: 5px;
            box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1);
            z-index: 1000;
            max-height: 300px;
            overflow-y: auto;
            display: none;
            text-align: right;
        }
        .suggestion-item {
            padding: 10px 15px;
            border-bottom: 1px solid var(--border);
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            color: var(--text);
            transition: background 0.1s;
        }
        .suggestion-item:last-child { border-bottom: none; }
        .suggestion-item:hover { background: #f1f5f9; }
        .suggestion-icon { font-size: 1.2rem; }
        .suggestion-content { flex: 1; }
        .suggestion-title { font-weight: 600; font-size: 0.95rem; display: block; }
        .suggestion-subtitle { font-size: 0.8rem; color: var(--text-light); }
        
        body.dark-mode .suggestions-list { background: var(--card); border-color: var(--border); }
        body.dark-mode .suggestion-item:hover { background: #334155; }
    </style>
</head>
<body>
    <?php include __DIR__ . '/header.php'; ?>

    <div class="container">
        <div class="search-box-container">
            <form action="search.php" method="GET" class="search-form">
                <div class="search-wrapper">
                    <input type="text" name="q" id="search-input" class="search-input" placeholder="ابحث عن فريق، مباراة، أو خبر..." value="<?php echo htmlspecialchars($query); ?>" required autocomplete="off">
                    <div id="suggestions" class="suggestions-list"></div>
                </div>
                <button type="submit" class="search-btn">بحث</button>
            </form>
        </div>

        <?php if (!empty($query)): ?>
            <?php if (empty($matches) && empty($news_results)): ?>
                <div class="no-results">
                    لم يتم العثور على نتائج لـ "<strong><?php echo htmlspecialchars($query); ?></strong>"
                </div>
            <?php else: ?>
                
                <!-- Matches Results -->
                <?php if (!empty($matches)): ?>
                    <div class="section-title">⚽ المباريات</div>
                    <div class="match-card">
                        <?php foreach ($matches as $m): ?>
                            <div class="match-item">
                                <a href="view_match.php?id=<?php echo $m['id']; ?>" class="match-link">
                                    <div class="match-info">
                                        <div class="team home">
                                            <?php echo team_logo_html($m['team_home'], 40, $m['team_home_logo'] ?? null); ?> 
                                            <?php echo htmlspecialchars($m['team_home']); ?>
                                        </div>
                                        <div class="match-center-info" style="display:flex; flex-direction:column; align-items:center;">
                                            <?php if ($m['score_home'] !== null && $m['score_away'] !== null): ?>
                                                <div class="score-box"><?php echo (int)$m['score_home'] . ' - ' . (int)$m['score_away']; ?></div>
                                            <?php else: ?>
                                                <div class="score-box vs"><?php echo format_time_ar($m['match_time']); ?></div>
                                            <?php endif; ?>
                                            <div class="match-date-small"><?php echo $m['match_date']; ?></div>
                                        </div>
                                        <div class="team away">
                                            <?php echo htmlspecialchars($m['team_away']); ?> 
                                            <?php echo team_logo_html($m['team_away'], 40, $m['team_away_logo'] ?? null); ?>
                                        </div>
                                    </div>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- News Results -->
                <?php if (!empty($news_results)): ?>
                    <div class="section-title">📰 الأخبار</div>
                    <div class="news-grid">
                        <?php foreach ($news_results as $news): ?>
                            <a href="view_news.php?id=<?php echo $news['id']; ?>" class="news-card">
                                <?php if ($news['image_url']): ?>
                                    <img src="<?php echo htmlspecialchars($news['image_url']); ?>" alt="صورة الخبر" class="news-img">
                                <?php else: ?>
                                    <div style="height:160px; background:#f1f5f9; display:flex; align-items:center; justify-content:center; color:#94a3b8;">لا توجد صورة</div>
                                <?php endif; ?>
                                <div class="news-body">
                                    <h3 class="news-title"><?php echo htmlspecialchars($news['title']); ?></h3>
                                    <div class="news-date"><?php echo date('Y/m/d', strtotime($news['created_at'])); ?></div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

            <?php endif; ?>
        <?php endif; ?>
    </div>

    <?php include __DIR__ . '/footer.php'; ?>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const toggleBtn = document.createElement('button');
            toggleBtn.innerHTML = '🌙';
            toggleBtn.className = 'theme-toggle';
            toggleBtn.title = 'تبديل الوضع الليلي';
            document.body.appendChild(toggleBtn);
            const currentTheme = localStorage.getItem('theme');
            if (currentTheme === 'dark') {
                document.body.classList.add('dark-mode');
                toggleBtn.innerHTML = '☀️';
            }
            toggleBtn.addEventListener('click', function() {
                document.body.classList.toggle('dark-mode');
                let theme = 'light';
                if (document.body.classList.contains('dark-mode')) { theme = 'dark'; toggleBtn.innerHTML = '☀️'; } 
                else { toggleBtn.innerHTML = '🌙'; }
                localStorage.setItem('theme', theme);
            });
        });

        // Autocomplete Logic
        const searchInput = document.getElementById('search-input');
        const suggestionsBox = document.getElementById('suggestions');
        let timeoutId;

        if (searchInput) {
            searchInput.addEventListener('input', function() {
                clearTimeout(timeoutId);
                const query = this.value.trim();
                
                if (query.length < 2) {
                    suggestionsBox.style.display = 'none';
                    return;
                }

                timeoutId = setTimeout(() => {
                    fetch('search_ajax.php?q=' + encodeURIComponent(query))
                        .then(response => response.json())
                        .then(data => {
                            suggestionsBox.innerHTML = '';
                            if (data.length > 0) {
                                data.forEach(item => {
                                    const div = document.createElement('a');
                                    div.href = item.url;
                                    div.className = 'suggestion-item';
                                    const icon = item.type === 'match' ? '⚽' : '📰';
                                    div.innerHTML = `
                                        <span class="suggestion-icon">${icon}</span>
                                        <div class="suggestion-content">
                                            <span class="suggestion-title">${item.text}</span>
                                            <span class="suggestion-subtitle">${item.subtext}</span>
                                        </div>
                                    `;
                                    suggestionsBox.appendChild(div);
                                });
                                suggestionsBox.style.display = 'block';
                            } else {
                                suggestionsBox.style.display = 'none';
                            }
                        })
                        .catch(err => console.error('Error fetching suggestions:', err));
                }, 300);
            });

            // Close suggestions when clicking outside
            document.addEventListener('click', function(e) {
                if (!searchInput.contains(e.target) && !suggestionsBox.contains(e.target)) {
                    suggestionsBox.style.display = 'none';
                }
            });
        }
    </script>
</body>
</html>