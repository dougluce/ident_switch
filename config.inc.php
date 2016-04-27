<?php
/* Password encryption:
    'rcmail': encrypt passwords by default Roundcube methods.
    'secure': encrypt passwords by using the IMAP password as encryption key.
              NOTE: When using 'secure' encryption, If IMAP passwords are changed
              using methods other than Roundcube Webmail interface
              (hmail_password or password plugin) then IMAP server identities
              passwords must be re-entered by users.
*/
$rcmail_config['identities_imap_crypt'] = 'rcmail';

/* password encryption salt (only used for secure encryption) */
$rcmail_config['identities_imap_salt'] = '!kQm*fF3pXe1Kbm%9';

/* predefined imap hosts (associated with the domain part of the identity email property) */
$rcmail_config['identities_imap_external'] = array(
);

$rcmail_config['identities_imap_internal'] = array(
  'BoresSoft Webmail' => array(
    'host' =>'localhost',
    'delimiter' => '/',
    'readonly' => false, // on match prevent field editing
  ),
);
?>
