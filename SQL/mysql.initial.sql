CREATE TABLE IF NOT EXISTS ident_switch
(
	id
		int(10) UNSIGNED
		NOT NULL
		AUTO_INCREMENT,
	user_id
		int(10) UNSIGNED
		NOT NULL,
	iid
		int(10) UNSIGNED
		NOT NULL,
	username
		varchar(64),
	password
		varchar(64),
	host
		varchar(64),
	port
		int
		CHECK(port > 0 AND port <= 65535),
	delimiter
		char(1),
	label
		varchar(32),
	flags
		int
		NOT NULL
		DEFAULT 0,
	UNIQUE KEY user_id_label (user_id, label),
	CONSTRAINT fk_user_id FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE ON UPDATE CASCADE,
	CONSTRAINT fk_identity_id FOREIGN KEY (iid) REFERENCES identities(identity_id) ON DELETE CASCADE ON UPDATE CASCADE,
	PRIMARY KEY(id),
	INDEX IX_ident_switch_user_id (user_id),
	INDEX IX_ident_switch_iid (iid)
);

