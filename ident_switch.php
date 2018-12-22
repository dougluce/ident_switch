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
	const DB_ENABLED		    = 1;
	//const DB_SECURE_SSL		= 2; // Not supported any more
	const DB_SECURE_IMAP_TLS	= 4;

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

		switch ($rc->task)
		{
		case 'mail':
			$this->render_switch($rc, $args);
			break;
		case 'settings':
			$this->include_script('ident_switch-form.js');
			break;
		}


		return $args;
	}

	private function render_switch($rc, $args)
	{
		// Currently selected identity
		$iid_s = $_SESSION['iid' . self::MY_POSTFIX];

		$iid = 0;
		if (is_int($iid_s))
			$iid = $iid_s;
		elseif ($iid_s === '-1')
			$iid = -1;
		elseif (ctype_digit($iid_s))
			$iid = intval($iid_s);

		// Get list of alternative accounts
		$sOpt = '';
		$sql = 'SELECT id, iid, label, username FROM ' . $rc->db->table_name(self::TABLE) . ' WHERE user_id = ? AND flags & ? > 0';
		$qRec = $rc->db->query($sql, $rc->user->data['user_id'], self::DB_ENABLED);
		while ($r = $rc->db->fetch_assoc($qRec))
		{
			$opts = array('value' => $r['id']);
			if ($iid == $r['iid'])
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
			if ($iid <= 0)
				$opts['selected'] = 'selected';

			$sOpt = html::tag(
				'option',
				$opts,
				$_SESSION['global_alias'] ? $_SESSION['global_alias'] : $rc->user->data['username']
			) . $sOpt;

			$this->include_script('ident_switch-switch.js');
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
	}

	function on_smtp_connect($args)
	{
		$iid = $_SESSION['iid' . self::MY_POSTFIX];
		if (!is_integer($iid) || $iid == -1)
			return $args;

		$rc = rcmail::get_instance();

		$sql = 'SELECT smtp_host, flags, smtp_port, username, password FROM ' . $rc->db->table_name(self::TABLE) . ' WHERE iid = ? AND user_id = ?';
		$q = $rc->db->query($sql, $iid ,$rc->user->ID);
		$r = $rc->db->fetch_assoc($q);
		if (is_array($r))
		{
			if (!$r['username'])
			{ // Load email from identity
				$sql = 'SELECT email FROM ' . $rc->db->table_name('identities') . ' WHERE identity_id = ?';
				$q = $rc->db->query($sql, $iid);
				$rIid = $rc->db->fetch_assoc($q);

				$r['username'] = $rIid['email'];
			}

			$args['smtp_user'] = $r['username'];
			$args['smtp_pass'] = $rc->decrypt($r['password']);
			$args['smtp_server'] = $r['smtp_host'] ? $r['smtp_host'] : 'localhost'; // Default SMTP host here
			$args['smtp_port'] = $r['smtp_port'] ? $r['smtp_port'] : 587; // Default SMTP port here

			self::write_log(print_r($args, true));
		}

		return $args;
	}

	private static function get_common_form(&$record)
	{
		$prefix = 'ident_switch.form.common.';
		return array(
			$prefix . 'enabled' => array('type' => 'checkbox', 'onchange' => 'plugin_switchIdent_enabled_onChange();'),
			$prefix . 'label' => array('type' => 'text', 'size' => 32, 'placeholder' => $record['email']),
			$prefix . 'readonly' => array('type' => 'hidden'),
		);
	}

	private static function get_imap_form(&$record)
	{
		$prefix = 'ident_switch.form.imap.';
		return array(
			$prefix . 'host' => array('type' => 'text', 'size' => 64, 'placeholder' => 'localhost'),
			$prefix . 'port' => array('type' => 'text', 'size' => 5, 'placeholder' => 143),
			$prefix . 'tls' => array('type' => 'checkbox'),
			$prefix . 'username' => array('type' => 'text', 'size' => 64, 'placeholder' => $record['email']),
			$prefix . 'password' => array('type' => 'password', 'size' => 64),
		);
	}

	private static function get_smtp_form(&$record)
	{
		$prefix = 'ident_switch.form.smtp.';
		return array(
			$prefix . 'host' => array('type' => 'text', 'size' => 64, 'placeholder' => 'localhost'),
			$prefix . 'port' => array('type' => 'text', 'size' => 5, 'placeholder' => 587),
		);
	}

	function on_identity_form($args)
	{
		$rc = rcmail::get_instance();

		// Do not show options for default identity
		if (strcasecmp($args['record']['email'], $rc->user->data['username']) === 0)
			return $args;

		$this->add_texts('localization');

		$row = null;
		if (isset($args['record']['identity_id']))
		{
			$sql = 'SELECT * FROM ' . $rc->db->table_name(self::TABLE) . ' WHERE iid = ? AND user_id = ?';
			$q = $rc->db->query($sql, $args['record']['identity_id'], $rc->user->ID);
			$row = $rc->db->fetch_assoc($q);
		}

		$record = &$args['record'];

		// Load data if exists
		if ($row)
		{
			$dbToForm = array(
				'label' => 'common.label',
				'imap_host' => 'imap.host',
				'imap_port' => 'imap.port',
				'username' => 'imap.username',
				'password' => 'imap.password',
				'smtp_host' => 'smtp.host',
				'smtp_port' => 'smtp.port'

			);
			foreach ($row as $k => $v)
				$record['ident_switch.form.' . $dbToForm[$k]] = $v;

			// Parse flags
			$record['ident_switch.form.common.enabled'] = !!($row['flags'] & self::DB_ENABLED);
			$record['ident_switch.form.imap.tls'] = !!($row['flags'] & self::DB_SECURE_IMAP_TLS);

			// Set readonly if needed
			$cfg = $this->get_preconfig($record['email']);
			if (is_array($cfg) && $cfg['readonly'])
			{
				$record['ident_switch.form.common.readonly'] = 1;
				if (in_array(strtoupper($cfg['user']), array('EMAIL', 'MBOX')))
					$record['ident_switch.form.common.readonly'] = 2;
			}
		}
		else
			$this->apply_preconfig($record);

		$args['form']['ident_switch.common'] = array(
			'name' => $this->gettext('form.common.caption'),
			'content' => ident_switch::get_common_form($record)
		);
		$args['form']['ident_switch.imap'] = array(
			'name' => $this->gettext('form.imap.caption'),
			'content' => ident_switch::get_imap_form($record)
		);
		$args['form']['ident_switch.smtp'] = array(
			'name' => $this->gettext('form.smtp.caption'),
			'content' => ident_switch::get_smtp_form($record)
		);

		return $args;
	}

	function on_identity_update($args)
	{
		$rc = rcmail::get_instance();

		// Do not do anything for default identity
		if (strcasecmp($args['record']['email'], $rc->user->data['username']) === 0)
			return $args;

		if (!self::get_field_value('common', 'enabled', false))
		{
			self::sw_imap_off($args['id']);
			return $args;
		}

		$data = self::check_field_values();
		if ($data['err'])
		{
			$this->add_texts('localization');
			$args['abort'] = true;
			$args['message'] = 'ident_switch.err.' . $data['err'];
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

		if (!self::get_field_value('common', 'enabled', false))
				return $args;

		$data = self::check_field_values();
		if ($data['err'])
		{
			$this->add_texts('localization');
			$args['abort'] = true;
			$args['message'] = 'ident_switch.err.' . $data['err'];
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

		$retVal['common.label'] = self::get_field_value('common', 'label');
		if (strlen($retVal['common.label']) > 32)
			$retVal['err'] = 'label.long';
		else
		{
			$retVal['imap.host'] = self::get_field_value('imap', 'host');
			if (strlen($retVal['imap.host']) > 64)
				$retVal['err'] = 'host.long';
			else
			{
				$retVal['imap.port'] = self::get_field_value('imap', 'port');
				if ($retVal['imap.port'] && !ctype_digit($retVal['imap.port']))
					$retVal['err'] = 'port.num';
				else
				{
					if ($retVal['imap.port'] && ($retVal['imap.port'] <= 0 || $retVal['imap.port'] > 65535))
						$retVal['err'] = 'port.range';
					else
					{
						$retVal['imap.user'] = self::get_field_value('imap', 'username');
						if (strlen($retVal['imap.user']) > 64)
							$retVal['err'] = 'user.long';
						else
						{
							$retVal['smtp.host'] = self::get_field_value('smtp', 'host');
							if (strlen($retVal['smtp.host']) > 64)
								$retVal['err'] = 'host.long';
							else
							{
								$retVal['smtp.port'] = self::get_field_value('smtp', 'port');
								if ($retVal['smtp.port'] && !ctype_digit($retVal['smtp.port']))
									$retVal['err'] = 'port.num';
								else
								{
									if ($retVal['smtp.port'] && ($retVal['smtp.port'] <= 0 || $retVal['smtp.port'] > 65535))
										$retVal['err'] = 'port.range';
								}
							}
						}
					}
				}
			}
		}

		// Get also password
		$retVal['imap.pass'] = self::get_field_value('imap', 'password', false);

		// Parse secure settings
		$retVal['flags'] = self::DB_ENABLED;

		$tls = self::get_field_value('imap', 'tls', false);
		if ($tls)
			$retVal['flags'] |= self::DB_SECURE_IMAP_TLS;

		return $retVal;
	}

	private static function get_field_value($section, $field, $trim = true)
	{
		$retVal = rcube_utils::get_input_value(
			'_ident_switch_form_' . $section . '_' . $field,
			rcube_utils::INPUT_POST
		);
		if (!$trim)
			return $retVal;

		return self::ntrim($retVal);
	}

	private static function save_field_values($rc, $data)
	{
		$sql = 'SELECT id, password FROM ' . $rc->db->table_name(self::TABLE) . ' WHERE iid = ? AND user_id = ?';
		$q = $rc->db->query($sql, $data['id'], $rc->user->ID);
		$r = $rc->db->fetch_assoc($q);
		if ($r)
		{ // Record already exists, will update it
			$sql = 'UPDATE ' .
				$rc->db->table_name(self::TABLE) .
				' SET flags = ?, label = ?, imap_host = ?, imap_port = ?, username = ?, password = ?, smtp_host = ?, smtp_port = ?, user_id = ?, iid = ?' .
				' WHERE id = ?';
		}
		else if ($data['flags'] & self::DB_ENABLED)
		{ // No record exists, create new one
			$sql = 'INSERT INTO ' .
				$rc->db->table_name(self::TABLE) .
				'(flags, label, imap_host, imap_port, username, password, smtp_host, smtp_port, user_id, iid) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
		}

		if ($sql)
		{
			// Do we need to update pwd?
			if ($data['imap.pass'] != $r['password'])
				$data['imap.pass'] = $rc->encrypt($data['imap.pass']);

			$rc->db->query(
				$sql,
				$data['flags'],
				$data['label'],
				$data['imap.host'],
				$data['imap.port'],
				$data['imap.user'],
				$data['imap.pass'],
				$data['smtp.host'],
				$data['smtp.port'],
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
			$sql = 'SELECT imap_host, flags, imap_port, username, password, iid FROM ' . $rc->db->table_name(self::TABLE) . ' WHERE id = ? AND user_id = ?';
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

				$def_port = 143; // Default IMAP port here!
				$ssl = null;
				if ($r['flags'] & self::DB_SECURE_IMAP_TLS)
				{
					$ssl = 'tls';
					$def_port = 143; // Default IMAP TLS port here!
				}
				$port = $r['imap_port'] ? $r['imap_port'] : $def_port;

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

				$_SESSION['storage_host'] = $r['imap_host'] ? $r['imap_host'] : 'localhost'; // Default IMAP host here!
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

				$record['ident_switch.form.imap.host'] = $urlArr['host'] ? rcube::Q($urlArr['host'], 'url') : '';
				$record['ident_switch.form.imap.port'] = $urlArr['port'] ? intval($urlArr['port']) : '';

				if (strcasecmp('tls', $urlArr['scheme']) === 0)
					$record['ident_switch.form.imap.tls'] = true;
				else
					$record['ident_switch.form.imap.rls'] = false;
			}

			$loginSet = false;
			if ($cfg['user'])
			{ // Set up user name
				switch (strtoupper($cfg['user']))
				{
				case 'EMAIL':
					$record['ident_switch.form.imap.username'] = $email;
					$loginSet = true;
					break;
				case 'MBOX':
					$record['ident_switch.form.imap.username'] = strstr($email, '@', true);
					$loginSet = true;
					break;
				}
			}

			if ($cfg['readonly'])
			{
				$record['ident_switch.form.common.readonly'] = 1;
				if ($loginSet)
					$record['ident_switch.form.common.readonly'] = 2;
			}

			return $cfg['readonly'];
		}

		return false;
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
