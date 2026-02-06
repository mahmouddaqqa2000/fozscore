<?php
require_once __DIR__ . '/db.php';

header('Content-Type: application/json');

$query = isset($_GET['q']) ? trim($_GET['q']) : '';
$results = [];

if (!empty($query) && mb_strlen($query) >= 2) {
    $term = '%' . $query . '%';
    
    // البحث في المباريات (الفرق)
    $stmt = $pdo->prepare("SELECT id, team_home, team_away, match_date FROM matches WHERE team_home LIKE ? OR team_away LIKE ? ORDER BY match_date DESC LIMIT 5");
    $stmt->execute([$term, $term]);
    $matches = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($matches as $m) {
        $results[] = [
            'type' => 'match',
            'text' => $m['team_home'] . ' ضد ' . $m['team_away'],
            'subtext' => $m['match_date'],
            'url' => 'view_match.php?id=' . $m['id']
        ];
    }
    
    // البحث في الأخبار
    $stmt = $pdo->prepare("SELECT id, title FROM news WHERE title LIKE ? ORDER BY created_at DESC LIMIT 5");
    $stmt->execute([$term]);
    $news = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($news as $n) {
        $results[] = [
            'type' => 'news',
            'text' => $n['title'],
            'subtext' => 'خبر',
            'url' => 'view_news.php?id=' . $n['id']
        ];
    }
}

echo json_encode($results);