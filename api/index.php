<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

define('GITHUB_REPO', 'https://raw.githubusercontent.com/thecloudyy/lua-database/refs/heads/master/uploaded/');
define('GITHUB_LIST_URL', 'https://raw.githubusercontent.com/thecloudyy/lua-database/refs/heads/master/lua_list.json');

$requestUri = $_SERVER['REQUEST_URI'];
$path = parse_url($requestUri, PHP_URL_PATH);

if (strpos($path, '/api/download/') === 0) {
    $appid = substr($path, strlen('/api/download/'));
    $appid = preg_replace('/[^0-9]/', '', $appid);
    handleDownload($appid);
} elseif ($path === '/api/download' || $path === '/api/download/') {
    $appid = isset($_GET['appid']) ? preg_replace('/[^0-9]/', '', $_GET['appid']) : '';
    handleDownload($appid);
} elseif ($path === '/api/list' || $path === '/api/list/') {
    handleList();
} elseif ($path === '/api/search') {
    handleSearch($_GET['q'] ?? '');
} else {
    http_response_code(404);
    echo json_encode(['error' => 'Endpoint not found']);
}

function handleDownload($appid) {
    if (empty($appid)) {
        http_response_code(400);
        echo "Error: Missing appid parameter";
        return;
    }

    $githubUrl = GITHUB_REPO . $appid . '.lua';
    $content = fetchFromGitHub($githubUrl);

    if ($content === null) {
        http_response_code(404);
        echo "Not Found";
        return;
    }

    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $appid . '.lua"');
    header('Content-Length: ' . strlen($content));
    echo $content;
}

function handleList() {
    $json = fetchFromGitHub(GITHUB_LIST_URL);
    
    if ($json === null) {
        $appids = getAppidsFromGitHubAPI();
    } else {
        $data = json_decode($json, true);
        if (isset($data['appids']) && is_array($data['appids'])) {
            $appids = $data['appids'];
        } else {
            $appids = getAppidsFromGitHubAPI();
        }
    }

    header('Content-Type: application/json');
    echo json_encode([
        'count' => count($appids),
        'appids' => $appids
    ]);
}

function handleSearch($query) {
    $query = trim($query);
    if (empty($query)) {
        handleList();
        return;
    }

    $json = fetchFromGitHub(GITHUB_LIST_URL);
    
    if ($json !== null) {
        $data = json_decode($json, true);
        if (isset($data['appids']) && is_array($data['appids'])) {
            $appids = $data['appids'];
        } else {
            $appids = [];
        }
    } else {
        $appids = getAppidsFromGitHubAPI();
    }

    $pattern = '/' . preg_quote($query, '/') . '/i';
    $results = array_filter($appids, function($id) use ($pattern) {
        return preg_match($pattern, $id);
    });

    header('Content-Type: application/json');
    echo json_encode([
        'query' => $query,
        'count' => count($results),
        'appids' => array_values($results)
    ]);
}

function getAppidsFromGitHubAPI() {
    $url = 'https://api.github.com/repos/thecloudyy/lua-database/contents/uploaded';
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Steam-Lua-API/1.0');
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/vnd.github.v3+json']);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $appids = [];

    if ($httpCode === 200) {
        $data = json_decode($response, true);
        foreach ($data as $item) {
            if (preg_match('/^([0-9]+)\.lua$/', $item['name'], $matches)) {
                $appids[] = $matches[1];
            }
        }
    }

    return $appids;
}

function fetchFromGitHub($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Steam-Lua-API/1.0');
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ($httpCode === 200) ? $response : null;
}
?>
