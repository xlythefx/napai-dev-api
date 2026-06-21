-- Content Studio: per-user projects + reusable design templates.

CREATE TABLE IF NOT EXISTS content_templates (
    id            INT           AUTO_INCREMENT PRIMARY KEY,
    user_email    VARCHAR(255)  NULL,            -- NULL = global starter template
    is_global     TINYINT(1)    NOT NULL DEFAULT 0,
    name          VARCHAR(255)  NOT NULL,
    description   TEXT          NULL,
    aspect_ratio  VARCHAR(8)    NOT NULL DEFAULT '1:1',
    canvas_json   LONGTEXT      NOT NULL,         -- a single CanvasItem JSON
    created_at    TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_ct_user   (user_email),
    INDEX idx_ct_global (is_global),
    INDEX idx_ct_ratio  (aspect_ratio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS content_projects (
    id            INT           AUTO_INCREMENT PRIMARY KEY,
    user_email    VARCHAR(255)  NOT NULL,
    name          VARCHAR(255)  NOT NULL DEFAULT 'Untitled project',
    project_json  LONGTEXT      NOT NULL,         -- { canvases: CanvasItem[] }
    created_at    TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_cp_user (user_email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---- Seed starter templates (global, available to everyone) ----

INSERT INTO content_templates (user_email, is_global, name, description, aspect_ratio, canvas_json) VALUES
(NULL, 1, 'Numbered Tip', 'Dark numbered knowledge slide', '1:1',
'{"id":"tpl","name":"Numbered Tip","aspectRatio":"1:1","width":1080,"height":1080,"imageElements":[],"background":{"type":"solid","color":"#0f172a","gradientFrom":"#6366f1","gradientTo":"#ec4899","gradientAngle":135,"src":null,"opacity":1},"textElements":[{"id":"t1","text":"01","x":540,"y":300,"fontSize":150,"color":"#38bdf8","fontFamily":"Oswald","fontWeight":"700","align":"center","originX":"center","originY":"center","width":880,"height":0,"opacity":1,"rotation":0},{"id":"t2","text":"Your headline goes here","x":540,"y":500,"fontSize":80,"color":"#ffffff","fontFamily":"Montserrat","fontWeight":"800","align":"center","originX":"center","originY":"center","width":900,"height":0,"opacity":1,"rotation":0},{"id":"t3","text":"Add a short supporting sentence that explains the idea in plain language.","x":540,"y":720,"fontSize":40,"color":"#cbd5e1","fontFamily":"Inter","fontWeight":"400","align":"center","originX":"center","originY":"center","width":820,"height":0,"opacity":1,"rotation":0}]}'),
(NULL, 1, 'Quote', 'Gradient centered quote', '1:1',
'{"id":"tpl","name":"Quote","aspectRatio":"1:1","width":1080,"height":1080,"imageElements":[],"background":{"type":"gradient","color":"#0f172a","gradientFrom":"#6366f1","gradientTo":"#ec4899","gradientAngle":135,"src":null,"opacity":1},"textElements":[{"id":"t1","text":"Design is how it works, not just how it looks.","x":540,"y":480,"fontSize":74,"color":"#ffffff","fontFamily":"Playfair Display","fontWeight":"700","align":"center","originX":"center","originY":"center","width":880,"height":0,"opacity":1,"rotation":0},{"id":"t2","text":"Your Name","x":540,"y":700,"fontSize":36,"color":"#f1f5f9","fontFamily":"Inter","fontWeight":"500","align":"center","originX":"center","originY":"center","width":700,"height":0,"opacity":1,"rotation":0}]}'),
(NULL, 1, 'Title Cover', 'Vertical title cover for stories/reels', '9:16',
'{"id":"tpl","name":"Title Cover","aspectRatio":"9:16","width":1080,"height":1920,"imageElements":[],"background":{"type":"gradient","color":"#111827","gradientFrom":"#111827","gradientTo":"#312e81","gradientAngle":160,"src":null,"opacity":1},"textElements":[{"id":"t1","text":"EPISODE 01","x":540,"y":760,"fontSize":46,"color":"#a5b4fc","fontFamily":"Oswald","fontWeight":"600","align":"center","originX":"center","originY":"center","width":900,"height":0,"opacity":1,"rotation":0},{"id":"t2","text":"The Future of AI Agents","x":540,"y":980,"fontSize":112,"color":"#ffffff","fontFamily":"Montserrat","fontWeight":"800","align":"center","originX":"center","originY":"center","width":920,"height":0,"opacity":1,"rotation":0},{"id":"t3","text":"A practical guide","x":540,"y":1200,"fontSize":50,"color":"#cbd5e1","fontFamily":"Inter","fontWeight":"400","align":"center","originX":"center","originY":"center","width":860,"height":0,"opacity":1,"rotation":0}]}'),
(NULL, 1, 'Banner', 'Wide left-aligned banner', '16:9',
'{"id":"tpl","name":"Banner","aspectRatio":"16:9","width":1920,"height":1080,"imageElements":[],"background":{"type":"solid","color":"#111827","gradientFrom":"#6366f1","gradientTo":"#ec4899","gradientAngle":135,"src":null,"opacity":1},"textElements":[{"id":"t1","text":"Build faster with AI","x":140,"y":470,"fontSize":120,"color":"#ffffff","fontFamily":"Montserrat","fontWeight":"800","align":"left","originX":"left","originY":"center","width":1500,"height":0,"opacity":1,"rotation":0},{"id":"t2","text":"Ship your ideas in record time.","x":140,"y":650,"fontSize":56,"color":"#94a3b8","fontFamily":"Inter","fontWeight":"400","align":"left","originX":"left","originY":"center","width":1300,"height":0,"opacity":1,"rotation":0}]}'),
(NULL, 1, 'Announcement', 'Light announcement card', '1:1',
'{"id":"tpl","name":"Announcement","aspectRatio":"1:1","width":1080,"height":1080,"imageElements":[],"background":{"type":"solid","color":"#f8fafc","gradientFrom":"#6366f1","gradientTo":"#ec4899","gradientAngle":135,"src":null,"opacity":1},"textElements":[{"id":"t1","text":"WE ARE HIRING","x":540,"y":380,"fontSize":56,"color":"#2563eb","fontFamily":"Oswald","fontWeight":"700","align":"center","originX":"center","originY":"center","width":900,"height":0,"opacity":1,"rotation":0},{"id":"t2","text":"Senior Product Designer","x":540,"y":540,"fontSize":84,"color":"#0f172a","fontFamily":"Montserrat","fontWeight":"800","align":"center","originX":"center","originY":"center","width":920,"height":0,"opacity":1,"rotation":0},{"id":"t3","text":"Join our team and help shape delightful product experiences. Apply now.","x":540,"y":760,"fontSize":40,"color":"#475569","fontFamily":"Inter","fontWeight":"400","align":"center","originX":"center","originY":"center","width":820,"height":0,"opacity":1,"rotation":0}]}');
