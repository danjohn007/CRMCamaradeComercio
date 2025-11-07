-- Add password reset token fields to usuarios table
ALTER TABLE usuarios 
ADD COLUMN IF NOT EXISTS reset_token VARCHAR(64) NULL,
ADD COLUMN IF NOT EXISTS reset_token_expiry DATETIME NULL;

-- Add index for faster lookups
ALTER TABLE usuarios ADD INDEX IF NOT EXISTS idx_reset_token (reset_token);
