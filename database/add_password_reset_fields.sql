-- Add password reset token fields to usuarios table
ALTER TABLE usuarios 
ADD COLUMN IF NOT EXISTS reset_token VARCHAR(64) NULL,
ADD COLUMN IF NOT EXISTS reset_token_expiry DATETIME NULL;

-- Add composite index for faster lookups on both token and expiry
-- This is more efficient since we always query both fields together
ALTER TABLE usuarios ADD INDEX IF NOT EXISTS idx_reset_token_expiry (reset_token, reset_token_expiry);
