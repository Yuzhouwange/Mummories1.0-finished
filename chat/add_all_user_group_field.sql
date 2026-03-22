-- 修改groups表，添加all_user_group字段
USE chat;

-- 为groups表添加all_user_group字段
ALTER TABLE groups ADD COLUMN all_user_group INT DEFAULT 0 AFTER owner_id;

-- 创建索引以提高查询性能
CREATE INDEX idx_groups_all_user_group ON groups(all_user_group);