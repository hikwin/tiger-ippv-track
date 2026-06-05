<?php
/**
 * 统计项目管理页面
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

// 处理AJAX请求
if (isAjax()) {
    require_once ROOT_PATH . '/api/generate.php';
    exit;
}

// 获取项目列表
$projects = $db->fetchAll("SELECT * FROM projects ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>统计项目 - <?php echo SITE_NAME; ?></title>
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
        
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        
        
        
        .btn-info {
            background: #17a2b8;
            color: white;
        }
        
        .btn-info:hover {
            background: #138496;
        }
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
        }
        
        .card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .card-header {
            padding: 20px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .card-body {
            padding: 20px;
        }
        
        .project-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .project-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 20px;
            transition: transform 0.3s;
        }
        
        .project-card:hover {
            transform: translateY(-2px);
        }
        
        .project-title {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 10px;
            color: #333;
        }
        
        .project-description {
            color: #666;
            margin-bottom: 15px;
            font-size: 14px;
        }
        
        .project-code {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 4px;
            padding: 10px;
            font-family: monospace;
            font-size: 12px;
            margin-bottom: 15px;
            word-break: break-all;
        }
        
        .project-actions {
            display: flex;
            gap: 10px;
        }
        
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .badge-success {
            background: #d4edda;
            color: #155724;
        }
        
        .badge-danger {
            background: #f8d7da;
            color: #721c24;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 2000;
        }
        
        .modal-content {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            border-radius: 8px;
            padding: 30px;
            max-width: 500px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .modal-title {
            font-size: 18px;
            font-weight: bold;
        }
        
        .close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #666;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #333;
        }
        
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 2px solid #e9ecef;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .code-preview {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 4px;
            padding: 15px;
            font-family: monospace;
            font-size: 12px;
            white-space: pre-wrap;
            max-height: 200px;
            overflow-y: auto;
        }
        
        .method-tabs {
            display: flex;
            margin-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        
        .method-tab {
            padding: 10px 20px;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            transition: all 0.3s;
        }
        
        .method-tab.active {
            border-bottom-color: #667eea;
            color: #667eea;
        }
        
        .method-content {
            display: none;
        }
        
        .method-content.active {
            display: block;
        }
        
        .alert {
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        @media (max-width: 768px) {
            .project-grid {
                grid-template-columns: 1fr;
            }
            
            .nav {
                display: none;
            }
            
            .main-content {
                padding: 10px;
            }
        }
        
        /* PHP统计代码样式 */
        .php-code-section,
        .include-code-section,
        .access-key-section {
            margin-bottom: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 6px;
            border: 1px solid #e9ecef;
        }
        
        .php-code-section h5,
        .include-code-section h5,
        .access-key-section h5 {
            margin-bottom: 10px;
            color: #495057;
            font-size: 14px;
            font-weight: 600;
        }
        
        .php-actions {
            margin-top: 10px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .access-key-display {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
        }
        
        .access-key-display code {
            background: #e9ecef;
            padding: 8px 12px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            color: #495057;
            flex: 1;
            word-break: break-all;
        }
        
        .text-muted {
            color: #6c757d;
            font-size: 12px;
            margin: 0;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="main-content">
        <div class="container">
            <div class="page-header">
                <h1>统计项目</h1>
                <button class="btn btn-primary" onclick="showCreateModal()">创建新项目</button>
            </div>
            
            <div class="project-grid" id="projectGrid">
                <?php foreach ($projects as $project): ?>
                    <div class="project-card">
                        <div class="project-title"><?php echo htmlspecialchars($project['name']); ?></div>
                        <div class="project-description"><?php echo htmlspecialchars($project['description'] ?: '暂无描述'); ?></div>
                        <div class="project-code"><?php echo htmlspecialchars($project['tracking_code']); ?></div>
                        <div class="project-actions">
                            <button class="btn btn-success btn-sm" onclick="viewDashboard(<?php echo $project['id']; ?>)">仪表盘</button>
                            <button class="btn btn-primary btn-sm" onclick="showCodeModal(<?php echo $project['id']; ?>)">生成代码</button>
                            <button class="btn btn-info btn-sm" onclick="editProject(<?php echo $project['id']; ?>, '<?php echo htmlspecialchars($project['name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($project['description'] ?: '', ENT_QUOTES); ?>')">编辑</button>
                            <button class="btn btn-danger btn-sm" onclick="deleteProject(<?php echo $project['id']; ?>)">删除</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    
    <!-- 创建项目模态框 -->
    <div id="createModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">创建统计项目</h3>
                <button class="close" onclick="hideCreateModal()">&times;</button>
            </div>
            <form id="createForm">
                <div class="form-group">
                    <label for="projectName">项目名称</label>
                    <input type="text" id="projectName" name="name" required>
                </div>
                <div class="form-group">
                    <label for="projectDescription">项目描述（可选）</label>
                    <textarea id="projectDescription" name="description" rows="3"></textarea>
                </div>
                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" class="btn btn-secondary" onclick="hideCreateModal()">取消</button>
                    <button type="submit" class="btn btn-primary">创建</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- 编辑项目模态框 -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">编辑统计项目</h3>
                <button class="close" onclick="hideEditModal()">&times;</button>
            </div>
            <form id="editForm">
                <input type="hidden" id="editProjectId" name="project_id">
                <div class="form-group">
                    <label for="editProjectName">项目名称</label>
                    <input type="text" id="editProjectName" name="name" required>
                </div>
                <div class="form-group">
                    <label for="editProjectDescription">项目描述（可选）</label>
                    <textarea id="editProjectDescription" name="description" rows="3"></textarea>
                </div>
                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" class="btn btn-secondary" onclick="hideEditModal()">取消</button>
                    <button type="submit" class="btn btn-primary">保存</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- 代码生成模态框 -->
    <div id="codeModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">生成统计代码</h3>
                <button class="close" onclick="hideCodeModal()">&times;</button>
            </div>
            <div class="method-tabs">
                <div class="method-tab active" onclick="switchMethod('js')">JavaScript方式</div>
                <div class="method-tab" onclick="switchMethod('image')">纯图片方式</div>
                <div class="method-tab" onclick="switchMethod('php')">内嵌PHP方式</div>
            </div>
            <div id="jsMethod" class="method-content active">
                <h4>JavaScript统计代码</h4>
                <p>将以下代码添加到您网站的每个页面中：</p>
                <div class="code-preview" id="jsCode"></div>
                <button class="btn btn-primary" onclick="copyCode('jsCode')">复制代码</button>
            </div>
            <div id="imageMethod" class="method-content">
                <h4>纯图片统计代码</h4>
                <p>将以下代码添加到您网站的每个页面中：</p>
                <div class="code-preview" id="imageCode"></div>
                <button class="btn btn-primary" onclick="copyCode('imageCode')">复制代码</button>
            </div>
            <div id="phpMethod" class="method-content">
                <h4>内嵌PHP统计代码</h4>
                <p>下载PHP统计文件，通过include方式引入到您的网站中：</p>
                <div class="php-code-section">
                    <h5>PHP统计文件代码：</h5>
                    <div class="code-preview" id="phpCode"></div>
                    <div class="php-actions">
                        <button class="btn btn-primary" onclick="copyCode('phpCode')">复制代码</button>
                        <button class="btn btn-success" onclick="downloadPhpFile()">下载PHP文件</button>
                        <button class="btn btn-warning" onclick="resetAccessKey()">重置访问密钥</button>
                    </div>
                </div>
                <div class="include-code-section">
                    <h5>引入代码：</h5>
                    <div class="code-preview" id="includeCode"></div>
                    <button class="btn btn-primary" onclick="copyCode('includeCode')">复制代码</button>
                </div>
                <div class="access-key-section">
                    <h5>访问密钥：</h5>
                    <div class="access-key-display">
                        <code id="accessKeyDisplay"></code>
                        <button class="btn btn-sm btn-outline-secondary" onclick="copyAccessKey()">复制密钥</button>
                    </div>
                    <p class="text-muted">请妥善保管此密钥，用于验证统计请求的合法性</p>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        let currentProjectId = null;
        
        // 显示创建项目模态框
        function showCreateModal() {
            document.getElementById('createModal').style.display = 'block';
        }
        
        // 隐藏创建项目模态框
        function hideCreateModal() {
            document.getElementById('createModal').style.display = 'none';
            document.getElementById('createForm').reset();
        }
        
        // 显示代码生成模态框
        function showCodeModal(projectId) {
            currentProjectId = projectId;
            document.getElementById('codeModal').style.display = 'block';
            generateCode('js');
        }
        
        // 隐藏代码生成模态框
        function hideCodeModal() {
            document.getElementById('codeModal').style.display = 'none';
        }
        
        // 显示编辑项目模态框
        function editProject(projectId, projectName, projectDescription) {
            document.getElementById('editProjectId').value = projectId;
            document.getElementById('editProjectName').value = projectName;
            document.getElementById('editProjectDescription').value = projectDescription;
            document.getElementById('editModal').style.display = 'block';
        }
        
        // 隐藏编辑项目模态框
        function hideEditModal() {
            document.getElementById('editModal').style.display = 'none';
            document.getElementById('editForm').reset();
        }
        
        // 切换统计方式
        function switchMethod(method) {
            // 更新标签页
            document.querySelectorAll('.method-tab').forEach(tab => tab.classList.remove('active'));
            document.querySelectorAll('.method-content').forEach(content => content.classList.remove('active'));
            
            event.target.classList.add('active');
            document.getElementById(method + 'Method').classList.add('active');
            
            // 生成对应代码
            generateCode(method);
        }
        
        // 生成统计代码
        function generateCode(method) {
            if (!currentProjectId) return;
            
            fetch(`../api/generate.php?action=generate_code&project_id=${currentProjectId}&method=${method}`)
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        if (method === 'php') {
                            // PHP方式需要特殊处理
                            document.getElementById('phpCode').textContent = data.data.phpCode;
                            document.getElementById('includeCode').textContent = data.data.includeCode;
                            document.getElementById('accessKeyDisplay').textContent = data.data.accessKey;
                            // 保存项目代码用于下载
                            window.currentProjectCode = data.data.project.tracking_code;
                        } else {
                            document.getElementById(method + 'Code').textContent = data.data.code;
                        }
                    } else {
                        alert('生成代码失败: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('生成代码失败');
                });
        }
        
        // 复制代码
        function copyCode(elementId) {
            const codeElement = document.getElementById(elementId);
            if (!codeElement) {
                alert('找不到要复制的代码元素');
                console.error('找不到元素:', elementId);
                return;
            }
            
            const text = codeElement.textContent || codeElement.innerText || '';
            
            if (!text.trim()) {
                alert('代码内容为空，请先生成代码');
                console.error('代码内容为空:', elementId);
                return;
            }
            
            console.log('准备复制代码:', text.substring(0, 100) + '...');
            
            // 检查是否支持现代剪贴板API
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(() => {
                    alert('代码已复制到剪贴板');
                }).catch(err => {
                    console.error('现代剪贴板API复制失败:', err);
                    fallbackCopy(text);
                });
            } else {
                // 使用备用复制方法
                console.log('使用备用复制方法');
                fallbackCopy(text);
            }
        }
        
        // 备用复制方法
        function fallbackCopy(text) {
            try {
                const textArea = document.createElement('textarea');
                textArea.value = text;
                textArea.style.position = 'fixed';
                textArea.style.left = '-999999px';
                textArea.style.top = '-999999px';
                document.body.appendChild(textArea);
                textArea.focus();
                textArea.select();
                
                const successful = document.execCommand('copy');
                document.body.removeChild(textArea);
                
                if (successful) {
                    alert('代码已复制到剪贴板');
                } else {
                    alert('复制失败，请手动选择并复制代码');
                }
            } catch (err) {
                console.error('备用复制方法失败:', err);
                alert('复制失败，请手动选择并复制代码');
            }
        }
        
        // 创建项目
        document.getElementById('createForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            fetch('../api/generate.php?action=create_project', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    alert('项目创建成功');
                    location.reload();
                } else {
                    alert('创建失败: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('创建失败');
            });
        });
        
        // 编辑项目
        document.getElementById('editForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            fetch('../api/generate.php?action=update_project', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    alert('项目更新成功');
                    location.reload();
                } else {
                    alert('更新失败: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('更新失败');
            });
        });
        
        // 查看项目仪表盘
        function viewDashboard(projectId) {
            window.location.href = 'index.php?project_id=' + projectId;
        }
        
        
        // 删除项目
        function deleteProject(projectId) {
            // 先获取项目的访问记录数量
            fetch(`../api/generate.php?action=get_project_stats&project_id=${projectId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        const visitCount = data.data.visit_count || 0;
                        let confirmMessage = `确定要删除这个项目吗？\n\n`;
                        confirmMessage += `⚠️ 警告：此操作将同时删除该项目的所有访问记录！\n\n`;
                        confirmMessage += `📊 该项目共有 ${visitCount} 条访问记录\n`;
                        confirmMessage += `🗑️ 删除后数据将无法恢复！\n\n`;
                        confirmMessage += `请确认是否继续？`;
                        
                        if (confirm(confirmMessage)) {
                            performDelete(projectId);
                        }
                    } else {
                        // 如果获取统计信息失败，使用简单确认
                        if (confirm('确定要删除这个项目吗？此操作将同时删除所有相关访问记录，且不可恢复！')) {
                            performDelete(projectId);
                        }
                    }
                })
                .catch(error => {
                    console.error('获取项目统计失败:', error);
                    // 如果获取统计信息失败，使用简单确认
                    if (confirm('确定要删除这个项目吗？此操作将同时删除所有相关访问记录，且不可恢复！')) {
                        performDelete(projectId);
                    }
                });
        }
        
        // 执行删除操作
        function performDelete(projectId) {
            const formData = new FormData();
            formData.append('project_id', projectId);
            
            fetch('../api/generate.php?action=delete_project', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    alert(data.message);
                    location.reload();
                } else {
                    alert('删除失败: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('删除失败');
            });
        }
        
        // 点击模态框外部关闭
        window.onclick = function(event) {
            const createModal = document.getElementById('createModal');
            const editModal = document.getElementById('editModal');
            const codeModal = document.getElementById('codeModal');
            
            if (event.target === createModal) {
                hideCreateModal();
            }
            if (event.target === editModal) {
                hideEditModal();
            }
            if (event.target === codeModal) {
                hideCodeModal();
            }
        }
        
        // 下载PHP文件
        function downloadPhpFile() {
            if (!currentProjectId) return;
            
            fetch(`../api/generate.php?action=download_php&project_id=${currentProjectId}`)
                .then(response => response.blob())
                .then(blob => {
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    // 使用项目代码而不是项目ID
                    const projectCode = window.currentProjectCode || currentProjectId;
                    a.download = `tracking_${projectCode}.php`;
                    document.body.appendChild(a);
                    a.click();
                    window.URL.revokeObjectURL(url);
                    document.body.removeChild(a);
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('下载失败');
                });
        }
        
        // 重置访问密钥
        function resetAccessKey() {
            if (!currentProjectId) return;
            
            if (!confirm('确定要重置访问密钥吗？重置后旧的PHP文件将无法使用。')) {
                return;
            }
            
            const formData = new FormData();
            formData.append('project_id', currentProjectId);
            
            fetch(`../api/generate.php?action=reset_access_key`, {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        alert('访问密钥重置成功');
                        // 重新生成代码
                        generateCode('php');
                    } else {
                        alert('重置失败: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('重置失败');
                });
        }
        
        // 复制访问密钥
        function copyAccessKey() {
            const accessKeyElement = document.getElementById('accessKeyDisplay');
            const text = accessKeyElement.textContent;
            navigator.clipboard.writeText(text).then(() => {
                alert('访问密钥已复制到剪贴板');
            }).catch(() => {
                // 降级处理
                const textArea = document.createElement('textarea');
                textArea.value = text;
                document.body.appendChild(textArea);
                textArea.select();
                document.execCommand('copy');
                document.body.removeChild(textArea);
                alert('访问密钥已复制到剪贴板');
            });
        }
    </script>
</body>
</html>
