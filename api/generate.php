<?php
/**
 * 统计代码生成器
 * 生成JavaScript和图片方式的统计代码
 */

// 定义路径常量（检查是否已定义）
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__));
}
if (!defined('INSTALL_LOCK_FILE')) {
    define('INSTALL_LOCK_FILE', ROOT_PATH . '/install.lock');
}
if (!defined('CONFIG_FILE')) {
    define('CONFIG_FILE', ROOT_PATH . '/config/config.php');
}

// 检查安装状态
if (!file_exists(INSTALL_LOCK_FILE) || !file_exists(CONFIG_FILE)) {
    jsonResponse(['status' => 'error', 'message' => '系统未安装或配置错误'], 503);
}

// 加载配置和函数
require_once CONFIG_FILE;
require_once ROOT_PATH . '/includes/functions.php';

// 检查管理员登录状态
session_start();
if (!isset($_SESSION['admin_id'])) {
    jsonResponse(['status' => 'error', 'message' => '请先登录'], 401);
}

$action = $_GET['action'] ?? '';

// ── CSRF 验证：写操作（创建/删除/更新/重置）须校验 Token ─────────────────
$writingActions = ['create_project', 'delete_project', 'update_project', 'reset_access_key'];
if (in_array($action, $writingActions, true)) {
    $csrfToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!verifyCsrfToken($csrfToken)) {
        jsonResponse(['status' => 'error', 'message' => '安全验证失败，请刷新页面后重试'], 403);
    }
}

switch ($action) {
    case 'create_project':
        createProject();
        break;
    case 'get_projects':
        getProjects();
        break;
    case 'generate_code':
        generateCode();
        break;
    case 'delete_project':
        deleteProject();
        break;
    case 'update_project':
        updateProject();
        break;
    case 'download_php':
        downloadPhpFile();
        break;
    case 'reset_access_key':
        resetAccessKey();
        break;
    case 'get_project_stats':
        getProjectStats();
        break;
    default:
        jsonResponse(['status' => 'error', 'message' => '无效的操作'], 400);
}

/**
 * 创建统计项目
 */
function createProject() {
    global $db;
    
    $name = sanitizeInput($_POST['name'] ?? '');
    $description = sanitizeInput($_POST['description'] ?? '');
    
    if (empty($name)) {
        jsonResponse(['status' => 'error', 'message' => '项目名称不能为空'], 400);
    }
    
    // 生成唯一的跟踪代码
    $trackingCode = generateRandomString(16);
    
    // 检查代码是否已存在
    while ($db->fetchOne("SELECT id FROM projects WHERE tracking_code = ?", [$trackingCode])) {
        $trackingCode = generateRandomString(16);
    }
    
    // 插入项目记录
    $projectId = $db->insert('projects', [
        'name' => $name,
        'description' => $description,
        'tracking_code' => $trackingCode,
        'is_active' => 1,
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ]);
    
    jsonResponse([
        'status' => 'success',
        'message' => '项目创建成功',
        'data' => [
            'id' => $projectId,
            'tracking_code' => $trackingCode
        ]
    ]);
}

/**
 * 获取项目列表
 */
function getProjects() {
    global $db;
    
    $projects = $db->fetchAll("SELECT * FROM projects ORDER BY created_at DESC");
    
    jsonResponse([
        'status' => 'success',
        'data' => $projects
    ]);
}

/**
 * 生成统计代码
 */
function generateCode() {
    $projectId = $_GET['project_id'] ?? '';
    $method = $_GET['method'] ?? 'js'; // js, image 或 php
    
    if (empty($projectId)) {
        jsonResponse(['status' => 'error', 'message' => '缺少项目ID'], 400);
    }
    
    global $db;
    $project = $db->fetchOne("SELECT * FROM projects WHERE id = ?", [$projectId]);
    
    if (!$project) {
        jsonResponse(['status' => 'error', 'message' => '项目不存在'], 404);
    }
    
    $baseUrl = getBaseUrl();
    $trackingCode = $project['tracking_code'];
    
    if ($method === 'js') {
        $code = generateJavaScriptCode($baseUrl, $trackingCode);
        jsonResponse([
            'status' => 'success',
            'data' => [
                'method' => $method,
                'code' => $code,
                'project' => $project
            ]
        ]);
    } elseif ($method === 'image') {
        $code = generateImageCode($baseUrl, $trackingCode);
        jsonResponse([
            'status' => 'success',
            'data' => [
                'method' => $method,
                'code' => $code,
                'project' => $project
            ]
        ]);
    } elseif ($method === 'php') {
        // 获取或生成访问密钥
        $accessKey = getOrGenerateAccessKey($projectId);
        
        // 生成PHP代码和引入代码
        $phpCode = generatePhpTrackingCode($baseUrl, $trackingCode, $accessKey);
        $includeCode = generateIncludeCode($trackingCode);
        
        jsonResponse([
            'status' => 'success',
            'data' => [
                'method' => $method,
                'phpCode' => $phpCode,
                'includeCode' => $includeCode,
                'accessKey' => $accessKey,
                'project' => $project
            ]
        ]);
    } else {
        jsonResponse(['status' => 'error', 'message' => '不支持的统计方式'], 400);
    }
}

/**
 * 删除项目
 */
function deleteProject() {
    global $db;
    
    $projectId = $_POST['project_id'] ?? '';
    
    if (empty($projectId)) {
        jsonResponse(['status' => 'error', 'message' => '缺少项目ID'], 400);
    }
    
    // 检查项目是否存在
    $project = $db->fetchOne("SELECT * FROM projects WHERE id = ?", [$projectId]);
    if (!$project) {
        jsonResponse(['status' => 'error', 'message' => '项目不存在'], 404);
    }
    
    try {
        // 开始事务
        $db->beginTransaction();
        
        // 获取该项目的访问记录数量
        $visitCount = $db->fetchOne("SELECT COUNT(*) as count FROM visits WHERE project_id = ?", [$projectId])['count'];
        
        // 删除该项目的所有访问记录
        if ($visitCount > 0) {
            $db->delete('visits', 'project_id = ?', [$projectId]);
        }
        
        // 删除项目
        $db->delete('projects', 'id = ?', [$projectId]);
        
        // 提交事务
        $db->commit();
        
        jsonResponse([
            'status' => 'success',
            'message' => "项目删除成功，同时删除了 {$visitCount} 条访问记录"
        ]);
        
    } catch (Exception $e) {
        // 回滚事务
        $db->rollback();
        jsonResponse(['status' => 'error', 'message' => '删除失败: ' . $e->getMessage()], 500);
    }
}

/**
 * 生成JavaScript统计代码
 */
function generateJavaScriptCode($baseUrl, $trackingCode) {
    $code = <<<HTML
<!-- IP/PV统计系统 - JavaScript方式 -->
<script>
(function() {
    // 获取页面信息
    var pageUrl = window.location.href;
    var pageTitle = document.title;
    
    // 创建统计请求
    var img = new Image();
    img.onload = function() {
        console.log('统计记录成功');
    };
    img.onerror = function() {
        console.log('统计记录失败');
    };
    
    // 发送统计请求
    img.src = '{$baseUrl}/api/track.php?project={$trackingCode}&url=' + 
              encodeURIComponent(pageUrl) + 
              '&title=' + encodeURIComponent(pageTitle) + 
              '&t=' + new Date().getTime();
})();
</script>
HTML;
    
    return $code;
}

/**
 * 生成图片统计代码
 */
function generateImageCode($baseUrl, $trackingCode) {
    $code = <<<HTML
<!-- IP/PV统计系统 - 图片方式 -->
<img src="{$baseUrl}/api/image.php?project={$trackingCode}" 
     width="1" height="1" style="display:none;" alt="" />
HTML;
    
    return $code;
}

/**
 * 获取基础URL
 */
function getBaseUrl() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $scriptName = dirname($_SERVER['SCRIPT_NAME']);
    
    // 移除 /api 路径，返回根URL
    $basePath = rtrim($scriptName, '/');
    if (substr($basePath, -4) === '/api') {
        $basePath = substr($basePath, 0, -4);
    }
    
    // 确保basePath不为空
    if (empty($basePath) || $basePath === '.') {
        $basePath = '';
    }
    
    return $protocol . '://' . $host . $basePath;
}

/**
 * 更新统计项目
 */
function updateProject() {
    global $db;
    
    $projectId = $_POST['project_id'] ?? '';
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    
    if (empty($projectId) || empty($name)) {
        jsonResponse(['status' => 'error', 'message' => '项目ID和名称不能为空'], 400);
    }
    
    // 验证项目是否存在
    $project = $db->fetchOne("SELECT * FROM projects WHERE id = ?", [$projectId]);
    if (!$project) {
        jsonResponse(['status' => 'error', 'message' => '项目不存在'], 404);
    }
    
    // 检查名称是否与其他项目重复
    $existingProject = $db->fetchOne("SELECT id FROM projects WHERE name = ? AND id != ?", [$name, $projectId]);
    if ($existingProject) {
        jsonResponse(['status' => 'error', 'message' => '项目名称已存在'], 400);
    }
    
    try {
        // 更新项目信息
        $db->query(
            "UPDATE projects SET name = ?, description = ? WHERE id = ?",
            [$name, $description, $projectId]
        );
        
        jsonResponse(['status' => 'success', 'message' => '项目更新成功']);
    } catch (Exception $e) {
        jsonResponse(['status' => 'error', 'message' => '更新失败: ' . $e->getMessage()], 500);
    }
}

/**
 * 获取或生成访问密钥
 */
function getOrGenerateAccessKey($projectId) {
    global $db;
    
    // 检查是否已有访问密钥
    $project = $db->fetchOne("SELECT access_key FROM projects WHERE id = ?", [$projectId]);
    
    if ($project && !empty($project['access_key'])) {
        return $project['access_key'];
    }
    
    // 生成新的访问密钥
    $accessKey = generateRandomString(32);
    
    // 更新项目记录
    $db->query("UPDATE projects SET access_key = ? WHERE id = ?", [$accessKey, $projectId]);
    
    return $accessKey;
}

/**
 * 生成PHP统计代码
 */
function generatePhpTrackingCode($baseUrl, $trackingCode, $accessKey) {
    $code = <<<PHP
<?php
define('TRACKING_BASE_URL', '{$baseUrl}');
define('TRACKING_PROJECT_CODE', '{$trackingCode}');
define('TRACKING_ACCESS_KEY', '{$accessKey}');

/**
 * 获取真实客户端IP地址
 * 考虑代理、负载均衡器等环境
 */
function getRealClientIp() {
    // 检查HTTP_CLIENT_IP（某些代理服务器会设置）
    if (!empty(\$_SERVER['HTTP_CLIENT_IP'])) {
        return \$_SERVER['HTTP_CLIENT_IP'];
    }
    
    // 检查HTTP_X_FORWARDED_FOR（最常见的代理头）
    if (!empty(\$_SERVER['HTTP_X_FORWARDED_FOR'])) {
        // 可能有多个IP，取第一个非unknown的
        \$ips = explode(',', \$_SERVER['HTTP_X_FORWARDED_FOR']);
        foreach (\$ips as \$ip) {
            \$ip = trim(\$ip);
            if (strtolower(\$ip) !== 'unknown' && filter_var(\$ip, FILTER_VALIDATE_IP)) {
                return \$ip;
            }
        }
    }
    
    // 检查HTTP_X_REAL_IP（Nginx等）
    if (!empty(\$_SERVER['HTTP_X_REAL_IP'])) {
        return \$_SERVER['HTTP_X_REAL_IP'];
    }
    
    // 检查HTTP_X_FORWARDED（较少使用）
    if (!empty(\$_SERVER['HTTP_X_FORWARDED'])) {
        return \$_SERVER['HTTP_X_FORWARDED'];
    }
    
    // 最后使用REMOTE_ADDR
    return \$_SERVER['REMOTE_ADDR'] ?? '未知';
}

/**
 * 获取页面标题
 */
function getPageTitle() {
    // 尝试从全局变量获取
    if (isset(\$GLOBALS['page_title']) && !empty(\$GLOBALS['page_title'])) {
        return \$GLOBALS['page_title'];
    }
    
    // 尝试从输出缓冲区获取标题
    \$title = extractTitleFromOutput();
    if (!empty(\$title)) {
        return \$title;
    }
    
    // 尝试从当前脚本文件内容中提取标题
    \$title = extractTitleFromFile();
    if (!empty(\$title)) {
        return \$title;
    }
    
    // 尝试从文件名生成标题
    \$scriptName = basename(\$_SERVER['SCRIPT_NAME'], '.php');
    if (\$scriptName && \$scriptName !== 'index') {
        return ucfirst(\$scriptName);
    }
    
    // 尝试从URL路径生成标题
    \$path = parse_url(\$_SERVER['REQUEST_URI'], PHP_URL_PATH);
    \$pathParts = explode('/', trim(\$path, '/'));
    if (!empty(\$pathParts) && \$pathParts[0] !== 'index.php') {
        \$lastPart = end(\$pathParts);
        if (\$lastPart && \$lastPart !== 'index') {
            return ucfirst(str_replace(['-', '_'], ' ', \$lastPart));
        }
    }
    
    return '未知页面';
}

/**
 * 从输出缓冲区提取标题
 */
function extractTitleFromOutput() {
    // 检查是否有输出缓冲区
    if (ob_get_level() > 0) {
        \$content = ob_get_contents();
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', \$content, \$matches)) {
            \$title = trim(strip_tags(\$matches[1]));
            if (!empty(\$title)) {
                return \$title;
            }
        }
    }
    
    return null;
}

/**
 * 从当前脚本文件内容中提取标题
 */
function extractTitleFromFile() {
    \$scriptFile = \$_SERVER['SCRIPT_FILENAME'] ?? \$_SERVER['SCRIPT_NAME'];
    if (file_exists(\$scriptFile)) {
        \$content = file_get_contents(\$scriptFile);
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', \$content, \$matches)) {
            \$title = trim(strip_tags(\$matches[1]));
            if (!empty(\$title)) {
                return \$title;
            }
        }
    }
    
    return null;
}

/**
 * 统计页面访问
 */
function trackPage(\$pageUrl = null, \$pageTitle = null, \$referer = null, \$clientIp = null) {
    if (\$pageUrl === null) {
        \$protocol = (!empty(\$_SERVER['HTTPS']) && \$_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        \$pageUrl = \$protocol . '://' . \$_SERVER['HTTP_HOST'] . \$_SERVER['REQUEST_URI'];
    }
    
    if (\$pageTitle === null) {
        \$pageTitle = getPageTitle();
    }
    
    if (\$referer === null) {
        \$referer = \$_SERVER['HTTP_REFERER'] ?? '';
    }
    
    if (\$clientIp === null) {
        \$clientIp = getRealClientIp();
    }
    
    \$trackingUrl = TRACKING_BASE_URL . '/api/track_fast.php?' . http_build_query([
        'project' => TRACKING_PROJECT_CODE,
        'access_key' => TRACKING_ACCESS_KEY,
        'url' => \$pageUrl,
        'title' => \$pageTitle,
        'referer' => \$referer,
        'ip' => \$clientIp,
        'ua' => \$_SERVER['HTTP_USER_AGENT'] ?? '',
        't' => time()
    ]);
    
    sendTrackingRequest(\$trackingUrl);
}

/**
 * 发送统计请求
 */
function sendTrackingRequest(\$url) {
    sendTrackingRequestAsync(\$url);
}

/**
 * 使用fsockopen发送请求
 */
function sendTrackingRequestAsync(\$url) {
    \$parsedUrl = parse_url(\$url);
    \$host = \$parsedUrl['host'];
    \$port = isset(\$parsedUrl['port']) ? \$parsedUrl['port'] : 80;
    \$path = isset(\$parsedUrl['path']) ? \$parsedUrl['path'] : '/';
    \$query = isset(\$parsedUrl['query']) ? '?' . \$parsedUrl['query'] : '';
    
    \$fp = @fsockopen(\$host, \$port, \$errno, \$errstr, 0.1);
    if (\$fp) {
        stream_set_blocking(\$fp, false);
        
        \$userAgent = \$_SERVER['HTTP_USER_AGENT'] ?? 'PHP Tracking Client/1.0';
        
        \$request = "GET {\$path}{\$query} HTTP/1.1\\r\\n";
        \$request .= "Host: {\$host}\\r\\n";
        \$request .= "User-Agent: {\$userAgent}\\r\\n";
        \$request .= "Connection: close\\r\\n\\r\\n";
        
        fwrite(\$fp, \$request);
        fclose(\$fp);
    } else {
        \$userAgent = \$_SERVER['HTTP_USER_AGENT'] ?? 'PHP Tracking Client/1.0';
        
        \$context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 2,
                'user_agent' => \$userAgent,
                'ignore_errors' => true
            ]
        ]);
        @file_get_contents(\$url, false, \$context);
    }
}

if (basename(\$_SERVER['PHP_SELF']) === basename(__FILE__)) {
    trackPage();
    echo '<!-- 统计记录已发送 -->';
}
?>
PHP;
    
    return $code;
}

/**
 * 生成引入代码
 */
function generateIncludeCode($trackingCode) {
    $code = <<<PHP
<?php
require_once 'tracking_{$trackingCode}.php';
trackPage();
?>
PHP;
    
    return $code;
}

/**
 * 下载PHP文件
 */
function downloadPhpFile() {
    $projectId = $_GET['project_id'] ?? '';
    
    if (empty($projectId)) {
        jsonResponse(['status' => 'error', 'message' => '缺少项目ID'], 400);
    }
    
    global $db;
    $project = $db->fetchOne("SELECT * FROM projects WHERE id = ?", [$projectId]);
    
    if (!$project) {
        jsonResponse(['status' => 'error', 'message' => '项目不存在'], 404);
    }
    
    // 获取或生成访问密钥
    $accessKey = getOrGenerateAccessKey($projectId);
    
    // 生成PHP代码
    $baseUrl = getBaseUrl();
    $phpCode = generatePhpTrackingCode($baseUrl, $project['tracking_code'], $accessKey);
    
    // 设置下载头
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="tracking_' . $project['tracking_code'] . '.php"');
    header('Content-Length: ' . strlen($phpCode));
    
    echo $phpCode;
    exit;
}

/**
 * 重置访问密钥
 */
function resetAccessKey() {
    $projectId = $_POST['project_id'] ?? '';
    
    if (empty($projectId)) {
        jsonResponse(['status' => 'error', 'message' => '缺少项目ID'], 400);
    }
    
    global $db;
    $project = $db->fetchOne("SELECT * FROM projects WHERE id = ?", [$projectId]);
    
    if (!$project) {
        jsonResponse(['status' => 'error', 'message' => '项目不存在'], 404);
    }
    
    // 生成新的访问密钥
    $accessKey = generateRandomString(32);
    
    // 更新项目记录
    $db->query("UPDATE projects SET access_key = ? WHERE id = ?", [$accessKey, $projectId]);
    
    jsonResponse([
        'status' => 'success',
        'message' => '访问密钥重置成功',
        'data' => ['access_key' => $accessKey]
    ]);
}

/**
 * 获取项目统计信息
 */
function getProjectStats() {
    $projectId = $_GET['project_id'] ?? '';
    
    if (empty($projectId)) {
        jsonResponse(['status' => 'error', 'message' => '缺少项目ID'], 400);
    }
    
    global $db;
    
    // 检查项目是否存在
    $project = $db->fetchOne("SELECT * FROM projects WHERE id = ?", [$projectId]);
    if (!$project) {
        jsonResponse(['status' => 'error', 'message' => '项目不存在'], 404);
    }
    
    // 获取访问记录数量
    $visitCount = $db->fetchOne("SELECT COUNT(*) as count FROM visits WHERE project_id = ?", [$projectId])['count'];
    
    // 获取页面数量
    $pageCount = $db->fetchOne("SELECT COUNT(DISTINCT page_url) as count FROM visits WHERE project_id = ?", [$projectId])['count'];
    
    // 获取唯一IP数量
    $uniqueIpCount = $db->fetchOne("SELECT COUNT(DISTINCT ip_address) as count FROM visits WHERE project_id = ?", [$projectId])['count'];
    
    jsonResponse([
        'status' => 'success',
        'data' => [
            'visit_count' => $visitCount,
            'page_count' => $pageCount,
            'unique_ip_count' => $uniqueIpCount,
            'project' => $project
        ]
    ]);
}
?>
