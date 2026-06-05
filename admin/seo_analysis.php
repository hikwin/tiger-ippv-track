<?php
/**
 * SEO分析页面
 * 显示爬虫特征排行和饼状图
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
if (!file_exists(INSTALL_LOCK_FILE)) {
    // 未安装，跳转到安装页面
    header('Location: ../install.php');
    exit;
}

// 检查配置文件是否存在
if (!file_exists(CONFIG_FILE)) {
    // 配置文件不存在，跳转到安装页面
    header('Location: ../install.php');
    exit;
}

// 加载配置和函数
require_once CONFIG_FILE;
require_once ROOT_PATH . '/includes/functions.php';

// 检查登录状态
session_start();
if (!isset($_SESSION['admin_id'])) {
    // 判断是通过根目录访问还是直接访问admin目录
    $isFromRoot = strpos($_SERVER['REQUEST_URI'], 'index.php?action=admin') !== false;
    $loginPath = $isFromRoot ? 'admin/login.php' : 'login.php';
    header('Location: ' . $loginPath);
    exit;
}

// 获取筛选参数
$timeFilter = isset($_GET['time_filter']) ? $_GET['time_filter'] : 'total';
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : null;
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : null;
$projectId = isset($_GET['project_id']) ? $_GET['project_id'] : '';

// 获取项目列表
$projects = $db->fetchAll("SELECT id, name FROM projects ORDER BY name");

// 获取分页参数
$referrerPage = isset($_GET['referrer_page']) ? max(1, intval($_GET['referrer_page'])) : 1;
$referrerLimit = 15;

// 初始化默认空数据（避免初始同步加载卡顿，改由前端 DOM 加载后异步获取）
$seoData = [
    'bot_types' => [],
    'search_engines' => [],
    'referrers' => []
];
$referrerTotalCount = 0;
$referrerTotalPages = 0;

/**
 * 验证日期字符串是否为合法 Y-m-d 格式
 */
function validateDateParam($date) {
    if (empty($date)) return null;
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) return null;
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return ($d && $d->format('Y-m-d') === $date) ? $date : null;
}

/**
 * 构建时间筛选条件
 */
function buildTimeFilterCondition($timeFilter, $startDate = null, $endDate = null) {
    $allowed = ['today', 'yesterday', 'week', 'month', 'custom', 'total'];
    if (!in_array($timeFilter, $allowed, true)) {
        return '';
    }
    switch ($timeFilter) {
        case 'today':
            return " AND DATE(visit_time) = DATE('now')";
        case 'yesterday':
            return " AND DATE(visit_time) = DATE('now', '-1 day')";
        case 'week':
            return " AND visit_time >= DATETIME('now', '-7 days')";
        case 'month':
            return " AND visit_time >= DATETIME('now', '-30 days')";
        case 'custom':
            $safeStart = validateDateParam($startDate);
            $safeEnd   = validateDateParam($endDate);
            if ($safeStart && $safeEnd) {
                return " AND DATE(visit_time) BETWEEN '{$safeStart}' AND '{$safeEnd}'";
            }
            return '';
        default:
            return '';
    }
}

/**
 * 获取SEO分析数据
 */
function getSeoAnalysisData($timeFilter = 'total', $startDate = null, $endDate = null, $projectId = '', $referrerPage = 1, $referrerLimit = 15) {
    global $db;
    
    $timeFilterCondition = buildTimeFilterCondition($timeFilter, $startDate, $endDate);
    // 强制转 int，防止 projectId 注入
    $safeProjectId = !empty($projectId) ? intval($projectId) : 0;
    $projectFilterCondition = $safeProjectId > 0 ? " AND project_id = {$safeProjectId}" : '';
    
    // 获取爬虫类型排行
    $botTypeData = $db->fetchAll(
        "SELECT 
            CASE 
                WHEN bot_type IS NULL OR bot_type = '' THEN '未知爬虫'
                ELSE bot_type
            END as bot_type,
            COUNT(*) as visit_count,
            COUNT(DISTINCT ip_address) as unique_visitors
         FROM visits 
         WHERE is_bot = 1" . $timeFilterCondition . $projectFilterCondition . "
         GROUP BY 
            CASE 
                WHEN bot_type IS NULL OR bot_type = '' THEN '未知爬虫'
                ELSE bot_type
            END
         ORDER BY visit_count DESC"
    );
    
    // 获取搜索引擎爬虫排行
    $searchEngineData = $db->fetchAll(
        "SELECT 
            CASE 
                WHEN bot_type LIKE '%Google%' OR bot_type LIKE '%googlebot%' THEN 'Google'
                WHEN bot_type LIKE '%Baidu%' OR bot_type LIKE '%baiduspider%' THEN '百度'
                WHEN bot_type LIKE '%Bing%' OR bot_type LIKE '%bingbot%' THEN 'Bing'
                WHEN bot_type LIKE '%Yandex%' OR bot_type LIKE '%yandexbot%' THEN 'Yandex'
                WHEN bot_type LIKE '%Sogou%' OR bot_type LIKE '%sogou%' THEN '搜狗'
                WHEN bot_type LIKE '%360%' OR bot_type LIKE '%360spider%' THEN '360搜索'
                WHEN bot_type LIKE '%Shenma%' OR bot_type LIKE '%shenma%' THEN '神马'
                ELSE '其他搜索引擎'
            END as search_engine,
            COUNT(*) as visit_count,
            COUNT(DISTINCT ip_address) as unique_visitors
         FROM visits 
         WHERE is_bot = 1" . $timeFilterCondition . $projectFilterCondition . "
         GROUP BY 
            CASE 
                WHEN bot_type LIKE '%Google%' OR bot_type LIKE '%googlebot%' THEN 'Google'
                WHEN bot_type LIKE '%Baidu%' OR bot_type LIKE '%baiduspider%' THEN '百度'
                WHEN bot_type LIKE '%Bing%' OR bot_type LIKE '%bingbot%' THEN 'Bing'
                WHEN bot_type LIKE '%Yandex%' OR bot_type LIKE '%yandexbot%' THEN 'Yandex'
                WHEN bot_type LIKE '%Sogou%' OR bot_type LIKE '%sogou%' THEN '搜狗'
                WHEN bot_type LIKE '%360%' OR bot_type LIKE '%360spider%' THEN '360搜索'
                WHEN bot_type LIKE '%Shenma%' OR bot_type LIKE '%shenma%' THEN '神马'
                ELSE '其他搜索引擎'
            END
         ORDER BY visit_count DESC"
    );
    
    // 计算外链接分页偏移量
    $referrerOffset = ($referrerPage - 1) * $referrerLimit;
    
    // 获取外链接进入排行（按referer_host分组）
    $referrerData = $db->fetchAll(
        "SELECT 
            CASE 
                WHEN referer_host IS NULL OR referer_host = '' THEN '直接访问/内部链接'
                ELSE referer_host
            END as referer_host,
            COUNT(*) as visit_count,
            COUNT(DISTINCT ip_address) as unique_visitors,
            GROUP_CONCAT(DISTINCT referer) as sample_urls
         FROM visits 
         WHERE 1=1" . $timeFilterCondition . $projectFilterCondition . "
         GROUP BY 
            CASE 
                WHEN referer_host IS NULL OR referer_host = '' THEN '直接访问/内部链接'
                ELSE referer_host
            END
         ORDER BY visit_count DESC
         LIMIT {$referrerLimit} OFFSET {$referrerOffset}"
    );
    
    return [
        'bot_types' => $botTypeData,
        'search_engines' => $searchEngineData,
        'referrers' => $referrerData
    ];
}

/**
 * 获取外链接总数
 */
function getReferrerCount($timeFilter = 'total', $startDate = null, $endDate = null, $projectId = '') {
    global $db;
    
    $timeFilterCondition = buildTimeFilterCondition($timeFilter, $startDate, $endDate);
    // 强制转 int，防止 projectId 注入
    $safeProjectId = !empty($projectId) ? intval($projectId) : 0;
    $projectFilterCondition = $safeProjectId > 0 ? " AND project_id = {$safeProjectId}" : '';
    
    // 性能优化：避免在大型数据集上执行 COUNT(DISTINCT CASE WHEN ...)
    // 1. 利用索引快速统计有值的 referer_host 数量
    $referrerCountRaw = $db->fetchOne(
        "SELECT COUNT(DISTINCT referer_host) as count FROM visits WHERE referer_host IS NOT NULL AND referer_host != ''" . $timeFilterCondition . $projectFilterCondition
    )['count'];
    
    // 2. 检查是否存在“直接访问/内部链接”的记录（referer_host 为空或为 NULL）
    $hasDirect = $db->fetchOne(
        "SELECT 1 FROM visits WHERE (referer_host IS NULL OR referer_host = '')" . $timeFilterCondition . $projectFilterCondition . " LIMIT 1"
    );
    
    return $referrerCountRaw + ($hasDirect ? 1 : 0);
}

/**
 * 获取SEO统计概览
 */
function getSeoStats($timeFilter = 'total', $startDate = null, $endDate = null, $projectId = '') {
    global $db;
    
    $timeFilterCondition = buildTimeFilterCondition($timeFilter, $startDate, $endDate);
    // 强制转 int，防止 projectId 注入
    $safeProjectId = !empty($projectId) ? intval($projectId) : 0;
    $projectFilterCondition = $safeProjectId > 0 ? " AND project_id = {$safeProjectId}" : '';
    
    // 1. 总爬虫访问数
    $totalBotVisits = $db->fetchOne(
        "SELECT COUNT(*) as count FROM visits WHERE is_bot = 1" . $timeFilterCondition . $projectFilterCondition
    )['count'];
    
    // 2. 不同爬虫类型数
    $botTypesCount = $db->fetchOne(
        "SELECT COUNT(DISTINCT bot_type) as count FROM visits WHERE is_bot = 1 AND bot_type IS NOT NULL AND bot_type != ''" . $timeFilterCondition . $projectFilterCondition
    )['count'];
    
    // 3. 搜索引擎爬虫数（快速归类计数，避免数据库层面对千万条记录进行繁重的 LIKE DISTINCT CASE WHEN 计算）
    $searchEngineCount = $db->fetchOne(
        "SELECT COUNT(DISTINCT 
            CASE 
                WHEN bot_type LIKE '%Google%' OR bot_type LIKE '%googlebot%' THEN 1
                WHEN bot_type LIKE '%Baidu%' OR bot_type LIKE '%baiduspider%' THEN 2
                WHEN bot_type LIKE '%Bing%' OR bot_type LIKE '%bingbot%' THEN 3
                WHEN bot_type LIKE '%Yandex%' OR bot_type LIKE '%yandexbot%' THEN 4
                WHEN bot_type LIKE '%Sogou%' OR bot_type LIKE '%sogou%' THEN 5
                WHEN bot_type LIKE '%360%' OR bot_type LIKE '%360spider%' THEN 6
                WHEN bot_type LIKE '%Shenma%' OR bot_type LIKE '%shenma%' THEN 7
                ELSE 8
            END
        ) as count FROM visits WHERE is_bot = 1" . $timeFilterCondition . $projectFilterCondition
    )['count'];
    
    // 4. 外链接来源数（性能优化：避免 COUNT(DISTINCT CASE WHEN ...) 扫表）
    $referrersCountRaw = $db->fetchOne(
        "SELECT COUNT(DISTINCT referer_host) as count FROM visits WHERE referer_host IS NOT NULL AND referer_host != ''" . $timeFilterCondition . $projectFilterCondition
    )['count'];
    
    $hasDirect = $db->fetchOne(
        "SELECT 1 FROM visits WHERE (referer_host IS NULL OR referer_host = '')" . $timeFilterCondition . $projectFilterCondition . " LIMIT 1"
    );
    
    $referrersCount = $referrersCountRaw + ($hasDirect ? 1 : 0);
    
    return [
        'total_bot_visits' => $totalBotVisits,
        'bot_types_count' => $botTypesCount,
        'search_engines_count' => $searchEngineCount,
        'referrers_count' => $referrersCount
    ];
}

// 初始化默认概览数据（避免初次加载卡顿，由前端异步拉取）
$stats = [
    'total_bot_visits' => 0,
    'bot_types_count' => 0,
    'search_engines_count' => 0,
    'referrers_count' => 0
];

// 处理AJAX请求
if (isset($_GET['action']) && $_GET['action'] === 'getBotTypeData') {
    // 重新获取筛选参数
    $timeFilter = isset($_GET['time_filter']) ? $_GET['time_filter'] : 'total';
    $startDate = isset($_GET['start_date']) ? $_GET['start_date'] : null;
    $endDate = isset($_GET['end_date']) ? $_GET['end_date'] : null;
    $projectId = isset($_GET['project_id']) ? $_GET['project_id'] : '';
    
    try {
        $seoData = getSeoAnalysisData($timeFilter, $startDate, $endDate, $projectId);
        
        header('Content-Type: application/json');
        echo json_encode([
            'code' => 0,
            'msg' => 'success',
            'data' => [
                'bot_types' => $seoData['bot_types'],
                'search_engines' => $seoData['search_engines']
            ]
        ]);
    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode([
            'code' => 1,
            'msg' => '数据获取失败: ' . $e->getMessage(),
            'data' => [
                'bot_types' => [],
                'search_engines' => []
            ]
        ]);
    }
    exit;
}

if (isset($_GET['action']) && $_GET['action'] === 'getReferrerData') {
    // 重新获取筛选参数
    $timeFilter = isset($_GET['time_filter']) ? $_GET['time_filter'] : 'total';
    $startDate = isset($_GET['start_date']) ? $_GET['start_date'] : null;
    $endDate = isset($_GET['end_date']) ? $_GET['end_date'] : null;
    $projectId = isset($_GET['project_id']) ? $_GET['project_id'] : '';
    $referrerPage = isset($_GET['referrer_page']) ? max(1, intval($_GET['referrer_page'])) : 1;
    $referrerLimit = isset($_GET['referrer_limit']) ? max(1, intval($_GET['referrer_limit'])) : 15;
    
    try {
        $referrerData = getSeoAnalysisData($timeFilter, $startDate, $endDate, $projectId, $referrerPage, $referrerLimit);
        $referrerTotalCount = getReferrerCount($timeFilter, $startDate, $endDate, $projectId);
        
        // 添加排名
        $startRank = ($referrerPage - 1) * $referrerLimit + 1;
        foreach ($referrerData['referrers'] as $index => &$referrer) {
            $referrer['rank'] = $startRank + $index;
        }
        
        header('Content-Type: application/json');
        echo json_encode([
            'code' => 0,
            'msg' => 'success',
            'count' => $referrerTotalCount,
            'data' => $referrerData['referrers']
        ]);
    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode([
            'code' => 1,
            'msg' => '数据获取失败: ' . $e->getMessage(),
            'count' => 0,
            'data' => []
        ]);
    }
    exit;
}

if (isset($_GET['action']) && $_GET['action'] === 'getStatsOverview') {
    // 重新获取筛选参数
    $timeFilter = isset($_GET['time_filter']) ? $_GET['time_filter'] : 'total';
    $startDate = isset($_GET['start_date']) ? $_GET['start_date'] : null;
    $endDate = isset($_GET['end_date']) ? $_GET['end_date'] : null;
    $projectId = isset($_GET['project_id']) ? $_GET['project_id'] : '';
    
    try {
        $stats = getSeoStats($timeFilter, $startDate, $endDate, $projectId);
        
        header('Content-Type: application/json');
        echo json_encode([
            'code' => 0,
            'msg' => 'success',
            'data' => $stats
        ]);
    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode([
            'code' => 1,
            'msg' => '统计数据获取失败: ' . $e->getMessage(),
            'data' => [
                'total_bot_visits' => 0,
                'bot_types_count' => 0,
                'search_engines_count' => 0,
                'referrers_count' => 0
            ]
        ]);
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SEO分析 - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/layui@2.8.18/dist/css/layui.css">
    <script src="https://cdn.jsdelivr.net/npm/layui@2.8.18/dist/layui.js"></script>
    <script src="../assets/js/echarts/echarts.min.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f7fa;
            color: #333;
        }
        
        .main-content {
            margin-top: 60px;
            padding: 20px;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .page-title {
            font-size: 28px;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 30px;
            text-align: center;
        }
        
        /* 筛选器样式 */
        .filter-section {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .filter-form {
            display: flex;
            gap: 20px;
            align-items: end;
            flex-wrap: wrap;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .form-group label {
            font-weight: 500;
            color: #555;
            font-size: 14px;
        }
        
        .form-group select,
        .form-group input {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            min-width: 120px;
        }
        
        .form-group input[type="date"] {
            min-width: 150px;
        }
        
        .btn {
            padding: 8px 16px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            transition: background 0.3s;
        }
        
        .btn:hover {
            background: #5a6fd8;
        }
        
        /* 统计卡片 */
        .stats-section {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            text-align: center;
            transition: transform 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
        }
        
        .stat-card h3 {
            color: #666;
            font-size: 14px;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .stat-card .number {
            font-size: 32px;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 5px;
        }
        
        .stat-card .change {
            color: #888;
            font-size: 12px;
        }
        
        /* 分析区域 */
        .analysis-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .analysis-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .analysis-header {
            background: #667eea;
            color: white;
            padding: 20px;
            font-size: 18px;
            font-weight: 600;
        }
        
        .analysis-content {
            padding: 20px;
        }
        
        /* 表格样式 */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        
        .data-table th,
        .data-table td {
            padding: 12px 8px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        .data-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #555;
            font-size: 13px;
        }
        
        .data-table td {
            font-size: 14px;
        }
        
        .data-table tr:hover {
            background: #f8f9fa;
        }
        
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .badge-primary {
            background: #e3f2fd;
            color: #1976d2;
        }
        
        .badge-success {
            background: #e8f5e8;
            color: #2e7d32;
        }
        
        .badge-warning {
            background: #fff3e0;
            color: #f57c00;
        }
        
        /* 图表容器 */
        .chart-container {
            height: 400px;
            margin-top: 20px;
        }
        
        /* 页面排行区域 */
        .pages-section {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .pages-header {
            background: #28a745;
            color: white;
            padding: 20px;
            font-size: 18px;
            font-weight: 600;
        }
        
        .pages-content {
            padding: 20px;
        }
        
        .page-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid #eee;
        }
        
        .page-item:last-child {
            border-bottom: none;
        }
        
        .page-info {
            flex: 1;
        }
        
        .page-title {
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
            font-size: 14px;
            word-break: break-all;
            line-height: 1.4;
        }
        
        .page-url {
            color: #666;
            font-size: 12px;
            word-break: break-all;
        }
        
        .page-stats {
            text-align: right;
            margin-left: 20px;
        }
        
        .page-count {
            font-weight: bold;
            color: #667eea;
            font-size: 16px;
        }
        
        .page-unique {
            color: #666;
            font-size: 12px;
        }
        
        /* 响应式设计 */
        @media (max-width: 1200px) {
            .analysis-section {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .filter-form {
                flex-direction: column;
                align-items: stretch;
            }
            
            .stats-section {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            }
            
            .analysis-content {
                padding: 15px;
            }
            
            .chart-container {
                height: 300px;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="main-content">
        <div class="container">
            <!-- 筛选器 -->
            <div class="filter-section">
                <form method="GET" class="filter-form">
                    <div class="form-group">
                        <label for="time_filter">时间范围</label>
                        <select id="time_filter" name="time_filter">
                            <option value="total" <?php echo $timeFilter === 'total' ? 'selected' : ''; ?>>全部时间</option>
                            <option value="today" <?php echo $timeFilter === 'today' ? 'selected' : ''; ?>>今天</option>
                            <option value="yesterday" <?php echo $timeFilter === 'yesterday' ? 'selected' : ''; ?>>昨天</option>
                            <option value="week" <?php echo $timeFilter === 'week' ? 'selected' : ''; ?>>最近7天</option>
                            <option value="month" <?php echo $timeFilter === 'month' ? 'selected' : ''; ?>>最近30天</option>
                            <option value="custom" <?php echo $timeFilter === 'custom' ? 'selected' : ''; ?>>自定义</option>
                        </select>
                    </div>
                    
                    <div class="form-group" id="customDateGroup" style="<?php echo $timeFilter !== 'custom' ? 'display: none;' : ''; ?>">
                        <label for="start_date">开始日期</label>
                        <input type="date" id="start_date" name="start_date" value="<?php echo $startDate; ?>">
                    </div>
                    
                    <div class="form-group" id="customDateGroup2" style="<?php echo $timeFilter !== 'custom' ? 'display: none;' : ''; ?>">
                        <label for="end_date">结束日期</label>
                        <input type="date" id="end_date" name="end_date" value="<?php echo $endDate; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="project_id">统计项目</label>
                        <select id="project_id" name="project_id">
                            <option value="">全部项目</option>
                            <?php foreach ($projects as $project): ?>
                                <option value="<?php echo $project['id']; ?>" <?php echo $projectId === $project['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($project['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <button type="button" class="btn" id="filterBtn">筛选</button>
                </form>
            </div>
            
            <!-- 统计概览 -->
            <div class="stats-section">
                <div class="stat-card">
                    <h3>爬虫访问总数</h3>
                    <div class="number"><?php echo formatNumber($stats['total_bot_visits']); ?></div>
                    <div class="change">总访问次数</div>
                </div>
                
                <div class="stat-card">
                    <h3>爬虫类型</h3>
                    <div class="number"><?php echo formatNumber($stats['bot_types_count']); ?></div>
                    <div class="change">不同爬虫类型</div>
                </div>
                
                <div class="stat-card">
                    <h3>搜索引擎</h3>
                    <div class="number"><?php echo formatNumber($stats['search_engines_count']); ?></div>
                    <div class="change">不同搜索引擎</div>
                </div>
                
                <div class="stat-card">
                    <h3>外链接来源</h3>
                    <div class="number"><?php echo formatNumber($stats['referrers_count']); ?></div>
                    <div class="change">不同来源数量</div>
                </div>
            </div>
            
            <!-- 分析区域 -->
            <div class="analysis-section">
                <!-- 爬虫类型排行 -->
                <div class="analysis-card">
                    <div class="analysis-header">爬虫类型排行</div>
                    <div class="analysis-content">
                        <table class="data-table" id="botTypeTable">
                            <thead>
                                <tr>
                                    <th>排名</th>
                                    <th>爬虫类型</th>
                                    <th>访问次数</th>
                                    <th>独立访客</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($seoData['bot_types'])): ?>
                                <tr>
                                    <td colspan="4" style="text-align: center; color: #999; padding: 30px;">
                                        <i class="layui-icon layui-icon-loading layui-anim layui-anim-rotate layui-anim-loop" style="display: inline-block; font-size: 20px; margin-bottom: 10px;"></i>
                                        <div>数据分析中，请稍候...</div>
                                    </td>
                                </tr>
                                <?php else: ?>
                                    <?php 
                                    $rank = 1;
                                    $totalBotVisits = $stats['total_bot_visits'];
                                    foreach ($seoData['bot_types'] as $bot): 
                                        $percentage = $totalBotVisits > 0 ? round(($bot['visit_count'] / $totalBotVisits) * 100, 1) : 0;
                                    ?>
                                    <tr>
                                        <td><?php echo $rank++; ?></td>
                                        <td><?php echo htmlspecialchars($bot['bot_type']); ?></td>
                                        <td><span class="badge badge-primary"><?php echo formatNumber($bot['visit_count']); ?></span></td>
                                        <td><span class="badge badge-success"><?php echo formatNumber($bot['unique_visitors']); ?></span></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                        
                        <!-- 爬虫类型饼状图 -->
                        <div class="chart-container" id="botTypeChart"></div>
                    </div>
                </div>
                
                <!-- 搜索引擎排行 -->
                <div class="analysis-card">
                    <div class="analysis-header">搜索引擎排行</div>
                    <div class="analysis-content">
                        <table class="data-table" id="searchEngineTable">
                            <thead>
                                <tr>
                                    <th>排名</th>
                                    <th>搜索引擎</th>
                                    <th>访问次数</th>
                                    <th>独立访客</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($seoData['search_engines'])): ?>
                                <tr>
                                    <td colspan="4" style="text-align: center; color: #999; padding: 30px;">
                                        <i class="layui-icon layui-icon-loading layui-anim layui-anim-rotate layui-anim-loop" style="display: inline-block; font-size: 20px; margin-bottom: 10px;"></i>
                                        <div>数据分析中，请稍候...</div>
                                    </td>
                                </tr>
                                <?php else: ?>
                                    <?php 
                                    $rank = 1;
                                    foreach ($seoData['search_engines'] as $engine): 
                                        $percentage = $totalBotVisits > 0 ? round(($engine['visit_count'] / $totalBotVisits) * 100, 1) : 0;
                                    ?>
                                    <tr>
                                        <td><?php echo $rank++; ?></td>
                                        <td><?php echo htmlspecialchars($engine['search_engine']); ?></td>
                                        <td><span class="badge badge-primary"><?php echo formatNumber($engine['visit_count']); ?></span></td>
                                        <td><span class="badge badge-success"><?php echo formatNumber($engine['unique_visitors']); ?></span></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                        
                        <!-- 搜索引擎饼状图 -->
                        <div class="chart-container" id="searchEngineChart"></div>
                    </div>
                </div>
            </div>
            
            <!-- 外链接进入排行 -->
            <div class="pages-section">
                <div class="pages-header">外链接进入排行</div>
                <div class="pages-content">
                    <table class="layui-hide" id="referrerTable" lay-filter="referrerTable"></table>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // 判定夜间模式
        const isDark = document.documentElement.classList.contains('dark-mode');

        // 初始化 ECharts 实例
        const botTypeChart = echarts.init(document.getElementById('botTypeChart'));
        const searchEngineChart = echarts.init(document.getElementById('searchEngineChart'));
        
        // 启用高级发光加载动画
        botTypeChart.showLoading({ text: '数据分析中...', color: '#667eea', textColor: '#667eea', maskColor: isDark ? 'rgba(17, 24, 39, 0.8)' : 'rgba(255, 255, 255, 0.8)' });
        searchEngineChart.showLoading({ text: '数据分析中...', color: '#28a745', textColor: '#28a745', maskColor: isDark ? 'rgba(17, 24, 39, 0.8)' : 'rgba(255, 255, 255, 0.8)' });
        
        // 初始化 Echarts 配置
        const botTypeOption = {
            tooltip: {
                trigger: 'item',
                formatter: '{a} <br/>{b}: {c} ({d}%)'
            },
            legend: {
                orient: 'vertical',
                left: 'left',
                top: 'middle',
                textStyle: {
                    fontSize: 12,
                    color: isDark ? '#cbd5e1' : '#333'
                }
            },
            series: [
                {
                    name: '爬虫类型',
                    type: 'pie',
                    radius: ['40%', '70%'],
                    center: ['60%', '50%'],
                    avoidLabelOverlap: false,
                    itemStyle: {
                        borderRadius: 10,
                        borderColor: '#fff',
                        borderWidth: 2
                    },
                    label: {
                        show: false,
                        position: 'center'
                    },
                    emphasis: {
                        label: {
                            show: true,
                            fontSize: '18',
                            fontWeight: 'bold'
                        }
                    },
                    labelLine: {
                        show: false
                    },
                    data: []
                }
            ],
            color: ['#667eea', '#f093fb', '#4facfe', '#43e97b', '#fa709a', '#ffecd2', '#a8edea', '#d299c2']
        };
        
        const searchEngineOption = {
            tooltip: {
                trigger: 'item',
                formatter: '{a} <br/>{b}: {c} ({d}%)'
            },
            legend: {
                orient: 'vertical',
                left: 'left',
                top: 'middle',
                textStyle: {
                    fontSize: 12,
                    color: isDark ? '#cbd5e1' : '#333'
                }
            },
            series: [
                {
                    name: '搜索引擎',
                    type: 'pie',
                    radius: ['40%', '70%'],
                    center: ['60%', '50%'],
                    avoidLabelOverlap: false,
                    itemStyle: {
                        borderRadius: 10,
                        borderColor: '#fff',
                        borderWidth: 2
                    },
                    label: {
                        show: false,
                        position: 'center'
                    },
                    emphasis: {
                        label: {
                            show: true,
                            fontSize: '18',
                            fontWeight: 'bold'
                        }
                    },
                    labelLine: {
                        show: false
                    },
                    data: []
                }
            ],
            color: ['#28a745', '#ffc107', '#dc3545', '#17a2b8', '#6f42c1', '#fd7e14', '#20c997', '#6c757d']
        };
        
        botTypeChart.setOption(botTypeOption);
        searchEngineChart.setOption(searchEngineOption);

        layui.use(['table', 'laypage'], function(){
            var table = layui.table;
            var laypage = layui.laypage;
            
            // 外链接排行表格
            table.render({
                elem: '#referrerTable',
                url: 'seo_analysis.php?action=getReferrerData',
                method: 'GET',
                page: true,
                limit: 15,
                limits: [10, 15, 20, 30, 50],
                cols: [[
                    {field: 'rank', title: '排名', width: 80, align: 'center'},
                    {field: 'referer_host', title: '来源Host', minWidth: 200, templet: function(d){
                        if (d.referer_host === '直接访问/内部链接') {
                            return '<span style="color: #999;">直接访问/内部链接</span>';
                        }
                        return '<span style="background: #e3f2fd; color: #1976d2; padding: 2px 6px; border-radius: 3px; font-size: 12px;">' + d.referer_host + '</span>';
                    }},
                    {field: 'sample_urls', title: '示例链接', minWidth: 300, templet: function(d){
                        if (!d.sample_urls || d.sample_urls === '直接访问/内部链接') {
                            return '<span style="color: #999;">-</span>';
                        }
                        var urls = d.sample_urls.split(',').slice(0, 3); // 只显示前3个链接
                        var html = '';
                        urls.forEach(function(url) {
                            if (url.trim()) {
                                html += '<div style="margin: 2px 0;"><a href="' + url.trim() + '" target="_blank" style="color: #1890ff; font-size: 12px;">' + url.trim() + '</a></div>';
                            }
                        });
                        return html || '<span style="color: #999;">-</span>';
                    }},
                    {field: 'visit_count', title: '访问次数', width: 120, align: 'center', templet: function(d){
                        return '<span class="layui-badge layui-bg-blue">' + d.visit_count + '</span>';
                    }},
                    {field: 'unique_visitors', title: '独立访客', width: 120, align: 'center', templet: function(d){
                        return '<span class="layui-badge layui-bg-green">' + d.unique_visitors + '</span>';
                    }}
                ]],
                request: {
                    pageName: 'referrer_page',
                    limitName: 'referrer_limit'
                },
                response: {
                    statusName: 'code',
                    statusCode: 0,
                    msgName: 'msg',
                    countName: 'count',
                    dataName: 'data'
                },
                done: function(res, curr, count){
                    // 添加排名
                    for(var i = 0; i < res.data.length; i++){
                        res.data[i].rank = (curr - 1) * 15 + i + 1;
                    }
                }
            });
            
            // 监听筛选按钮点击
            var filterBtn = document.getElementById('filterBtn');
            if (filterBtn) {
                filterBtn.addEventListener('click', function() {
                    var formData = new FormData(document.querySelector('.filter-form'));
                    var params = new URLSearchParams(formData);
                    
                    // 重新加载外链接表格数据
                    params.set('action', 'getReferrerData');
                    table.reload('referrerTable', {
                        url: 'seo_analysis.php?' + params.toString(),
                        page: {curr: 1}
                    });
                    
                    // 重新加载爬虫和搜索引擎数据
                    botTypeChart.showLoading({ text: '数据分析中...', color: '#667eea', textColor: '#667eea', maskColor: isDark ? 'rgba(17, 24, 39, 0.8)' : 'rgba(255, 255, 255, 0.8)' });
                    searchEngineChart.showLoading({ text: '数据分析中...', color: '#28a745', textColor: '#28a745', maskColor: isDark ? 'rgba(17, 24, 39, 0.8)' : 'rgba(255, 255, 255, 0.8)' });
                    params.set('action', 'getBotTypeData');
                    fetch('seo_analysis.php?' + params.toString())
                        .then(response => response.json())
                        .then(result => {
                            botTypeChart.hideLoading();
                            searchEngineChart.hideLoading();
                            if (result.code === 0) {
                                // 更新爬虫类型饼状图
                                updateBotTypeChart(result.data.bot_types);
                                // 更新搜索引擎饼状图
                                updateSearchEngineChart(result.data.search_engines);
                                // 更新爬虫类型列表
                                updateBotTypeList(result.data.bot_types);
                                // 更新搜索引擎列表
                                updateSearchEngineList(result.data.search_engines);
                            } else {
                                console.error('数据获取失败:', result.msg);
                            }
                        })
                        .catch(error => {
                            botTypeChart.hideLoading();
                            searchEngineChart.hideLoading();
                            console.error('请求失败:', error);
                        });
                    
                    // 更新统计概览数据
                    updateStatsOverview(params);
                });
            }

            // 初始异步数据加载
            var initialParams = new URLSearchParams(new FormData(document.querySelector('.filter-form')));
            
            // 1. 异步加载统计概览数据
            updateStatsOverview(initialParams);
            
            // 2. 异步加载图表与列表数据
            initialParams.set('action', 'getBotTypeData');
            fetch('seo_analysis.php?' + initialParams.toString())
                .then(response => response.json())
                .then(result => {
                    botTypeChart.hideLoading();
                    searchEngineChart.hideLoading();
                    if (result.code === 0) {
                        updateBotTypeChart(result.data.bot_types);
                        updateSearchEngineChart(result.data.search_engines);
                        updateBotTypeList(result.data.bot_types);
                        updateSearchEngineList(result.data.search_engines);
                    }
                })
                .catch(error => {
                    botTypeChart.hideLoading();
                    searchEngineChart.hideLoading();
                    console.error('初始加载失败:', error);
                });
        });
        
        // 更新爬虫类型饼状图
        function updateBotTypeChart(botTypeData) {
            botTypeChart.hideLoading();
            const chartData = botTypeData.map(item => ({
                value: item.visit_count,
                name: item.bot_type
            }));
            
            botTypeOption.series[0].data = chartData;
            botTypeChart.setOption(botTypeOption);
        }
        
        // 更新搜索引擎饼状图
        function updateSearchEngineChart(searchEngineData) {
            searchEngineChart.hideLoading();
            const chartData = searchEngineData.map(item => ({
                value: item.visit_count,
                name: item.search_engine
            }));
            
            searchEngineOption.series[0].data = chartData;
            searchEngineChart.setOption(searchEngineOption);
        }
        
        // 更新爬虫类型列表
        function updateBotTypeList(botTypeData) {
            const tbody = document.querySelector('#botTypeTable tbody');
            if (tbody) {
                tbody.innerHTML = '';
                if (botTypeData.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="4" style="text-align:center; color:#999; padding:20px;">无相关爬虫访问数据</td></tr>';
                    return;
                }
                let rank = 1;
                botTypeData.forEach(bot => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>${rank++}</td>
                        <td>${bot.bot_type}</td>
                        <td><span class="badge badge-primary">${formatNumber(bot.visit_count)}</span></td>
                        <td><span class="badge badge-success">${formatNumber(bot.unique_visitors)}</span></td>
                    `;
                    tbody.appendChild(row);
                });
            }
        }
        
        // 更新搜索引擎列表
        function updateSearchEngineList(searchEngineData) {
            const tbody = document.querySelector('#searchEngineTable tbody');
            if (tbody) {
                tbody.innerHTML = '';
                if (searchEngineData.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="4" style="text-align:center; color:#999; padding:20px;">无搜索引擎访问数据</td></tr>';
                    return;
                }
                let rank = 1;
                searchEngineData.forEach(engine => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>${rank++}</td>
                        <td>${engine.search_engine}</td>
                        <td><span class="badge badge-primary">${formatNumber(engine.visit_count)}</span></td>
                        <td><span class="badge badge-success">${formatNumber(engine.unique_visitors)}</span></td>
                    `;
                    tbody.appendChild(row);
                });
            }
        }
        
        // 更新统计概览数据
        function updateStatsOverview(params) {
            var statsParams = new URLSearchParams(params);
            statsParams.set('action', 'getStatsOverview');
            fetch('seo_analysis.php?' + statsParams.toString())
                .then(response => response.json())
                .then(result => {
                    if (result.code === 0) {
                        // 更新四个统计卡片
                        updateStatCard('total_bot_visits', result.data.total_bot_visits);
                        updateStatCard('bot_types_count', result.data.bot_types_count);
                        updateStatCard('search_engines_count', result.data.search_engines_count);
                        updateStatCard('referrers_count', result.data.referrers_count);
                    } else {
                        console.error('统计数据获取失败:', result.msg);
                    }
                })
                .catch(error => {
                    console.error('统计数据请求失败:', error);
                });
        }
        
        // 更新单个统计卡片
        function updateStatCard(statType, value) {
            const statCards = document.querySelectorAll('.stat-card');
            statCards.forEach(card => {
                const numberElement = card.querySelector('.number');
                if (numberElement) {
                    // 根据卡片内容判断类型
                    const cardText = card.querySelector('h3').textContent;
                    if (cardText.includes('爬虫访问总数') && statType === 'total_bot_visits') {
                        numberElement.textContent = formatNumber(value);
                    } else if (cardText.includes('爬虫类型') && statType === 'bot_types_count') {
                        numberElement.textContent = formatNumber(value);
                    } else if (cardText.includes('搜索引擎') && statType === 'search_engines_count') {
                        numberElement.textContent = formatNumber(value);
                    } else if (cardText.includes('外链接来源') && statType === 'referrers_count') {
                        numberElement.textContent = formatNumber(value);
                    }
                }
            });
        }
        
        // 格式化数字显示
        function formatNumber(number) {
            if (number >= 1000000) {
                return Math.round(number / 1000000 * 10) / 10 + 'M';
            } else if (number >= 1000) {
                return Math.round(number / 1000 * 10) / 10 + 'K';
            }
            return number.toString();
        }
        
        // 时间筛选器切换
        document.getElementById('time_filter').addEventListener('change', function() {
            const customGroup = document.getElementById('customDateGroup');
            const customGroup2 = document.getElementById('customDateGroup2');
            
            if (this.value === 'custom') {
                customGroup.style.display = 'flex';
                customGroup2.style.display = 'flex';
            } else {
                customGroup.style.display = 'none';
                customGroup2.style.display = 'none';
            }
        });
        
        // 响应式处理
        window.addEventListener('resize', function() {
            botTypeChart.resize();
            searchEngineChart.resize();
        });

        // 监听主题切换事件以重新渲染饼状图
        window.addEventListener('themechanged', function() {
            const isDarkNow = document.documentElement.classList.contains('dark-mode');
            
            // 更新 Option 配置的 Legend 文字颜色
            botTypeOption.legend.textStyle.color = isDarkNow ? '#cbd5e1' : '#333';
            searchEngineOption.legend.textStyle.color = isDarkNow ? '#cbd5e1' : '#333';
            
            // 应用新配置
            botTypeChart.setOption(botTypeOption);
            searchEngineChart.setOption(searchEngineOption);
        });
    </script>
</body>
</html>
