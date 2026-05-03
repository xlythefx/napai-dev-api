-- Each document belongs to one chat (unique KB per chat)
ALTER TABLE documents ADD COLUMN chat_id INT NULL AFTER user_email;
ALTER TABLE documents ADD INDEX idx_chat_id (chat_id);
ALTER TABLE documents ADD CONSTRAINT fk_documents_chat
  FOREIGN KEY (chat_id) REFERENCES chats(id) ON DELETE CASCADE;
