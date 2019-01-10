ALTER TABLE
  ident_switch
RENAME COLUMN
  host
TO
  imap_host;

ALTER TABLE
  ident_switch
RENAME COLUMN
  port
TO
  imap_port;

ALTER TABLE
  ident_switch
ADD COLUMN
	smtp_host
    varchar(64);

ALTER TABLE
  ident_switch
ADD COLUMN
  smtp_port
		int;