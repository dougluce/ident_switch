# ident_switch
ident_switch plugin for Roundcube

This plugin allows users to switch between different accounts (including remote) in single Roundcube session.

*Inspired by identities_imap plugin that is no longer supported.*

### Where to start ###
* In settigs interface create new identity.
* For all identities except default you will see new section of settings - IMAP. Enter data required to connect to  remote server. Don't forget to check Enabled check box.
* After you have created at least one identity with active IMAP settings you will see combobox in the top right corner instead of plain text field with account name. It will allows you to switch to another account.

### Version compatibility ###
* Branch 1.X - for Roundcube v1.1
* Branch 2.X - for Roundcube v1.2
* Branch 3.X - for Roundcube v1.3

Please specify verion like "~2.0" in your composer.json file for ident_switch requirement. In this case you will stay inside compatible branch until you manually update ypur Roundcube installation.

### Switching SMTP ###
Plugin also switched SMTP credentials but only for server specified in general config. You should use %u and %p substitutions for user and password to make it work. This substitutions are replaced by username and password for selected IMAP account.
