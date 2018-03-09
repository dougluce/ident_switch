<?php
/**
 * Identities IMAP
 *
 * This plugin allows fast switching between accounts.
 *
 * @version 1.0
 * @author Boris Gulay
 * @url 
 */
class ident_switch extends rcube_plugin
{
	public $task='?(?!login|logout).*';

	const TABLE = 'ident_switch';
	const MY_POSTFIX = '_iswitch';

	// Flags user in database
	const DB_ENABLED		= 1;
	const DB_SECURE_SSL		= 2;
	const DB_SECURE_TLS		= 4;

	function init()
	{
		$this->add_hook('startup', array($this, 'on_startup'));
		$this->add_hook('render_page', array($this, 'on_render_page'));
		$this->add_hook('smtp_connect', array($this, 'on_smtp_connect'));
		$this->add_hook('identity_form', array($this, 'on_identity_form'));
		$this->add_hook('identity_update', array($this, 'on_identity_update'));
		$this->add_hook('identity_create', array($this, 'on_identity_create'));
		$this->add_hook('identity_create_after', array($this, 'on_identity_create_after'));
		$this->add_hook('identity_delete', array($this, 'on_identity_delete'));
		$this->add_hook('template_object_composeheaders', array($this, 'on_template_object_composeheaders'));

		$this->register_action('plugin.ident_switch.switch', array($this, 'on_switch'));
	}

	function on_startup($args)
	{
		$rc = rcmail::get_instance();

		if (strcasecmp($rc->user->data['username'], $_SESSION['username']) !== 0)
		{ // We are impersonating
			$rc->config->set('imap_cache', null);
			$rc->config->set('messages_cache', false);

			if ($args['task'] == 'mail')
			{
				$this->add_texts('localization/');
				$rc->config->set('create_default_folders', false);
			}
		}

		return $args;
	}

	function on_render_page($args)
	{
		$rc = rcmail::get_instance();

		// Currently selected identity
		$iid = $_SESSION['iid' . self::MY_POSTFIX];

		$iid_int = 0;
		if (is_int($iid))
			$iid_int = $iid;
		elseif ($iid === '-1')
			$iid_int = -1;
		elseif (ctype_digit($iid))
			$iid_int = intval($iid);

		// Get list of alternative accounts
		$sOpt = '';
		$sql = 'SELECT id, iid, label, username FROM ' . $rc->db->table_name(self::TABLE) . ' WHERE user_id = ? AND flags & ? > 0';
		$qRec = $rc->db->query($sql, $rc->user->data['user_id'], self::DB_ENABLED);
		while ($r = $rc->db->fetch_assoc($qRec))
		{
			$opts = array('value' => $r['id']);
			if ($iid_int == $r['iid'])
				$opts['selected'] = 'selected';

			// Make label
			$lbl = $r['label'];
			if (!$lbl)
			{
				if (!$r['username'])
				{ // Load email from identity
					$sql = 'SELECT email FROM ' . $rc->db->table_name('identities') . ' WHERE identity_id = ?';
					$q = $rc->db->query($sql, $r['iid']);
					$rIid = $rc->db->fetch_assoc($q);

					$r['username'] = $rIid['email'];
				}

				if (strpos($r['username'], '@') === false)
					$lbl = $r['username'] . '@' . ($r['host'] ? $r['host'] : 'localhost');
				else
					$lbl = $r['username'];
			}

			$sOpt .= html::tag(
				'option',
				$opts,
				rcube::Q($lbl)
			);
		}

		// Render UI if user has extra accounts
		if (!empty($sOpt))
		{
			// Add main account
			$opts = array('value' => -1);
			if (!$iid || $iid_int == -1)
				$opts['selected'] = 'selected';

			$sOpt = html::tag(
				'option',
				$opts,
				$_SESSION['global_alias'] ? $_SESSION['global_alias'] : $rc->user->data['username']
			) . $sOpt;

			$this->include_script('ident_switch.js');
			$sw = html::tag(
				'select', 
				array(
					'id' => 'plugin-ident_switch-account',
					'style' => 'display: none; padding: 0;',
					'onchange' => 'plugin_switchIdent_switch(this.value);',
				),
				$sOpt
			);
			$rc->output->add_footer($sw);
		}

		return $args;
	}

	function on_smtp_connect($args)
	{
		$rc = rcmail::get_instance();

		// TODO: Rewrite with full settings!

		if (strcasecmp($rc->user->data['username'], $_SESSION['username']) !== 0)
		{
			if ($args['smtp_user'] == '%u')
				$args['smtp_user'] = $rc->user->data['username'];
			if ($args['smtp_pass'] == '%p')
				$args['smtp_pass'] = $rc->decrypt($_SESSION['password' . self::MY_POSTFIX]);
		}

		return $args;
	}

	function on_identity_form($args)
	{
		$rc = rcmail::get_instance();

		// Do not show options for default identity
		if (strcasecmp($args['record']['email'], $rc->user->data['username']) === 0)
				return $args;

		$this->add_texts('localization');

		// Create our field set
		$args['form']['ident_switch'] = array(
			'name' => $this->gettext('form.caption'),
			'content' => array(
				'ident_switch.form.enabled' => array('type' => 'checkbox', 'onchange' => 'plugin_switchIdent_enabled_onChange();'),
				'ident_switch.form.label' => array('type' => 'text', 'size' => 32, 'placeholder' => $args['record']['email']),
				'ident_switch.form.host' => array('type' => 'text', 'size' => 64, 'placeholder' => 'localhost'),
				'ident_switch.form.secure' => array(
					'type' => 'select', 
					'options' => array('ssl' => 'SSL', 'tls' => 'TLS'), 
					'onchange' => 'plugin_switchIdent_secure_onChange();'
				),
				'ident_switch.form.port' => array('type' => 'text', 'size' => 5),
				'ident_switch.form.username' => array('type' => 'text', 'size' => 64, 'placeholder' => $args['record']['email']),
				'ident_switch.form.password' => array('type' => 'password', 'size' => 64),
				'ident_switch.form.delimiter' => array('type' => 'text', 'size' => 1, 'placeholder' => '.'),
				'ident_switch.form.readonly' => array('type' => 'hidden'),
			),
		);

		// Load data if exists
		if (isset($args['record']['identity_id']))
		{
			$sql = 'SELECT * FROM ' . $rc->db->table_name(self::TABLE) . ' WHERE iid = ? AND user_id = ?';
			$q = $rc->db->query($sql, $args['record']['identity_id'], $rc->user->ID);
			$r = $rc->db->fetch_assoc($q);
			if ($r)
			{
				foreach ($r as $k => $v)
					$args['record']['ident_switch.form.' . $k] = $v;

				// Parse flags
				if ($r['flags'] & self::DB_ENABLED)
					$args['record']['ident_switch.form.enabled'] = true;
				if ($r['flags'] & self::DB_SECURE_TLS) // TLS has priority
					$args['record']['ident_switch.form.secure'] = 'tls';
				elseif ($r['flags'] & self::DB_SECURE_SSL)
					$args['record']['ident_switch.form.secure'] = 'ssl';

				// Set readonly if needed
				$cfg = $this->get_preconfig($args['record']['email']);
				if (is_array($cfg) && $cfg['readonly'])
				{
					$args['record']['ident_switch.form.readonly'] = 1;
					if (in_array(strtoupper($cfg['user']), array('EMAIL', 'MBOX')))
						$args['record']['ident_switch.form.readonly'] = 2;
				}
			}
			else
				$this->apply_preconfig($args['record']);
		}

		return $args;
	}

	function on_identity_update($args)
	{
		$rc = rcmail::get_instance();

		// Do not do anything for default identity
		if (strcasecmp($args['record']['email'], $rc->user->data['username']) === 0)
			return $args;

		// Process boolean fields
		if (!rcube_utils::get_input_value('_ident_switch_form_enabled', rcube_utils::INPUT_POST))
		{
			self::sw_imap_off($args['iid']);
			return $args;
		}

		$data = self::check_field_values();
		if ($data['err'])
		{
			$this->add_texts('localization');
			$rc->output->show_message('ident_switch.err.' . $data['err'], 'error');
			$args['abort'] = true;
			return $args;
		}

		$data['id'] = $args['id'];
		self::save_field_values($rc, $data);

		return $args;
	}

	function on_identity_create($args)
	{
		$rc = rcmail::get_instance();

		// Do not do anything for default identity
		if (strcasecmp($args['record']['email'], $rc->user->data['username']) === 0)
			return $args;

		// Process boolean fields
		if (!rcube_utils::get_input_value('_ident_switch_form_enabled', rcube_utils::INPUT_POST))
				return $args;

		$data = self::check_field_values();
		if ($data['err'])
		{
			$this->add_texts('localization');
			$rc->output->show_message('ident_switch.err.' . $data['err'], 'error');
			$args['abort'] = true;
		}

		// Save data for _after (cannot pass with $args)
		$_SESSION['createData' . self::MY_POSTFIX] = $data;

		return $args;
	}

	function on_identity_create_after($args)
	{
		$rc = rcmail::get_instance();

		// Do not do anything for default identity
		if (strcasecmp($args['record']['email'], $rc->user->data['username']) === 0)
			return $args;

		$data = $_SESSION['createData' . self::MY_POSTFIX];

		unset($_SESSION['createData' . self::MY_POSTFIX]);
		if (!$data || count($data) == 0)
			self::write_log('Object with ident_switch values not found in session for ID = ' . $args['id'] . '.');
		else
		{
			$data['id'] = $args['id'];
			self::save_field_values($rc, $data);
		}

		return $args;
	}

	function on_identity_delete($args)
	{
		$rc = rcmail::get_instance();

		$sql = 'DELETE FROM ' . $rc->db->table_name(self::TABLE) . ' WHERE iid = ? AND user_id = ?';
		$q = $rc->db->query($sql, $args['id'], $rc->user->ID);

		if ($rc->db->affected_rows($q))
			self::write_log('Deleted associated information for identity with ID = ' . $args['id'] . '.');

		return $args;
	}

	function on_template_object_composeheaders($args)
	{
		if ($args['id'] == '_from')
		{
			$rc = rcmail::get_instance();
			if (strcasecmp($_SESSION['username'], $rc->user->data['username']) !== 0)
			{
				if (isset($_SESSION['iid' . self::MY_POSTFIX]))
					$rc->output->add_script('plugin_switchIdent_fixIdent(' . $_SESSION['iid' . self::MY_POSTFIX] . ');', 'docready');
				else
					self::write_log('Special session variable with active identity ID not found.');
			}
		}
	}

	private static function check_field_values()
	{
		$retVal = array();

		$retVal['label'] = self::ntrim(rcube_utils::get_input_value('_ident_switch_form_label', rcube_utils::INPUT_POST));
		if (strlen($retVal['label']) > 32)
			$retVal['err'] = 'label.long';
		else
		{
			$retVal['host'] = self::ntrim(rcube_utils::get_input_value('_ident_switch_form_host', rcube_utils::INPUT_POST));
			if (strlen($retVal['host']) > 64)
				$retVal['err'] = 'host.long';
			else
			{
				$retVal['port'] = self::ntrim(rcube_utils::get_input_value('_ident_switch_form_port', rcube_utils::INPUT_POST));
				if ($retVal['port'] && !ctype_digit($retVal['port']))
					$retVal['err'] = 'port.num';
				else
				{
					if ($retVal['port'] && ($retVal['port'] <= 0 || $retVal['port'] > 65535))
						$retVal['err'] = 'port.range';
					else
					{
						$retVal['user'] = self::ntrim(rcube_utils::get_input_value('_ident_switch_form_username', rcube_utils::INPUT_POST));
						if (strlen($retVal['user']) > 64)
							$retVal['err'] = 'user.long';
						else
						{
							$retVal['delim'] = self::ntrim(rcube_utils::get_input_value('_ident_switch_form_delimiter', rcube_utils::INPUT_POST));
							if (strlen($retVal['delim']) > 1)
								$retVal['err'] = 'delim.long';
						}
					}
				}
			}
		}

		// Get also password
		$retVal['pass'] = rcube_utils::get_input_value('_ident_switch_form_password', rcube_utils::INPUT_POST);

		// Parse secure settings
		$retVal['flags'] = self::DB_ENABLED;
		$ssl = rcube_utils::get_input_value('_ident_switch_form_secure', rcube_utils::INPUT_POST);
		if (strcasecmp($ssl, 'tls') === 0)
			$retVal['flags'] |= self::DB_SECURE_TLS;
		elseif (strcasecmp($ssl, 'ssl') === 0)
			$retVal['flags'] |= self::DB_SECURE_SSL;

		return $retVal;
	}


	private static function save_field_values($rc, $data)
	{
		$sql = 'SELECT id, password FROM ' . $rc->db->table_name(self::TABLE) . ' WHERE iid = ? AND user_id = ?';
		$q = $rc->db->query($sql, $args['id'], $rc->user->ID);
		$r = $rc->db->fetch_assoc($q);
		if ($r)
		{ // Record already exists, will update it
			$sql = 'UPDATE ' .
				$rc->db->table_name(self::TABLE) .
				' SET flags = ?, label = ?, host = ?, port = ?, username = ?, password = ?, delimiter = ?, user_id = ?, iid = ?' .
				' WHERE id = ?';
		}
		else if ($data['flags'] & self::DB_ENABLED)
		{ // No record exists, create new one
			$sql = 'INSERT INTO ' .
				$rc->db->table_name(self::TABLE) .
				'(flags, label, host, port, username, password, delimiter, user_id, iid) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)';
		}

		if ($sql)
		{
			// Do we need to update pwd?
			if ($data['pass'] != $r['password'])
				$data['pass'] = $rc->encrypt($data['pass']);

			$rc->db->query(
				$sql,
				$data['flags'],
				$data['label'],
				$data['host'],
				$data['port'],
				$data['user'],
				$data['pass'],
				$data['delim'],
				$rc->user->ID,
				$data['id'],
				$r['id']
			);

			return true;
		}

		return false;
	}

	function on_switch()
	{
		$rc = rcmail::get_instance();

		$my_postfix_len = strlen(self::MY_POSTFIX);
		$identId = rcube_utils::get_input_value('_ident-id', rcube_utils::INPUT_POST);

		if (-1 == $identId)
		{ // Switch to main account
			self::write_log('Switching mailbox back to default.');

			// Restore everything with STORAGE*my_postfix
			foreach ($_SESSION as $k => $v)
			{
				if (strncasecmp($k, 'storage', 7) === 0 && substr_compare($k, self::MY_POSTFIX, -$my_postfix_len, $my_postfix_len) === 0)
				{
					$realKey = substr($k, 0, -$my_postfix_len);
					$_SESSION[$realKey] = $_SESSION[$k];
					$rc->session->remove($k);
				}
			}
			$_SESSION['username'] = $rc->user->data['username'];
			$_SESSION['password'] = $_SESSION['password' . self::MY_POSTFIX];
			$_SESSION['iid' . self::MY_POSTFIX] = -1;
		}
		else
		{
			$sql = 'SELECT host, flags, port, username, password, iid FROM ' . $rc->db->table_name(self::TABLE) . ' WHERE id = ? AND user_id = ?';
			$q = $rc->db->query($sql, $identId ,$rc->user->ID);
			$r = $rc->db->fetch_assoc($q);
			if (is_array($r))
			{
				if (!$r['username'])
				{ // Load email from identity
					$sql = 'SELECT email FROM ' . $rc->db->table_name('identities') . ' WHERE identity_id = ?';
					$q = $rc->db->query($sql, $r['iid']);
					$rIid = $rc->db->fetch_assoc($q);

					$r['username'] = $rIid['email'];
				}

				self::write_log('Switching mailbox to one for identity with ID = ' . $r['iid'] . ' (username = \'' . $r['username'] . '\').');

				$def_port = 143; // Default port here!
				$ssl = null;
				if ($r['flags'] & self::DB_SECURE_TLS)
				{
					$ssl = 'tls';
					$def_port = 143; // Default TLS port here!
				}
				elseif ($r['flags'] & self::DB_SECURE_SSL)
				{
					$ssl = 'ssl';
					$def_port = 993; // Default SSL port here!
				}

				$port = $r['port'];
				if (!$port)
					$port = $def_port;

				// If we are in default account now
				// save everything with STORAGE
				if ($_SESSION['username'] == $rc->user->data['username'])
				{
					foreach ($_SESSION as $k => $v)
					{
						if (strncasecmp($k, 'storage', 7) === 0 && substr_compare($k, self::MY_POSTFIX, -$my_postfix_len, $my_postfix_len) !== 0)
						{
							if (!$_SESSION[$k . self::MY_POSTFIX])
								$_SESSION[$k . self::MY_POSTFIX] = $_SESSION[$k];
                            $rc->session->remove($k);
						}
					}
				}
				if (!$_SESSION['password' . self::MY_POSTFIX])
					$_SESSION['password' . self::MY_POSTFIX] = $_SESSION['password'];

				$_SESSION['storage_host'] = $r['host'] ? $r['host'] : 'localhost'; // Default host here!
				$_SESSION['storage_ssl'] = $ssl;
				$_SESSION['storage_port'] = $port;
				$_SESSION['username'] = $r['username'];
				$_SESSION['password'] = $r['password'];
				$_SESSION['iid' . self::MY_POSTFIX] = $r['iid'];

				$rc->session->remove('folders');
			}
			else
			{
				// TODO: Show message in browser
				self::write_log('Requested remote mailbox with ID = ' . $identId . ' not found.');
				return;
			}
		}

		$rc->output->redirect(
			array(
				'_task' => 'mail',
				'_mbox' => 'INBOX',
			)
		);
	}

	private static function sw_imap_off($iid)
	{
		$rc = rcmail::get_instance();
		
		$sql = 'UPDATE ' . $rc->db->table_name(self::TABLE) . ' SET flags = flags & ? WHERE iid = ? AND user_id = ?';
		$rc->db->query($sql, ~self::DB_ENABLED, $iid, $rc->user->ID);
	}

	private function get_preconfig($email)
	{
		$dom = substr(strstr($email, '@'), 1);
		if (!$dom)
			return false;

		$this->load_config(); // config.inc.php

		$cfg = rcmail::get_instance()->config->get('ident_switch.preconfig', array());
		$cfg = $cfg[$dom];

		if ($cfg)
		{
			if (!$cfg['host'])
				return false; # Host must be specified!
		}
		return $cfg;
	}

	private function apply_preconfig(&$record)
	{
		$email = $record['email'];
		$cfg = $this->get_preconfig($email);
		if (is_array($cfg))
		{
			self::write_log('Applying predefined configuration for \'' . $email . '\'.');

			if ($cfg['host'])
			{ // Parse and set host and related
				$urlArr = parse_url($cfg['host']);

				$record['ident_switch.form.host'] = $urlArr['host'] ? rcube::Q($urlArr['host'], 'url') : '';
				$record['ident_switch.form.port'] = $urlArr['port'] ? intval($urlArr['port']) : '';

				if (strcasecmp('tls', $urlArr['scheme']) === 0)
					$record['ident_switch.form.secure'] = 'tls';
				elseif (strcasecmp('ssl', $urlArr['scheme']) === 0)
					$record['ident_switch.form.secure'] = 'ssl';
				else
					$record['ident_switch.form.secure'] = '';
			}

			$loginSet = false;
			if ($cfg['user'])
			{ // Set up user name
				switch (strtoupper($cfg['user']))
				{
				case 'EMAIL':
					$record['ident_switch.form.username'] = $email;
					$loginSet = true;
					break;
				case 'MBOX':
					$record['ident_switch.form.username'] = strstr($email, '@', true);
					$loginSet = true;
					break;
				}
			}

			if ($cfg['readonly'])
			{
				$record['ident_switch.form.readonly'] = 1;
				if ($loginSet)
					$record['ident_switch.form.readonly'] = 2;
			}

			return $cfg['readonly'];
		}
	}

	private static function ntrim($str)
	{
		if (is_null($str))
			return $str;

		$s = trim($str);
		if (!$s)
			return null;

		return $s;
	}

	private static function write_log($txt)
	{
		rcmail::get_instance()->write_log('ident_switch', $txt);
	}
}
