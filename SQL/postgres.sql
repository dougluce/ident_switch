CREATE TABLE identities_imap (
  id serial PRIMARY KEY,
  user_id integer NOT NULL REFERENCES users(user_id) ON DELETE CASCADE ON UPDATE CASCADE,
  iid integer NOT NULL REFERENCES identities(identity_id) ON DELETE CASCADE ON UPDATE CASCADE,
  username varchar(256) DEFAULT NULL,
  password text,
  server varchar(256) DEFAULT NULL,
  enabled smallint NOT NULL DEFAULT 0,
  label text,
  preferences text
);

CREATE INDEX ix_identities_imap_user_id ON identities_imap(user_id);
CREATE INDEX ix_identities_imap_iid ON identities_imap(iid);
