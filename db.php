<?php
// db.php - فتح أو إنشاء قاعدة بيانات SQLite وتأكد من وجود جدول المباريات

// Set a consistent timezone for the application to ensure date comparisons work correctly.
date_default_timezone_set('Asia/Riyadh');

$dir = __DIR__;
$pdo = new PDO('sqlite:' . $dir . '/matches.sqlite');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("CREATE TABLE IF NOT EXISTS matches (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  match_date TEXT NOT NULL,
  match_time TEXT,
  team_home TEXT NOT NULL,
  team_away TEXT NOT NULL,
  venue TEXT,
  score_home INTEGER,
  score_away INTEGER,
  championship TEXT
)");

// التحقق من وجود عمود championship وإضافته إذا كان ناقصاً (تحديث قاعدة البيانات القديمة)
$columns = $pdo->query("PRAGMA table_info(matches)")->fetchAll(PDO::FETCH_COLUMN, 1);
if (!in_array('championship', $columns)) {
    $pdo->exec("ALTER TABLE matches ADD COLUMN championship TEXT");
}
if (!in_array('channel', $columns)) {
    $pdo->exec("ALTER TABLE matches ADD COLUMN channel TEXT");
}
if (!in_array('commentator', $columns)) {
    $pdo->exec("ALTER TABLE matches ADD COLUMN commentator TEXT");
}
if (!in_array('lineup_home', $columns)) {
    $pdo->exec("ALTER TABLE matches ADD COLUMN lineup_home TEXT");
}
if (!in_array('lineup_away', $columns)) {
    $pdo->exec("ALTER TABLE matches ADD COLUMN lineup_away TEXT");
}
if (!in_array('stream_url', $columns)) {
    $pdo->exec("ALTER TABLE matches ADD COLUMN stream_url TEXT");
}
if (!in_array('coach_home', $columns)) {
    $pdo->exec("ALTER TABLE matches ADD COLUMN coach_home TEXT");
}
if (!in_array('coach_away', $columns)) {
    $pdo->exec("ALTER TABLE matches ADD COLUMN coach_away TEXT");
}
if (!in_array('coach_home_image', $columns)) {
    $pdo->exec("ALTER TABLE matches ADD COLUMN coach_home_image TEXT");
}
if (!in_array('coach_away_image', $columns)) {
    $pdo->exec("ALTER TABLE matches ADD COLUMN coach_away_image TEXT");
}
if (!in_array('bench_home', $columns)) {
    $pdo->exec("ALTER TABLE matches ADD COLUMN bench_home TEXT");
}
if (!in_array('bench_away', $columns)) {
    $pdo->exec("ALTER TABLE matches ADD COLUMN bench_away TEXT");
}
if (!in_array('absent_home', $columns)) {
    $pdo->exec("ALTER TABLE matches ADD COLUMN absent_home TEXT");
}
if (!in_array('absent_away', $columns)) {
    $pdo->exec("ALTER TABLE matches ADD COLUMN absent_away TEXT");
}
if (!in_array('match_news', $columns)) {
    $pdo->exec("ALTER TABLE matches ADD COLUMN match_news TEXT");
}
if (!in_array('player_stats_home', $columns)) {
    $pdo->exec("ALTER TABLE matches ADD COLUMN player_stats_home TEXT");
}
if (!in_array('player_stats_away', $columns)) {
    $pdo->exec("ALTER TABLE matches ADD COLUMN player_stats_away TEXT");
}
if (!in_array('team_home_logo', $columns)) {
    $pdo->exec("ALTER TABLE matches ADD COLUMN team_home_logo TEXT");
}
if (!in_array('team_away_logo', $columns)) {
    $pdo->exec("ALTER TABLE matches ADD COLUMN team_away_logo TEXT");
}
if (!in_array('championship_logo', $columns)) {
    $pdo->exec("ALTER TABLE matches ADD COLUMN championship_logo TEXT");
}
if (!in_array('source_url', $columns)) {
    $pdo->exec("ALTER TABLE matches ADD COLUMN source_url TEXT");
}
// إحصائيات المباراة
$stats_cols = ['stats_possession_home', 'stats_possession_away', 'stats_shots_home', 'stats_shots_away', 'stats_corners_home', 'stats_corners_away', 'stats_fouls_home', 'stats_fouls_away'];
foreach ($stats_cols as $col) {
    if (!in_array($col, $columns)) {
        $pdo->exec("ALTER TABLE matches ADD COLUMN $col INTEGER");
    }
}
if (!in_array('match_stats', $columns)) {
    $pdo->exec("ALTER TABLE matches ADD COLUMN match_stats TEXT DEFAULT NULL");
}
if (!in_array('match_videos', $columns)) {
    $pdo->exec("ALTER TABLE matches ADD COLUMN match_videos TEXT DEFAULT NULL");
}

// إنشاء جدول الأخبار إذا لم يكن موجوداً
$pdo->exec("CREATE TABLE IF NOT EXISTS news (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  title TEXT NOT NULL,
  summary TEXT,
  content TEXT NOT NULL,
  image_url TEXT,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP
)");

// إنشاء جدول الفرق (لتخزين الشعارات والأسماء بشكل دائم)
$pdo->exec("CREATE TABLE IF NOT EXISTS teams (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL UNIQUE,
  logo TEXT,
  league_name TEXT
)");

// إنشاء جدول الدوريات (لتخزين الشعارات والأسماء)
$pdo->exec("CREATE TABLE IF NOT EXISTS leagues (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL UNIQUE,
  logo TEXT,
  external_id INTEGER
)");

?>
