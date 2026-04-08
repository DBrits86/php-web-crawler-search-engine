<!-- Developed by Danie Brits -->

<?php
ini_set('memory_limit', '512M');
header('Content-Type: application/json');
// Security: Only allow POST and a specific query flag
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
//    exit;
}

require_once '../includes/db.php';
$conn = $pdo->open();
$info = '';
$index_count = 0;

function logError($conn, $urlId, $context, $error) {
    try {
        $stmt = $conn->prepare("INSERT INTO error_logs (url_id, context, error_text) VALUES (?, ?, ?)");
        $stmt->execute([$urlId, $context, $error]);
    } catch (Exception $e) {}
}

function isActive($domain) {
    $protocols = ['https://', 'http://'];
    foreach ($protocols as $protocol) {
        $url = $protocol . $domain;
        $headers = @get_headers($url, 1);
        if ($headers && isset($headers[0]) &&
            preg_match('/^HTTP\/\d\.\d\s+(2\d\d|3\d\d)/', $headers[0])) {
            return $protocol;
        }
    }
    return false;
}



function getDomain($index_count, $conn, $max_index_count = null) {
    $info = "getDomain_start: index_count=$index_count | ";

    if ($max_index_count === null) {
        $max_index_count = $index_count + 5;
    }

    try {
        $stmt = $conn->prepare("SELECT id, domain, path FROM links WHERE index_count = ? AND status = 'Active' ORDER BY RAND() LIMIT 1");
        $stmt->execute([$index_count]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            $info .= "No link found at index_count=$index_count. ";

            $index_count++;

            if ($index_count > $max_index_count) {
                $info .= "Max index count reached. Stopping.\n";
                return [null, null, $index_count, $info, 'stop'];
            }

            //RETRY by recursively calling the function again
            return getDomain($index_count, $conn, $max_index_count);
        }

        $linkId = $row['id'];
        $domain = $row['domain'];
        $path = $row['path'];
        $url = rtrim($domain, '/') . '/' . ltrim($path, '/');

        $info .= "Link selected: id=$linkId, url=$url | ";

        $protocol = isActive($url);
        if (!$protocol) {
            $info .= "Inactive URL. Marking link and domain as inactive. ";

            $conn->prepare("UPDATE links SET status = 'Inactive', index_count = ? WHERE id = ?")
                ->execute([$index_count + 1, $linkId]);

            $conn->prepare("UPDATE domains SET status = 'Inactive', index_count = ? WHERE domain = ?")
                ->execute([$index_count + 1, $domain]);

            return [null, null, $index_count, $info, 'inactive'];
        }

        $conn->prepare("UPDATE links SET protocol = ? WHERE id = ?")
            ->execute([$protocol, $linkId]);

        $conn->prepare("UPDATE domains SET protocol = ? WHERE domain = ?")
            ->execute([$protocol, $domain]);

        $info .= "Active using protocol $protocol | ";

        return [$linkId, $protocol, $url, $index_count + 1, $info, 'ok'];

    } catch (PDOException $e) {
        $info .= "Exception in getDomain: " . $e->getMessage();
        logError($conn, null, 'getDomain', $e->getMessage());
        return [null, null, $index_count, $info, 'error'];
    }
}

$result = getDomain($index_count, $conn);

echo json_encode($result);
?>