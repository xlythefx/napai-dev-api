-- Video Studio: per-user animated video projects.

CREATE TABLE IF NOT EXISTS content_videos (
    id          INT           AUTO_INCREMENT PRIMARY KEY,
    user_email  VARCHAR(255)  NOT NULL,
    name        VARCHAR(255)  NOT NULL DEFAULT 'Untitled video',
    video_json  LONGTEXT      NOT NULL,        -- { fps, scenes: [...] }
    created_at  TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_cv_user (user_email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
