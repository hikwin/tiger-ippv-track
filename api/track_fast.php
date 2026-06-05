<?php
/**
 * 快速统计接口 - 性能优化版本
 * 处理访问统计请求（简化版，专注于性能）
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

// 开始计时
$startTime = microtime(true);

// 定义路径常量
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
    
    // 根据跟踪代码获取项目信息（使用缓存）
    static $projectCache = [];
    if (!isset($projectCache[$projectCode])) {
        $project = $db->fetchOne("SELECT id, access_key FROM projects WHERE tracking_code = ? AND is_active = 1", [$projectCode]);
        if (!$project) {
            throw new Exception('无效的项目跟踪代码');
        }
        $projectCache[$projectCode] = $project;
    }
    $project = $projectCache[$projectCode];
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
    
    // 获取访问信息（优先使用URL参数传递的真实访客信息）
    $ip = $_GET['ip'] ?? $_POST['ip'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $userAgent = $_GET['ua'] ?? $_POST['ua'] ?? $_SERVER['HTTP_USER_AGENT'] ?? '';
    $referer = $_GET['referer'] ?? $_POST['referer'] ?? $_SERVER['HTTP_REFERER'] ?? '';
    
    // 使用统一的爬虫检测函数
    $botInfo = isBot($userAgent);
    $isBot = $botInfo['is_bot'];
    $botType = $botInfo['bot_type'];
    
    // 检查是否统计爬虫
    if ($isBot && !TRACK_BOTS) {
        jsonResponse(['status' => 'success', 'message' => 'Bot访问已忽略']);
    }
    
    // 简化的重复检查（只检查最近1分钟）
    if (!TRACK_DUPLICATES) {
        $recentVisit = $db->fetchOne(
            "SELECT id FROM visits WHERE ip_address = ? AND page_url = ? AND visit_time > datetime('now', '-1 minute')",
            [$ip, $pageUrl]
        );
        
        if ($recentVisit) {
            jsonResponse(['status' => 'success', 'message' => '重复访问已忽略']);
        }
    }
    
    // 简化的设备信息解析
    $deviceType = 'desktop';
    $browser = 'unknown';
    $os = 'unknown';
    
    if (!empty($userAgent)) {
        $userAgentLower = strtolower($userAgent);
        
        // 设备类型检测
        if (preg_match('/mobile|android|iphone/', $userAgentLower)) {
            $deviceType = 'mobile';
        } elseif (preg_match('/tablet|ipad/', $userAgentLower)) {
            $deviceType = 'tablet';
        }
        
        // 浏览器检测
        if (strpos($userAgentLower, 'chrome') !== false) {
            $browser = 'Chrome';
        } elseif (strpos($userAgentLower, 'firefox') !== false) {
            $browser = 'Firefox';
        } elseif (strpos($userAgentLower, 'safari') !== false) {
            $browser = 'Safari';
        }
        
        // 操作系统检测
        if (strpos($userAgentLower, 'windows') !== false) {
            $os = 'Windows';
        } elseif (strpos($userAgentLower, 'mac') !== false) {
            $os = 'macOS';
        } elseif (strpos($userAgentLower, 'linux') !== false) {
            $os = 'Linux';
        }
    }
    
    // 使用精细化的来源类型检测
    $sourceType = getDetailedSourceType($referer, $pageUrl);
    
    // 获取地理位置信息
    $locationInfo = getLocationByIp($ip);
    
    // 提取来源host地址
    $refererHost = extractRefererHost($referer, $pageUrl);
    
    // 准备访问记录数据（简化版）
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
        'session_id' => $_COOKIE['stat_session'] ?? '',
        'project_id' => $projectId,
        'country' => $locationInfo['country'],
        'city' => $locationInfo['city'],
        'province' => $locationInfo['province'],
        'device_type' => $deviceType,
        'browser' => $browser,
        'os' => $os
    ];
    
    // 插入访问记录
    $visitId = $db->insert('visits', $visitData);
    
    // 简化的页面统计更新（异步处理）
    $pageUpdateData = [
        'page_url' => $pageUrl,
        'page_title' => $pageTitle,
        'total_views' => 1,
        'unique_views' => 1,
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ];
    
    // 使用INSERT OR REPLACE简化页面统计
    $db->query(
        "INSERT OR REPLACE INTO pages (page_url, page_title, total_views, unique_views, created_at, updated_at) 
         VALUES (?, ?, COALESCE((SELECT total_views FROM pages WHERE page_url = ?), 0) + 1, 
                 COALESCE((SELECT unique_views FROM pages WHERE page_url = ?), 0) + 1, 
                 COALESCE((SELECT created_at FROM pages WHERE page_url = ?), ?), ?)",
        [$pageUrl, $pageTitle, $pageUrl, $pageUrl, $pageUrl, $pageUpdateData['created_at'], $pageUpdateData['updated_at']]
    );
    
    // 计算处理时间
    $endTime = microtime(true);
    $processTime = round(($endTime - $startTime) * 1000, 2);
    
    // 返回成功响应
    jsonResponse([
        'status' => 'success',
        'message' => '统计记录成功',
        'data' => [
            'visit_id' => $visitId,
            'timestamp' => time(),
            'process_time' => $processTime . 'ms'
        ]
    ]);
    
} catch (Exception $e) {
    // #11 不向客户端暴露内部异常细节
    error_log('[IPPVS track_fast] ' . $e->getMessage());
    jsonResponse([
        'status'  => 'error',
        'message' => '请求处理失败，请稍后重试'
    ], 400);
}

// getLocationByIp函数已在includes/functions.php中定义，这里不再重复定义
?>
