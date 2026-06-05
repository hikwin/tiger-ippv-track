<?php
/**
 * 访问记录管理页面
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

// 处理AJAX请求
if (isAjax() || (isset($_POST['action']) && !empty($_POST['action']))) {
    $action = $_POST['action'] ?? '';
    
    // 不需要登录验证的接口
    if ($action === 'get_top_pages') {
        $page = (int)($_POST['page'] ?? 1);
        $limit = (int)($_POST['limit'] ?? 20);
        $projectId = $_POST['project_id'] ?? '';
        
        $topPages = getTopPagesList($page, $limit, $projectId);
        $total = getTopPagesCount($projectId);
        
        jsonResponse([
            'code' => 0,
            'msg' => 'success',
            'count' => $total,
            'data' => $topPages
        ]);
    }
    
    // 需要登录验证的接口
    if (!isset($_SESSION['admin_id'])) {
        // 判断是通过根目录访问还是直接访问admin目录
        $isFromRoot = strpos($_SERVER['REQUEST_URI'], 'index.php?action=admin') !== false;
        $loginPath = $isFromRoot ? 'admin/login.php' : 'login.php';
        header('Location: ' . $loginPath);
        exit;
    }
    
    switch ($action) {
        case 'get_visits':
            $page = (int)($_POST['page'] ?? 1);
            $limit = (int)($_POST['limit'] ?? 20);
            $search = $_POST['search'] ?? '';
            $dateFrom = $_POST['date_from'] ?? '';
            $dateTo = $_POST['date_to'] ?? '';
            $projectId = $_POST['project_id'] ?? '';
            $filterType = $_POST['filter_type'] ?? '';
            $filterValue = $_POST['filter_value'] ?? '';
            $status = $_POST['status'] ?? '';
            $type = $_POST['type'] ?? '';
            $source = $_POST['source'] ?? '';
            
            $visits = getVisitsList($page, $limit, $search, $dateFrom, $dateTo, $projectId, $filterType, $filterValue, $status, $type, $source);
            $total = getVisitsCount($search, $dateFrom, $dateTo, $projectId, $filterType, $filterValue, $status, $type, $source);
            
            jsonResponse([
                'code' => 0,
                'msg' => 'success',
                'count' => $total,
                'data' => $visits
            ]);
            break;
            
        case 'delete_visit':
            $visitId = $_POST['visit_id'] ?? '';
            if (empty($visitId)) {
                jsonResponse(['status' => 'error', 'message' => '缺少访问记录ID'], 400);
            }
            
            $result = $db->delete('visits', 'id = ?', [$visitId]);
            if ($result) {
                jsonResponse(['status' => 'success', 'message' => '访问记录删除成功']);
            } else {
                jsonResponse(['status' => 'error', 'message' => '删除失败'], 500);
            }
            break;
            
        case 'batch_delete_visits':
            $visitIds = $_POST['visit_ids'] ?? '';
            if (empty($visitIds)) {
                jsonResponse(['status' => 'error', 'message' => '请选择要删除的记录'], 400);
            }
            
            // 解析ID数组
            $ids = is_string($visitIds) ? explode(',', $visitIds) : $visitIds;
            $ids = array_filter($ids, 'is_numeric');
            
            if (empty($ids)) {
                jsonResponse(['status' => 'error', 'message' => '无效的记录ID'], 400);
            }
            
            // 构建删除条件
            $placeholders = str_repeat('?,', count($ids) - 1) . '?';
            $result = $db->delete('visits', "id IN ({$placeholders})", $ids);
            
            if ($result) {
                jsonResponse(['status' => 'success', 'message' => '批量删除成功，共删除 ' . count($ids) . ' 条记录']);
            } else {
                jsonResponse(['status' => 'error', 'message' => '批量删除失败'], 500);
            }
            break;
            
        case 'get_projects':
            $projects = getProjectsList();
            jsonResponse([
                'status' => 'success',
                'data' => $projects
            ]);
            break;
            
        case 'export_visits':
            $search = $_POST['search'] ?? '';
            $dateFrom = $_POST['date_from'] ?? '';
            $dateTo = $_POST['date_to'] ?? '';
            $projectId = $_POST['project_id'] ?? '';
            
            $visits = getVisitsList(1, 10000, $search, $dateFrom, $dateTo, $projectId);
            exportVisitsToCSV($visits);
            break;
            
        default:
            jsonResponse(['status' => 'error', 'message' => '无效的操作'], 400);
    }
}

/**
 * 获取访问记录列表
 */
function getVisitsList($page = 1, $limit = 20, $search = '', $dateFrom = '', $dateTo = '', $projectId = '', $filterType = '', $filterValue = '', $status = '', $type = '', $source = '') {
    global $db;
    
    $offset = ($page - 1) * $limit;
    $where = [];
    $params = [];
    
    // 搜索条件
    if (!empty($search)) {
        $where[] = "(v.page_url LIKE ? OR v.page_title LIKE ? OR v.ip_address LIKE ? OR v.user_agent LIKE ? OR v.country LIKE ? OR v.city LIKE ?)";
        $searchTerm = "%{$search}%";
        $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    }
    
    // 日期范围
    if (!empty($dateFrom)) {
        $where[] = "v.visit_time >= ?";
        $params[] = $dateFrom . ' 00:00:00';
    }
    
    if (!empty($dateTo)) {
        $where[] = "v.visit_time <= ?";
        $params[] = $dateTo . ' 23:59:59';
    }
    
    // 项目筛选
    if (!empty($projectId)) {
        $where[] = "v.project_id = ?";
        $params[] = $projectId;
    }
    
    // 状态筛选
    if (!empty($status)) {
        if ($status === 'normal') {
            $where[] = "v.is_bot = 0";
        } elseif ($status === 'bot') {
            $where[] = "v.is_bot = 1";
        }
    }
    
    // 访问类型筛选
    if (!empty($type)) {
        if ($type === 'desktop') {
            $where[] = "v.device_type = 'desktop'";
        } elseif ($type === 'mobile') {
            $where[] = "v.device_type = 'mobile'";
        } elseif ($type === 'tablet') {
            $where[] = "v.device_type = 'tablet'";
        }
    }
    
    // 来源筛选
    if (!empty($source)) {
        $where[] = "v.source_type = ?";
        $params[] = $source;
    }
    
    // 来源过滤
    if (!empty($filterType) && !empty($filterValue)) {
        if ($filterType === 'source') {
            $where[] = "v.source_type = ?";
            $params[] = $filterValue;
        } elseif ($filterType === 'country') {
            // 根据国家过滤
            $where[] = "v.country = ?";
            $params[] = $filterValue;
        }
    }
    
    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    
    $sql = "SELECT v.*, p.name as project_name 
            FROM visits v 
            LEFT JOIN projects p ON v.project_id = p.id
            {$whereClause}
            ORDER BY v.visit_time DESC 
            LIMIT {$limit} OFFSET {$offset}";
    
    return $db->fetchAll($sql, $params);
}

/**
 * 获取访问记录总数
 */
function getVisitsCount($search = '', $dateFrom = '', $dateTo = '', $projectId = '', $filterType = '', $filterValue = '', $status = '', $type = '', $source = '') {
    global $db;
    
    $where = [];
    $params = [];
    
    // 搜索条件
    if (!empty($search)) {
        $where[] = "(v.page_url LIKE ? OR v.page_title LIKE ? OR v.ip_address LIKE ? OR v.user_agent LIKE ? OR v.country LIKE ? OR v.city LIKE ?)";
        $searchTerm = "%{$search}%";
        $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    }
    
    // 日期范围
    if (!empty($dateFrom)) {
        $where[] = "v.visit_time >= ?";
        $params[] = $dateFrom . ' 00:00:00';
    }
    
    if (!empty($dateTo)) {
        $where[] = "v.visit_time <= ?";
        $params[] = $dateTo . ' 23:59:59';
    }
    
    // 项目筛选
    if (!empty($projectId)) {
        $where[] = "v.project_id = ?";
        $params[] = $projectId;
    }
    
    // 状态筛选
    if (!empty($status)) {
        if ($status === 'normal') {
            $where[] = "v.is_bot = 0";
        } elseif ($status === 'bot') {
            $where[] = "v.is_bot = 1";
        }
    }
    
    // 访问类型筛选
    if (!empty($type)) {
        if ($type === 'desktop') {
            $where[] = "v.device_type = 'desktop'";
        } elseif ($type === 'mobile') {
            $where[] = "v.device_type = 'mobile'";
        } elseif ($type === 'tablet') {
            $where[] = "v.device_type = 'tablet'";
        }
    }
    
    // 来源筛选
    if (!empty($source)) {
        $where[] = "v.source_type = ?";
        $params[] = $source;
    }
    
    // 来源过滤
    if (!empty($filterType) && !empty($filterValue)) {
        if ($filterType === 'source') {
            $where[] = "v.source_type = ?";
            $params[] = $filterValue;
        } elseif ($filterType === 'country') {
            // 根据国家过滤
            $where[] = "v.country = ?";
            $params[] = $filterValue;
        }
    }
    
    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    
    $sql = "SELECT COUNT(*) as count 
            FROM visits v 
            {$whereClause}";
    $result = $db->fetchOne($sql, $params);
    
    return $result['count'] ?? 0;
}

/**
 * 获取热门页面列表（分页）
 */
function getTopPagesList($page = 1, $limit = 20, $projectId = '') {
    global $db;
    
    $offset = ($page - 1) * $limit;
    $where = [];
    $params = [];
    
    // 项目筛选
    if (!empty($projectId)) {
        $where[] = "project_id = ?";
        $params[] = $projectId;
    }
    
    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    
    $sql = "SELECT page_url, page_title, COUNT(*) as views, 
                   COUNT(DISTINCT ip_address) as unique_views
            FROM visits 
            {$whereClause}
            GROUP BY page_url, page_title
            ORDER BY views DESC, unique_views DESC
            LIMIT {$limit} OFFSET {$offset}";
    
    return $db->fetchAll($sql, $params);
}

/**
 * 获取热门页面总数
 */
function getTopPagesCount($projectId = '') {
    global $db;
    
    $where = [];
    $params = [];
    
    // 项目筛选
    if (!empty($projectId)) {
        $where[] = "project_id = ?";
        $params[] = $projectId;
    }
    
    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    
    $sql = "SELECT COUNT(*) as count FROM (
                SELECT 1 FROM visits 
                {$whereClause} 
                GROUP BY page_url, page_title
            )";
    
    $result = $db->fetchOne($sql, $params);
    return $result['count'] ?? 0;
}

/**
 * 获取项目列表
 */
function getProjectsList() {
    global $db;
    
    $sql = "SELECT id, name FROM projects WHERE is_active = 1 ORDER BY name";
    return $db->fetchAll($sql);
}

/**
 * 导出访问记录为CSV
 */
function exportVisitsToCSV($visits) {
    $filename = 'visits_' . date('Y-m-d_H-i-s') . '.csv';
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    
    // 输出BOM以支持中文
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // 输出表头
    fputcsv($output, [
        'ID', '项目', '页面URL', '页面标题', 'IP地址', 
        '地理位置', '设备信息', '用户代理', '来源', '来源Host', '来源类型', 
        '状态', '访问时间'
    ]);
    
    // 输出数据
    foreach ($visits as $visit) {
        // 处理地理位置
        $location = '未知';
        if ($visit['country'] && $visit['country'] !== '未知') {
            if ($visit['country'] === '局域网') {
                $location = '局域网';
            } else {
                $location = $visit['country'] . ($visit['city'] ? ', ' . $visit['city'] : '');
            }
        }
        
        // 处理设备信息
        $deviceInfo = ($visit['device_type'] ?: '未知') . ', ' . 
                     ($visit['os'] ?: '未知') . ', ' . 
                     ($visit['browser'] ?: '未知');
        
        // 处理来源类型
        $sourceType = getSourceTypeName($visit['source_type']);
        
        // 处理状态
        $status = ($visit['is_bot'] == 1 || $visit['is_bot'] === '1') ? '爬虫' : '正常';
        
        fputcsv($output, [
            $visit['id'],
            $visit['project_name'] ?: '未知',
            $visit['page_url'],
            $visit['page_title'],
            $visit['ip_address'],
            $location,
            $deviceInfo,
            $visit['user_agent'],
            $visit['referer'],
            $visit['referer_host'] ?: '-',
            $sourceType,
            $status,
            $visit['visit_time']
        ]);
    }
    
    fclose($output);
    exit;
}

?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>访问记录 - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/layui@2.8.18/dist/css/layui.css">
    <script src="https://cdn.jsdelivr.net/npm/layui@2.8.18/dist/layui.js"></script>
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
        
        
        
        .btn-sm {
            padding: 4px 8px;
            font-size: 12px;
        }
        
        .search-form {
            background: #fff;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .search-form .form-row {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
            align-items: end;
        }
        
        .search-form .form-group {
            flex: 1;
        }
        
        .search-form .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        
        .search-form input, .search-form select {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .search-form .btn-group {
            display: flex;
            gap: 10px;
        }
        
        /* 弹窗样式 */
        .modal {
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background-color: #fefefe;
            margin: 15% auto;
            padding: 0;
            border: none;
            border-radius: 8px;
            width: 400px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
        }
        
        .modal-header {
            padding: 20px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-header h3 {
            margin: 0;
            color: #333;
        }
        
        .close {
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            line-height: 1;
        }
        
        .close:hover {
            color: #000;
        }
        
        .modal-body {
            padding: 20px;
        }
        
        .modal-footer {
            padding: 20px;
            border-top: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-footer .btn-group {
            display: flex;
            gap: 10px;
        }
        
        .checkbox-group {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .checkbox-item {
            display: flex;
            align-items: center;
            cursor: pointer;
            font-size: 14px;
            color: #333;
        }
        
        .checkbox-item input[type="checkbox"] {
            margin-right: 10px;
            width: 16px;
            height: 16px;
            cursor: pointer;
        }
        
        .checkmark {
            margin-left: 5px;
        }
        
        /* 清除按钮样式 */
        .btn-warning {
            background-color: #6c757d !important;
            border-color: #6c757d !important;
            color: white !important;
        }
        
        .btn-warning:hover {
            background-color: #5a6268 !important;
            border-color: #545b62 !important;
        }
        
        .visits-table {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .table-header {
            background: #f8f9fa;
            padding: 15px 20px;
            border-bottom: 1px solid #dee2e6;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .table-title {
            font-size: 18px;
            font-weight: 600;
            color: #333;
        }
        
        .table-actions {
            display: flex;
            gap: 10px;
        }
        
        .table-container {
            overflow-x: auto;
        }
        
        .visits-table table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .visits-table th,
        .visits-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #dee2e6;
        }
        
        .visits-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #495057;
        }
        
        .visits-table tbody tr:hover {
            background: #f8f9fa;
        }
        
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .badge-bot {
            background: #ffc107;
            color: #000;
        }
        
        .badge-human {
            background: #28a745;
            color: #fff;
        }
        
        .badge-direct {
            background: #6c757d;
            color: #fff;
        }
        
        .badge-search {
            background: #007bff;
            color: #fff;
        }
        
        .badge-external {
            background: #17a2b8;
            color: #fff;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
            gap: 10px;
        }
        
        .pagination button {
            padding: 8px 12px;
            border: 1px solid #ddd;
            background: #fff;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .pagination button:hover:not(:disabled) {
            background: #f8f9fa;
        }
        
        .pagination button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .pagination .current {
            background: #007bff;
            color: #fff;
            border-color: #007bff;
        }
        
        .loading {
            text-align: center;
            padding: 40px;
            color: #666;
        }
        
        .empty {
            text-align: center;
            padding: 40px;
            color: #666;
        }
        
        .user-agent {
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .location-info {
            font-size: 12px;
            color: #666;
            min-width: 80px;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="main-content">
        <div class="container">
            <div class="search-form">
                <form id="searchForm">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="search">搜索关键词</label>
                            <input type="text" id="search" name="search" placeholder="搜索URL、标题、IP地址等">
                        </div>
                        <div class="form-group">
                            <label for="projectId">项目筛选</label>
                            <select id="projectId" name="projectId">
                                <option value="">全部项目</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="dateFrom">开始日期</label>
                            <input type="date" id="dateFrom" name="dateFrom">
                        </div>
                        <div class="form-group">
                            <label for="dateTo">结束日期</label>
                            <input type="date" id="dateTo" name="dateTo">
                        </div>
                        <div class="form-group">
                            <div class="btn-group">
                                <button type="submit" class="btn btn-primary">搜索</button>
                                <button type="button" id="resetBtn" class="btn btn-secondary">重置</button>
                                <button type="button" id="settingsBtn" class="btn btn-info">设置</button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- 动态搜索字段容器 -->
                    <div class="form-row" id="dynamicSearchFields" style="display: none;">
                        <div class="form-group" id="statusField" style="display: none;">
                            <label for="status">状态筛选</label>
                            <select id="status" name="status">
                                <option value="">全部状态</option>
                                <option value="normal">正常</option>
                                <option value="bot">爬虫</option>
                            </select>
                        </div>
                        <div class="form-group" id="typeField" style="display: none;">
                            <label for="type">设备类型</label>
                            <select id="type" name="type">
                                <option value="">全部设备</option>
                                <option value="desktop">桌面端</option>
                                <option value="mobile">移动端</option>
                                <option value="tablet">平板端</option>
                            </select>
                        </div>
                        <div class="form-group" id="sourceField" style="display: none;">
                            <label for="source">来源筛选</label>
                            <select id="source" name="source">
                                <option value="">全部来源</option>
                                <option value="direct">直接访问</option>
                                <option value="internal">内部链接</option>
                                <option value="search">搜索引擎</option>
                                <option value="social">社交媒体</option>
                                <option value="referral">外部链接</option>
                            </select>
                        </div>
                    </div>
                </form>
            </div>
            
            <div class="visits-table">
                
                <div class="table-container">
                    <script type="text/html" id="toolbarDemo">
                        <div class="layui-btn-container">
                            <button class="layui-btn layui-btn-sm" lay-event="exportData">导出全部数据</button>
                            <button class="layui-btn layui-btn-danger layui-btn-sm" lay-event="batchDelete">批量删除</button>
                            <button class="layui-btn layui-btn-normal layui-btn-sm" lay-event="refresh">刷新</button>
                        </div>
                    </script>
                    <table id="visitsTable" lay-filter="visitsTable"></table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- 设置弹窗 -->
    <div id="settingsModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>搜索字段设置</h3>
                <span class="close">&times;</span>
            </div>
            <div class="modal-body">
                <div class="checkbox-group">
                    <label class="checkbox-item">
                        <input type="checkbox" id="enableStatus" value="status">
                        <span class="checkmark"></span>
                        状态筛选
                    </label>
                    <label class="checkbox-item">
                        <input type="checkbox" id="enableType" value="type">
                        <span class="checkmark"></span>
                        访问类型
                    </label>
                    <label class="checkbox-item">
                        <input type="checkbox" id="enableSource" value="source">
                        <span class="checkmark"></span>
                        来源筛选
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" id="clearSettings" class="btn btn-warning">清除所选</button>
                <div class="btn-group">
                    <button type="button" id="saveSettings" class="btn btn-primary">保存设置</button>
                    <button type="button" id="cancelSettings" class="btn btn-secondary">取消</button>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // 搜索字段设置管理
        const SearchSettings = {
            // 从localStorage加载设置
            loadSettings: function() {
                const saved = localStorage.getItem('visits_search_settings');
                if (saved) {
                    return JSON.parse(saved);
                }
                return {
                    status: false,
                    type: false,
                    source: false
                };
            },
            
            // 保存设置到localStorage
            saveSettings: function(settings) {
                localStorage.setItem('visits_search_settings', JSON.stringify(settings));
            },
            
            // 应用设置到页面
            applySettings: function(settings) {
                // 显示/隐藏动态搜索字段容器
                const container = document.getElementById('dynamicSearchFields');
                const hasEnabledFields = Object.values(settings).some(enabled => enabled);
                container.style.display = hasEnabledFields ? 'flex' : 'none';
                
                // 显示/隐藏具体字段
                document.getElementById('statusField').style.display = settings.status ? 'block' : 'none';
                document.getElementById('typeField').style.display = settings.type ? 'block' : 'none';
                document.getElementById('sourceField').style.display = settings.source ? 'block' : 'none';
            },
            
            // 清除设置
            clearSettings: function() {
                // 清除localStorage中的设置
                localStorage.removeItem('visits_search_settings');
                
                // 重置为默认设置
                const defaultSettings = {
                    status: false,
                    type: false,
                    source: false
                };
                
                // 应用默认设置
                this.applySettings(defaultSettings);
                
                // 重置弹窗中的复选框状态
                document.getElementById('enableStatus').checked = false;
                document.getElementById('enableType').checked = false;
                document.getElementById('enableSource').checked = false;
                
                // 清空搜索表单中的动态字段值
                document.getElementById('status').value = '';
                document.getElementById('type').value = '';
                document.getElementById('source').value = '';
                
                return defaultSettings;
            },
            
            // 初始化设置
            init: function() {
                const settings = this.loadSettings();
                this.applySettings(settings);
                
                // 设置弹窗中的复选框状态
                document.getElementById('enableStatus').checked = settings.status;
                document.getElementById('enableType').checked = settings.type;
                document.getElementById('enableSource').checked = settings.source;
            }
        };
        
        // 弹窗管理
        const ModalManager = {
            show: function() {
                document.getElementById('settingsModal').style.display = 'block';
            },
            
            hide: function() {
                document.getElementById('settingsModal').style.display = 'none';
            },
            
            init: function() {
                // 设置按钮点击事件
                document.getElementById('settingsBtn').addEventListener('click', this.show);
                
                // 关闭按钮点击事件
                document.querySelector('.close').addEventListener('click', this.hide);
                document.getElementById('cancelSettings').addEventListener('click', this.hide);
                
                // 点击弹窗外部关闭
                document.getElementById('settingsModal').addEventListener('click', function(e) {
                    if (e.target === this) {
                        ModalManager.hide();
                    }
                });
                
                // 清除设置按钮点击事件
                document.getElementById('clearSettings').addEventListener('click', function() {
                    SearchSettings.clearSettings();
                    ModalManager.hide();
                    
                    // 重新加载表格以应用默认设置
                    if (typeof reloadTable === 'function') {
                        reloadTable();
                    }
                });
                
                // 保存设置按钮点击事件
                document.getElementById('saveSettings').addEventListener('click', function() {
                    const settings = {
                        status: document.getElementById('enableStatus').checked,
                        type: document.getElementById('enableType').checked,
                        source: document.getElementById('enableSource').checked
                    };
                    
                    SearchSettings.saveSettings(settings);
                    SearchSettings.applySettings(settings);
                    ModalManager.hide();
                });
            }
        };
        
        // 页面加载完成后初始化
        document.addEventListener('DOMContentLoaded', function() {
            SearchSettings.init();
            ModalManager.init();
        });
        
        layui.use(['table', 'layer', 'form'], function(){
            var table = layui.table;
            var layer = layui.layer;
            var form = layui.form;
            
            // 渲染表格
            table.render({
                elem: '#visitsTable',
                url: 'visits.php',
                method: 'POST',
                where: {
                    action: 'get_visits'
                },
                page: true,
                limit: 20,
                limits: [10, 20, 50, 100],
                toolbar: '#toolbarDemo',
                defaultToolbar: ['filter', 'exports', 'print'],
                cols: [[
                    {type: 'checkbox', fixed: 'left'},
                    {field: 'id', title: 'ID', width: 60, sort: true, export: true},
                    {field: 'project_name', title: '项目', width: 120, templet: function(d){
                        return d.project_name || '未知';
                    }, export: true, filter: true},
                    {field: 'page_url', title: '页面URL', width: 200, templet: function(d){
                        var url = d.page_url || '';
                        var href = url;
                        if (href && !href.match(/^https?:\/\//i)) {
                            href = href.charAt(0) === '/' ? href : '/' + href;
                        }
                        return '<a href="' + href + '" target="_blank" class="layui-table-link" title="' + url + '">' + url + '</a>';
                    }, export: true},
                    {field: 'page_title', title: '页面标题', width: 150, templet: function(d){
                        return '<div title="' + d.page_title + '">' + d.page_title + '</div>';
                    }, export: true},
                    {field: 'ip_address', title: 'IP地址', width: 120, export: true, filter: true},
                    {field: 'country', title: '地理位置', width: 120, templet: function(d){
                        if (!d.country || d.country === '未知') {
                            return '未知';
                        }
                        if (d.country === '局域网') {
                            return '局域网';
                        }
                        return d.country + (d.city ? ', ' + d.city : '');
                    }, export: true, filter: true},
                    {field: 'device_info', title: '设备', width: 200, templet: function(d){
                        var deviceType = d.device_type || '未知';
                        var browser = d.browser || '未知';
                        var os = d.os || '未知';
                        return deviceType + ', ' + os + ', ' + browser;
                    }, export: true},
                    {field: 'user_agent', title: '用户UA', width: 150, templet: function(d){
                        return '<div title="' + d.user_agent + '">' + d.user_agent + '</div>';
                    }, export: true},
                    {field: 'referer', title: '来源', width: 200, templet: function(d){
                        return '<div title="' + d.referer + '">' + d.referer + '</div>';
                    }, export: true},
                    {field: 'referer_host', title: '来源Host', width: 150, templet: function(d){
                        if (!d.referer_host) {
                            return '<span style="color: #6c757d; font-size: 11px;">-</span>';
                        }
                        return '<span style="background: #e3f2fd; color: #1976d2; padding: 2px 6px; border-radius: 3px; font-size: 11px;">' + d.referer_host + '</span>';
                    }, export: true, filter: true},
                    {field: 'source_type', title: '类型', width: 100, templet: function(d){
                        var types = {
                            'direct': '直接访问',
                            'internal': '内部链接',
                            'search': '搜索引擎',
                            'social': '社交媒体',
                            'referral': '外部链接'
                        };
                        return types[d.source_type] || d.source_type;
                    }, export: true, filter: true},
                    {field: 'status', title: '状态', width: 100, templet: function(d){
                        if (d.is_bot == 1 || d.is_bot === '1') {
                            return '<span style="background: #ffc107; color: #000; padding: 2px 6px; border-radius: 3px; font-size: 11px;">爬虫</span>';
                        } else {
                            return '<span style="background: #28a745; color: #fff; padding: 2px 6px; border-radius: 3px; font-size: 11px;">正常</span>';
                        }
                    }, export: true, filter: true},
                    {field: 'bot_type', title: '爬虫类型', width: 150, templet: function(d){
                        if (d.is_bot == 1 || d.is_bot === '1') {
                            return '<span style="background: #e9ecef; color: #495057; padding: 2px 6px; border-radius: 3px; font-size: 11px;">' + (d.bot_type || '未知爬虫') + '</span>';
                        } else {
                            return '<span style="color: #6c757d; font-size: 11px;">-</span>';
                        }
                    }, export: true, filter: true},
                    {field: 'visit_time', title: '访问时间', width: 150, sort: true, export: true, filter: true},
                    {title: '操作', width: 100, templet: function(d){
                        return '<button class="layui-btn layui-btn-danger layui-btn-xs" onclick="deleteVisit(' + d.id + ')">删除</button>';
                    }}
                ]],
                done: function(res, curr, count){
                    // 表格数据加载完成
                }
            });
            
            // 监听工具栏事件
            table.on('toolbar(visitsTable)', function(obj){
                var checkStatus = table.checkStatus(obj.config.id);
                switch(obj.event){
                    case 'exportData':
                        exportVisits();
                        break;
                    case 'batchDelete':
                        batchDeleteVisits();
                        break;
                    case 'refresh':
                        reloadTable();
                        break;
                };
            });
            
            // 重新加载表格
            window.reloadTable = function() {
                var search = document.getElementById('search').value;
                var dateFrom = document.getElementById('dateFrom').value;
                var dateTo = document.getElementById('dateTo').value;
                var projectId = document.getElementById('projectId').value;
                
                // 动态搜索字段
                var status = '';
                var type = '';
                var source = '';
                
                if (document.getElementById('statusField').style.display !== 'none') {
                    status = document.getElementById('status').value;
                }
                if (document.getElementById('typeField').style.display !== 'none') {
                    type = document.getElementById('type').value;
                }
                if (document.getElementById('sourceField').style.display !== 'none') {
                    source = document.getElementById('source').value;
                }
                
                table.reload('visitsTable', {
                    where: {
                        action: 'get_visits',
                        search: search,
                        date_from: dateFrom,
                        date_to: dateTo,
                        project_id: projectId,
                        status: status,
                        type: type,
                        source: source
                    },
                    page: {
                        curr: 1
                    }
                });
            };
            
            // 删除单条记录
            window.deleteVisit = function(id) {
                layer.confirm('确定要删除这条访问记录吗？', {icon: 3, title: '确认删除'}, function(index){
                    fetch('visits.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: new URLSearchParams({
                            action: 'delete_visit',
                            visit_id: id
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'success') {
                            layer.msg('删除成功');
                            reloadTable();
                        } else {
                            layer.msg('删除失败：' + data.message);
                        }
                    })
                    .catch(error => {
                        layer.msg('网络错误');
                    });
                    layer.close(index);
                });
            };
            
            // 批量删除记录
            window.batchDeleteVisits = function() {
                var checkStatus = table.checkStatus('visitsTable');
                var data = checkStatus.data;
                
                if (data.length === 0) {
                    layer.msg('请选择要删除的记录');
                    return;
                }
                
                layer.confirm('确定要删除选中的 ' + data.length + ' 条访问记录吗？', {icon: 3, title: '确认批量删除'}, function(index){
                    var ids = data.map(function(item) {
                        return item.id;
                    });
                    
                    fetch('visits.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: new URLSearchParams({
                            action: 'batch_delete_visits',
                            visit_ids: ids.join(',')
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'success') {
                            layer.msg('批量删除成功');
                            reloadTable();
                        } else {
                            layer.msg('批量删除失败：' + data.message);
                        }
                    })
                    .catch(error => {
                        layer.msg('网络错误');
                    });
                    layer.close(index);
                });
            };
        });
        
        // 页面加载完成后获取项目列表
        document.addEventListener('DOMContentLoaded', function() {
            loadProjects();
            
            // 添加日期校验
            addDateValidation();
            
            // 搜索表单提交
            document.getElementById('searchForm').addEventListener('submit', function(e) {
                e.preventDefault();
                if (validateDates()) {
                    reloadTable();
                }
            });
            
            // 重置按钮
            document.getElementById('resetBtn').addEventListener('click', function() {
                document.getElementById('searchForm').reset();
                clearDateError(); // 清除日期错误提示
                reloadTable();
            });
            
        });
        
        // 添加日期校验功能
        function addDateValidation() {
            const dateFrom = document.getElementById('dateFrom');
            const dateTo = document.getElementById('dateTo');
            
            // 开始日期变化时校验
            dateFrom.addEventListener('change', function() {
                validateDates();
            });
            
            // 结束日期变化时校验
            dateTo.addEventListener('change', function() {
                validateDates();
            });
        }
        
        // 校验日期范围
        function validateDates() {
            const dateFrom = document.getElementById('dateFrom').value;
            const dateTo = document.getElementById('dateTo').value;
            
            // 如果两个日期都为空，则通过校验
            if (!dateFrom && !dateTo) {
                clearDateError();
                return true;
            }
            
            // 如果只有一个日期为空，则通过校验
            if (!dateFrom || !dateTo) {
                clearDateError();
                return true;
            }
            
            // 比较日期
            const fromDate = new Date(dateFrom);
            const toDate = new Date(dateTo);
            
            if (toDate < fromDate) {
                showDateError('结束时间不能小于开始时间，请重新选择！');
                return false;
            }
            
            clearDateError();
            return true;
        }
        
        // 显示日期错误提示（使用弹窗）
        function showDateError(message) {
            // 移除之前的错误提示
            clearDateError();
            
            // 添加错误样式
            const dateFrom = document.getElementById('dateFrom');
            const dateTo = document.getElementById('dateTo');
            dateFrom.style.borderColor = '#dc3545';
            dateTo.style.borderColor = '#dc3545';
            
            // 使用layui弹窗显示错误信息
            if (typeof layui !== 'undefined' && layui.layer) {
                layui.layer.msg(message, {
                    icon: 2, // 错误图标
                    time: 3000, // 3秒后自动关闭
                    shade: 0.3
                });
            } else {
                // 如果layui不可用，使用原生alert
                alert('⚠️ ' + message);
            }
        }
        
        // 清除日期错误提示
        function clearDateError() {
            // 恢复正常样式
            const dateFrom = document.getElementById('dateFrom');
            const dateTo = document.getElementById('dateTo');
            dateFrom.style.borderColor = '';
            dateTo.style.borderColor = '';
        }
        
        // 加载项目列表
        function loadProjects() {
            fetch('visits.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'get_projects'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    const projectSelect = document.getElementById('projectId');
                    projectSelect.innerHTML = '<option value="">全部项目</option>';
                    
                    data.data.forEach(project => {
                        const option = document.createElement('option');
                        option.value = project.id;
                        option.textContent = project.name;
                        projectSelect.appendChild(option);
                    });
                }
            })
            .catch(error => {
                console.error('加载项目列表失败:', error);
            });
        }
        
        // 导出访问记录
        function exportVisits() {
            const search = document.getElementById('search').value;
            const dateFrom = document.getElementById('dateFrom').value;
            const dateTo = document.getElementById('dateTo').value;
            const projectId = document.getElementById('projectId').value;
            
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'visits.php';
            
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'export_visits';
            form.appendChild(actionInput);
            
            const searchInput = document.createElement('input');
            searchInput.type = 'hidden';
            searchInput.name = 'search';
            searchInput.value = search;
            form.appendChild(searchInput);
            
            const dateFromInput = document.createElement('input');
            dateFromInput.type = 'hidden';
            dateFromInput.name = 'date_from';
            dateFromInput.value = dateFrom;
            form.appendChild(dateFromInput);
            
            const dateToInput = document.createElement('input');
            dateToInput.type = 'hidden';
            dateToInput.name = 'date_to';
            dateToInput.value = dateTo;
            form.appendChild(dateToInput);
            
            const projectIdInput = document.createElement('input');
            projectIdInput.type = 'hidden';
            projectIdInput.name = 'project_id';
            projectIdInput.value = projectId;
            form.appendChild(projectIdInput);
            
            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);
        }
        
    </script>
</body>
</html>
