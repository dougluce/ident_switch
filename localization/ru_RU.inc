<?php
/*
 * Localization file for ident_switch plugin
 */
$labels = array();

// IMAP
$labels['form.caption'] = 'IMAP';

// Enabled
$labels['form.enabled'] = 'Включено';

// Label
$labels['form.label'] = 'Название';

// Server host name
$labels['form.host'] = 'Адрес сервера';

// Secure connection (SSL/TLS)
$labels['form.secure'] = 'Защита подключения';

// Port
$labels['form.port'] = 'Порт';

// Username
$labels['form.username'] = 'Имя пользователя';

// Password
$labels['form.password'] = 'Пароль';

// Folder hierarchy delimiter
$labels['form.delimiter'] = 'Разделитель в иерархии папок';

// Value in \'Label\' field of IMAP section is too long (32 chars max).
$labels['err.label.long'] = '\'Название\' в разделе IMAP должно быть не длинее 32 символов.';

// Value in \'Server host name\' field of IMAP section is too long (64 chars max).
$labels['err.host.long'] = '\'Адрес сервера\' в разделе IMAP должен быть не длинее 64 символов.';

// Value in \'Username\' field of IMAP section is too long (64 chars max).
$labels['err.user.long'] = '\'Имя пользователя\' в секции IMAP должно быть не длинее 64 символов.';

// Value in \'Folder hierarchy delimiter\' field of IMAP section is too long (1 char max).
$labels['err.delim.long'] = '\'Разделитель в иерархии папок\' в секции IMAP должен быть не длинее 1 символа.';

// Value in \'Port\' field of IMAP section must be a number.
$labels['err.port.num'] = '\'Порт\' в секции IMAP должен быть числом.';

// Value in \'Port\' field of IMAP section must be between 1 and 65535.
$labels['err.port.range'] = '\'Порт\' в секции IMAP должен быть в диапазоне от 1 до 65535.';
