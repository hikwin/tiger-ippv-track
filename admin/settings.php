<?php
/**
 * 系统设置管理页面
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
    // 判断是通过根目录访问还是直接访问admin目录
    $isFromRoot = strpos($_SERVER['REQUEST_URI'], 'index.php?action=admin') !== false;
    $loginPath = $isFromRoot ? 'admin/login.php' : 'login.php';
    header('Location: ' . $loginPath);
    exit;
}

// 处理AJAX请求
if (isAjax() || (isset($_POST['action']) && !empty($_POST['action']))) {
    $action = $_POST['action'] ?? '';

    // ── CSRF 验证：所有状态变更操作必须通过 ──────────────────────────────
    $csrfToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!verifyCsrfToken($csrfToken)) {
        jsonResponse(['status' => 'error', 'message' => '安全验证失败，请刷新页面后重试'], 403);
    }

    switch ($action) {
        case 'get_settings':
            $settings = getAllSettings();
            jsonResponse([
                'status' => 'success',
                'data' => $settings
            ]);
            break;
            
        case 'update_settings':
            $settings = $_POST['settings'] ?? [];
            if (empty($settings)) {
                jsonResponse(['status' => 'error', 'message' => '缺少设置数据'], 400);
            }
            
            $result = updateSettings($settings);
            if ($result) {
                jsonResponse(['status' => 'success', 'message' => '设置更新成功']);
            } else {
                jsonResponse(['status' => 'error', 'message' => '设置更新失败'], 500);
            }
            break;
            
        case 'update_admin_password':
            $oldPassword = $_POST['old_password'] ?? '';
            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';
            
            if (empty($oldPassword) || empty($newPassword) || empty($confirmPassword)) {
                jsonResponse(['status' => 'error', 'message' => '请填写所有密码字段'], 400);
            }
            
            if ($newPassword !== $confirmPassword) {
                jsonResponse(['status' => 'error', 'message' => '新密码和确认密码不匹配'], 400);
            }
            
            if (strlen($newPassword) < 6) {
                jsonResponse(['status' => 'error', 'message' => '新密码长度至少6位'], 400);
            }
            
            $result = updateAdminPassword($_SESSION['admin_id'], $oldPassword, $newPassword);
            if ($result['success']) {
                jsonResponse(['status' => 'success', 'message' => '密码修改成功']);
            } else {
                jsonResponse(['status' => 'error', 'message' => $result['message']], 400);
            }
            break;
            
        case 'clear_data':
            $confirmText = $_POST['confirm_text'] ?? '';
            if ($confirmText !== 'CLEAR_ALL_DATA') {
                jsonResponse(['status' => 'error', 'message' => '确认文本不正确'], 400);
            }
            
            $result = clearAllData();
            if ($result) {
                jsonResponse(['status' => 'success', 'message' => '数据清理完成']);
            } else {
                jsonResponse(['status' => 'error', 'message' => '数据清理失败'], 500);
            }
            break;
            
        case 'export_data':
            $format = $_POST['format'] ?? 'json';
            exportData($format);
            break;
            
        default:
            jsonResponse(['status' => 'error', 'message' => '无效的操作'], 400);
    }
}

/**
 * 获取所有设置
 */
function getAllSettings() {
    global $db;
    
    $settings = $db->fetchAll("SELECT * FROM settings ORDER BY setting_key");
    $result = [];
    
    foreach ($settings as $setting) {
        $result[$setting['setting_key']] = $setting['setting_value'];
    }
    
    return $result;
}

/**
 * 更新设置
 */
function updateSettings($settings) {
    global $db;
    
    try {
        $db->beginTransaction();
        
        foreach ($settings as $key => $value) {
            $existing = $db->fetchOne("SELECT id FROM settings WHERE setting_key = ?", [$key]);
            
            if ($existing) {
                $db->update('settings', 
                    ['setting_value' => $value, 'updated_at' => date('Y-m-d H:i:s')],
                    'setting_key = ?', [$key]
                );
            } else {
                $db->insert('settings', [
                    'setting_key' => $key,
                    'setting_value' => $value,
                    'description' => getSettingDescription($key),
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
            }
        }
        
        $db->commit();
        return true;
    } catch (Exception $e) {
        $db->rollback();
        writeLog("设置更新失败: " . $e->getMessage(), 'ERROR');
        return false;
    }
}

/**
 * 获取设置描述
 */
function getSettingDescription($key) {
    $descriptions = [
        'site_name' => '网站名称',
        'site_url' => '网站URL',
        'track_bots' => '是否跟踪机器人',
        'track_duplicates' => '是否跟踪重复访问',
        'session_timeout' => '会话超时时间（分钟）',
        'max_visits_per_hour' => '每小时最大访问记录数',
        'enable_api' => '是否启用API',
        'api_rate_limit' => 'API请求频率限制（次/分钟）',
        'enable_export' => '是否启用数据导出',
        'log_level' => '日志级别',
        'timezone' => '时区设置'
    ];
    
    return $descriptions[$key] ?? $key;
}

/**
 * 更新管理员密码
 */
function updateAdminPassword($adminId, $oldPassword, $newPassword) {
    global $db;
    
    // 验证旧密码
    $admin = $db->fetchOne("SELECT password FROM admins WHERE id = ?", [$adminId]);
    if (!$admin || !password_verify($oldPassword, $admin['password'])) {
        return ['success' => false, 'message' => '旧密码不正确'];
    }
    
    // 更新密码
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
    $result = $db->update('admins', 
        ['password' => $hashedPassword, 'updated_at' => date('Y-m-d H:i:s')],
        'id = ?', [$adminId]
    );
    
    if ($result) {
        writeLog("管理员密码已更新", 'INFO');
        return ['success' => true, 'message' => '密码更新成功'];
    } else {
        return ['success' => false, 'message' => '密码更新失败'];
    }
}

/**
 * 清理所有数据
 */
function clearAllData() {
    global $db;
    
    try {
        $db->beginTransaction();
        
        // 清理访问记录
        $db->query("DELETE FROM visits");
        
        // 清理项目（可选，保留项目设置）
        // $db->query("DELETE FROM projects");
        
        // 重置自增ID
        $db->query("DELETE FROM sqlite_sequence WHERE name = 'visits'");
        
        $db->commit();
        writeLog("数据清理完成", 'INFO');
        return true;
    } catch (Exception $e) {
        $db->rollback();
        writeLog("数据清理失败: " . $e->getMessage(), 'ERROR');
        return false;
    }
}

/**
 * 导出数据
 */
function exportData($format = 'json') {
    global $db;
    
    $data = [
        'export_time' => date('Y-m-d H:i:s'),
        'visits' => $db->fetchAll("SELECT * FROM visits ORDER BY visit_time DESC"),
        'projects' => $db->fetchAll("SELECT * FROM projects"),
        'settings' => $db->fetchAll("SELECT * FROM settings")
    ];
    
    $filename = 'ippvs_export_' . date('Y-m-d_H-i-s') . '.' . $format;
    
    if ($format === 'json') {
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    } elseif ($format === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM for UTF-8
        
        // 导出访问记录
        fputcsv($output, ['访问记录']);
        fputcsv($output, ['ID', '页面URL', '页面标题', 'IP地址', '用户代理', '来源', '来源类型', '是否机器人', '访问时间']);
        foreach ($data['visits'] as $visit) {
            fputcsv($output, [
                $visit['id'], $visit['page_url'], $visit['page_title'],
                $visit['ip_address'], $visit['user_agent'], $visit['referer'],
                $visit['source_type'], $visit['is_bot'] ? '是' : '否', $visit['visit_time']
            ]);
        }
        
        fclose($output);
    }
    
    exit;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>系统设置 - <?php echo SITE_NAME; ?></title>
    <style>
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
        
        .settings-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .settings-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.05);
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }
        
        .card-header {
            padding: 16px 20px;
            border-bottom: 1px solid #f1f5f9;
            background: transparent;
        }
        
        .card-title {
            font-family: 'Outfit', sans-serif;
            font-size: 16px;
            font-weight: 600;
            color: #0f172a;
        }
        
        .card-body {
            padding: 20px;
            flex: 1;
        }
        
        .form-group {
            margin-bottom: 14px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 4px;
            font-size: 12px;
            font-weight: 600;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 13.5px;
            color: #0f172a;
            transition: all 0.2s ease;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.12);
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 60px;
        }
        
        .form-group .form-text {
            font-size: 11px;
            color: #64748b;
            margin-top: 3px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 12px;
        }
        
        .checkbox-panel {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 12px 16px;
            margin-bottom: 14px;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: auto;
            accent-color: #6366f1;
            cursor: pointer;
        }
        
        .checkbox-group label {
            margin-bottom: 0 !important;
            text-transform: none !important;
            font-weight: 500 !important;
            font-size: 13px !important;
            color: #1e293b !important;
            cursor: pointer;
            user-select: none;
        }

        
        .btn-group {
            display: flex;
            gap: 10px;
            margin-top: 8px;
        }
        
        
        .success, .error {
            padding: 10px 16px;
            border-radius: 10px;
            margin-bottom: 16px;
            font-size: 13.5px;
            display: none;
            animation: slideDown 0.3s ease;
        }
        .success {
            background: rgba(16, 185, 129, 0.1);
            color: #059669;
            border: 1px solid rgba(16, 185, 129, 0.2);
        }
        .error {
            background: rgba(239, 68, 68, 0.1);
            color: #dc2626;
            border: 1px solid rgba(239, 68, 68, 0.2);
        }
        @keyframes slideDown {
            from { transform: translateY(-10px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        @media (max-width: 768px) {
            .settings-container {
                grid-template-columns: 1fr;
                gap: 16px;
            }
            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="main-content">
        <div class="container">
            <div id="successMessage" class="success"></div>
            <div id="errorMessage" class="error"></div>
            
            <!-- Top Column Settings -->
            <div class="settings-container">
                <!-- Basic Settings Card -->
                <div class="settings-card">
                    <div class="card-header">
                        <h3 class="card-title">基本系统参数</h3>
                    </div>
                    <div class="card-body">
                        <form id="basicSettingsForm">
                            <div class="form-group">
                                <label for="site_name">网站名称</label>
                                <input type="text" id="site_name" name="site_name" required>
                                <div class="form-text">显示在控制中心标题的文字名称</div>
                            </div>
                            
                            <div class="form-group">
                                <label for="site_url">统计主站 URL</label>
                                <input type="url" id="site_url" name="site_url" required>
                                <div class="form-text">当前站点的绝对根域名（生成埋点代码时使用）</div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="timezone">系统时区</label>
                                    <select id="timezone" name="timezone">
                                        <option value="Asia/Shanghai">Asia/Shanghai (北京时间)</option>
                                        <option value="UTC">UTC (世界协调时)</option>
                                        <option value="America/New_York">America/New_York (纽约)</option>
                                        <option value="Europe/London">Europe/London (伦敦)</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="session_timeout">登录超时 (分钟)</label>
                                    <input type="number" id="session_timeout" name="session_timeout" min="5" max="1440" value="120">
                                </div>
                            </div>
                            
                            <div class="btn-group">
                                <button type="submit" class="btn btn-primary btn-sm">保存基本设置</button>
                                <button type="button" id="resetBasicBtn" class="btn btn-secondary btn-sm">重置表单</button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Tracking Configuration Card -->
                <div class="settings-card">
                    <div class="card-header">
                        <h3 class="card-title">统计策略规则</h3>
                    </div>
                    <div class="card-body">
                        <form id="trackingSettingsForm">
                            <!-- Compact Checkbox panel -->
                            <div class="checkbox-panel">
                                <div class="checkbox-group" title="记录搜索引擎蜘蛛、GPT蜘蛛等流量">
                                    <input type="checkbox" id="track_bots" name="track_bots" value="1" title="记录搜索引擎蜘蛛、GPT蜘蛛等流量">
                                    <label for="track_bots" title="记录搜索引擎蜘蛛、GPT蜘蛛等流量">跟踪机器人爬虫访问</label>
                                </div>
                                
                                <div class="checkbox-group" title="对同一 IP 产生的重复点击计入浏览 PV">
                                    <input type="checkbox" id="track_duplicates" name="track_duplicates" value="1" title="对同一 IP 产生的重复点击计入浏览 PV">
                                    <label for="track_duplicates" title="对同一 IP 产生的重复点击计入浏览 PV">跟踪同一访客重复访问</label>
                                </div>
                                
                                <div class="checkbox-group" title="允许通过密钥请求项目 JSON telemetry 数据">
                                    <input type="checkbox" id="enable_api" name="enable_api" value="1" title="允许通过密钥请求项目 JSON telemetry 数据">
                                    <label for="enable_api" title="允许通过密钥请求项目 JSON telemetry 数据">启用第三方控制台 API 接口</label>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="max_visits_per_hour">单IP限额 (次/小时)</label>
                                    <input type="number" id="max_visits_per_hour" name="max_visits_per_hour" min="1" max="10000" value="1000">
                                </div>
                                
                                <div class="form-group">
                                    <label for="api_rate_limit">API限制 (次/分钟)</label>
                                    <input type="number" id="api_rate_limit" name="api_rate_limit" min="1" max="1000" value="60">
                                </div>
                            </div>
                            
                            <div class="btn-group">
                                <button type="submit" class="btn btn-primary btn-sm">保存统计规则</button>
                                <button type="button" id="resetTrackingBtn" class="btn btn-secondary btn-sm">重置</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Bottom Column Settings Grid -->
            <div class="settings-container" style="margin-top: 20px;">
                <!-- Password Settings Card -->
                <div class="settings-card">
                    <div class="card-header">
                        <h3 class="card-title">安全凭证密码管理</h3>
                    </div>
                    <div class="card-body">
                        <form id="passwordForm">
                            <div class="form-group">
                                <label for="old_password">当前旧密码</label>
                                <input type="password" id="old_password" name="old_password" required placeholder="请输入原管理员密码">
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="new_password">新密码</label>
                                    <input type="password" id="new_password" name="new_password" required minlength="6" placeholder="最少6位">
                                </div>
                                <div class="form-group">
                                    <label for="confirm_password">确认新密码</label>
                                    <input type="password" id="confirm_password" name="confirm_password" required minlength="6" placeholder="重复密码">
                                </div>
                            </div>
                            <div class="btn-group" style="margin-top: 10px;">
                                <button type="submit" class="btn btn-primary btn-sm" style="background:#f59e0b !important; color:#fff !important; box-shadow: 0 2px 8px rgba(245,158,11,0.25) !important;">修改安全密码</button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Data Management Card -->
                <div class="settings-card">
                    <div class="card-header">
                        <h3 class="card-title">系统数据流管理</h3>
                    </div>
                    <div class="card-body" style="display:flex; flex-direction:column; justify-content:space-between;">
                        <div class="form-group" style="margin-bottom: 12px;">
                            <label>全库原始数据导出</label>
                            <div class="form-text" style="margin-bottom: 6px; margin-top: 0;">打包离线归档项目及访问遥测记录。</div>
                            <div class="btn-group">
                                <button type="button" id="exportJsonBtn" class="btn btn-info btn-sm">导出 JSON 数据</button>
                                <button type="button" id="exportCsvBtn" class="btn btn-info btn-sm">导出 CSV 报表</button>
                            </div>
                        </div>
                        
                        <div class="form-group" style="margin-bottom: 0;">
                            <label>快速清空记录</label>
                            <div class="form-text" style="margin-bottom: 6px; margin-top: 0;">一键擦除全站历史流量遥测（保留项目）。</div>
                            <div class="btn-group">
                                <button type="button" id="clearDataBtn" class="btn btn-danger btn-sm">快速清空流量包</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            

        </div>
    </div>
    
    <script>
        // 页面加载完成后获取数据
        document.addEventListener('DOMContentLoaded', function() {
            loadSettings();
            
            // 基本设置表单
            document.getElementById('basicSettingsForm').addEventListener('submit', function(e) {
                e.preventDefault();
                saveSettings('basic');
            });
            
            // 统计设置表单
            document.getElementById('trackingSettingsForm').addEventListener('submit', function(e) {
                e.preventDefault();
                saveSettings('tracking');
            });
            
            // 密码表单
            document.getElementById('passwordForm').addEventListener('submit', function(e) {
                e.preventDefault();
                updatePassword();
            });
            
            // 重置按钮
            document.getElementById('resetBasicBtn').addEventListener('click', function() {
                loadSettings();
            });
            
            document.getElementById('resetTrackingBtn').addEventListener('click', function() {
                loadSettings();
            });
            
            // 导出按钮
            document.getElementById('exportJsonBtn').addEventListener('click', function() {
                exportData('json');
            });
            
            document.getElementById('exportCsvBtn').addEventListener('click', function() {
                exportData('csv');
            });
            
            // 清理数据按钮
            document.getElementById('clearDataBtn').addEventListener('click', function() {
                const confirmText = prompt("⚠️ 警告：该操作将清空全站的历史访问记录！此操作不可逆！\n\n请输入 CLEAR_ALL_DATA 确认清理：");
                if (confirmText === 'CLEAR_ALL_DATA') {
                    clearData();
                } else if (confirmText !== null) {
                    alert("验证码输入错误，操作已取消。");
                }
            });
        });
        
        // 加载设置
        function loadSettings() {
            fetch('settings.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'get_settings'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    const settings = data.data;
                    
                    // 基本设置
                    document.getElementById('site_name').value = settings.site_name || '';
                    document.getElementById('site_url').value = settings.site_url || '';
                    document.getElementById('timezone').value = settings.timezone || 'Asia/Shanghai';
                    document.getElementById('session_timeout').value = settings.session_timeout || 120;
                    
                    // 统计设置
                    document.getElementById('track_bots').checked = settings.track_bots === '1';
                    document.getElementById('track_duplicates').checked = settings.track_duplicates === '1';
                    document.getElementById('max_visits_per_hour').value = settings.max_visits_per_hour || 1000;
                    document.getElementById('enable_api').checked = settings.enable_api === '1';
                    document.getElementById('api_rate_limit').value = settings.api_rate_limit || 60;
                } else {
                    showError(data.message || '加载设置失败');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showError('网络错误');
            });
        }
        
        
        // 保存设置
        function saveSettings(type) {
            const form = document.getElementById(type + 'SettingsForm');
            const formData = new FormData(form);
            const settings = {};
            
            for (let [key, value] of formData.entries()) {
                if (form.querySelector(`[name="${key}"]`).type === 'checkbox') {
                    settings[key] = form.querySelector(`[name="${key}"]`).checked ? '1' : '0';
                } else {
                    settings[key] = value;
                }
            }
            
            fetch('settings.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'update_settings',
                    settings: JSON.stringify(settings)
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    showSuccess(data.message);
                } else {
                    showError(data.message || '保存失败');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showError('网络错误');
            });
        }
        
        // 更新密码
        function updatePassword() {
            const form = document.getElementById('passwordForm');
            const formData = new FormData(form);
            
            fetch('settings.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'update_admin_password',
                    old_password: formData.get('old_password'),
                    new_password: formData.get('new_password'),
                    confirm_password: formData.get('confirm_password')
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    showSuccess(data.message);
                    form.reset();
                } else {
                    showError(data.message || '密码修改失败');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showError('网络错误');
            });
        }
        
        // 导出数据
        function exportData(format) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'settings.php';
            
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'export_data';
            form.appendChild(actionInput);
            
            const formatInput = document.createElement('input');
            formatInput.type = 'hidden';
            formatInput.name = 'format';
            formatInput.value = format;
            form.appendChild(formatInput);
            
            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);
        }
        
        // 清理数据
        function clearData() {
            fetch('settings.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'clear_data',
                    confirm_text: 'CLEAR_ALL_DATA'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    showSuccess(data.message);
                } else {
                    showError(data.message || '清理失败');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showError('网络错误');
            });
        }
        

        
        // 显示成功消息
        function showSuccess(message) {
            const successDiv = document.getElementById('successMessage');
            successDiv.textContent = message;
            successDiv.style.display = 'block';
            setTimeout(() => {
                successDiv.style.display = 'none';
            }, 3000);
        }
        
        // 显示错误消息
        function showError(message) {
            const errorDiv = document.getElementById('errorMessage');
            errorDiv.textContent = message;
            errorDiv.style.display = 'block';
            setTimeout(() => {
                errorDiv.style.display = 'none';
            }, 5000);
        }
    </script>
</body>
</html>
