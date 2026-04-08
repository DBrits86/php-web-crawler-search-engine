<!-- Developed by Danie Brits -->

<?php
ini_set('memory_limit', '512M');
header('Content-Type: application/json');
// Security: Only allow POST and a specific query flag
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['query']) || $_POST['query'] !== 'run') {
    exit;
}

require_once '../includes/db.php';
$index = 0;

try{
	$conn = $pdo->open();
	$stmt = $conn->prepare('SELECT COUNT(`id`) AS tImg FROM `images` ORDER BY `id`');
	$stmt->execute();
	$totalImages = $stmt->fetchColumn();
	$stmt = $conn->prepare('SELECT COUNT(`id`) AS tVid FROM `videos` ORDER BY `id`');
	$stmt->execute();
	$totalVideos = $stmt->fetchColumn();
	$stmt = $conn->prepare('SELECT COUNT(`id`) AS tLink FROM `links` ORDER BY `id`');
	$stmt->execute();
	$totalLinks = $stmt->fetchColumn();
	$stmt = $conn->prepare('SELECT COUNT(`id`) AS tLink FROM `links` WHERE `index_count` > :index ORDER BY `id`');
	$stmt->execute([':index'=>$index]);
	$totalScannedLinks = $stmt->fetchColumn();
	$stmt = $conn->prepare('SELECT COUNT(`id`) AS tWord FROM `words` ORDER BY `id`');
	$stmt->execute();
	$totalWords = $stmt->fetchColumn();
}catch(PDOException $e) {
	echo json_encode(['error'=>'database error', 'message'=>$e-getMessage()]);
	exit();
}
//////////////////////////////////////////////

function logError($conn, $urlId, $context, $error) {
    try {
        $stmt = $conn->prepare("INSERT INTO error_logs (url_id, context, error_text) VALUES (?, ?, ?)");
        $stmt->execute([$urlId, $context, $error]);
    } catch (Exception $e) {
		echo json_encode(['error'=>'database error', 'message'=>$e-getMessage()]);
		exit();
	}
}

function fetchHTML($protocol,$url) {
    return @file_get_contents($protocol.$url);
}

function getRelativePath($url) {
    return parse_url($url, PHP_URL_PATH) ?: $url;
}

function buildUrl($protocol, $domain, $path) {
    $protocol = rtrim($protocol, ':/');
    $domain = trim($domain, '/');
    $path = '/' . ltrim($path, '/');
    return $protocol . '://' . $domain . $path;
}

function getDomainId($src, $conn) {
    $src = trim($src);
    $domain = parse_url($src, PHP_URL_HOST);
    $defaultProtocol = 'https://';

    if (!$domain && strpos($src, '//') === 0) {
        $domain = parse_url('http:' . $src, PHP_URL_HOST);
        $defaultProtocol = 'http://';
    }

    if ($domain) {
        try {
            $stmt = $conn->prepare('SELECT id FROM domains WHERE domain = ? LIMIT 1');
            $stmt->execute([$domain]);
            $id = $stmt->fetchColumn();

            $activeProtocol = isActive($domain);

            if ($id) {
                $stmt = $conn->prepare('UPDATE domains SET protocol = ? WHERE id = ?');
                $stmt->execute([$activeProtocol ?: $defaultProtocol, $id]);
                return $id;
            }

            $protocolToSave = $activeProtocol ?: 'http://';
            $status = $activeProtocol ? 'Active' : 'Inactive';

            $stmt = $conn->prepare("INSERT IGNORE INTO domains (domain, protocol, date, index_count, status) VALUES (?, ?, NOW(), 0, ?)");
            $stmt->execute([$domain, $protocolToSave, $status]);

            return $conn->lastInsertId();

        } catch (PDOException $e) {
            logError($conn, null, 'getDomainId', $e->getMessage());
			echo json_encode(['error'=>'database error', 'message'=>$e-getMessage()]);
			exit();
        }
    }

    return null;
}

function formatSingle($value) {
    if ($value >= 1000000) {
        return number_format($value / 1000000, 2) . 'M';
    } elseif ($value >= 1000) {
        return number_format($value / 1000, 2) . 'K';
    } else {
        return number_format($value, 2);
    }
}

function formatCount($base, $additional) {
    $total = $base + $additional;

    if ($total >= 1000000) {
        return number_format($total / 1000000, 0) . 'M';
    } elseif ($total >= 1000) {
        return number_format($total / 1000, 0) . 'K';
    } else {
		return number_format($total, 0);
    }
}

function stripSrc($src, $conn) {
    $info = 'stripSrc() starting: | src: ' . $src . ' |';
    $src = trim($src);
    $info .= 'src trim: ' . $src . ' |';
    if ($src === '' || str_starts_with($src, '/')) {
        $info .= '$src empty or relative / |';
        return null;
    }

    // Ensure scheme exists
    $scheme = parse_url($src, PHP_URL_SCHEME);
    if (!$scheme) {
        $src = 'http://' . ltrim($src, '/');
        $scheme = 'http';
    }
    $scheme .= '://';
    $info .= '!scheme? appended: ' . $scheme . ' |';
    $host = parse_url($src, PHP_URL_HOST);
    if (!$host) {
        $info .= 'No host found! |';
//        return null;
    }

    $host = strtolower(preg_replace('/^www\./i', '', $host));
    $info .= 'Host->strtolower(preg replace www. |';
    // Safely get the path from parse_url
    $parsed_path = parse_url($src, PHP_URL_PATH);
    $path = $parsed_path ?: '/';
    $info .= 'Path extracted or /: ' . $path . ' |';

    // Validate the host (basic TLD check)
    if (preg_match('/\.[a-z]{2,3}(\.[a-z]{2})?$/i', $host) || preg_match('/\.[a-z]{2,10}$/i', $host)) {
        $info .= 'validate .com, .co.za, etc... |';
        $info .= 
            ' | scheme:' . $scheme .
            ' | host:' . $host .
            ' | path:' . $path .
        ' |';
        $dirs = ['videos','images','media','private','porn','photos','pictures','categories','users','gallery','models'];
        foreach($dirs as $key=>$val) {
            if (preg_match('#^(https?://[^/]+(?:/[^/]+)*/' . $val . '/)#i', $path, $matches)) {
                try {
                    $conn->prepare("INSERT IGNORE INTO domains (domain) VALUES (?)")->execute([$host]);
                    $conn->prepare("INSERT IGNORE INTO links (domain, path) VALUES (?, ?)")->execute([$host, $path]);
                    $conn->prepare("INSERT IGNORE INTO links (domain, path) VALUES (?, '/')")->execute([$host]);
                }catch (PDOException $e) {
                    $info .= $e->getMessage();
					echo json_encode(['error'=>'database error', 'message'=>$e-getMessage()]);
					exit();
                }
            } 
        }
        return [
            'scheme' => $scheme,
            'host'   => $host,
            'path'   => $path,
            'info' => $info
        ];
    }

    return null;
}


function findClosestHeaderText($node) {
    // First: walk UP the DOM to find a real header (most semantically relevant)
    $current = $node;
    while ($current) {
        // Look at previous siblings on this level
        $sibling = $current->previousSibling;
        while ($sibling) {
            if ($sibling->nodeType === XML_ELEMENT_NODE) {
                $tagName = strtolower($sibling->nodeName);
                if (in_array($tagName, ['h1', 'h2', 'h3', 'h4', 'h5', 'h6','title', 'header'])) {
                    return trim($sibling->textContent);
                }

                // Check inside children
                if ($sibling->hasChildNodes()) {
                    foreach (array_reverse(iterator_to_array($sibling->childNodes)) as $child) {
                        if ($child->nodeType === XML_ELEMENT_NODE) {
                            $childTag = strtolower($child->nodeName);
                            if (in_array($childTag, ['h1', 'h2', 'h3', 'h4', 'h5', 'h6','title','header'])) {
                                return trim($child->textContent);
                            }
                        }
                    }
                }
            }
            $sibling = $sibling->previousSibling;
        }

        // Move up the tree
        $current = $current->parentNode;
    }

    // Fallback: restart from original node, look for closest usable text
    $fallback = $node;
    while ($fallback) {
        $sibling = $fallback->previousSibling;
        while ($sibling) {
            if ($sibling->nodeType === XML_ELEMENT_NODE) {
                $tagName = strtolower($sibling->nodeName);
                if (in_array($tagName, ['p', 'strong', 'b', 'pre'])) {
                    $text = trim($sibling->textContent);
                    if (!empty($text)) return $text;
                }

                if ($sibling->hasChildNodes()) {
                    foreach (array_reverse(iterator_to_array($sibling->childNodes)) as $child) {
                        if ($child->nodeType === XML_ELEMENT_NODE) {
                            $childTag = strtolower($child->nodeName);
                            if (in_array($childTag, ['p', 'strong', 'b', 'pre'])) {
                                $childText = trim($child->textContent);
                                if (!empty($childText)) return $childText;
                            }
                        }
                    }
                }
            }

            $sibling = $sibling->previousSibling;
        }

        $fallback = $fallback->parentNode;
    }

    return '';
}


function findClosestParagraphText($node) {
    $current = $node;

    while ($current) {
        $sibling = $current->previousSibling;

        while ($sibling) {
            if ($sibling->nodeType === XML_ELEMENT_NODE) {
                $tagName = strtolower($sibling->nodeName);

                if (in_array($tagName, ['p', 'strong', 'b', 'pre'])) {
                    $text = trim($sibling->textContent);
                    if (!empty($text)) {
                        // Return only first 10 words as a safe alt
                        return implode(' ', array_slice(explode(' ', $text), 0, 15)) . '...';
                    }
                }

                if ($sibling->hasChildNodes()) {
                    foreach (array_reverse(iterator_to_array($sibling->childNodes)) as $child) {
                        if ($child->nodeType === XML_ELEMENT_NODE) {
                            $childTag = strtolower($child->nodeName);
                            if (in_array($childTag, ['p', 'strong', 'b', 'span', 'div'])) {
                                $childText = trim($child->textContent);
                                if (!empty($childText)) {
                                    return implode(' ', array_slice(explode(' ', $childText), 0, 10)) . '...';
                                }
                            }
                        }
                    }
                }
            }

            $sibling = $sibling->previousSibling;
        }

        $current = $current->parentNode;
    }

    return '';
}
///////////////////////////////////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////////////////////////////

function extractLinks($dom, $urlId, $conn) {
    $linkCount = 0;
    foreach ($dom->getElementsByTagName('a') as $a) {
        $href = $a->getAttribute('href');
        if (!$href || strpos($href, 'http') !== 0) continue;
        $domain = parse_url($href, PHP_URL_HOST);
        $path = getRelativePath($href);
        $conn->prepare("INSERT IGNORE INTO domains (domain) VALUES (?)")->execute([$domain]);
        $conn->prepare("INSERT IGNORE INTO links (domain, path) VALUES (?, ?)")->execute([$domain, $path]);
        $conn->prepare("INSERT IGNORE INTO links (domain, path) VALUES (?, '/')")->execute([$domain]);
        $linkCount++;
    }
    return $linkCount;
}

function extractVideos($dom, $urlId, $conn, $domain) {
    $info = 'extractVideos |';
    $videoCount = 0;

    foreach (['video', 'iframe'] as $tag) {
        foreach ($dom->getElementsByTagName($tag) as $node) {
            $src = trim($node->getAttribute('src'));
            $info .= 'src found: ' . $src . ' |';

            if (!$src && ($tag === 'video' || $tag === 'iframe')) {
                foreach ($node->getElementsByTagName('source') as $s) {
                    $src = trim($s->getAttribute('src'));
                    if ($src) break;
                }
            } else {
                $info .= 'tag != video || iframe |';
            }

            if (!$src || !preg_match('/\.(mp4|webm|ogg|ogv)$/i', $src)) {
                $info .= 'type != mp4|webm|ogg|ogv |';
                continue;
            }

            $vid_domain = stripSrc($src, $conn);
            if (!isset($vid_domain['host'])) {
                $vid_domain = stripSrc(rtrim($domain ,'/') . '/' . ltrim($src, '/'), $conn);
            }
            $info .= $vid_domain['info'];
            $vid_url = $vid_domain['scheme'] . rtrim($vid_domain['host'], '/') . '/' . ltrim($vid_domain['path'], '/');

            // Alt fallback logic
            $alt = $node->getAttribute('title') ?: $node->getAttribute('aria-label');
            if (!$alt) {
                $alt = findClosestHeaderText($node); // also used as fallback
            }
            if (!$alt) {
                $alt = pathinfo(basename(parse_url($src, PHP_URL_PATH)), PATHINFO_FILENAME);
            }

            // Header should always be stored, even if not used for alt
            $header = findClosestHeaderText($node) ?: '';

            $stmt = $conn->prepare("INSERT IGNORE INTO videos (video_src, video_alt, video_header, url_id) VALUES (?, ?, ?, ?)");
            $stmt->execute([$vid_url, $alt, $header, $urlId]);
            $videoId = $conn->lastInsertId();

            if ($alt && $videoId) {
                foreach (preg_split('/[\s\W]+/', strtolower($header)) as $word) {
                    $clean = preg_replace('/[^a-z0-9]/i', '', $word);
                    if (!$clean) continue;

                    $conn->prepare("INSERT IGNORE INTO words (word) VALUES (:word)")
                        ->execute([':word' => $clean]);
                    $wordId = $conn->lastInsertId();

                    if (!$wordId) {
                        $stmt = $conn->prepare("SELECT id FROM words WHERE word = :word LIMIT 1");
                        $stmt->execute([':word' => $clean]);
                        $wordId = $stmt->fetchColumn();
                    }

                    if ($wordId) {
                        $conn->prepare("INSERT IGNORE INTO word_video (word_id, video_id) VALUES (?, ?)")
                            ->execute([$wordId, $videoId]);
                    }
                }
                foreach (preg_split('/[\s\W]+/', strtolower($alt)) as $word) {
                    $clean = preg_replace('/[^a-z0-9]/i', '', $word);
                    if (!$clean) continue;

                    $conn->prepare("INSERT IGNORE INTO words (word) VALUES (:word)")
                        ->execute([':word' => $clean]);
                    $wordId = $conn->lastInsertId();

                    if (!$wordId) {
                        $stmt = $conn->prepare("SELECT id FROM words WHERE word = :word LIMIT 1");
                        $stmt->execute([':word' => $clean]);
                        $wordId = $stmt->fetchColumn();
                    }

                    if ($wordId) {
                        $conn->prepare("INSERT IGNORE INTO word_video (word_id, video_id) VALUES (?, ?)")
                            ->execute([$wordId, $videoId]);
                    }
                }
            }

            $videoCount++;
        }
    }

    return [$videoCount, $info];
}


function extractImages($dom, $urlId, $conn, $domain) {
    $info = 'extractImages() starting |';
    $imgCount = 0;

    foreach ($dom->getElementsByTagName('img') as $img) {
        $src = trim($img->getAttribute('src'));
        $info .= '<-- raw src: ' . $src . ' \\ ';

        if (!$src || preg_match('/(\.svg$|thumbnail|icon|\(.*?\)|\d{2,5}x\d{2,5})/i', $src)) continue;

        $info .= 'src !empty and !.svg files | ';

        if (!empty($src) && preg_match('/\.(jpg|jpeg|gif|bmp|png)$/i', $src)) {
            $type = preg_match('/\.(jpg|jpeg|gif|bmp|png)$/i', $src);
            $info .= 'src not empty and img type: ' . $type . ' | ';

            // Alt fallback logic:
            $alt = $img->getAttribute('alt');			
            if(!$alt) {
				$alt = $img->getAttribute('title') ?: $img->getAttribute('aria-label');
			}
		// Fallback to nearby paragraph-like text (not headers)
			$keywords = findClosestParagraphText($img);
            if (!$alt) {
                $alt = pathinfo(basename(parse_url($src, PHP_URL_PATH)), PATHINFO_FILENAME);
            }

            // Always grab the closest header separately
            $header = findClosestHeaderText($img) ?: '';

            $info .= 'Image alt: ' . $alt . ' | ';
            $info .= 'Image header: ' . $header . ' | continue to stripSrc() |';

            $img_domain = stripSrc($src, $conn);
            if (!isset($img_domain['host'])) {
                $info .= 'src->relative path, append url |';
                $img_domain = stripSrc(rtrim($domain, '/') . '/' . ltrim($src, '/'), $conn);
            }

            $info .= $img_domain['info'];
            $img_url = $img_domain['scheme'] . rtrim($img_domain['host'], '/') . '/' . ltrim($img_domain['path'], '/');
            $info .= 'image url: ' . $img_url . ' inserted to images |';

            $stmt = $conn->prepare("INSERT IGNORE INTO images (img_src, img_alt, img_header, url_id) VALUES (?, ?, ?, ?)");
            $stmt->execute([$img_url, $alt, $header, $urlId]);
            $imgId = $conn->lastInsertId();

            if ($imgId) {
                $info .= 'new imgId: ' . $imgId . ' |';
            } else {
                $stmt = $conn->prepare("SELECT id FROM images WHERE img_src = ? AND url_id = ? LIMIT 1");
                $stmt->execute([$src, $urlId]);
                $imgId = $stmt->fetchColumn();
                $info .= 'Image exists, id: ' . $imgId . ' |';
            }
if($keywords) $alt .= ' ' .  $keywords;
if($header) $alt .= ' ' . $header;
            if ($alt && $imgId) {
                foreach (preg_split('/[\s\W]+/', strtolower($alt)) as $word) {
                    $clean = preg_replace('/[^a-z0-9]/i', '', $word);
                    if (!$clean) continue;

                    $conn->prepare("INSERT IGNORE INTO words (word) VALUES (:word)")
                        ->execute([':word' => $clean]);
                    $wordId = $conn->lastInsertId();

                    if ($wordId) {
                        $info .= 'New word: ' . $clean . ', id: ' . $wordId . ' inserted |';
                    }

                    if (!$wordId) {
                        $stmt = $conn->prepare("SELECT id FROM words WHERE word = :word LIMIT 1");
                        $stmt->execute([':word' => $clean]);
                        $wordId = $stmt->fetchColumn();
                        $info .= 'img_word exists: ' . $clean . ', id: ' . $wordId . ' |';
                    }

                    if ($wordId) {
                        $conn->prepare("INSERT IGNORE INTO word_image (word_id, img_id) VALUES (?, ?)")
                            ->execute([$wordId, $imgId]);
                        $info .= 'wordId: ' . $wordId . ' <-> ' . $imgId . ' joined! |';
                    }
                }
           }

            $imgCount++;
        }
    }

    return [$imgCount, $info];
}

function extractHeadersAndWords($dom, $urlId, $conn) {
    $wordCount = 0;
    $existing = [];
    
    $insertHeader = $conn->prepare("INSERT IGNORE INTO headers (text, tag, url_id) VALUES (?, ?, ?)");
    $insertWord = $conn->prepare("INSERT IGNORE INTO words (word) VALUES (:word)");
    $selectWord = $conn->prepare("SELECT id FROM words WHERE word = :word LIMIT 1");
    $insertWordUrl = $conn->prepare("INSERT IGNORE INTO word_url (word_id, url_id) VALUES (?, ?)");
    $info = 'extractHeadersAndWords() starting! | ';
    foreach (['h1','h2','h3','h4','h5','h6','p','keywords','title'] as $tag) {
        $info .= 'tags' . $tag . ' | ';
        foreach ($dom->getElementsByTagName($tag) as $node) {
            $text = trim($node->textContent);
            $info .= $text . ' | ';
            if (!$text) continue;

            try { 
                if (strpos($tag, 'h') === 0) $info .= 'Header inserted! | ';
                $insertHeader->execute([$text, $tag, $urlId]);
                $info .= 'Inserting words: | ';
                foreach (preg_split('/[\s\W]+/', strtolower($text)) as $word) {
                    $clean = preg_replace('/[^a-z0-9]/i', '', $word);
                    if (!$clean || isset($existing[$clean])) continue;
                    $info .= 'word: ' . $clean . ' | ';
                    $insertWord->execute([':word' => $clean]);
                    $id = $conn->lastInsertId();
                    if($id) {
                        $info .= 'new word, id: ' . $id . ' | ';
                    }
                    if (!$id) {
                        $selectWord->execute([':word' => $clean]);
                        $id = $selectWord->fetchColumn();
                        $info .= 'word_exists, id: ' . $id . ' | '; 
                    }
                    if ($id) {
                        $insertWordUrl->execute([$id, $urlId]);
                        $existing[$clean] = $id;
                        $wordCount++;
                        $info .= 'wordId: ' . $id . ' <-> ' . $urlId . ':URLid Linked! | ';
                    }
                }
            }catch (PDOException $e) {
                $info .= $e->getMessage();
            }
        }
    }
    return( [$wordCount, $info]);
}




//////////////////////////////////////////////

// MAIN
$response = ['success' => false, 'message' => 'Main starting', 'info'=>''];
$index_count = $_POST['index'] ?? 0;
$info = "MAIN start | ";
$status = $_POST['status'] ?? '';
$url = $_POST['url'] ?? '';
$urlId = $_POST['linkId'] ?? 0;
$protocol = $_POST['protocol'] ?? '';
$info = 'protocol: ' . $protocol . $url;
$info .= 'url: ' . $url . ' | ';
switch ($status) {
    case 'ok':
$info .= 'status ok | ';
        $html = fetchHTML($protocol, $url);
        if ($html) {
            $dom = new DOMDocument();
            @$dom->loadHTML($html);
            $words = extractHeadersAndWords($dom, $urlId, $conn);
            $imgs  = extractImages($dom, $urlId, $conn, $url);
		$info .= $imgs[1];
            $vids  = extractVideos($dom, $urlId, $conn, $url);
            $links = extractLinks($dom, $urlId, $conn);

            $conn->prepare("UPDATE links SET index_count = ?, indexed = NOW() WHERE id = ?")->execute([$index_count, $urlId]);
            $conn->prepare("UPDATE crawler_stats SET scanned_sites = scanned_sites + 1, total_words = total_words + ?, total_images = total_images + ?, total_videos = total_videos + ?, total_links = total_links + ? WHERE id = 1")
                  ->execute([$words[0], $imgs[0], $vids[0], $links]);

            $totalLinks = $totalLinks + $links;

            $response = [
                'success' => true,
                'url' => substr($url,0,10) . '. . . . . .',
                'words' => formatCount($words[0], $totalWords),
                'images' => formatCount($imgs[0], $totalImages),
                'videos' => formatCount($vids[0], $totalVideos),
                'links' => formatSingle($totalScannedLinks) . ' / ' . formatSingle($totalLinks),
                'persent' => formatSingle(($totalScannedLinks / $totalLinks) * 100),
                'info' => $info
            ];
        }        break;
    
    case 'inactive':
        $info .= 'inactive url | ';
        if ($urlId) {
            $conn->prepare("UPDATE links SET index_count = ?, indexed = NOW(), status = 'Inactive' WHERE id = ?")->execute([$index_count, $urlId]);
        }
        $response['message'] = 'Inactive URL: ' . $url;
        break;

    case 'skip':
        $response['message'] = 'No link found.';
        break;

    case 'error':
    default:
        $response['message'] = 'Error occurred: ' . $info;
        break;
}

$pdo->close();
$response['info'] = $info;
echo json_encode($response);
