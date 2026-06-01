-- ==========================================
-- 1. Create dedicated API user (only for alumni_ds_db)
-- ==========================================
CREATE USER IF NOT EXISTS 'alumni_api'@'localhost' IDENTIFIED BY 'ApiPass_2026!';
GRANT SELECT, INSERT, UPDATE, DELETE ON alumni_ds_db.* TO 'alumni_api'@'localhost';
FLUSH PRIVILEGES;

-- ==========================================
-- 2. Add password column to alumni table
--    (IF NOT EXISTS is NOT supported in MySQL 8.0 for columns,
--     so we use a workaround)
-- ==========================================
SET @dbname = 'alumni_ds_db';
SET @tablename = 'alumni';
SET @columnname = 'password';

SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = @dbname
     AND TABLE_NAME = @tablename
     AND COLUMN_NAME = @columnname) > 0,
    'SELECT 1',  -- column exists, do nothing
    CONCAT('ALTER TABLE ', @tablename, ' ADD COLUMN ', @columnname, ' VARCHAR(255) NULL AFTER email;')
));

PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ==========================================
-- 3. Set default password for existing alumni
--    (password = "alumni2026" hashed with bcrypt)
-- ==========================================
UPDATE alumni_ds_db.alumni 
SET password = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi' 
WHERE password IS NULL;