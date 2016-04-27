CREATE TABLE IF NOT EXISTS 'identities_imap_hosts' (
  'id' INTEGER NOT NULL PRIMARY KEY ASC,
  'domain' varchar(255) NOT NULL,
  'host' varchar(255) DEFAULT NULL,
  'ts' DATETIME NOT NULL
);

UPDATE 'system' SET value='initial|20140112' WHERE name='myrc_identities_imap';
