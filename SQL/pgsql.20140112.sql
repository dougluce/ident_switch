CREATE TABLE identities_imap_hosts (
  id serial PRIMARY KEY,
  domain varchar(255) NOT NULL,
  host varchar(255) DEFAULT NULL,
  ts timestamp NOT NULL
);

UPDATE "system" SET value='initial|20140112' WHERE name='myrc_identities_imap';
