-- Seed default admin developer matching the React hardcoded login.
-- Password is bcrypt hash of 'asdasd'.
INSERT IGNORE INTO developers (email, name, password_hash, role)
VALUES ('admin@nap-ai.com', 'Admin', '$2y$10$hGzdp8jzr/Mdz1Hr9YNc8Ouprv3oshZ6Pg20LjA71hA2bUKeZ6sre', 'admin');
