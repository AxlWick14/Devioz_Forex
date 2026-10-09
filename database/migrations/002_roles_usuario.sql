-- Preserve existing accounts while replacing the legacy role identifier.
ALTER TABLE usuarios MODIFY role ENUM('admin','superuser','user') NOT NULL DEFAULT 'user';
UPDATE usuarios SET role='user', updated_at=updated_at WHERE role='superuser';
ALTER TABLE usuarios MODIFY role ENUM('admin','user') NOT NULL DEFAULT 'user';
