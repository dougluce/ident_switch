<?php
/*
 * Localization file for ident_switch plugin
 */
$labels = array();

// IMAP
$labels['form.caption'] = 'IMAP';

// Enabled
$labels['form.enabled'] = 'Aktiviert';

// Label
$labels['form.label'] = 'Bezeichnung';

// Server host name
$labels['form.host']='Servername';

// Secure connection (SSL/TLS)
$labels['form.secure']='Verbindungssicherheit';

// Port
$labels['form.port']='Port';

// Username
$labels['form.username'] = 'Benutzername';

// Password
$labels['form.password'] = 'Passwort';

// Folder hierarchy delimiter
$labels['form.delimiter'] = 'Hierachietrennzeichen für Verzeichnisse';

// Value in \'Label\' field of IMAP section is too long (32 chars max).
$labels['err.label.long'] = 'Der Wert in Feld \'Bezeichnung\' im Abschnitt IMAP ist zu lang (max. 32 Zeichen).';

// Value in \'Server host name\' field of IMAP section is too long (64 chars max).
$labels['err.host.long'] = 'Der Wert in Feld \'Servername\' im Abschnitt IMAP ist zu lang (max. 64 Zeichen).';

// Value in \'Username\' field of IMAP section is too long (64 chars max).
$labels['err.user.long'] = 'Der Wert in Feld \'Benutzername\' im Abschnitt IMAP ist zu lang (max. 64 Zeichen).';

// Value in \'Folder hierarchy delimiter\' field of IMAP section is too long (1 char max).
$labels['err.delim.long'] = 'Der Wert in Feld \'Hierachietrennzeichen für Verzeichnisse\' im Abschnitt IMAP ist zu lang (max. 1 Zeichen).';

// Value in \'Port\' field of IMAP section must be a number.
$labels['err.port.num'] = 'Der Wert in Feld \'Port\' im Abschnitt IMAP muss eine Zahl sein.';

// Value in \'Port\' field of IMAP section must be between 1 and 65535.
$labels['err.port.range'] = 'Der Wert in Feld \'Port\' im Abschnitt IMAP muss 1 und 65535 liegen.';
