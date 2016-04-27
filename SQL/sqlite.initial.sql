CREATE TABLE IF NOT EXISTS 'identities_imap' (
  'id' INTEGER NOT NULL PRIMARY KEY ASC,
  'user_id' int(10) NOT NULL,
  'iid' int(10) NOT NULL,
  'username' varchar(256) DEFAULT NULL,
  'password' text,
  'server' varchar(256) DEFAULT NULL,
  'enabled' int(1) NOT NULL DEFAULT '0',
  'label' text,
  'preferences' text,
  CONSTRAINT 'identities_imap_ibfk_1' FOREIGN KEY ('user_id') REFERENCES 'users'
    ('user_id') ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE IF NOT EXISTS 'system' (
  name varchar(64) NOT NULL PRIMARY KEY,
  value text NOT NULL
);

INSERT INTO system (name, value) VALUES ('myrc_identities_imap', 'initial');

CREATE INDEX identities_imap_iid ON 'identities_imap'('iid');