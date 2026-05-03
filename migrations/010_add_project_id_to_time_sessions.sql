ALTER TABLE time_sessions
  ADD COLUMN project_id INT NULL AFTER developer_id,
  ADD CONSTRAINT fk_session_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL;
