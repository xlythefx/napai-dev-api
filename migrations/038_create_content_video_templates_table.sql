-- Video Studio: shared library of vibe-coded Remotion templates.
-- All rows are global (visible to every user); `user_email` records the creator
-- for attribution only. A template_json blob holds { code, propsSchema, previewProps }.
-- The 4 built-in starter templates stay bundled in the client (their TSX lives in
-- .tpl files); this table holds templates authored on the Video Template page.

CREATE TABLE IF NOT EXISTS content_video_templates (
    id            INT          AUTO_INCREMENT PRIMARY KEY,
    user_email    VARCHAR(255) NULL,            -- creator (attribution only; all rows are shared)
    name          VARCHAR(255) NOT NULL,
    description   TEXT         NULL,
    template_json LONGTEXT     NOT NULL,         -- { code, propsSchema, previewProps }
    created_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_cvt_user (user_email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
