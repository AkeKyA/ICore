CREATE TABLE IF NOT EXISTS simpleauth_players (
  name VARCHAR(16) PRIMARY KEY,
  hash CHAR(128),
  lastip VARCHAR(50)
);