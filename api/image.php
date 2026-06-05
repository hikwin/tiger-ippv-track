<?php
/**
 * 统计接口 - 图片方式
 * 返回1x1透明像素图片并记录访问统计
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
    // 即使未安装也要输出图片，避免影响页面显示
    header('Content-Type: image/png');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    echo base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');
    exit;
}

// 加载配置和函数
require_once CONFIG_FILE;
require_once ROOT_PATH . '/includes/functions.php';

try {
    // 获取请求参数
    $projectCode = $_GET['project'] ?? '';
    
    // 验证必要参数
    if (empty($projectCode)) {
        throw new Exception('缺少项目跟踪代码参数');
    }
    
    // 自动获取页面信息
    $pageUrl = $_GET['url'] ?? '';
    $pageTitle = $_GET['title'] ?? '';
    
    // 如果没有页面URL，尝试从多个来源获取
    if (empty($pageUrl)) {
        // 检查Referer是否指向API本身，如果是则忽略
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        if (!empty($referer) && strpos($referer, '/api/image.php') === false) {
            // Referer不是指向API本身，可以使用
            $pageUrl = $referer;
        }
        // 如果Referer指向API本身或为空，尝试从其他来源获取
        elseif (!empty($_SERVER['HTTP_HOST'])) {
            // 尝试从HTTP_HOST构建，但需要排除API路径
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $requestUri = $_SERVER['REQUEST_URI'] ?? '';
            // 如果请求URI包含API路径，尝试从查询参数中获取原始页面
            if (strpos($requestUri, '/api/image.php') !== false) {
                // 从查询参数中尝试获取原始页面信息
                $pageUrl = 'unknown'; // 默认值
            } else {
                $pageUrl = $protocol . '://' . $_SERVER['HTTP_HOST'] . $requestUri;
            }
        }
        // 最后才设置为unknown
        else {
            $pageUrl = 'unknown';
        }
    }
    
    // 根据跟踪代码获取项目ID
    $project = $db->fetchOne("SELECT id FROM projects WHERE tracking_code = ? AND is_active = 1", [$projectCode]);
    if (!$project) {
        // 仍然返回图片，但不记录统计
        outputImage();
    }
    $projectId = $project['id'];
    
    // 获取访问信息
    $ip = getRealIP();
    $userAgent = getUserAgent();
    $referer = getReferer();
    $botInfo = isBot($userAgent);
    $isBot = $botInfo['is_bot'];
    $botType = $botInfo['bot_type'];
    $sourceType = getSourceType($referer);
    
    // 检查是否统计爬虫
    if ($isBot && !TRACK_BOTS) {
        // 仍然返回图片，但不记录统计
        outputImage();
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
            // 仍然返回图片，但不记录统计
            outputImage();
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
        'project_id' => $projectId,
        'session_id' => $sessionId ?? '',
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
    writeLog("图片统计记录: IP={$ip}, URL={$pageUrl}, Source={$sourceType}, Bot=" . ($isBot ? 'Yes' : 'No'));
    
    // 输出1x1透明图片
    outputImage();
    
} catch (Exception $e) {
    writeLog("图片统计接口错误: " . $e->getMessage(), 'ERROR');
    // 即使出错也要输出图片，避免影响页面显示
    outputImage();
}

/**
 * 输出1x1透明PNG图片
 */
function outputImage() {
    // 设置图片响应头
    header('Content-Type: image/png');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // 1x1透明PNG图片的二进制数据
    $imageData = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');
    
    // 输出图片
    echo $imageData;
    exit;
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
    // 使用统一的IP地理位置检测函数
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
