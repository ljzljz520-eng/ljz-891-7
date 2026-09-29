<?php
/**
 * 全局配置
 */

// 数据库（SQLite，文件位于 data 目录，不在 Web 根目录内）
define('DB_PATH', dirname(__DIR__) . '/data/referee.db');

// 初始管理员（仅在库中无管理员时使用）
define('INIT_ADMIN_USER', 'admin');
define('INIT_ADMIN_PASS', 'admin123');

// 会话有效期（秒）：2 小时
define('SESSION_LIFETIME', 7200);

// 级别字典
const LEVELS = ['国家级', '一级', '二级', '三级'];

// 有效期“即将到期”阈值（天）
define('EXPIRY_WARN_DAYS', 90);

// 错误是否显示（生产环境请改为 false）
define('DEBUG_MODE', true);
