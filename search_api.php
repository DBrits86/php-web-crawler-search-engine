<?php
require_once __DIR__ . '/includes/db.php';
$conn = $pdo->open();

header('Content-Type: application/json');

$type   = $_GET['type']   ?? 'word';
$query  = trim($_GET['query']  ?? '');
$words  = array_filter(explode(' ', $query));
$domain = trim($_GET['domain'] ?? '');
$page   = max(1, intval($_GET['page']  ?? 1));
$limit  = max(1, intval($_GET['limit'] ?? 12));
$offset = ($page - 1) * $limit;

// Only require a query for word search
if ($query === '' && $type === 'word') {
    echo json_encode(["error" => "Query is required"]);
    exit;
}

function getUrlById($conn, $id) {
    $stmt = $conn->prepare('SELECT `protocol`, `domain`, `path` FROM `links` WHERE `id` = :id');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? $row['protocol'] . $row['domain'] . $row['path'] : null;
}

function wordSearch($conn, $query, $words, $domain, $page, $limit, $offset) {
    $results = [
        'result' => [],
        'seen'   => [],
        'total'  => 0
    ];

    $urlScores = [];

    // Stage 1: Exact header match
    $stmt = $conn->prepare('SELECT `url_id`, `text` FROM `headers` WHERE `text` = :query');
    $stmt->execute([':query' => $query]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $url_id = $row['url_id'];
        $urlScores[$url_id]['score'] = ($urlScores[$url_id]['score'] ?? 0) + 100;
        $urlScores[$url_id]['match'][] = $row['text'];
    }

    // Stage 2: Partial header match
    $stmt = $conn->prepare('SELECT `url_id`, `text` FROM `headers` WHERE `text` LIKE :query');
    $stmt->execute([':query' => $query . '%']);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $url_id = $row['url_id'];
        $urlScores[$url_id]['score'] = ($urlScores[$url_id]['score'] ?? 0) + 50;
        $urlScores[$url_id]['match'][] = $row['text'];
    }

    // Stage 3: Word table matches
    foreach ($words as $word) {
        $stmt = $conn->prepare('SELECT `id`, `word` FROM `words` WHERE `word` = :query');
        $stmt->execute([':query' => $word]);
        if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $stmt2 = $conn->prepare('SELECT `url_id` FROM `word_url` WHERE `word_id` = :id');
            $stmt2->execute([':id' => $row['id']]);
            while ($row2 = $stmt2->fetch(PDO::FETCH_ASSOC)) {
                $url_id = $row2['url_id'];
                $urlScores[$url_id]['score'] = ($urlScores[$url_id]['score'] ?? 0) + 10;
                $urlScores[$url_id]['match'][] = $row['word'];
            }
        }
    }

    // Sort by score descending
    arsort($urlScores);

    foreach ($urlScores as $url_id => $data) {
        if (!in_array($url_id, $results['seen'])) {
            $url = getUrlById($conn, $url_id);
            if ($url) {
                $results['result'][] = [
                    'url'  => $url,
                    'text' => implode(', ', array_unique($data['match'] ?? []))
                ];
                $results['seen'][] = $url_id;
                $results['total']++;
            }
        }
    }

    return $results;
}

function mediaSearch($conn, $type, $query, $words, $domain, $page, $limit, $offset) {
    $results = [
        'result' => [],
        'seen'   => [],
        'total'  => 0
    ];

    $matches = [];
    $isImage = $type === 'image';
    $table = $isImage ? 'images' : 'videos';
    $srcCol = $isImage ? 'img_src' : 'video_src';
    $altCol = $isImage ? 'img_alt' : 'video_alt';
    $headerCol = $isImage ? 'img_header' : 'video_header';
    $wordJoinTable = $isImage ? 'word_image' : 'word_video';
    $idCol = $isImage ? 'img_id' : 'video_id';

    if ($query === '') {
        $stmt = $conn->prepare("SELECT id, $srcCol AS src, $altCol AS alt, $headerCol AS header, url_id FROM `$table` ORDER BY indexed DESC LIMIT :limit OFFSET :offset");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) $matches[] = $row;
    } else {
        // Stage 1: Exact alt match
        $stmt = $conn->prepare("SELECT id, $srcCol AS src, $altCol AS alt, $headerCol AS header, url_id FROM `$table` WHERE $altCol = :query ORDER BY indexed DESC");
        $stmt->execute([':query' => $query]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) $matches[] = $row;

        // Stage 2: Exact header match
        $stmt = $conn->prepare("SELECT id, $srcCol AS src, $altCol AS alt, $headerCol AS header, url_id FROM `$table` WHERE $headerCol = :query ORDER BY indexed DESC");
        $stmt->execute([':query' => $query]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) $matches[] = $row;

        // Stage 3: Partial alt/header match
        $like = $query . '%';
        $stmt = $conn->prepare("SELECT id, $srcCol AS src, $altCol AS alt, $headerCol AS header, url_id FROM `$table` WHERE $altCol LIKE :query OR $headerCol LIKE :query ORDER BY indexed DESC");
        $stmt->execute([':query' => $like]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) $matches[] = $row;

        // Stage 4: Word matches grouped and counted
        $wordMatches = [];
        foreach ($words as $word) {
            $stmt = $conn->prepare("SELECT id FROM words WHERE word = :word");
            $stmt->execute([':word' => $word]);
            if ($wordRow = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $stmt2 = $conn->prepare("
                    SELECT t.id, t.$srcCol AS src, t.$altCol AS alt, t.$headerCol AS header, t.url_id 
                    FROM `$wordJoinTable` wj 
                    JOIN `$table` t ON t.id = wj.$idCol 
                    WHERE wj.word_id = :wid
                ");
                $stmt2->execute([':wid' => $wordRow['id']]);
                while ($row = $stmt2->fetch(PDO::FETCH_ASSOC)) {
                    $key = $row['id'];
                    if (!isset($wordMatches[$key])) {
                        $wordMatches[$key] = $row;
                        $wordMatches[$key]['hits'] = 0;
                    }
                    $wordMatches[$key]['hits']++;
                }
            }
        }

        // Sort word matches by number of hits DESC
        usort($wordMatches, fn($a, $b) => $b['hits'] <=> $a['hits']);

        // Add word matches to main matches array
        foreach ($wordMatches as $row) {
            $matches[] = $row;
        }
    }

    // Duplicate and prepare for front-end
    $seen = [];
    foreach ($matches as $row) {
        if (!in_array($row['id'], $seen)) {
            $seen[] = $row['id'];
            $parsed = parse_url($row['src']);
            $domainFromSrc = $parsed['host'] ?? '';

            $results['result'][] = [
                'url'    => $row['src'],
                'alt'    => $row['alt'],
                'header' => $row['header'],
                'domain' => $domainFromSrc
            ];
            $results['total']++;
        }
    }

    return $results;
}

// Run the search based on type
if ($type === 'word') {
    $results = wordSearch($conn, $query, $words, $domain, $page, $limit, $offset);
} elseif ($type === 'image' || $type === 'video') {
    $results = mediaSearch($conn, $type, $query, $words, $domain, $page, $limit, $offset);
} else {
    echo json_encode(['error' => 'Unsupported search type']);
    exit;
}

// Output JSON with pagination slice
echo json_encode([
    "page"    => $page,
    "limit"   => $limit,
    "results" => array_slice($results['result'], 0, $limit),
    "total"   => $results['total'],
    "type"    => $type
]);
