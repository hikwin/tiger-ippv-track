<?php
/**
 * 数据库操作类
 * 基于SQLite的数据库操作封装
 */

class Database {
    private $pdo;
    private $dbPath;
    
    public function __construct($dbPath = null) {
        $this->dbPath = $dbPath ?: DB_PATH;
        $this->connect();
        $this->createTables();
    }
    
    /**
     * 连接数据库
     */
    private function connect() {
        try {
            // 确保数据目录存在
            $dataDir = dirname($this->dbPath);
            if (!is_dir($dataDir)) {
                mkdir($dataDir, 0755, true);
            }
            
            $this->pdo = new PDO('sqlite:' . $this->dbPath);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            
            // 启用外键约束
            $this->pdo->exec('PRAGMA foreign_keys = ON');
            
        } catch (PDOException $e) {
            throw new Exception('数据库连接失败: ' . $e->getMessage());
        }
    }
    
    /**
     * 创建数据表
     */
    private function createTables() {
        $tables = [
            // 访问记录表
            'visits' => "
                CREATE TABLE IF NOT EXISTS visits (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    ip_address TEXT NOT NULL,
                    user_agent TEXT,
                    referer TEXT,
                    source_type TEXT DEFAULT 'direct',
                    page_url TEXT,
                    page_title TEXT,
                    is_bot BOOLEAN DEFAULT 0,
                    visit_time DATETIME DEFAULT CURRENT_TIMESTAMP,
                    session_id TEXT,
                    country TEXT,
                    city TEXT,
                    device_type TEXT,
                    browser TEXT,
                    os TEXT
                )
            ",
            
            // 页面统计表
            'pages' => "
                CREATE TABLE IF NOT EXISTS pages (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    page_url TEXT UNIQUE NOT NULL,
                    page_title TEXT,
                    total_views INTEGER DEFAULT 0,
                    unique_views INTEGER DEFAULT 0,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )
            ",
            
            // 管理员表
            'admins' => "
                CREATE TABLE IF NOT EXISTS admins (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    username TEXT UNIQUE NOT NULL,
                    password TEXT NOT NULL,
                    email TEXT,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    last_login DATETIME,
                    is_active BOOLEAN DEFAULT 1
                )
            ",
            
            // 系统设置表
            'settings' => "
                CREATE TABLE IF NOT EXISTS settings (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    setting_key TEXT UNIQUE NOT NULL,
                    setting_value TEXT,
                    description TEXT,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )
            ",
            
            // 统计项目表
            'projects' => "
                CREATE TABLE IF NOT EXISTS projects (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    name TEXT NOT NULL,
                    description TEXT,
                    tracking_code TEXT UNIQUE NOT NULL,
                    is_active BOOLEAN DEFAULT 1,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )
            "
        ];
        
        foreach ($tables as $tableName => $sql) {
            try {
                $this->pdo->exec($sql);
            } catch (PDOException $e) {
                throw new Exception("创建表 {$tableName} 失败: " . $e->getMessage());
            }
        }
        
        // 执行数据库升级
        $this->upgradeDatabase();
        
        // 创建索引
        $this->createIndexes();
        
        // 插入默认数据
        $this->insertDefaultData();
    }
    
    /**
     * 数据库升级
     */
    private function upgradeDatabase() {
        try {
            // 检查visits表是否有project_id字段
            $columns = $this->pdo->query("PRAGMA table_info(visits)")->fetchAll();
            $hasProjectId = false;
            foreach ($columns as $column) {
                if ($column['name'] === 'project_id') {
                    $hasProjectId = true;
                    break;
                }
            }
            
            // 如果没有project_id字段，则添加
            if (!$hasProjectId) {
                $this->pdo->exec("ALTER TABLE visits ADD COLUMN project_id INTEGER");
                $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_visits_project_id ON visits(project_id)");
            }
            
            // 检查projects表是否有access_key字段
            $projectColumns = $this->pdo->query("PRAGMA table_info(projects)")->fetchAll();
            $hasAccessKey = false;
            foreach ($projectColumns as $column) {
                if ($column['name'] === 'access_key') {
                    $hasAccessKey = true;
                    break;
                }
            }
            
            // 如果没有access_key字段，则添加
            if (!$hasAccessKey) {
                $this->pdo->exec("ALTER TABLE projects ADD COLUMN access_key TEXT");
                $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_projects_access_key ON projects(access_key)");
            }
            
            // 检查visits表是否有province字段
            $hasProvince = false;
            foreach ($columns as $column) {
                if ($column['name'] === 'province') {
                    $hasProvince = true;
                    break;
                }
            }
            
            // 如果没有province字段，则添加
            if (!$hasProvince) {
                $this->pdo->exec("ALTER TABLE visits ADD COLUMN province TEXT");
                $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_visits_province ON visits(province)");
            }
            
            // 检查visits表是否有bot_type字段
            $botTypeColumns = $this->pdo->query("PRAGMA table_info(visits)")->fetchAll();
            $hasBotType = false;
            foreach ($botTypeColumns as $column) {
                if ($column['name'] === 'bot_type') {
                    $hasBotType = true;
                    break;
                }
            }
            
            // 如果没有bot_type字段，则添加
            if (!$hasBotType) {
                $this->pdo->exec("ALTER TABLE visits ADD COLUMN bot_type TEXT");
                $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_visits_bot_type ON visits(bot_type)");
            }
            
            // 检查visits表是否有referer_host字段
            $hasRefererHost = false;
            foreach ($botTypeColumns as $column) {
                if ($column['name'] === 'referer_host') {
                    $hasRefererHost = true;
                    break;
                }
            }
            
            // 如果没有referer_host字段，则添加
            if (!$hasRefererHost) {
                $this->pdo->exec("ALTER TABLE visits ADD COLUMN referer_host TEXT");
                $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_visits_referer_host ON visits(referer_host)");
            }
        } catch (PDOException $e) {
            // 忽略升级错误，不影响系统运行
            error_log("数据库升级失败: " . $e->getMessage());
        }
    }
    
    /**
     * 创建索引
     */
    private function createIndexes() {
        $indexes = [
            'CREATE INDEX IF NOT EXISTS idx_visits_ip ON visits(ip_address)',
            'CREATE INDEX IF NOT EXISTS idx_visits_time ON visits(visit_time)',
            'CREATE INDEX IF NOT EXISTS idx_visits_page ON visits(page_url)',
            'CREATE INDEX IF NOT EXISTS idx_visits_bot ON visits(is_bot)',
            'CREATE INDEX IF NOT EXISTS idx_visits_source ON visits(source_type)',
            'CREATE INDEX IF NOT EXISTS idx_pages_url ON pages(page_url)',
            'CREATE INDEX IF NOT EXISTS idx_projects_code ON projects(tracking_code)',
            'CREATE INDEX IF NOT EXISTS idx_visits_bot_project_time ON visits(is_bot, project_id, visit_time)',
            'CREATE INDEX IF NOT EXISTS idx_visits_project_time ON visits(project_id, visit_time)'
        ];
        
        foreach ($indexes as $index) {
            try {
                $this->pdo->exec($index);
            } catch (PDOException $e) {
                // 索引创建失败不影响主要功能
                writeLog("创建索引失败: " . $e->getMessage(), 'WARNING');
            }
        }
    }
    
    /**
     * 插入默认数据
     */
    private function insertDefaultData() {
        // 检查是否已有数据
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM settings");
        if ($stmt->fetchColumn() > 0) {
            return;
        }
        
        // 插入默认设置
        $defaultSettings = [
            ['site_name', '泰格网站流量统计', '网站名称'],
            ['site_url', '', '网站URL'],
            ['track_bots', '1', '是否统计爬虫'],
            ['track_duplicates', '0', '是否允许重复统计'],
            ['timezone', 'Asia/Shanghai', '时区设置'],
            ['date_format', 'Y-m-d H:i:s', '日期格式']
        ];
        
        $stmt = $this->pdo->prepare("INSERT INTO settings (setting_key, setting_value, description) VALUES (?, ?, ?)");
        foreach ($defaultSettings as $setting) {
            $stmt->execute($setting);
        }
    }
    
    /**
     * 执行查询
     */
    public function query($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            writeLog("数据库查询失败: " . $e->getMessage(), 'ERROR');
            throw new Exception('数据库查询失败: ' . $e->getMessage());
        }
    }
    
    /**
     * 获取单行数据
     */
    public function fetchOne($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetch();
    }
    
    /**
     * 获取多行数据
     */
    public function fetchAll($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }
    
    /**
     * 插入数据
     */
    public function insert($table, $data) {
        $fields = array_keys($data);
        $placeholders = ':' . implode(', :', $fields);
        $sql = "INSERT INTO {$table} (" . implode(', ', $fields) . ") VALUES ({$placeholders})";
        
        $this->query($sql, $data);
        return $this->pdo->lastInsertId();
    }
    
    /**
     * 更新数据
     */
    public function update($table, $data, $where, $whereParams = []) {
        $fields = [];
        foreach ($data as $key => $value) {
            $fields[] = "{$key} = :{$key}";
        }
        
        $sql = "UPDATE {$table} SET " . implode(', ', $fields) . " WHERE {$where}";
        $params = array_merge($data, $whereParams);
        
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }
    
    /**
     * 删除数据
     */
    public function delete($table, $where, $params = []) {
        $sql = "DELETE FROM {$table} WHERE {$where}";
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }
    
    /**
     * 开始事务
     */
    public function beginTransaction() {
        return $this->pdo->beginTransaction();
    }
    
    /**
     * 提交事务
     */
    public function commit() {
        return $this->pdo->commit();
    }
    
    /**
     * 回滚事务
     */
    public function rollback() {
        return $this->pdo->rollback();
    }
    
    /**
     * 获取最后插入的ID
     */
    public function lastInsertId() {
        return $this->pdo->lastInsertId();
    }
    
    /**
     * 获取PDO对象
     */
    public function getPdo() {
        return $this->pdo;
    }
}
?>
