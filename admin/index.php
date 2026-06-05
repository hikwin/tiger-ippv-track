<?php
/**
 * 管理后台主页面
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

// 获取项目参数
$projectId = isset($_GET['project_id']) ? (int)$_GET['project_id'] : null;
$projectName = null;

// 获取时间筛选参数
$timeFilter = isset($_GET['time_filter']) ? $_GET['time_filter'] : 'total';
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : null;
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : null;

// 如果指定了项目ID，获取项目信息
if ($projectId) {
    $project = $db->fetchOne("SELECT * FROM projects WHERE id = ?", [$projectId]);
    if ($project) {
        $projectName = $project['name'];
    } else {
        // 项目不存在，跳转到默认仪表盘
        header('Location: index.php');
        exit;
    }
}

// 获取统计数据（支持项目过滤和时间筛选）
$stats = getDashboardStats($projectId, $timeFilter, $startDate, $endDate);
$topPages = getTopPages(10, $projectId, $timeFilter, $startDate, $endDate);
$visitsByHour = getVisitsByHour($projectId, $timeFilter, $startDate, $endDate);
$visitsByDay = getVisitsByDay($projectId, $timeFilter, $startDate, $endDate);
$uvByHour = getUVByHour($projectId, $timeFilter, $startDate, $endDate);
$uvByDay = getUVByDay($projectId, $timeFilter, $startDate, $endDate);
$visitsBySource = getVisitsBySource($projectId, $timeFilter, $startDate, $endDate);
$visitsByCountry = getVisitsByCountry(10, $projectId, $timeFilter, $startDate, $endDate);

// 获取设备UA统计信息
$deviceStats = getDeviceTypeStats(10, $projectId, $timeFilter, $startDate, $endDate);
$browserStats = getBrowserStats(10, $projectId, $timeFilter, $startDate, $endDate);
$osStats = getOSStats(10, $projectId, $timeFilter, $startDate, $endDate);

// 获取项目访问量统计（仅在总览时显示）
$projectStats = null;
if (!$projectId) {
    $projectStats = getProjectVisitStats(10, $timeFilter, $startDate, $endDate);
}

/**
 * 验证日期字符串是否为合法 Y-m-d 格式（防 SQL 注入）
 */
function validateDateParam($date) {
    if (empty($date)) return null;
    // 只允许 YYYY-MM-DD 格式
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) return null;
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return ($d && $d->format('Y-m-d') === $date) ? $date : null;
}

/**
 * 构建时间筛选条件（所有插入 SQL 的值均来自 PHP date() 或严格验证的日期）
 */
function buildTimeFilterCondition($timeFilter, $startDate = null, $endDate = null) {
    // 白名单校验 timeFilter
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
            // 严格验证：只接受 Y-m-d 格式，拒绝任何含特殊字符的输入
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
 * 获取仪表板统计数据
 */
function getDashboardStats($projectId = null, $timeFilter = 'total', $startDate = null, $endDate = null) {
    global $db;
    
    // 构建时间筛选条件
    $timeFilterCondition = buildTimeFilterCondition($timeFilter, $startDate, $endDate);
    
    // 构建项目过滤条件
    $projectFilter = $projectId ? " AND project_id = " . (int)$projectId : "";
    
    $stats = [];
    
    // 根据时间筛选获取统计数据
    $stats['filtered'] = [
        'visits' => $db->fetchOne("SELECT COUNT(*) as count FROM visits WHERE 1=1" . $timeFilterCondition . $projectFilter)['count'],
        'unique_ips' => $db->fetchOne("SELECT COUNT(DISTINCT ip_address) as count FROM visits WHERE 1=1" . $timeFilterCondition . $projectFilter)['count'],
        'pages' => $db->fetchOne("SELECT COUNT(DISTINCT page_url) as count FROM visits WHERE 1=1" . $timeFilterCondition . $projectFilter)['count']
    ];
    
    // 保持原有的今日、昨日等统计（用于对比）
    $today = date('Y-m-d');
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    $thisWeek = date('Y-m-d', strtotime('-7 days'));
    $thisMonth = date('Y-m-d', strtotime('-30 days'));
    
    $stats['today'] = [
        'visits' => $db->fetchOne("SELECT COUNT(*) as count FROM visits WHERE DATE(visit_time) = ?" . $projectFilter, [$today])['count'],
        'unique_ips' => $db->fetchOne("SELECT COUNT(DISTINCT ip_address) as count FROM visits WHERE DATE(visit_time) = ?" . $projectFilter, [$today])['count'],
        'pages' => $db->fetchOne("SELECT COUNT(DISTINCT page_url) as count FROM visits WHERE DATE(visit_time) = ?" . $projectFilter, [$today])['count']
    ];
    
    $stats['yesterday'] = [
        'visits' => $db->fetchOne("SELECT COUNT(*) as count FROM visits WHERE DATE(visit_time) = ?" . $projectFilter, [$yesterday])['count'],
        'unique_ips' => $db->fetchOne("SELECT COUNT(DISTINCT ip_address) as count FROM visits WHERE DATE(visit_time) = ?" . $projectFilter, [$yesterday])['count']
    ];
    
    $stats['week'] = [
        'visits' => $db->fetchOne("SELECT COUNT(*) as count FROM visits WHERE DATE(visit_time) >= ?" . $projectFilter, [$thisWeek])['count'],
        'unique_ips' => $db->fetchOne("SELECT COUNT(DISTINCT ip_address) as count FROM visits WHERE DATE(visit_time) >= ?" . $projectFilter, [$thisWeek])['count']
    ];
    
    $stats['month'] = [
        'visits' => $db->fetchOne("SELECT COUNT(*) as count FROM visits WHERE DATE(visit_time) >= ?" . $projectFilter, [$thisMonth])['count'],
        'unique_ips' => $db->fetchOne("SELECT COUNT(DISTINCT ip_address) as count FROM visits WHERE DATE(visit_time) >= ?" . $projectFilter, [$thisMonth])['count']
    ];
    
    $stats['total'] = [
        'visits' => $db->fetchOne("SELECT COUNT(*) as count FROM visits" . ($projectId ? " WHERE project_id = " . (int)$projectId : ""))['count'],
        'unique_ips' => $db->fetchOne("SELECT COUNT(DISTINCT ip_address) as count FROM visits" . ($projectId ? " WHERE project_id = " . (int)$projectId : ""))['count'],
        'pages' => $db->fetchOne("SELECT COUNT(DISTINCT page_url) as count FROM visits" . ($projectId ? " WHERE project_id = " . (int)$projectId : ""))['count']
    ];
    
    return $stats;
}

/**
 * 获取最近访问记录
 */
function getRecentVisits($limit = 10, $projectId = null) {
    global $db;
    
    $projectFilter = $projectId ? " AND v.project_id = " . (int)$projectId : "";
    
    return $db->fetchAll(
        "SELECT v.*, p.name as project_name 
         FROM visits v 
         LEFT JOIN projects p ON v.project_id = p.id
         WHERE 1=1" . $projectFilter . "
         ORDER BY v.visit_time DESC 
         LIMIT ?",
        [$limit]
    );
}

/**
 * 获取热门页面
 */
function getTopPages($limit = 10, $projectId = null, $timeFilter = 'total', $startDate = null, $endDate = null) {
    global $db;
    
    $timeFilterCondition = buildTimeFilterCondition($timeFilter, $startDate, $endDate);
    $projectFilter = $projectId ? " AND project_id = " . (int)$projectId : "";
    
    return $db->fetchAll(
        "SELECT page_url, page_title, COUNT(*) as views, 
                COUNT(DISTINCT ip_address) as unique_views
         FROM visits 
         WHERE 1=1" . $timeFilterCondition . $projectFilter . "
         GROUP BY page_url, page_title 
         ORDER BY views DESC 
         LIMIT ?",
        [$limit]
    );
}

/**
 * 获取最近24小时访问量趋势
 */
function getVisitsByHour($projectId = null, $timeFilter = 'total', $startDate = null, $endDate = null) {
    global $db;
    
    $timeFilterCondition = buildTimeFilterCondition($timeFilter, $startDate, $endDate);
    $projectFilter = $projectId ? " AND project_id = " . (int)$projectId : "";
    
    // 根据时间筛选决定查询范围
    if ($timeFilter === 'today') {
        // 今日数据（只到当前时间）
        $today = date('Y-m-d');
        $now = date('Y-m-d H:i:s');
        $data = $db->fetchAll(
            "SELECT strftime('%H', visit_time) as hour, COUNT(*) as count
             FROM visits 
             WHERE DATE(visit_time) = ? AND visit_time <= ?" . $projectFilter . "
             GROUP BY strftime('%H', visit_time)
             ORDER BY hour",
            [$today, $now]
        );
        
        // 创建今日24小时的时间轴（只到当前小时）
        $currentHour = (int)date('H');
        $result = [];
        for ($i = 0; $i <= $currentHour; $i++) {
            $hourStr = sprintf('%02d', $i);
            $result[$hourStr] = 0;
        }
    } else {
        // 其他情况使用最近24小时
        $data = $db->fetchAll(
            "SELECT strftime('%H', visit_time) as hour, COUNT(*) as count
             FROM visits 
             WHERE visit_time >= datetime('now', '-24 hours')" . $timeFilterCondition . $projectFilter . "
             GROUP BY strftime('%H', visit_time)
             ORDER BY hour"
        );
        
        // 创建最近24小时的时间轴
        $currentHour = (int)date('H');
        $result = [];
        for ($i = 0; $i < 24; $i++) {
            $hour = ($currentHour - 23 + $i + 24) % 24;
            $hourStr = sprintf('%02d', $hour);
            $result[$hourStr] = 0;
        }
    }
    
    // 填充实际数据
    foreach ($data as $row) {
        $result[$row['hour']] = (int)$row['count'];
    }
    
    return $result;
}

/**
 * 获取最近30日访问量趋势
 */
function getVisitsByDay($projectId = null, $timeFilter = 'total', $startDate = null, $endDate = null) {
    global $db;
    
    $timeFilterCondition = buildTimeFilterCondition($timeFilter, $startDate, $endDate);
    $projectFilter = $projectId ? " AND project_id = " . (int)$projectId : "";
    
    // 根据时间筛选决定查询范围
    if ($timeFilter === 'custom' && $startDate && $endDate) {
        // 自定义时间范围
        $data = $db->fetchAll(
            "SELECT strftime('%Y-%m-%d', visit_time) as date, COUNT(*) as count
             FROM visits 
             WHERE DATE(visit_time) >= ? AND DATE(visit_time) <= ?" . $projectFilter . "
             GROUP BY strftime('%Y-%m-%d', visit_time)
             ORDER BY date",
            [$startDate, $endDate]
        );
        
        // 创建自定义时间范围的时间轴
        $result = [];
        $start = new DateTime($startDate);
        $end = new DateTime($endDate);
        $interval = new DateInterval('P1D');
        $period = new DatePeriod($start, $interval, $end->modify('+1 day'));
        
        foreach ($period as $date) {
            $result[$date->format('Y-m-d')] = 0;
        }
    } else {
        // 默认最近30日
        $data = $db->fetchAll(
            "SELECT strftime('%Y-%m-%d', visit_time) as date, COUNT(*) as count
             FROM visits 
             WHERE visit_time >= datetime('now', '-30 days')" . $timeFilterCondition . $projectFilter . "
             GROUP BY strftime('%Y-%m-%d', visit_time)
             ORDER BY date"
        );
        
        // 创建最近30日的时间轴
        $result = [];
        for ($i = 29; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $result[$date] = 0;
        }
    }
    
    // 填充实际数据
    foreach ($data as $row) {
        $result[$row['date']] = (int)$row['count'];
    }
    
    return $result;
}

/**
 * 获取来源国家统计
 */
function getVisitsByCountry($limit = 10, $projectId = null, $timeFilter = 'total', $startDate = null, $endDate = null) {
    global $db;
    
    $timeFilterCondition = buildTimeFilterCondition($timeFilter, $startDate, $endDate);
    $projectFilter = $projectId ? " AND project_id = " . (int)$projectId : "";
    
    $data = $db->fetchAll(
        "SELECT 
            CASE 
                WHEN country = '局域网' THEN '局域网'
                WHEN country IS NULL OR country = '' OR country = '未知' THEN '未知'
                ELSE country
            END as country,
            COUNT(*) as count
         FROM visits 
         WHERE 1=1" . $timeFilterCondition . $projectFilter . "
         GROUP BY 
            CASE 
                WHEN country = '局域网' THEN '局域网'
                WHEN country IS NULL OR country = '' OR country = '未知' THEN '未知'
                ELSE country
            END
         ORDER BY count DESC 
         LIMIT ?",
        [$limit]
    );
    
    return $data;
}

/**
 * 获取按来源统计的访问量
 */
function getVisitsBySource($projectId = null, $timeFilter = 'total', $startDate = null, $endDate = null) {
    global $db;
    
    $timeFilterCondition = buildTimeFilterCondition($timeFilter, $startDate, $endDate);
    $projectFilter = $projectId ? " AND project_id = " . (int)$projectId : "";
    
    return $db->fetchAll(
        "SELECT source_type, COUNT(*) as count
         FROM visits 
         WHERE 1=1" . $timeFilterCondition . $projectFilter . "
         GROUP BY source_type
         ORDER BY count DESC"
    );
}

/**
 * 获取最近24小时独立访客趋势
 */
function getUVByHour($projectId = null, $timeFilter = 'total', $startDate = null, $endDate = null) {
    global $db;
    
    $timeFilterCondition = buildTimeFilterCondition($timeFilter, $startDate, $endDate);
    $projectFilter = $projectId ? " AND project_id = " . (int)$projectId : "";
    
    // 根据时间筛选决定查询范围
    if ($timeFilter === 'today') {
        // 今日数据（只到当前时间）
        $today = date('Y-m-d');
        $now = date('Y-m-d H:i:s');
        $data = $db->fetchAll(
            "SELECT strftime('%H', visit_time) as hour, COUNT(DISTINCT ip_address) as count
             FROM visits 
             WHERE DATE(visit_time) = ? AND visit_time <= ?" . $projectFilter . "
             GROUP BY strftime('%H', visit_time)
             ORDER BY hour",
            [$today, $now]
        );
        
        // 创建今日24小时的时间轴（只到当前小时）
        $currentHour = (int)date('H');
        $result = [];
        for ($i = 0; $i <= $currentHour; $i++) {
            $hourStr = sprintf('%02d', $i);
            $result[$hourStr] = 0;
        }
    } else {
        // 其他情况使用最近24小时
        $data = $db->fetchAll(
            "SELECT strftime('%H', visit_time) as hour, COUNT(DISTINCT ip_address) as count
             FROM visits 
             WHERE visit_time >= datetime('now', '-24 hours')" . $timeFilterCondition . $projectFilter . "
             GROUP BY strftime('%H', visit_time)
             ORDER BY hour"
        );
        
        // 创建最近24小时的时间轴
        $currentHour = (int)date('H');
        $result = [];
        for ($i = 0; $i < 24; $i++) {
            $hour = ($currentHour - 23 + $i + 24) % 24;
            $hourStr = sprintf('%02d', $hour);
            $result[$hourStr] = 0;
        }
    }
    
    // 填充实际数据
    foreach ($data as $row) {
        $result[$row['hour']] = (int)$row['count'];
    }
    
    return $result;
}

/**
 * 获取最近30日独立访客趋势
 */
function getUVByDay($projectId = null, $timeFilter = 'total', $startDate = null, $endDate = null) {
    global $db;
    
    $timeFilterCondition = buildTimeFilterCondition($timeFilter, $startDate, $endDate);
    $projectFilter = $projectId ? " AND project_id = " . (int)$projectId : "";
    
    // 根据时间筛选决定查询范围
    if ($timeFilter === 'custom' && $startDate && $endDate) {
        // 自定义时间范围
        $data = $db->fetchAll(
            "SELECT strftime('%Y-%m-%d', visit_time) as date, COUNT(DISTINCT ip_address) as count
             FROM visits 
             WHERE DATE(visit_time) >= ? AND DATE(visit_time) <= ?" . $projectFilter . "
             GROUP BY strftime('%Y-%m-%d', visit_time)
             ORDER BY date",
            [$startDate, $endDate]
        );
        
        // 创建自定义时间范围的时间轴
        $result = [];
        $start = new DateTime($startDate);
        $end = new DateTime($endDate);
        $interval = new DateInterval('P1D');
        $period = new DatePeriod($start, $interval, $end->modify('+1 day'));
        
        foreach ($period as $date) {
            $result[$date->format('Y-m-d')] = 0;
        }
    } else {
        // 默认最近30日
        $data = $db->fetchAll(
            "SELECT strftime('%Y-%m-%d', visit_time) as date, COUNT(DISTINCT ip_address) as count
             FROM visits 
             WHERE visit_time >= datetime('now', '-30 days')" . $timeFilterCondition . $projectFilter . "
             GROUP BY strftime('%Y-%m-%d', visit_time)
             ORDER BY date"
        );
        
        // 创建最近30日的时间轴
        $result = [];
        for ($i = 29; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $result[$date] = 0;
        }
    }
    
    // 填充实际数据
    foreach ($data as $row) {
        $result[$row['date']] = (int)$row['count'];
    }
    
    return $result;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>系统仪表盘 - <?php echo SITE_NAME; ?></title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@400;500;600;700&display=swap');
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: #f8fafc;
            color: #0f172a;
        }
        
        .main-content {
            margin-top: 60px;
            padding: 20px;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        /* Sleek Segmented Pill Filters (Capsules) */
        .time-filter-section {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 16px 20px;
            margin-bottom: 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.02);
        }
        
        .time-filter-container {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            flex-wrap: wrap;
        }
        
        .time-filter-title {
            font-family: 'Outfit', sans-serif;
            font-weight: 600;
            color: #334155;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .time-filter-controls {
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .time-radio-group {
            display: flex;
            background: #f1f5f9;
            padding: 4px;
            border-radius: 12px;
            gap: 2px;
        }
        
        .time-radio-item {
            cursor: pointer;
            position: relative;
        }
        
        .time-radio-item input[type="radio"] {
            position: absolute;
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .time-radio-item span {
            display: inline-block;
            padding: 8px 16px;
            font-size: 13px;
            font-weight: 500;
            color: #64748b;
            border-radius: 9px;
            transition: all 0.2s ease;
        }
        
        .time-radio-item:hover span {
            color: #0f172a;
        }
        
        .time-radio-item input[type="radio"]:checked + span {
            background: #ffffff;
            color: #6366f1;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08), 0 1px 2px rgba(0,0,0,0.04);
            font-weight: 600;
        }
        
        .custom-time-range {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 4px 8px;
            background: #f8fafc;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
        }
        
        .date-input {
            padding: 6px 10px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 13px;
            color: #334155;
            outline: none;
            background: #ffffff;
            transition: all 0.2s;
        }
        .date-input:focus {
            border-color: #6366f1;
            box-shadow: 0 0 0 2px rgba(99,102,241,0.12);
        }
        
        .date-separator {
            color: #64748b;
            font-size: 13px;
        }
        
        .search-btn {
            background: #6366f1;
            color: white;
            border: none;
            padding: 6px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.2s;
            box-shadow: 0 2px 4px rgba(99,102,241,0.12);
        }
        
        .search-btn:hover {
            background: #4f46e5;
        }
        
        /* Minimalist Metrics Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 24px;
        }
        
        .stat-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.02);
            transition: all 0.2s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(0,0,0,0.05), 0 4px 6px -2px rgba(0,0,0,0.02);
        }
        
        .stat-card h3 {
            color: #64748b;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 12px;
        }
        
        .stat-card .number {
            font-family: 'Outfit', sans-serif;
            font-size: 32px;
            font-weight: 700;
            color: #0f172a;
            line-height: 1;
            margin-bottom: 8px;
        }
        
        .stat-card .change {
            font-size: 13px;
            color: #64748b;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        
        .stat-card .change.text-success {
            color: #10b981 !important;
        }
        
        .stat-card .change.negative {
            color: #f43f5e !important;
        }
        
        /* Premium Handwritten Bar Charts */
        .chart-container {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.02);
            margin-bottom: 24px;
        }
        
        .chart-container.fixed-width {
            min-width: 0;
            overflow: hidden;
        }
        
        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .chart-title {
            font-family: 'Outfit', sans-serif;
            font-size: 18px;
            font-weight: 600;
            color: #0f172a;
        }
        
        .chart-controls {
            display: flex;
            gap: 15px;
            align-items: center;
        }
        
        .chart-toggle,
        .metric-toggle {
            display: flex;
            background: #f1f5f9;
            padding: 2px;
            border-radius: 8px;
            gap: 2px;
        }
        
        .toggle-btn {
            padding: 6px 12px;
            border: none;
            background: transparent;
            color: #64748b;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            transition: all 0.2s ease;
        }
        
        .toggle-btn:hover {
            color: #0f172a;
        }
        
        .toggle-btn.active {
            background: #ffffff;
            color: #6366f1;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        }
        
        .chart-wrapper {
            height: 280px;
            margin-top: 10px;
        }
        
        .chart {
            height: 240px;
            display: flex;
            align-items: end;
            gap: 6px;
        }
        
        .chart-item {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            height: 100%;
            justify-content: end;
        }
        
        /* Bar track styling (anchors bar and gives SaaS outline) */
        .chart-bar-track {
            background: #f8fafc;
            border-radius: 8px;
            width: 100%;
            height: 200px;
            display: flex;
            align-items: end;
            justify-content: center;
            position: relative;
            border: 1px solid #f1f5f9;
            overflow: visible;
        }
        
        .chart-bar {
            background: linear-gradient(to top, #6366f1, #8b5cf6);
            border-radius: 6px;
            width: 100%;
            position: relative;
            transition: height 0.5s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
        }
        
        .chart-bar:hover {
            background: linear-gradient(to top, #4f46e5, #7c3aed);
        }
        
        /* Beautiful CSS Tooltip */
        .chart-bar::after {
            content: attr(data-value);
            position: absolute;
            top: -28px;
            left: 50%;
            transform: translateX(-50%) scale(0.8);
            font-family: 'Outfit', sans-serif;
            font-size: 11px;
            font-weight: 700;
            background: #0f172a;
            color: #ffffff;
            padding: 4px 8px;
            border-radius: 6px;
            opacity: 0;
            pointer-events: none;
            transition: all 0.15s ease;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
        }
        
        .chart-bar:hover::after {
            opacity: 1;
            transform: translateX(-50%) scale(1);
        }
        
        .chart-label {
            font-size: 11px;
            color: #64748b;
            text-align: center;
            margin-top: 8px;
            font-weight: 500;
        }
        
        /* Dashboard Content Grids */
        .dashboard-row {
            display: grid;
            grid-template-columns: 1fr 1fr 2fr;
            gap: 20px;
            margin-bottom: 24px;
        }
        
        @media (max-width: 1200px) {
            .dashboard-row {
                grid-template-columns: 1fr;
            }
        }
        
        .item-list {
            padding: 10px 0;
        }
        
        .list-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #f1f5f9;
        }
        .list-row:last-child {
            border-bottom: none;
        }
        
        .list-row span {
            font-size: 13.5px;
            font-weight: 500;
            color: #334155;
        }
        
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            font-family: 'Outfit', sans-serif;
        }
        
        .badge-info {
            background: #e0e7ff;
            color: #4f46e5;
        }
        
        .badge-success {
            background: #d1fae5;
            color: #059669;
        }
        
        /* Popular Pages Styling */
        .top-page-card {
            margin-bottom: 12px;
            padding: 12px 14px;
            background: #f8fafc;
            border: 1px solid #f1f5f9;
            border-radius: 12px;
            transition: all 0.2s ease;
        }
        
        .top-page-card:hover {
            background: #ffffff;
            border-color: #cbd5e1;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02);
            transform: translateX(2px);
        }
        
        .page-meta-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .page-url-info {
            flex: 1;
            min-width: 0;
        }
        
        .page-title-text {
            font-weight: 600;
            font-size: 13.5px;
            color: #1e293b;
            margin-bottom: 2px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .page-url-text {
            font-size: 11px;
            color: #64748b;
            font-family: monospace;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .page-stats-pill {
            display: flex;
            gap: 12px;
            font-size: 11px;
            color: #475569;
            margin-left: 15px;
            flex-shrink: 0;
            background: #ffffff;
            padding: 4px 8px;
            border-radius: 6px;
            border: 1px solid #e2e8f0;
            font-weight: 500;
        }
        
        /* Device & Browser Analysis Cards */
        .analysis-section {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.02);
        }
        
        .analysis-section h2 {
            margin-bottom: 20px;
            color: #0f172a;
            font-family: 'Outfit', sans-serif;
            font-size: 18px;
            font-weight: 600;
        }
        
        .analysis-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
        }
        
        .analysis-card {
            background: #f8fafc;
            border-radius: 12px;
            padding: 20px;
            border: 1px solid #f1f5f9;
        }
        
        .analysis-card h3 {
            color: #475569;
            margin-bottom: 16px;
            font-size: 13.5px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 8px;
        }
        
        .stats-list {
            max-height: 250px;
            overflow-y: auto;
        }
        
        .stat-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #f1f5f9;
        }
        
        .stat-item:last-child {
            border-bottom: none;
        }
        
        .stat-label {
            color: #475569;
            font-size: 13px;
            font-weight: 500;
        }
        
        .stat-value {
            color: #6366f1;
            font-family: 'Outfit', sans-serif;
            font-weight: 700;
            font-size: 14px;
        }
        
        .no-data {
            text-align: center;
            color: #94a3b8;
            font-size: 13px;
            padding: 20px;
        }
        
        /* Projects Rankings List */
        .projects-section {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.02);
        }
        
        .projects-section h2 {
            margin-bottom: 20px;
            color: #0f172a;
            font-family: 'Outfit', sans-serif;
            font-size: 18px;
            font-weight: 600;
        }
        
        .projects-list {
            max-height: 500px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        
        .project-item {
            display: flex;
            align-items: center;
            padding: 16px 20px;
            background: #f8fafc;
            border-radius: 12px;
            border: 1px solid #f1f5f9;
            transition: all 0.2s ease;
        }
        
        .project-item:hover {
            transform: translateX(4px);
            background: #ffffff;
            border-color: #cbd5e1;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02);
        }
        
        .project-rank {
            width: 32px;
            height: 32px;
            background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Outfit', sans-serif;
            font-weight: 700;
            font-size: 13px;
            margin-right: 16px;
            flex-shrink: 0;
            box-shadow: 0 2px 4px rgba(99,102,241,0.2);
        }
        
        .project-info {
            flex: 1;
            margin-right: 20px;
        }
        
        .project-name {
            font-size: 15px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 4px;
        }
        
        .project-code {
            font-size: 10px;
            color: #475569;
            font-family: monospace;
            background: #e2e8f0;
            padding: 2px 6px;
            border-radius: 4px;
            display: inline-block;
            margin-bottom: 6px;
            font-weight: 600;
        }
        
        .project-desc {
            font-size: 12px;
            color: #64748b;
            line-height: 1.4;
        }
        
        .project-stats {
            display: flex;
            gap: 20px;
            margin-right: 20px;
        }
        
        .stat-group {
            text-align: center;
        }
        
        .stat-number {
            display: block;
            font-family: 'Outfit', sans-serif;
            font-size: 18px;
            font-weight: 700;
            color: #6366f1;
        }
        
        .stat-group .stat-label {
            font-size: 11px;
            color: #64748b;
            font-weight: 500;
        }
        
        .project-time {
            text-align: right;
            font-size: 11px;
            color: #64748b;
            font-weight: 500;
        }
        
        .last-visit {
            margin-bottom: 4px;
        }
        
        /* Modal Styles */
        .modal {
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(15, 23, 42, 0.4);
            backdrop-filter: blur(4px);
        }
        
        .modal-content {
            background-color: #ffffff;
            margin: 5% auto;
            padding: 0;
            border: 1px solid #e2e8f0;
            width: 80%;
            max-width: 1000px;
            border-radius: 16px;
            box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04);
            overflow: hidden;
        }
        
        .modal-header {
            padding: 20px 24px;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-header h3 {
            margin: 0;
            color: #0f172a;
            font-family: 'Outfit', sans-serif;
            font-size: 16px;
            font-weight: 600;
        }
        
        .close {
            color: #94a3b8;
            font-size: 24px;
            font-weight: bold;
            cursor: pointer;
            transition: color 0.15s ease;
        }
        
        .close:hover {
            color: #0f172a;
        }
        
        .modal-body {
            padding: 0;
            max-height: 70vh;
            overflow-y: auto;
        }
        
        .record-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .record-table th,
        .record-table td {
            padding: 12px 16px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
            font-size: 13px;
        }
        
        .record-table th {
            background-color: #f8fafc;
            font-weight: 600;
            color: #475569;
        }
        
        .record-table td {
            color: #334155;
        }
        
        .pagination {
            padding: 20px;
            text-align: center;
            border-top: 1px solid #e2e8f0;
            background: #f8fafc;
        }
        
        .pagination button {
            margin: 0 4px;
            padding: 8px 14px;
            border: 1px solid #e2e8f0;
            background: #ffffff;
            cursor: pointer;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            color: #475569;
            transition: all 0.2s ease;
        }
        
        .pagination button:hover {
            background: #f1f5f9;
            border-color: #cbd5e1;
            color: #0f172a;
        }
        
        .pagination button.active {
            background: #6366f1;
            color: white;
            border-color: #6366f1;
            box-shadow: 0 2px 4px rgba(99,102,241,0.25);
        }
        
        .pagination button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .clickable-stat:hover {
            transform: scale(1.05);
            transition: all 0.15s ease;
        }
        
        .text-muted {
            color: #64748b;
        }
        
        .text-success {
            color: #10b981;
        }
        
        .text-danger {
            color: #f43f5e;
        }
        
        @media (max-width: 768px) {
            .time-filter-container {
                flex-direction: column;
                align-items: flex-start;
            }
            .time-radio-group {
                flex-wrap: wrap;
            }
            .project-item {
                flex-direction: column;
                align-items: flex-start;
            }
            .project-rank {
                margin-bottom: 12px;
            }
            .project-info {
                margin-right: 0;
                margin-bottom: 12px;
            }
            .project-stats {
                margin-bottom: 12px;
                width: 100%;
                justify-content: space-between;
            }
            .project-time {
                text-align: left;
            }
            .modal-content {
                width: 95%;
                margin: 10% auto;
            }
        }

        /* 查看更多 button */
        .btn-view-more {
            font-size: 12px;
            padding: 6px 16px;
            border: 1px solid #e2e8f0;
            background: #ffffff;
            border-radius: 8px;
            font-weight: 600;
            color: #475569;
            cursor: pointer;
            transition: all 0.2s ease;
            font-family: 'Inter', sans-serif;
        }
        .btn-view-more:hover {
            background: #f1f5f9;
            border-color: #cbd5e1;
            color: #0f172a;
        }
        html.dark-mode .btn-view-more {
            background: #1f2937 !important;
            border-color: #374151 !important;
            color: #94a3b8 !important;
        }
        html.dark-mode .btn-view-more:hover {
            background: #374151 !important;
            border-color: #4b5563 !important;
            color: #f8fafc !important;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="main-content">
        <div class="container">
            <?php if ($projectName): ?>
                <div style="margin-bottom: 20px;">
                    <span style="font-size: 16px; color: #666; font-weight: normal;">
                        <?php echo htmlspecialchars($projectName); ?>
                    </span>
                    <a href="projects.php" style="font-size: 14px; color: #007bff; text-decoration: none; margin-left: 15px; font-weight: normal;">
                        ← 返回项目列表
                    </a>
                </div>
            <?php endif; ?>
            
            <!-- 时间筛选控件 -->
            <div class="time-filter-section">
                <div class="time-filter-container">
                    <div class="time-filter-title">时间维度筛选：</div>
                    <div class="time-filter-controls">
                        <div class="time-radio-group">
                            <label class="time-radio-item">
                                <input type="radio" name="timeFilter" value="total" checked>
                                <span>总访问</span>
                            </label>
                            <label class="time-radio-item">
                                <input type="radio" name="timeFilter" value="today">
                                <span>今日</span>
                            </label>
                            <label class="time-radio-item">
                                <input type="radio" name="timeFilter" value="yesterday">
                                <span>昨日</span>
                            </label>
                            <label class="time-radio-item">
                                <input type="radio" name="timeFilter" value="week">
                                <span>本周</span>
                            </label>
                            <label class="time-radio-item">
                                <input type="radio" name="timeFilter" value="custom">
                                <span>自定义</span>
                            </label>
                        </div>
                        <div class="custom-time-range" id="customTimeRange" style="display: none;">
                            <input type="date" id="startDate" class="date-input">
                            <span class="date-separator">至</span>
                            <input type="date" id="endDate" class="date-input">
                            <button class="search-btn" onclick="applyCustomTimeFilter()">查找</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 统计卡片 -->
            <div class="stats-grid">
                <div class="stat-card">
                    <h3><?php echo $timeFilter === 'total' ? '总访问量' : ($timeFilter === 'today' ? '今日访问量' : ($timeFilter === 'yesterday' ? '昨日访问量' : ($timeFilter === 'week' ? '本周访问量' : '筛选访问量'))); ?></h3>
                    <div class="number"><?php echo formatNumber($timeFilter === 'total' ? $stats['total']['visits'] : $stats['filtered']['visits']); ?></div>
                    <div class="change">
                        <?php if ($timeFilter === 'total'): ?>
                            共 <?php echo formatNumber($stats['total']['pages']); ?> 个页面
                        <?php else: ?>
                            筛选时间范围数据
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="stat-card">
                    <h3><?php echo $timeFilter === 'total' ? '总独立访客' : ($timeFilter === 'today' ? '今日独立访客' : ($timeFilter === 'yesterday' ? '昨日独立访客' : ($timeFilter === 'week' ? '本周独立访客' : '筛选独立访客'))); ?></h3>
                    <div class="number"><?php echo formatNumber($timeFilter === 'total' ? $stats['total']['unique_ips'] : $stats['filtered']['unique_ips']); ?></div>
                    <div class="change">
                        <?php if ($timeFilter === 'total'): ?>
                            共 <?php echo formatNumber($stats['total']['unique_ips']); ?> 个独立IP
                        <?php else: ?>
                            筛选时间范围数据
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="stat-card">
                    <h3>今日访问量</h3>
                    <div class="number"><?php echo formatNumber($stats['today']['visits']); ?></div>
                    <div class="change <?php echo $stats['today']['visits'] > $stats['yesterday']['visits'] ? '' : 'negative'; ?>">
                        <?php 
                        $change = $stats['yesterday']['visits'] > 0 ? 
                            (($stats['today']['visits'] - $stats['yesterday']['visits']) / $stats['yesterday']['visits'] * 100) : 0;
                        echo ($change >= 0 ? '+' : '') . number_format($change, 1) . '%';
                        ?>
                        较昨日
                    </div>
                </div>
                
                <div class="stat-card">
                    <h3>总访问量</h3>
                    <div class="number"><?php echo formatNumber($stats['total']['visits']); ?></div>
                    <div class="change">
                        共 <?php echo formatNumber($stats['total']['pages']); ?> 个页面
                    </div>
                </div>
            </div>
            
            <!-- 图表区域 -->
            <div class="chart-container">
                <div class="chart-header">
                    <div class="chart-title" id="chartTitle">24小时访问趋势</div>
                    <div class="chart-controls">
                        <div class="chart-toggle">
                            <button class="toggle-btn active" onclick="toggleChart('hourly')" id="hourlyBtn">24小时</button>
                            <button class="toggle-btn" onclick="toggleChart('daily')" id="dailyBtn">30日</button>
                        </div>
                        <div class="metric-toggle">
                            <button class="toggle-btn active" onclick="toggleMetric('pv')" id="pvBtn">PV</button>
                            <button class="toggle-btn" onclick="toggleMetric('uv')" id="uvBtn">UV</button>
                        </div>
                    </div>
                </div>
                <div class="chart-wrapper">
                    <!-- 24小时PV图表 -->
                    <div class="chart" id="hourlyChart">
                        <?php 
                        $currentHour = (int)date('H');
                        $maxCount = max($visitsByHour);
                        $maxHeight = 200; // 最大高度
                        foreach ($visitsByHour as $hour => $count): 
                            $height = $maxCount > 0 ? max(4, ($count / $maxCount) * $maxHeight) : 4;
                            $hourInt = (int)$hour;
                            $displayHour = $hourInt;
                        ?>
                            <div class="chart-item">
                                <div class="chart-bar-track">
                                    <div class="chart-bar" style="height: <?php echo $height; ?>px;" data-value="<?php echo $count; ?>"></div>
                                </div>
                                <div class="chart-label"><?php echo $displayHour; ?>时</div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- 24小时UV图表 -->
                    <div class="chart" id="hourlyUVChart" style="display: none;">
                        <?php 
                        $currentHour = (int)date('H');
                        $maxCount = max($uvByHour);
                        $maxHeight = 200; // 最大高度
                        foreach ($uvByHour as $hour => $count): 
                            $height = $maxCount > 0 ? max(4, ($count / $maxCount) * $maxHeight) : 4;
                            $hourInt = (int)$hour;
                            $displayHour = $hourInt;
                        ?>
                            <div class="chart-item">
                                <div class="chart-bar-track">
                                    <div class="chart-bar" style="height: <?php echo $height; ?>px;" data-value="<?php echo $count; ?>"></div>
                                </div>
                                <div class="chart-label"><?php echo $displayHour; ?>时</div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- 30日PV图表 -->
                    <div class="chart" id="dailyChart" style="display: none;">
                        <?php 
                        $maxCount = max($visitsByDay);
                        $maxHeight = 200; // 最大高度
                        foreach ($visitsByDay as $date => $count): 
                            $height = $maxCount > 0 ? max(4, ($count / $maxCount) * $maxHeight) : 4;
                            $displayDate = date('m-d', strtotime($date));
                        ?>
                            <div class="chart-item">
                                <div class="chart-bar-track">
                                    <div class="chart-bar" style="height: <?php echo $height; ?>px;" data-value="<?php echo $count; ?>"></div>
                                </div>
                                <div class="chart-label"><?php echo $displayDate; ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- 30日UV图表 -->
                    <div class="chart" id="dailyUVChart" style="display: none;">
                        <?php 
                        $maxCount = max($uvByDay);
                        $maxHeight = 200; // 最大高度
                        foreach ($uvByDay as $date => $count): 
                            $height = $maxCount > 0 ? max(4, ($count / $maxCount) * $maxHeight) : 4;
                            $displayDate = date('m-d', strtotime($date));
                        ?>
                            <div class="chart-item">
                                <div class="chart-bar-track">
                                    <div class="chart-bar" style="height: <?php echo $height; ?>px;" data-value="<?php echo $count; ?>"></div>
                                </div>
                                <div class="chart-label"><?php echo $displayDate; ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <div class="dashboard-row">
                <!-- 访问来源 -->
                <div class="chart-container fixed-width">
                    <div class="chart-title">访问来源分布</div>
                    <div class="item-list">
                        <?php foreach ($visitsBySource as $source): ?>
                            <div class="list-row">
                                <span><?php 
                                    $sourceTypeMap = [
                                        'direct' => '直接访问',
                                        'internal' => '内部链接', 
                                        'search' => '搜索引擎',
                                        'social' => '社交媒体',
                                        'referral' => '外部链接'
                                    ];
                                    echo $sourceTypeMap[$source['source_type']] ?? $source['source_type'];
                                ?></span>
                                <span class="badge badge-info clickable-stat" data-type="source" data-value="<?php echo htmlspecialchars($source['source_type']); ?>" style="cursor: pointer;"><?php echo formatNumber($source['count']); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- 来源国家 -->
                <div class="chart-container fixed-width">
                    <div class="chart-title">来源国家/地区分布</div>
                    <div class="item-list">
                        <?php foreach ($visitsByCountry as $country): ?>
                            <div class="list-row">
                                <span><?php echo htmlspecialchars($country['country']); ?></span>
                                <span class="badge badge-success clickable-stat" data-type="country" data-value="<?php echo htmlspecialchars($country['country']); ?>" style="cursor: pointer;"><?php echo formatNumber($country['count']); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- 热门页面 -->
                <div class="chart-container fixed-width">
                    <div class="chart-title">热门页面</div>
                    <div class="item-list" style="padding-top: 10px;">
                        <?php foreach ($topPages as $page): ?>
                            <div class="top-page-card">
                                <div class="page-meta-row">
                                    <div class="page-url-info">
                                        <div class="page-title-text" title="<?php echo htmlspecialchars($page['page_title'] ?: '无标题'); ?>">
                                            <?php 
                                            $title = $page['page_title'] ?: '无标题';
                                            echo htmlspecialchars(mb_strlen($title, 'UTF-8') > 20 ? mb_substr($title, 0, 20, 'UTF-8') . '...' : $title);
                                            ?>
                                        </div>
                                        <div class="page-url-text" title="<?php echo htmlspecialchars($page['page_url']); ?>">
                                            <?php 
                                            $url = $page['page_url'];
                                            echo htmlspecialchars(mb_strlen($url, 'UTF-8') > 30 ? mb_substr($url, 0, 30, 'UTF-8') . '...' : $url);
                                            ?>
                                        </div>
                                    </div>
                                    <div class="page-stats-pill">
                                        <span>访问: <strong><?php echo formatNumber($page['views']); ?></strong></span>
                                        <span>独立: <strong><?php echo formatNumber($page['unique_views']); ?></strong></span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        
                        <!-- 查看更多按钮 -->
                        <div style="text-align: center; margin-top: 15px;">
                            <button id="viewMorePages" class="btn-view-more">
                                查看更多 >>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- 设备分析区域 -->
            <div class="analysis-section">
                <h2>设备分析</h2>
                <div class="analysis-grid">
                    <div class="analysis-card">
                        <h3>设备类型</h3>
                        <div class="stats-list">
                            <?php if (!empty($deviceStats)): ?>
                                <?php foreach ($deviceStats as $device): ?>
                                    <div class="stat-item">
                                        <span class="stat-label"><?php echo htmlspecialchars(ucfirst($device['device_type'])); ?></span>
                                        <span class="stat-value"><?php echo formatNumber($device['count']); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="no-data">暂无数据</div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="analysis-card">
                        <h3>浏览器</h3>
                        <div class="stats-list">
                            <?php if (!empty($browserStats)): ?>
                                <?php foreach ($browserStats as $browser): ?>
                                    <div class="stat-item">
                                        <span class="stat-label"><?php echo htmlspecialchars($browser['browser']); ?></span>
                                        <span class="stat-value"><?php echo formatNumber($browser['count']); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="no-data">暂无数据</div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="analysis-card">
                        <h3>操作系统</h3>
                        <div class="stats-list">
                            <?php if (!empty($osStats)): ?>
                                <?php foreach ($osStats as $os): ?>
                                    <div class="stat-item">
                                        <span class="stat-label"><?php echo htmlspecialchars($os['os']); ?></span>
                                        <span class="stat-value"><?php echo formatNumber($os['count']); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="no-data">暂无数据</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- 项目统计区域（仅在总览时显示） -->
            <?php if ($projectStats): ?>
            <div class="projects-section">
                <h2>项目访问量排行</h2>
                <div class="projects-list">
                    <?php foreach ($projectStats as $index => $project): ?>
                        <div class="project-item">
                            <div class="project-rank"><?php echo $index + 1; ?></div>
                            <div class="project-info">
                                <div class="project-name"><?php echo htmlspecialchars($project['name']); ?></div>
                                <div class="project-code"><?php echo htmlspecialchars($project['tracking_code']); ?></div>
                                <?php if ($project['description']): ?>
                                    <div class="project-desc"><?php echo htmlspecialchars($project['description']); ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="project-stats">
                                <div class="stat-group">
                                    <span class="stat-number"><?php echo formatNumber($project['total_visits']); ?></span>
                                    <span class="stat-label">访问量</span>
                                </div>
                                <div class="stat-group">
                                    <span class="stat-number"><?php echo formatNumber($project['unique_visitors']); ?></span>
                                    <span class="stat-label">独立访客</span>
                                </div>
                            </div>
                            <div class="project-time">
                                <?php if ($project['last_visit']): ?>
                                    <div class="last-visit">最后访问: <?php echo date('m-d H:i', strtotime($project['last_visit'])); ?></div>
                                <?php endif; ?>
                                <div class="created-time">创建: <?php echo date('Y-m-d', strtotime($project['created_at'])); ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
        </div>
    </div>
    
    <script>
        let currentChartType = 'hourly';
        let currentMetric = 'pv';
        
        function toggleChart(type) {
            currentChartType = type;
            updateChart();
        }
        
        function toggleMetric(metric) {
            currentMetric = metric;
            updateChart();
        }
        
        function updateChart() {
            const hourlyChart = document.getElementById('hourlyChart');
            const hourlyUVChart = document.getElementById('hourlyUVChart');
            const dailyChart = document.getElementById('dailyChart');
            const dailyUVChart = document.getElementById('dailyUVChart');
            const chartTitle = document.getElementById('chartTitle');
            const hourlyBtn = document.getElementById('hourlyBtn');
            const dailyBtn = document.getElementById('dailyBtn');
            const pvBtn = document.getElementById('pvBtn');
            const uvBtn = document.getElementById('uvBtn');
            
            // 隐藏所有图表
            hourlyChart.style.display = 'none';
            hourlyUVChart.style.display = 'none';
            dailyChart.style.display = 'none';
            dailyUVChart.style.display = 'none';
            
            // 更新按钮状态
            hourlyBtn.classList.remove('active');
            dailyBtn.classList.remove('active');
            pvBtn.classList.remove('active');
            uvBtn.classList.remove('active');
            
            // 显示对应的图表
            if (currentChartType === 'hourly') {
                if (currentMetric === 'pv') {
                    hourlyChart.style.display = 'flex';
                    chartTitle.textContent = '24小时PV趋势';
                } else {
                    hourlyUVChart.style.display = 'flex';
                    chartTitle.textContent = '24小时UV趋势';
                }
                hourlyBtn.classList.add('active');
            } else if (currentChartType === 'daily') {
                if (currentMetric === 'pv') {
                    dailyChart.style.display = 'flex';
                    chartTitle.textContent = '30日PV趋势';
                } else {
                    dailyUVChart.style.display = 'flex';
                    chartTitle.textContent = '30日UV趋势';
                }
                dailyBtn.classList.add('active');
            }
            
            // 更新指标按钮状态
            if (currentMetric === 'pv') {
                pvBtn.classList.add('active');
            } else {
                uvBtn.classList.add('active');
            }
        }
    </script>
    
    <!-- 记录详情弹窗 -->
    <div id="recordModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">记录详情</h3>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <div class="modal-body">
                <div id="modalContent">
                    <div class="loading">加载中...</div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- 热门页面弹窗 -->
    <div id="topPagesModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="topPagesModalTitle">热门页面</h3>
                <span class="close" onclick="closeTopPagesModal()">&times;</span>
            </div>
            <div class="modal-body">
                <div id="topPagesModalContent">
                    <div class="loading">加载中...</div>
                </div>
            </div>
        </div>
    </div>
    

    
    <script>
        // 点击统计数字显示详情
        document.addEventListener('DOMContentLoaded', function() {
            const clickableStats = document.querySelectorAll('.clickable-stat');
            clickableStats.forEach(stat => {
                stat.addEventListener('click', function() {
                    const type = this.getAttribute('data-type');
                    const value = this.getAttribute('data-value');
                    showRecordModal(type, value);
                });
            });
        });
        
        function showRecordModal(type, value) {
            const modal = document.getElementById('recordModal');
            const modalTitle = document.getElementById('modalTitle');
            const modalContent = document.getElementById('modalContent');
            
            // 设置标题
            let title = '';
            if (type === 'source') {
                const sourceNames = {
                    'direct': '直接访问',
                    'internal': '内部链接',
                    'search': '搜索引擎',
                    'social': '社交媒体',
                    'referral': '外部链接'
                };
                title = sourceNames[value] || value;
            } else if (type === 'country') {
                title = value;
            }
            modalTitle.textContent = title + ' - 访问记录';
            
            // 显示加载状态
            modalContent.innerHTML = '<div class="loading">加载中...</div>';
            modal.style.display = 'block';
            
            // 加载数据
            loadRecordData(type, value, 1);
        }
        
        function loadRecordData(type, value, page) {
            const modalContent = document.getElementById('modalContent');
            
            fetch('visits.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'get_visits',
                    page: page,
                    limit: 10,
                    filter_type: type,
                    filter_value: value
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.code === 0) {
                    displayRecordData(data.data, data.count, page, type, value);
                } else {
                    modalContent.innerHTML = '<div class="loading">加载失败</div>';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                modalContent.innerHTML = '<div class="loading">加载失败</div>';
            });
        }
        
        function displayRecordData(records, total, currentPage, type, value) {
            const modalContent = document.getElementById('modalContent');
            
            let html = '<table class="record-table">';
            html += '<thead><tr>';
            html += '<th>时间</th><th>IP地址</th><th>页面标题</th><th>页面地址</th><th>设备</th><th>状态</th>';
            html += '</tr></thead><tbody>';
            
            records.forEach(record => {
                const visitTime = new Date(record.visit_time).toLocaleString('zh-CN');
                const deviceInfo = (record.device_type || '未知') + ', ' + (record.os || '未知') + ', ' + (record.browser || '未知');
                const status = record.is_bot == 1 || record.is_bot === '1' ? 
                    '<span style="background: #ffc107; color: #000; padding: 2px 6px; border-radius: 3px; font-size: 11px;">爬虫</span>' :
                    '<span style="background: #28a745; color: #fff; padding: 2px 6px; border-radius: 3px; font-size: 11px;">正常</span>';
                
                html += '<tr>';
                html += '<td>' + visitTime + '</td>';
                html += '<td>' + record.ip_address + '</td>';
                html += '<td title="' + (record.page_title || '无标题') + '">' + (record.page_title || '无标题') + '</td>';
                html += '<td title="' + record.page_url + '">' + record.page_url + '</td>';
                html += '<td>' + deviceInfo + '</td>';
                html += '<td>' + status + '</td>';
                html += '</tr>';
            });
            
            html += '</tbody></table>';
            
            // 添加分页
            const totalPages = Math.ceil(total / 10);
            if (totalPages > 1) {
                html += '<div class="pagination">';
                
                // 上一页
                if (currentPage > 1) {
                    html += '<button onclick="loadRecordData(\'' + type + '\', \'' + value + '\', ' + (currentPage - 1) + ')">上一页</button>';
                }
                
                // 页码
                const startPage = Math.max(1, currentPage - 2);
                const endPage = Math.min(totalPages, currentPage + 2);
                
                for (let i = startPage; i <= endPage; i++) {
                    const activeClass = i === currentPage ? 'active' : '';
                    html += '<button class="' + activeClass + '" onclick="loadRecordData(\'' + type + '\', \'' + value + '\', ' + i + ')">' + i + '</button>';
                }
                
                // 下一页
                if (currentPage < totalPages) {
                    html += '<button onclick="loadRecordData(\'' + type + '\', \'' + value + '\', ' + (currentPage + 1) + ')">下一页</button>';
                }
                
                html += '</div>';
            }
            
            modalContent.innerHTML = html;
        }
        
        function closeModal() {
            document.getElementById('recordModal').style.display = 'none';
        }
        
        // 点击弹窗外部关闭
        window.onclick = function(event) {
            const modal = document.getElementById('recordModal');
            const topPagesModal = document.getElementById('topPagesModal');
            if (event.target === modal) {
                closeModal();
            } else if (event.target === topPagesModal) {
                closeTopPagesModal();
            }
        }
        
        // 时间筛选功能
        document.addEventListener('DOMContentLoaded', function() {
            // 设置当前选中的时间筛选
            const currentTimeFilter = getUrlParameter('time_filter') || 'total';
            const currentStartDate = getUrlParameter('start_date');
            const currentEndDate = getUrlParameter('end_date');
            
            // 设置选中的单选按钮
            const selectedRadio = document.querySelector(`input[name="timeFilter"][value="${currentTimeFilter}"]`);
            if (selectedRadio) {
                selectedRadio.checked = true;
            }
            
            // 如果是自定义时间，显示日期选择器
            if (currentTimeFilter === 'custom') {
                document.getElementById('customTimeRange').style.display = 'flex';
                if (currentStartDate) document.getElementById('startDate').value = currentStartDate;
                if (currentEndDate) document.getElementById('endDate').value = currentEndDate;
            }
            
            // 时间筛选单选按钮事件
            const timeFilterRadios = document.querySelectorAll('input[name="timeFilter"]');
            timeFilterRadios.forEach(radio => {
                radio.addEventListener('change', function() {
                    if (this.value === 'custom') {
                        document.getElementById('customTimeRange').style.display = 'flex';
                    } else {
                        document.getElementById('customTimeRange').style.display = 'none';
                        applyTimeFilter(this.value);
                    }
                });
            });
            
            // 设置默认日期（如果没有设置的话）
            if (!currentStartDate) {
                const today = new Date().toISOString().split('T')[0];
                const yesterday = new Date(Date.now() - 24 * 60 * 60 * 1000).toISOString().split('T')[0];
                document.getElementById('startDate').value = yesterday;
                document.getElementById('endDate').value = today;
            }
        });
        
        // 应用时间筛选
        function applyTimeFilter(timeFilter) {
            const projectId = getUrlParameter('project_id') || '';
            const url = `index.php?time_filter=${timeFilter}${projectId ? '&project_id=' + projectId : ''}`;
            window.location.href = url;
        }
        
        // 应用自定义时间筛选
        function applyCustomTimeFilter() {
            const startDate = document.getElementById('startDate').value;
            const endDate = document.getElementById('endDate').value;
            
            if (!startDate || !endDate) {
                alert('请选择开始时间和结束时间');
                return;
            }
            
            if (new Date(startDate) > new Date(endDate)) {
                alert('开始时间不能晚于结束时间');
                return;
            }
            
            const projectId = getUrlParameter('project_id') || '';
            const url = `index.php?time_filter=custom&start_date=${startDate}&end_date=${endDate}${projectId ? '&project_id=' + projectId : ''}`;
            window.location.href = url;
        }
        
        // 获取URL参数
        function getUrlParameter(name) {
            const urlParams = new URLSearchParams(window.location.search);
            return urlParams.get(name);
        }

        // 热门页面弹窗功能
        document.addEventListener('DOMContentLoaded', function() {
            const viewMorePagesBtn = document.getElementById('viewMorePages');
            if (viewMorePagesBtn) {
                viewMorePagesBtn.addEventListener('click', function() {
                    showTopPagesModal();
                });
            }
        });
        
        function showTopPagesModal() {
            const modal = document.getElementById('topPagesModal');
            const modalContent = document.getElementById('topPagesModalContent');
            
            // 显示加载状态
            modalContent.innerHTML = '<div class="loading">加载中...</div>';
            modal.style.display = 'block';
            
            // 加载数据
            loadTopPagesData(1);
        }
        
        function loadTopPagesData(page) {
            const modalContent = document.getElementById('topPagesModalContent');
            
            fetch('visits.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'get_top_pages',
                    page: page,
                    limit: 20
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.code === 0) {
                    displayTopPagesData(data.data, data.count, page);
                } else {
                    modalContent.innerHTML = '<div class="loading">加载失败</div>';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                modalContent.innerHTML = '<div class="loading">加载失败</div>';
            });
        }
        
        function displayTopPagesData(pages, total, currentPage) {
            const modalContent = document.getElementById('topPagesModalContent');
            
            let html = '<div class="top-pages-list">';
            
            pages.forEach((page, index) => {
                const rank = (currentPage - 1) * 20 + index + 1;
                const title = page.page_title || '无标题';
                const url = page.page_url;
                
                html += '<div class="top-page-item">';
                html += '<div class="page-rank">#' + rank + '</div>';
                html += '<div class="page-info">';
                html += '<div class="page-title" title="' + title + '">' + title + '</div>';
                html += '<div class="page-url" title="' + url + '">' + url + '</div>';
                html += '</div>';
                html += '<div class="page-stats">';
                html += '<div class="stat-item"><span class="stat-label">访问:</span><span class="stat-value">' + page.views + '</span></div>';
                html += '<div class="stat-item"><span class="stat-label">独立:</span><span class="stat-value">' + page.unique_views + '</span></div>';
                html += '</div>';
                html += '</div>';
            });
            
            html += '</div>';
            
            // 添加分页
            const totalPages = Math.ceil(total / 20);
            if (totalPages > 1) {
                html += '<div class="pagination">';
                
                // 上一页
                if (currentPage > 1) {
                    html += '<button onclick="loadTopPagesData(' + (currentPage - 1) + ')">上一页</button>';
                }
                
                // 页码
                const startPage = Math.max(1, currentPage - 2);
                const endPage = Math.min(totalPages, currentPage + 2);
                
                for (let i = startPage; i <= endPage; i++) {
                    const activeClass = i === currentPage ? 'active' : '';
                    html += '<button class="' + activeClass + '" onclick="loadTopPagesData(' + i + ')">' + i + '</button>';
                }
                
                // 下一页
                if (currentPage < totalPages) {
                    html += '<button onclick="loadTopPagesData(' + (currentPage + 1) + ')">下一页</button>';
                }
                
                html += '</div>';
            }
            
            modalContent.innerHTML = html;
        }
        
        function closeTopPagesModal() {
            document.getElementById('topPagesModal').style.display = 'none';
        }
    </script>
</body>
</html>
