-- Slideshow Studio: per-user animated slideshows (rendered as DOM + framer-motion,
-- exported to video). Mirrors content_projects from migration 034.

CREATE TABLE IF NOT EXISTS content_slideshows (
    id              INT           AUTO_INCREMENT PRIMARY KEY,
    user_email      VARCHAR(255)  NOT NULL,
    name            VARCHAR(255)  NOT NULL DEFAULT 'Untitled slideshow',
    slideshow_json  LONGTEXT      NOT NULL,         -- { settings: DeckSettings, slides: Slide[] }
    created_at      TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_cs_user (user_email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
