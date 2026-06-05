<?php
/**
 * 访客分析页面 - 地理分布地图
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
    header('Location: ../install.php');
    exit;
}

// 检查配置文件是否存在
if (!file_exists(CONFIG_FILE)) {
    header('Location: ../install.php');
    exit;
}

// 加载配置和函数
require_once CONFIG_FILE;
require_once ROOT_PATH . '/includes/functions.php';

// 检查登录状态
session_start();
if (!isset($_SESSION['admin_id'])) {
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
$countryPage = isset($_GET['country_page']) ? max(1, intval($_GET['country_page'])) : 1;
$provincePage = isset($_GET['province_page']) ? max(1, intval($_GET['province_page'])) : 1;
$pageLimit = 15;

// 获取访客地理分布数据
$visitorData = getVisitorGeoData($timeFilter, $startDate, $endDate, $projectId, $countryPage, $pageLimit);
$visitorDataCounts = getVisitorGeoDataCount($timeFilter, $startDate, $endDate, $projectId);

// 计算分页信息
$countryTotalPages = ceil($visitorDataCounts['countries'] / $pageLimit);
$provinceTotalPages = ceil($visitorDataCounts['provinces'] / $pageLimit);

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
    $allowed = ['today', 'yesterday', 'week', 'custom', 'total'];
    if (!in_array($timeFilter, $allowed, true)) {
        return '';
    }
    switch ($timeFilter) {
        case 'today':
            $today = date('Y-m-d');
            $now   = date('Y-m-d H:i:s');
            return " AND DATE(visit_time) = '{$today}' AND visit_time <= '{$now}'";
        case 'yesterday':
            $yesterday = date('Y-m-d', strtotime('-1 day'));
            return " AND DATE(visit_time) = '{$yesterday}'";
        case 'week':
            $thisWeek = date('Y-m-d', strtotime('-7 days'));
            $now      = date('Y-m-d H:i:s');
            return " AND DATE(visit_time) >= '{$thisWeek}' AND visit_time <= '{$now}'";
        case 'custom':
            $safeStart = validateDateParam($startDate);
            $safeEnd   = validateDateParam($endDate);
            if ($safeStart && $safeEnd) {
                $now         = date('Y-m-d H:i:s');
                $endDateTime = $safeEnd . ' 23:59:59';
                if ($endDateTime > $now) {
                    $endDateTime = $now;
                }
                return " AND visit_time >= '{$safeStart} 00:00:00' AND visit_time <= '{$endDateTime}'";
            }
            return '';
        case 'total':
        default:
            return '';
    }
}

/**
 * 获取访客地理分布数据
 */
function getVisitorGeoData($timeFilter = 'total', $startDate = null, $endDate = null, $projectId = '', $page = 1, $limit = 15) {
    global $db;
    
    $timeFilterCondition = buildTimeFilterCondition($timeFilter, $startDate, $endDate);
    // 强制转 int，防止 projectId 注入
    $safeProjectId = !empty($projectId) ? intval($projectId) : 0;
    $projectFilterCondition = $safeProjectId > 0 ? " AND project_id = {$safeProjectId}" : '';

    // 计算偏移量
    $offset = ($page - 1) * $limit;

    // 获取国家/地区访客数据
    $countryData = $db->fetchAll(
        "SELECT 
            CASE 
                WHEN country = '局域网' OR country IS NULL OR country = '' OR country = '未知' THEN '局域网'
                ELSE country
            END as country,
            COUNT(*) as visitor_count,
            COUNT(DISTINCT ip_address) as unique_visitors
         FROM visits 
         WHERE 1=1" . $timeFilterCondition . $projectFilterCondition . "
         GROUP BY 
            CASE 
                WHEN country = '局域网' OR country IS NULL OR country = '' OR country = '未知' THEN '局域网'
                ELSE country
            END
         ORDER BY visitor_count DESC
         LIMIT ? OFFSET ?",
        [$limit, $offset]
    );

    // 获取城市访客数据（仅中国）
    $cityData = $db->fetchAll(
        "SELECT 
            CASE 
                WHEN city = '本地' OR city IS NULL OR city = '' OR city = '未知' THEN '本地'
                ELSE city
            END as city,
            country,
            COUNT(*) as visitor_count,
            COUNT(DISTINCT ip_address) as unique_visitors
         FROM visits 
         WHERE 1=1" . $timeFilterCondition . $projectFilterCondition . "
         AND (country = '中国' OR country = 'China' OR country = '局域网')
         GROUP BY 
            CASE 
                WHEN city = '本地' OR city IS NULL OR city = '' OR city = '未知' THEN '本地'
                ELSE city
            END, country
         ORDER BY visitor_count DESC"
    );
    
    // 获取省份访客数据（仅中国）
    $provinceData = $db->fetchAll(
        "SELECT 
            CASE 
                WHEN province = '局域网' OR province IS NULL OR province = '' OR province = '未知' THEN '局域网'
                ELSE province
            END as province,
            country,
            COUNT(*) as visitor_count,
            COUNT(DISTINCT ip_address) as unique_visitors
         FROM visits 
         WHERE 1=1" . $timeFilterCondition . $projectFilterCondition . "
         AND (country = '中国' OR country = 'China' OR country = '局域网')
         GROUP BY 
            CASE 
                WHEN province = '局域网' OR province IS NULL OR province = '' OR province = '未知' THEN '局域网'
                ELSE province
            END, country
         ORDER BY visitor_count DESC
         LIMIT ? OFFSET ?",
        [$limit, $offset]
    );

    return [
        'countries' => $countryData,
        'cities' => $cityData,
        'provinces' => $provinceData
    ];
}

/**
 * 获取访客地理分布数据总数
 */
function getVisitorGeoDataCount($timeFilter = 'total', $startDate = null, $endDate = null, $projectId = '') {
    global $db;
    
    $timeFilterCondition = buildTimeFilterCondition($timeFilter, $startDate, $endDate);
    // 强制转 int，防止 projectId 注入
    $safeProjectId = !empty($projectId) ? intval($projectId) : 0;
    $projectFilterCondition = $safeProjectId > 0 ? " AND project_id = {$safeProjectId}" : '';
    
    // 获取国家/地区总数
    $countryCount = $db->fetchOne(
        "SELECT COUNT(DISTINCT 
            CASE 
                WHEN country = '局域网' OR country IS NULL OR country = '' OR country = '未知' THEN '局域网'
                ELSE country
            END
        ) as count
         FROM visits 
         WHERE 1=1" . $timeFilterCondition . $projectFilterCondition
    )['count'];
    
    // 获取省份总数（仅中国）
    $provinceCount = $db->fetchOne(
        "SELECT COUNT(DISTINCT 
            CASE 
                WHEN province = '局域网' OR province IS NULL OR province = '' OR province = '未知' THEN '局域网'
                ELSE province
            END
        ) as count
         FROM visits 
         WHERE 1=1" . $timeFilterCondition . $projectFilterCondition . "
         AND (country = '中国' OR country = 'China' OR country = '局域网')"
    )['count'];
    
    return [
        'countries' => $countryCount,
        'provinces' => $provinceCount
    ];
}

/**
 * 获取访客统计概览
 */
function getVisitorStats($timeFilter = 'total', $startDate = null, $endDate = null, $projectId = '') {
    global $db;
    
    $timeFilterCondition = buildTimeFilterCondition($timeFilter, $startDate, $endDate);
    // 强制转 int，防止 projectId 注入
    $safeProjectId = !empty($projectId) ? intval($projectId) : 0;
    $projectFilterCondition = $safeProjectId > 0 ? " AND project_id = {$safeProjectId}" : '';
    
    $stats = [
        'total_visitors' => $db->fetchOne("SELECT COUNT(*) as count FROM visits WHERE 1=1" . $timeFilterCondition . $projectFilterCondition)['count'],
        'unique_visitors' => $db->fetchOne("SELECT COUNT(DISTINCT ip_address) as count FROM visits WHERE 1=1" . $timeFilterCondition . $projectFilterCondition)['count'],
        'countries_count' => $db->fetchOne("SELECT COUNT(DISTINCT country) as count FROM visits WHERE country IS NOT NULL AND country != '' AND country != '未知'" . $timeFilterCondition . $projectFilterCondition)['count'],
        'cities_count' => $db->fetchOne("SELECT COUNT(DISTINCT city) as count FROM visits WHERE city IS NOT NULL AND city != '' AND city != '未知'" . $timeFilterCondition . $projectFilterCondition)['count']
    ];
    
    return $stats;
}

$stats = getVisitorStats($timeFilter, $startDate, $endDate, $projectId);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>访客分析 - <?php echo SITE_NAME; ?></title>
    <script src="../assets/js/echarts/echarts.min.js"></script>
    <script src="../assets/js/echarts/world.js"></script>
    <script src="../assets/js/echarts/china.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f7fa;
            color: #333;
        }
        
        
        /* 筛选控件样式 */
        .filter-section {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .filter-container {
            width: 100%;
        }
        
        .filter-form {
            display: flex;
            align-items: flex-end;
            gap: 30px;
            flex-wrap: wrap;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
            min-width: 200px;
        }
        
        .filter-group.project-group {
            flex-direction: row;
            align-items: center;
            gap: 10px;
        }
        
        .filter-group.project-group .filter-title {
            min-width: 80px;
            margin-bottom: 0;
        }
        
        .filter-group.project-group .filter-controls {
            flex: 1;
        }
        
        .filter-group.time-group {
            flex-direction: row;
            align-items: center;
            gap: 10px;
            min-height: 38px;
        }
        
        .filter-group.time-group .filter-title {
            min-width: 80px;
            margin-bottom: 0;
            margin-top: 0;
        }
        
        .filter-group.time-group .filter-controls {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .time-controls-row {
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .filter-title {
            font-weight: 600;
            color: #333;
            font-size: 14px;
        }
        
        .filter-controls {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .project-select {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            min-width: 200px;
            background: #fff;
        }
        
        .project-select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 2px rgba(102, 126, 234, 0.2);
        }
        
        .time-radio-group {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .time-radio-item {
            display: flex;
            align-items: center;
            gap: 5px;
            cursor: pointer;
            font-size: 14px;
        }
        
        .time-radio-item input[type="radio"] {
            margin: 0;
        }
        
        .time-radio-item span {
            color: #666;
        }
        
        .time-radio-item:hover span {
            color: #333;
        }
        
        .time-radio-item input[type="radio"]:checked + span {
            color: #667eea;
            font-weight: 500;
        }
        
        .custom-time-range {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 0;
        }
        
        .date-input {
            padding: 6px 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .date-input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 2px rgba(102, 126, 234, 0.2);
        }
        
        .date-separator {
            color: #666;
            font-size: 14px;
        }
        
        .filter-actions {
            display: flex;
            align-items: flex-end;
        }
        
        .view-btn {
            padding: 8px 20px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 14px;
            cursor: pointer;
            transition: background 0.3s;
            height: 36px;
        }
        
        .view-btn:hover {
            background: #5a6fd8;
        }
        
        
        .date-input {
            padding: 6px 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .date-separator {
            color: #666;
            font-weight: 500;
        }
        
        .search-btn {
            background: #007bff;
            color: white;
            border: none;
            padding: 6px 16px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            transition: background 0.2s;
        }
        
        .search-btn:hover {
            background: #0056b3;
        }
        
        /* 统计卡片样式 */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .stat-card h3 {
            color: #666;
            font-size: 14px;
            margin-bottom: 10px;
        }
        
        .stat-card .number {
            font-size: 28px;
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }
        
        .stat-card .change {
            font-size: 14px;
            color: #28a745;
        }
        
        /* 地图容器样式 */
        .map-section {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .map-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .map-title {
            font-size: 18px;
            font-weight: bold;
            color: #333;
        }
        
        .map-controls {
            display: flex;
            gap: 15px;
            align-items: center;
        }
        
        .map-toggle {
            display: flex;
            gap: 5px;
        }
        
        .toggle-btn {
            padding: 8px 16px;
            border: 1px solid #ddd;
            background: #f8f9fa;
            color: #666;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s ease;
        }
        
        .toggle-btn:hover {
            background: #e9ecef;
            border-color: #adb5bd;
        }
        
        .toggle-btn.active {
            background: #007bff;
            color: white;
            border-color: #007bff;
        }
        
        .map-container {
            height: 600px;
            width: 100%;
            border: 1px solid #e0e0e0;
            border-radius: 4px;
        }
        
        /* 数据表格样式 */
        .data-section {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .data-title {
            font-size: 18px;
            font-weight: bold;
            color: #333;
            margin-bottom: 20px;
        }
        
        .data-tables-container {
            display: flex;
            gap: 20px;
            margin-top: 15px;
        }
        
        .data-table-wrapper {
            flex: 1;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .table-title {
            background: #f8f9fa;
            padding: 15px 20px;
            font-weight: 600;
            color: #495057;
            border-bottom: 1px solid #e0e0e0;
            font-size: 16px;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .data-table th,
        .data-table td {
            padding: 12px 20px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        .data-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
            font-size: 14px;
        }
        
        .data-table td {
            font-size: 14px;
        }
        
        .data-table tr:hover {
            background: #f8f9fa;
        }
        
        /* 响应式设计 */
        @media (max-width: 768px) {
            .data-tables-container {
                flex-direction: column;
            }
        }
        
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .badge-primary {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .badge-success {
            background: #d4edda;
            color: #155724;
        }
        
        @media (max-width: 768px) {
            .filter-form {
                flex-direction: column;
                align-items: stretch;
                gap: 20px;
            }
            
            .filter-group {
                min-width: auto;
            }
            
            .filter-group.project-group {
                flex-direction: column;
                align-items: stretch;
                gap: 8px;
            }
            
            .filter-group.project-group .filter-title {
                min-width: auto;
            }
            
            .filter-group.time-group {
                flex-direction: column;
                align-items: stretch;
                gap: 8px;
                min-height: auto;
            }
            
            .filter-group.time-group .filter-title {
                min-width: auto;
                margin-top: 0;
            }
            
            .time-controls-row {
                flex-direction: column;
                align-items: stretch;
                gap: 10px;
            }
            
            .time-radio-group {
                flex-wrap: wrap;
                gap: 10px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .nav {
                display: none;
            }
            
            .main-content {
                padding: 10px;
            }
            
            .map-controls {
                flex-direction: column;
                gap: 10px;
                align-items: flex-start;
            }
            
            .map-container {
                height: 400px;
            }
        }
        
        /* 分页样式 */
        .pagination-wrapper {
            margin-top: 20px;
            padding: 15px 0;
            border-top: 1px solid #e0e0e0;
        }
        
        .pagination-info {
            text-align: center;
            margin-bottom: 15px;
            color: #666;
            font-size: 14px;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 5px;
            flex-wrap: wrap;
        }
        
        .page-btn {
            display: inline-block;
            padding: 8px 12px;
            margin: 0 2px;
            background-color: #f8f9fa;
            color: #333;
            text-decoration: none;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            font-size: 14px;
            transition: all 0.3s ease;
            min-width: 40px;
            text-align: center;
        }
        
        .page-btn:hover {
            background-color: #e9ecef;
            border-color: #adb5bd;
            color: #495057;
        }
        
        .page-btn.current {
            background-color: #007bff;
            color: white;
            border-color: #007bff;
        }
        
        .page-btn.current:hover {
            background-color: #0056b3;
            border-color: #0056b3;
        }
        
        .page-ellipsis {
            padding: 8px 4px;
            color: #6c757d;
            font-size: 14px;
        }
        
        @media (max-width: 768px) {
            .pagination {
                gap: 3px;
            }
            
            .page-btn {
                padding: 6px 10px;
                font-size: 13px;
                min-width: 35px;
            }
            
            .pagination-info {
                font-size: 13px;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="main-content">
        <div class="container">
            <!-- 筛选控件 -->
            <div class="filter-section">
                <div class="filter-container">
                    <form method="GET" class="filter-form">
                        <!-- 项目筛选 -->
                        <div class="filter-group project-group">
                            <div class="filter-title">统计项目</div>
                            <div class="filter-controls">
                                <select name="project_id" id="projectSelect" class="project-select">
                                    <option value="">所有项目（默认显示）</option>
                                    <?php foreach ($projects as $project): ?>
                                        <option value="<?php echo $project['id']; ?>" <?php echo $projectId == $project['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($project['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <!-- 时间筛选 -->
                        <div class="filter-group time-group">
                            <div class="filter-title">时间维度</div>
                            <div class="filter-controls">
                                <div class="time-controls-row">
                                    <div class="time-radio-group">
                                        <label class="time-radio-item">
                                            <input type="radio" name="time_filter" value="total" <?php echo $timeFilter === 'total' ? 'checked' : ''; ?>>
                                            <span>总访问</span>
                                        </label>
                                        <label class="time-radio-item">
                                            <input type="radio" name="time_filter" value="today" <?php echo $timeFilter === 'today' ? 'checked' : ''; ?>>
                                            <span>今日</span>
                                        </label>
                                        <label class="time-radio-item">
                                            <input type="radio" name="time_filter" value="yesterday" <?php echo $timeFilter === 'yesterday' ? 'checked' : ''; ?>>
                                            <span>昨日</span>
                                        </label>
                                        <label class="time-radio-item">
                                            <input type="radio" name="time_filter" value="week" <?php echo $timeFilter === 'week' ? 'checked' : ''; ?>>
                                            <span>本周</span>
                                        </label>
                                        <label class="time-radio-item">
                                            <input type="radio" name="time_filter" value="custom" <?php echo $timeFilter === 'custom' ? 'checked' : ''; ?>>
                                            <span>自定义</span>
                                        </label>
                                    </div>
                                    <div class="custom-time-range" id="customTimeRange" style="display: <?php echo $timeFilter === 'custom' ? 'flex' : 'none'; ?>;">
                                        <input type="date" name="start_date" id="startDate" class="date-input" value="<?php echo $startDate; ?>">
                                        <span class="date-separator">至</span>
                                        <input type="date" name="end_date" id="endDate" class="date-input" value="<?php echo $endDate; ?>">
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- 操作按钮 -->
                        <div class="filter-actions">
                            <button type="submit" class="view-btn">查看</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- 统计概览 -->
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>总访客数</h3>
                    <div class="number"><?php echo formatNumber($stats['total_visitors']); ?></div>
                    <div class="change">访问次数</div>
                </div>
                
                <div class="stat-card">
                    <h3>独立访客</h3>
                    <div class="number"><?php echo formatNumber($stats['unique_visitors']); ?></div>
                    <div class="change">唯一IP数量</div>
                </div>
                
                <div class="stat-card">
                    <h3>覆盖国家</h3>
                    <div class="number"><?php echo formatNumber($stats['countries_count']); ?></div>
                    <div class="change">不同国家/地区</div>
                </div>
                
                <div class="stat-card">
                    <h3>覆盖城市</h3>
                    <div class="number"><?php echo formatNumber($stats['cities_count']); ?></div>
                    <div class="change">不同城市</div>
                </div>
            </div>
            
            <!-- 地图区域 -->
            <div class="map-section">
                <div class="map-header">
                    <div class="map-title">访客地理分布</div>
                    <div class="map-controls">
                        <div class="map-toggle">
                            <button class="toggle-btn active" onclick="switchMap('world')" id="worldBtn">世界地图</button>
                            <button class="toggle-btn" onclick="switchMap('china')" id="chinaBtn">中国地图</button>
                        </div>
                    </div>
                </div>
                <div class="map-container" id="mapContainer"></div>
            </div>
            
            <!-- 数据表格 -->
            <div class="data-section">
                <div class="data-title">访客分布详情</div>
                <div class="data-tables-container">
                    <!-- 国家分布表格 -->
                    <div class="data-table-wrapper">
                        <div class="table-title">国家/地区分布</div>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>排名</th>
                                    <th>国家/地区</th>
                                    <th>访问次数</th>
                                    <th>独立访客</th>
                                    <th>占比</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $totalVisitors = $stats['total_visitors'];
                                $rank = 1;
                                foreach ($visitorData['countries'] as $country): 
                                    $percentage = $totalVisitors > 0 ? round(($country['visitor_count'] / $totalVisitors) * 100, 2) : 0;
                                ?>
                                <tr>
                                    <td><?php echo $rank++; ?></td>
                                    <td><?php echo htmlspecialchars($country['country']); ?></td>
                                    <td><span class="badge badge-primary"><?php echo formatNumber($country['visitor_count']); ?></span></td>
                                    <td><span class="badge badge-success"><?php echo formatNumber($country['unique_visitors']); ?></span></td>
                                    <td><?php echo $percentage; ?>%</td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        
                        <!-- 国家分布分页控件 -->
                        <?php if ($countryTotalPages > 1): ?>
                        <div class="pagination-wrapper">
                            <div class="pagination-info">
                                共 <?php echo $visitorDataCounts['countries']; ?> 个国家/地区，第 <?php echo $countryPage; ?> / <?php echo $countryTotalPages; ?> 页
                            </div>
                            <div class="pagination">
                                <?php if ($countryPage > 1): ?>
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['country_page' => $countryPage - 1])); ?>" class="page-btn">上一页</a>
                                <?php endif; ?>
                                
                                <?php
                                $startPage = max(1, $countryPage - 2);
                                $endPage = min($countryTotalPages, $countryPage + 2);
                                
                                if ($startPage > 1): ?>
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['country_page' => 1])); ?>" class="page-btn">1</a>
                                    <?php if ($startPage > 2): ?>
                                        <span class="page-ellipsis">...</span>
                                    <?php endif; ?>
                                <?php endif; ?>
                                
                                <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                                    <?php if ($i == $countryPage): ?>
                                        <span class="page-btn current"><?php echo $i; ?></span>
                                    <?php else: ?>
                                        <a href="?<?php echo http_build_query(array_merge($_GET, ['country_page' => $i])); ?>" class="page-btn"><?php echo $i; ?></a>
                                    <?php endif; ?>
                                <?php endfor; ?>
                                
                                <?php if ($endPage < $countryTotalPages): ?>
                                    <?php if ($endPage < $countryTotalPages - 1): ?>
                                        <span class="page-ellipsis">...</span>
                                    <?php endif; ?>
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['country_page' => $countryTotalPages])); ?>" class="page-btn"><?php echo $countryTotalPages; ?></a>
                                <?php endif; ?>
                                
                                <?php if ($countryPage < $countryTotalPages): ?>
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['country_page' => $countryPage + 1])); ?>" class="page-btn">下一页</a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- 省份分布表格 -->
                    <div class="data-table-wrapper">
                        <div class="table-title">省份分布</div>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>排名</th>
                                    <th>省份</th>
                                    <th>访问次数</th>
                                    <th>独立访客</th>
                                    <th>占比</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $rank = 1;
                                foreach ($visitorData['provinces'] as $province): 
                                    $percentage = $totalVisitors > 0 ? round(($province['visitor_count'] / $totalVisitors) * 100, 2) : 0;
                                ?>
                                <tr>
                                    <td><?php echo $rank++; ?></td>
                                    <td><?php echo htmlspecialchars($province['province']); ?></td>
                                    <td><span class="badge badge-primary"><?php echo formatNumber($province['visitor_count']); ?></span></td>
                                    <td><span class="badge badge-success"><?php echo formatNumber($province['unique_visitors']); ?></span></td>
                                    <td><?php echo $percentage; ?>%</td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        
                        <!-- 省份分布分页控件 -->
                        <?php if ($provinceTotalPages > 1): ?>
                        <div class="pagination-wrapper">
                            <div class="pagination-info">
                                共 <?php echo $visitorDataCounts['provinces']; ?> 个省份，第 <?php echo $provincePage; ?> / <?php echo $provinceTotalPages; ?> 页
                            </div>
                            <div class="pagination">
                                <?php if ($provincePage > 1): ?>
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['province_page' => $provincePage - 1])); ?>" class="page-btn">上一页</a>
                                <?php endif; ?>
                                
                                <?php
                                $startPage = max(1, $provincePage - 2);
                                $endPage = min($provinceTotalPages, $provincePage + 2);
                                
                                if ($startPage > 1): ?>
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['province_page' => 1])); ?>" class="page-btn">1</a>
                                    <?php if ($startPage > 2): ?>
                                        <span class="page-ellipsis">...</span>
                                    <?php endif; ?>
                                <?php endif; ?>
                                
                                <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                                    <?php if ($i == $provincePage): ?>
                                        <span class="page-btn current"><?php echo $i; ?></span>
                                    <?php else: ?>
                                        <a href="?<?php echo http_build_query(array_merge($_GET, ['province_page' => $i])); ?>" class="page-btn"><?php echo $i; ?></a>
                                    <?php endif; ?>
                                <?php endfor; ?>
                                
                                <?php if ($endPage < $provinceTotalPages): ?>
                                    <?php if ($endPage < $provinceTotalPages - 1): ?>
                                        <span class="page-ellipsis">...</span>
                                    <?php endif; ?>
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['province_page' => $provinceTotalPages])); ?>" class="page-btn"><?php echo $provinceTotalPages; ?></a>
                                <?php endif; ?>
                                
                                <?php if ($provincePage < $provinceTotalPages): ?>
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['province_page' => $provincePage + 1])); ?>" class="page-btn">下一页</a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        let currentMapType = 'world';
        let mapChart = null;
        
        // 国家名称中文映射
        const countryNameMap = {
            'Afghanistan': '阿富汗',
            'Angola': '安哥拉',
            'Albania': '阿尔巴尼亚',
            'United Arab Emirates': '阿联酋',
            'Argentina': '阿根廷',
            'Armenia': '亚美尼亚',
            'Australia': '澳大利亚',
            'Austria': '奥地利',
            'Azerbaijan': '阿塞拜疆',
            'Burundi': '布隆迪',
            'Belgium': '比利时',
            'Benin': '贝宁',
            'Burkina Faso': '布基纳法索',
            'Bangladesh': '孟加拉国',
            'Bulgaria': '保加利亚',
            'The Bahamas': '巴哈马',
            'Bosnia and Herzegovina': '波斯尼亚和黑塞哥维那',
            'Belarus': '白俄罗斯',
            'Belize': '伯利兹',
            'Bermuda': '百慕大',
            'Bolivia': '玻利维亚',
            'Brazil': '巴西',
            'Brunei': '文莱',
            'Bhutan': '不丹',
            'Botswana': '博茨瓦纳',
            'Central African Republic': '中非共和国',
            'Canada': '加拿大',
            'Switzerland': '瑞士',
            'Chile': '智利',
            'China': '中国',
            'Ivory Coast': '科特迪瓦',
            'Cameroon': '喀麦隆',
            'Democratic Republic of the Congo': '刚果民主共和国',
            'Republic of the Congo': '刚果共和国',
            'Colombia': '哥伦比亚',
            'Costa Rica': '哥斯达黎加',
            'Cuba': '古巴',
            'Northern Cyprus': '北塞浦路斯',
            'Cyprus': '塞浦路斯',
            'Czech Republic': '捷克共和国',
            'Germany': '德国',
            'Djibouti': '吉布提',
            'Denmark': '丹麦',
            'Dominican Republic': '多米尼加共和国',
            'Algeria': '阿尔及利亚',
            'Ecuador': '厄瓜多尔',
            'Egypt': '埃及',
            'Eritrea': '厄立特里亚',
            'Spain': '西班牙',
            'Estonia': '爱沙尼亚',
            'Ethiopia': '埃塞俄比亚',
            'Finland': '芬兰',
            'Fiji': '斐济',
            'Falkland Islands': '福克兰群岛',
            'France': '法国',
            'Gabon': '加蓬',
            'United Kingdom': '英国',
            'Georgia': '格鲁吉亚',
            'Ghana': '加纳',
            'Guinea': '几内亚',
            'Gambia': '冈比亚',
            'Guinea Bissau': '几内亚比绍',
            'Equatorial Guinea': '赤道几内亚',
            'Greece': '希腊',
            'Greenland': '格陵兰',
            'Guatemala': '危地马拉',
            'French Guiana': '法属圭亚那',
            'Guyana': '圭亚那',
            'Honduras': '洪都拉斯',
            'Croatia': '克罗地亚',
            'Haiti': '海地',
            'Hungary': '匈牙利',
            'Indonesia': '印度尼西亚',
            'India': '印度',
            'Ireland': '爱尔兰',
            'Iran': '伊朗',
            'Iraq': '伊拉克',
            'Iceland': '冰岛',
            'Israel': '以色列',
            'Italy': '意大利',
            'Jamaica': '牙买加',
            'Jordan': '约旦',
            'Japan': '日本',
            'Kazakhstan': '哈萨克斯坦',
            'Kenya': '肯尼亚',
            'Kyrgyzstan': '吉尔吉斯斯坦',
            'Cambodia': '柬埔寨',
            'South Korea': '韩国',
            'Korea': '韩国',
            'Republic of Korea': '韩国',
            'Korea, Republic of': '韩国',
            'Korea (South)': '韩国',
            'Kosovo': '科索沃',
            'Kuwait': '科威特',
            'Laos': '老挝',
            'Lebanon': '黎巴嫩',
            'Liberia': '利比里亚',
            'Libya': '利比亚',
            'Sri Lanka': '斯里兰卡',
            'Lesotho': '莱索托',
            'Lithuania': '立陶宛',
            'Luxembourg': '卢森堡',
            'Latvia': '拉脱维亚',
            'Morocco': '摩洛哥',
            'Moldova': '摩尔多瓦',
            'Madagascar': '马达加斯加',
            'Mexico': '墨西哥',
            'Macedonia': '马其顿',
            'Mali': '马里',
            'Myanmar': '缅甸',
            'Montenegro': '黑山',
            'Mongolia': '蒙古',
            'Mozambique': '莫桑比克',
            'Mauritania': '毛里塔尼亚',
            'Malawi': '马拉维',
            'Malaysia': '马来西亚',
            'Namibia': '纳米比亚',
            'New Caledonia': '新喀里多尼亚',
            'Niger': '尼日尔',
            'Nigeria': '尼日利亚',
            'Nicaragua': '尼加拉瓜',
            'Netherlands': '荷兰',
            'Norway': '挪威',
            'Nepal': '尼泊尔',
            'New Zealand': '新西兰',
            'Oman': '阿曼',
            'Pakistan': '巴基斯坦',
            'Panama': '巴拿马',
            'Peru': '秘鲁',
            'Philippines': '菲律宾',
            'Papua New Guinea': '巴布亚新几内亚',
            'Poland': '波兰',
            'Puerto Rico': '波多黎各',
            'North Korea': '朝鲜',
            'Portugal': '葡萄牙',
            'Paraguay': '巴拉圭',
            'Qatar': '卡塔尔',
            'Romania': '罗马尼亚',
            'Russia': '俄罗斯',
            'Rwanda': '卢旺达',
            'Western Sahara': '西撒哈拉',
            'W. Sahara': '西撒哈拉',
            'Saudi Arabia': '沙特阿拉伯',
            'Sudan': '苏丹',
            'South Sudan': '南苏丹',
            'S.Sudan': '南苏丹',
            'Senegal': '塞内加尔',
            'Solomon Islands': '所罗门群岛',
            'Sierra Leone': '塞拉利昂',
            'El Salvador': '萨尔瓦多',
            'Somaliland': '索马里兰',
            'Somalia': '索马里',
            'Republic of Serbia': '塞尔维亚',
            'Suriname': '苏里南',
            'Slovakia': '斯洛伐克',
            'Slovenia': '斯洛文尼亚',
            'Sweden': '瑞典',
            'Swaziland': '斯威士兰',
            'Syria': '叙利亚',
            'Chad': '乍得',
            'Togo': '多哥',
            'Thailand': '泰国',
            'Tajikistan': '塔吉克斯坦',
            'Turkmenistan': '土库曼斯坦',
            'East Timor': '东帝汶',
            'Trinidad and Tobago': '特立尼达和多巴哥',
            'Tunisia': '突尼斯',
            'Turkey': '土耳其',
            'United Republic of Tanzania': '坦桑尼亚',
            'Tanzania': '坦桑尼亚',
            'Uganda': '乌干达',
            'Ukraine': '乌克兰',
            'Uruguay': '乌拉圭',
            'United States of America': '美国',
            'United States': '美国',
            'USA': '美国',
            'US': '美国',
            'America': '美国',
            'Uzbekistan': '乌兹别克斯坦',
            'Venezuela': '委内瑞拉',
            'Vietnam': '越南',
            'Vanuatu': '瓦努阿图',
            'West Bank': '西岸',
            'Yemen': '也门',
            'South Africa': '南非',
            'Zambia': '赞比亚',
            'Zimbabwe': '津巴布韦',
            'Taiwan': '台湾',
            'Taiwan, Province of China': '台湾',
            'Republic of China': '台湾',
            'ROC': '台湾'
        };
        
        // 访客数据
        const visitorData = {
            countries: <?php echo json_encode($visitorData['countries']); ?>,
            cities: <?php echo json_encode($visitorData['cities']); ?>,
            provinces: <?php echo json_encode($visitorData['provinces']); ?>
        };
        
        // 初始化地图
        function initMap() {
            const mapContainer = document.getElementById('mapContainer');
            mapChart = echarts.init(mapContainer);
            
            // 等待ECharts完全加载后再初始化地图
            setTimeout(() => {
                loadWorldMap();
            }, 100);
        }
        
        // 加载世界地图
        function loadWorldMap() {
            const isDark = document.documentElement.classList.contains('dark-mode');
            if (!visitorData.countries || visitorData.countries.length === 0) {
                console.log('没有访客数据');
                // 显示空状态
                const option = {
                    title: {
                        text: '世界访客分布',
                        left: 'center',
                        textStyle: {
                            fontSize: 18,
                            fontWeight: 'bold',
                            color: isDark ? '#f8fafc' : '#333'
                        }
                    },
                    graphic: {
                        type: 'text',
                        left: 'center',
                        top: 'middle',
                        style: {
                            text: '暂无访客数据',
                            fontSize: 16,
                            fill: isDark ? '#64748b' : '#999'
                        }
                    }
                };
                mapChart.setOption(option);
                return;
            }
            
            const maxValue = Math.max(...visitorData.countries.map(item => item.visitor_count));
            
            // 使用真正的地图显示访客分布
            const option = {
                title: {
                    text: '世界访客分布',
                    left: 'center',
                    textStyle: {
                        fontSize: 18,
                        fontWeight: 'bold',
                        color: isDark ? '#f8fafc' : '#333'
                    }
                },
                tooltip: {
                    trigger: 'item',
                    backgroundColor: isDark ? '#1f2937' : '#fff',
                    borderColor: isDark ? '#374151' : '#ccc',
                    textStyle: {
                        color: isDark ? '#f9fafb' : '#333'
                    },
                    formatter: function(params) {
                        if (params.data) {
                            const chineseName = countryNameMap[params.data.name] || params.data.name;
                            return `${chineseName}<br/>访问次数: ${params.data.value}<br/>独立访客: ${params.data.uniqueVisitors}`;
                        }
                        const chineseName = countryNameMap[params.name] || params.name;
                        return chineseName;
                    }
                },
                visualMap: {
                    min: 0,
                    max: maxValue,
                    left: 'left',
                    top: 'bottom',
                    text: ['高', '低'],
                    calculable: true,
                    textStyle: {
                        color: isDark ? '#cbd5e1' : '#333'
                    },
                    inRange: {
                        color: isDark ? ['#1e293b', '#6366f1', '#4f46e5'] : ['#e6f3ff', '#1890ff', '#0050b3']
                    }
                },
                series: [{
                    name: '访客分布',
                    type: 'map',
                    map: 'world',
                    nameMap: countryNameMap,
                    roam: false,
                    itemStyle: {
                        areaColor: isDark ? '#1f2937' : '#f3f4f6',
                        borderColor: isDark ? '#374151' : '#e5e7eb'
                    },
                    emphasis: {
                        label: {
                            show: true,
                            color: isDark ? '#ffffff' : '#333'
                        },
                        itemStyle: {
                            areaColor: '#ff6b6b'
                        }
                    },
                    data: visitorData.countries.map(item => ({
                        name: item.country,
                        value: item.visitor_count,
                        uniqueVisitors: item.unique_visitors
                    }))
                }]
            };
            
            mapChart.setOption(option);
        }
        
        // 加载中国地图
        function loadChinaMap() {
            const isDark = document.documentElement.classList.contains('dark-mode');
            if (!visitorData.provinces || visitorData.provinces.length === 0) {
                console.log('没有省份访客数据');
                // 显示空状态
                const option = {
                    title: {
                        text: '中国访客分布',
                        left: 'center',
                        textStyle: {
                            fontSize: 18,
                            fontWeight: 'bold',
                            color: isDark ? '#f8fafc' : '#333'
                        }
                    },
                    graphic: {
                        type: 'text',
                        left: 'center',
                        top: 'middle',
                        style: {
                            text: '暂无省份访客数据',
                            fontSize: 16,
                            fill: isDark ? '#64748b' : '#999'
                        }
                    }
                };
                mapChart.setOption(option);
                return;
            }
            
            const maxValue = Math.max(...visitorData.provinces.map(item => item.visitor_count));
            
            // 确保地图已注册
            if (typeof echarts !== 'undefined' && echarts.getMap) {
                const chinaMap = echarts.getMap('china');
                if (!chinaMap) {
                    console.log('中国地图未注册，尝试重新注册');
                    // 如果地图未注册，显示错误信息
                    const option = {
                        title: {
                            text: '中国访客分布',
                            left: 'center',
                            textStyle: {
                                fontSize: 18,
                                fontWeight: 'bold',
                                color: isDark ? '#f8fafc' : '#333'
                            }
                        },
                        graphic: {
                            type: 'text',
                            left: 'center',
                            top: 'middle',
                            style: {
                                text: '地图数据加载失败，请刷新页面重试',
                                fontSize: 16,
                                fill: '#ff6b6b'
                            }
                        }
                    };
                    mapChart.setOption(option);
                    return;
                }
            }
            
            // 使用真正的地图显示城市访客分布
            const option = {
                title: {
                    text: '中国访客分布',
                    left: 'center',
                    textStyle: {
                        fontSize: 18,
                        fontWeight: 'bold',
                        color: isDark ? '#f8fafc' : '#333'
                    }
                },
                tooltip: {
                    trigger: 'item',
                    backgroundColor: isDark ? '#1f2937' : '#fff',
                    borderColor: isDark ? '#374151' : '#ccc',
                    textStyle: {
                        color: isDark ? '#f9fafb' : '#333'
                    },
                    formatter: function(params) {
                        if (params.data) {
                            return `${params.name}<br/>访问次数: ${params.data.value}<br/>独立访客: ${params.data.uniqueVisitors}`;
                        }
                        return params.name;
                    }
                },
                visualMap: {
                    min: 0,
                    max: maxValue,
                    left: 'left',
                    top: 'bottom',
                    text: ['高', '低'],
                    calculable: true,
                    textStyle: {
                        color: isDark ? '#cbd5e1' : '#333'
                    },
                    inRange: {
                        color: isDark ? ['#1e293b', '#10b981', '#047857'] : ['#e6f3ff', '#28a745', '#155724']
                    }
                },
                series: [{
                    name: '访客分布',
                    type: 'map',
                    map: 'china',
                    roam: false,
                    itemStyle: {
                        areaColor: isDark ? '#1f2937' : '#f3f4f6',
                        borderColor: isDark ? '#374151' : '#e5e7eb'
                    },
                    emphasis: {
                        label: {
                            show: true,
                            color: isDark ? '#ffffff' : '#333'
                        },
                        itemStyle: {
                            areaColor: '#ff6b6b'
                        }
                    },
                    data: visitorData.provinces.map(item => ({
                        name: item.province,
                        value: item.visitor_count,
                        uniqueVisitors: item.unique_visitors
                    }))
                }]
            };
            
            mapChart.setOption(option);
        }
        
        // 切换地图
        function switchMap(type) {
            currentMapType = type;
            
            // 更新按钮状态
            document.getElementById('worldBtn').classList.toggle('active', type === 'world');
            document.getElementById('chinaBtn').classList.toggle('active', type === 'china');
            
            // 加载对应地图
            if (type === 'world') {
                loadWorldMap();
            } else {
                loadChinaMap();
            }
        }
        
        // 时间筛选功能
        document.addEventListener('DOMContentLoaded', function() {
            // 时间筛选单选按钮事件
            const timeFilterRadios = document.querySelectorAll('input[name="time_filter"]');
            timeFilterRadios.forEach(radio => {
                radio.addEventListener('change', function() {
                    toggleCustomTimeRange();
                });
            });
            
            // 表单提交验证
            const filterForm = document.querySelector('.filter-form');
            if (filterForm) {
                filterForm.addEventListener('submit', function(e) {
                    const customRadio = document.querySelector('input[name="time_filter"][value="custom"]');
                    if (customRadio && customRadio.checked) {
                        const startDate = document.getElementById('startDate').value;
                        const endDate = document.getElementById('endDate').value;
                        
                        if (!startDate || !endDate) {
                            e.preventDefault();
                            alert('请选择开始时间和结束时间');
                            return false;
                        }
                        
                        if (new Date(startDate) > new Date(endDate)) {
                            e.preventDefault();
                            alert('开始时间不能晚于结束时间');
                            return false;
                        }
                    }
                });
            }
            
            // 等待ECharts完全加载后再初始化地图
            setTimeout(() => {
                if (typeof echarts !== 'undefined') {
                    console.log('ECharts已加载');
                    // 检查地图是否已注册
                    const worldMap = echarts.getMap('world');
                    const chinaMap = echarts.getMap('china');
                    console.log('世界地图已注册:', !!worldMap);
                    console.log('中国地图已注册:', !!chinaMap);
                    
                    initMap();
                } else {
                    console.error('ECharts未加载成功');
                }
            }, 1000);
            
            // 窗口大小改变时重新调整地图
            window.addEventListener('resize', function() {
                if (mapChart) {
                    mapChart.resize();
                }
            });

            // 监听主题切换事件以重新渲染地图
            window.addEventListener('themechanged', function() {
                if (currentMapType === 'world') {
                    loadWorldMap();
                } else {
                    loadChinaMap();
                }
            });
        });
        
        // 自定义时间范围显示/隐藏
        function toggleCustomTimeRange() {
            const customTimeRange = document.getElementById('customTimeRange');
            const customRadio = document.querySelector('input[name="time_filter"][value="custom"]');
            
            if (customRadio && customRadio.checked) {
                customTimeRange.style.display = 'flex';
            } else {
                customTimeRange.style.display = 'none';
            }
        }
    </script>
</body>
</html>
