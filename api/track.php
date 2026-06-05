<?php
/**
 * 统计接口 - JavaScript方式
 * 处理访问统计请求
 */

// 设置响应头
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// 处理预检请求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

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

try {
    // 获取请求参数
    $projectCode = $_GET['project'] ?? $_POST['project'] ?? '';
    $pageUrl = $_GET['url'] ?? $_POST['url'] ?? '';
    $pageTitle = $_GET['title'] ?? $_POST['title'] ?? '';
    $accessKey = $_GET['access_key'] ?? $_POST['access_key'] ?? '';
    
    // 验证必要参数
    if (empty($projectCode)) {
        throw new Exception('缺少项目跟踪代码参数');
    }
    
    // 根据跟踪代码获取项目信息
    $project = $db->fetchOne("SELECT id, access_key FROM projects WHERE tracking_code = ? AND is_active = 1", [$projectCode]);
    if (!$project) {
        throw new Exception('无效的项目跟踪代码');
    }
    $projectId = $project['id'];
    
    // 验证访问密钥（如果提供了access_key参数）
    if (!empty($accessKey)) {
        if (empty($project['access_key'])) {
            throw new Exception('该项目未配置访问密钥');
        }
        if ($project['access_key'] !== $accessKey) {
            throw new Exception('访问密钥验证失败');
        }
    }
    
    // 项目已验证，继续处理
    
    // 获取访问信息
    $ip = getRealIP();
    $userAgent = getUserAgent();
    $referer = getReferer();
    $botInfo = isBot($userAgent);
    $isBot = $botInfo['is_bot'];
    $botType = $botInfo['bot_type'];
    $sourceType = getSourceType($referer, $pageUrl);
    
    // 检查是否统计爬虫
    if ($isBot && !TRACK_BOTS) {
        jsonResponse(['status' => 'success', 'message' => 'Bot访问已忽略']);
    }
    
    // 检查重复统计
    if (!TRACK_DUPLICATES) {
        $sessionId = $_COOKIE['stat_session'] ?? '';
        if (empty($sessionId)) {
            $sessionId = generateRandomString(32);
            setcookie('stat_session', $sessionId, time() + 3600, '/');
        }
        
        // 检查是否在短时间内重复访问
        $recentVisit = $db->fetchOne(
            "SELECT id FROM visits WHERE ip_address = ? AND page_url = ? AND visit_time > datetime('now', '-5 minutes')",
            [$ip, $pageUrl]
        );
        
        if ($recentVisit) {
            jsonResponse(['status' => 'success', 'message' => '重复访问已忽略']);
        }
    }
    
    // 解析用户代理信息
    $deviceInfo = parseUserAgent($userAgent);
    
    // 获取地理位置信息（简化版）
    $location = getLocationInfo($ip);
    
    // 提取来源host地址
    $refererHost = extractRefererHost($referer, $pageUrl);
    
    // 准备访问记录数据
    $visitData = [
        'ip_address' => $ip,
        'user_agent' => $userAgent,
        'referer' => $referer,
        'referer_host' => $refererHost,
        'source_type' => $sourceType,
        'page_url' => $pageUrl,
        'page_title' => $pageTitle,
        'is_bot' => $isBot ? 1 : 0,
        'bot_type' => $botType,
        'visit_time' => date('Y-m-d H:i:s'),
        'session_id' => $sessionId ?? '',
        'project_id' => $projectId,
        'country' => $location['country'] ?? '',
        'city' => $location['city'] ?? '',
        'province' => $location['province'] ?? '',
        'device_type' => $deviceInfo['device_type'] ?? '',
        'browser' => $deviceInfo['browser'] ?? '',
        'os' => $deviceInfo['os'] ?? ''
    ];
    
    // 插入访问记录
    $visitId = $db->insert('visits', $visitData);
    
    // 更新页面统计
    updatePageStats($pageUrl, $pageTitle);
    
    // 记录日志
    writeLog("访问记录: IP={$ip}, URL={$pageUrl}, Source={$sourceType}, Bot=" . ($isBot ? 'Yes' : 'No'));
    
    // 返回成功响应
    jsonResponse([
        'status' => 'success',
        'message' => '统计记录成功',
        'data' => [
            'visit_id' => $visitId,
            'timestamp' => time()
        ]
    ]);
    
} catch (Exception $e) {
    // #11 不向客户端暴露异常细节
    writeLog("统计接口错误: " . $e->getMessage(), 'ERROR');
    jsonResponse([
        'status'  => 'error',
        'message' => '请求处理失败，请稍后重试'
    ], 400);
}

/**
 * 解析用户代理信息
 */
function parseUserAgent($userAgent) {
    $deviceType = 'desktop';
    $browser = 'unknown';
    $os = 'unknown';
    
    $userAgent = strtolower($userAgent);
    
    // 检测设备类型
    if (preg_match('/mobile|android|iphone|ipad|tablet/', $userAgent)) {
        $deviceType = 'mobile';
    } elseif (preg_match('/tablet|ipad/', $userAgent)) {
        $deviceType = 'tablet';
    }
    
    // 检测浏览器
    if (strpos($userAgent, 'chrome') !== false) {
        $browser = 'Chrome';
    } elseif (strpos($userAgent, 'firefox') !== false) {
        $browser = 'Firefox';
    } elseif (strpos($userAgent, 'safari') !== false) {
        $browser = 'Safari';
    } elseif (strpos($userAgent, 'edge') !== false) {
        $browser = 'Edge';
    } elseif (strpos($userAgent, 'opera') !== false) {
        $browser = 'Opera';
    }
    
    // 检测操作系统
    if (strpos($userAgent, 'windows') !== false) {
        $os = 'Windows';
    } elseif (strpos($userAgent, 'mac') !== false) {
        $os = 'macOS';
    } elseif (strpos($userAgent, 'linux') !== false) {
        $os = 'Linux';
    } elseif (strpos($userAgent, 'android') !== false) {
        $os = 'Android';
    } elseif (strpos($userAgent, 'ios') !== false) {
        $os = 'iOS';
    }
    
    return [
        'device_type' => $deviceType,
        'browser' => $browser,
        'os' => $os
    ];
}

/**
 * 获取地理位置信息（简化版）
 */
function getLocationInfo($ip) {
    // 使用新的IP地理位置检测函数
    return getIPLocation($ip);
}

/**
 * 更新页面统计
 */
function updatePageStats($pageUrl, $pageTitle) {
    global $db;
    
    // 检查页面是否已存在
    $page = $db->fetchOne(
        "SELECT * FROM pages WHERE page_url = ?",
        [$pageUrl]
    );
    
    if ($page) {
        // 更新现有页面统计
        $db->update(
            'pages',
            [
                'page_title' => $pageTitle,
                'total_views' => $page['total_views'] + 1,
                'updated_at' => date('Y-m-d H:i:s')
            ],
            'page_url = ?',
            [$pageUrl]
        );
    } else {
        // 创建新页面记录
        $db->insert('pages', [
            'page_url' => $pageUrl,
            'page_title' => $pageTitle,
            'total_views' => 1,
            'unique_views' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);
    }
}
?>
