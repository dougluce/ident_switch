<?php
/*
 * Localization file for ident_switch plugin
 */
$labels = array();

// IMAP
$labels['form.caption'] = 'IMAP';

// Enabled
$labels['form.enabled'] = 'Activé';

// Label
$labels['form.label'] = 'Nom d\'affichage';

// Server host name
$labels['form.host']='Nom d\'hôte du serveur';

// Secure connection (SSL/TLS)
$labels['form.secure']='Sécurité de la connexion';

// Port
$labels['form.port']='Port';

// Username
$labels['form.username'] = 'Nom d\'utilisateur';

// Password
$labels['form.password'] = 'Mot de passe';

// Folder hierarchy delimiter
$labels['form.delimiter'] = 'Délimiteur de la hiérarchie des dossiers';

// Value in \'Label\' field of IMAP section is too long (32 chars max).
$labels['err.label.long'] = 'La valeur du champ \'Nom d\'affichage\' de la section IMAP est trop longue (32 car. max).';

// Value in \'Server host name\' field of IMAP section is too long (64 chars max).
$labels['err.host.long'] = 'La valeur du champ \'Nom d\'hôte du serveur\' de la section IMAP est trop longue (64 car. max).';

// Value in \'Username\' field of IMAP section is too long (64 chars max).
$labels['err.user.long'] = 'La valeur du champ \'Nom d\'utilisateur\' de la section IMAP est trop longue (64 car. max).';

// Value in \'Folder hierarchy delimiter\' field of IMAP section is too long (1 char max).
$labels['err.delim.long'] = 'La valeur du champ \'Délimiteur de la hiérarchie des dossiers\' de la section IMAP est trop longue (1 car. max).';

// Value in \'Port\' field of IMAP section must be a number.
$labels['err.port.num'] = 'La valeur du champ \'Port\' de la section IMAP doit être un nombre.';

// Value in \'Port\' field of IMAP section must be between 1 and 65535.
$labels['err.port.range'] = 'La valeur du champ \'Port\' de la section IMAP doit être comprise entre 1 et 65535.';
