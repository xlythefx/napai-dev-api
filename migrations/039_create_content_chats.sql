-- AI sidebar chat: per-project chat sessions across all studios.
-- Each row is one chat tab: a title, the resumable Claude Agent SDK session id
-- (memory), and the streamed transcript. Keyed by (project_type, project_id) so
-- each video/content/slideshow/template project owns its own list of chats.

CREATE TABLE IF NOT EXISTS content_chats (
    id                INT           AUTO_INCREMENT PRIMARY KEY,
    user_email        VARCHAR(255)  NOT NULL,
    project_type      VARCHAR(32)   NOT NULL,      -- 'video' | 'content' | 'slideshow' | 'template'
    project_id        VARCHAR(64)   NOT NULL,      -- the studio project id (string form)
    title             VARCHAR(255)  NOT NULL DEFAULT 'Chat 1',
    claude_session_id VARCHAR(128)  NULL,          -- resumable Agent SDK session id
    messages_json     LONGTEXT      NULL,          -- [{ role, content, thinking? }]
    created_at        TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    updated_at        TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_cc_lookup (user_email, project_type, project_id, updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
