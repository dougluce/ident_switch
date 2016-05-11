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

	private $table = 'ident_switch';

	// Flags user in database
	private $db_enabled = 1;
	private $db_secure = 2;

	function init()
	{
		$this->add_hook('startup', array($this, 'on_startup'));
		$this->add_hook('render_page', array($this, 'on_render_page'));
#		$this->add_hook('smtp_connect', array($this, 'on_smtp_connect'));
		$this->add_hook('identity_form', array($this, 'on_identity_form'));
		$this->add_hook('identity_update', array($this, 'on_identity_update'));
		$this->add_hook('identity_delete', array($this, 'on_identity_delete'));

		$this->register_action('plugin.ident_switch.switch', array($this, 'on_switch'));
	}

	function on_startup($args)
	{
		$rc = rcmail::get_instance();

		if (strtolower($rc->user->data['username']) != strtolower($_SESSION['username']))
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

		// Get list of alternative accounts
		$sOpt = '';
		$sql = 'SELECT id, label FROM ' . $rc->db->table_name($this->table) . ' WHERE user_id = ? AND flags & ? > 0';
		$q = $rc->db->query($sql, $rc->user->data['user_id'], $this->db_enabled);
		while ($r = $rc->db->fetch_assoc($q))
		{
			$sOpt .= html::tag(
				'option',
				array('value' => $r['id']),
				$r['label']
			);
		}

		// Render UI if user has extra accounts
		if (!empty($sOpt))
		{

			// TODO: Add main account

			$this->include_script('ident_switch.js');
			$sw = html::tag(
				'select', 
				array(
					'id' => 'plugin-ident_switch-account',
					'style' => 'display: none;',
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
		error_log('on_smtp_connect');
	}

	function on_identity_form($args)
	{
		$rc = rcmail::get_instance();
		$this->add_texts('localization');

		// Create our field set
		$args['form']['ident_switch'] = array(
			'name' => $this->gettext('form.caption'),
			'content' => array(
				'ident_switch.form.enabled' => array('type' => 'checkbox'),
				'ident_switch.form.label' => array('type' => 'text', 'size' => 32),
				'ident_switch.form.host' => array('type' => 'text', 'size' => 64),
				'ident_switch.form.secure' => array('type' => 'checkbox'),
				'ident_switch.form.port' => array('type' => 'text', 'size' => 5),
				'ident_switch.form.username' => array('type' => 'text', 'size' => 64),
				'ident_switch.form.password' => array('type' => 'password', 'size' => 64),
				'ident_switch.form.delimiter' => array('type' => 'text', 'size' => 1),
			),
		);

		// Load data if exists
		if (isset($args['record']['identity_id']))
		{
			$sql = 'SELECT * FROM ' . $rc->db->table_name($this->table) . ' WHERE iid = ? AND user_id = ?';
			$q = $rc->db->query($sql, $args['record']['identity_id'], $rc->user->ID);
			$r = $rc->db->fetch_assoc($q);
			if ($r)
			{
				foreach ($r as $k => $v)
					$args['record']['ident_switch.form.' . $k] = $v;
			}
		}

		return $args;
	}

	function on_identity_update($args)
	{
		$rc = rcmail::get_instance();

		// TODO: check field values

		$sql = 'SELECT NULL FROM ' . $rc->db->table_name($this->table) . ' WHERE iid = ? AND user_id = ?';
		$q = $rc->db->query($sql, $args['id'], $rc->user->ID);
		$r = $rc->db->fetch_assoc($q);
		if ($r)
		{ // Record already exists, will update it
			$sql = 'UPDATE ' .
				$rc->db->table_name($this->table) .
				' SET enabled = ?, label = ?, host = ?, secure = ?, port = ?, username = ?, password = ?, delimiter = ?, user_id = ?, iid = ?';
		}
		else
		{ // No record exists, create new one
			$sql = 'INSERT INTO ' .
				$rc->db->table_name($this->table) .
				'(enabled, label, host, secure, port, username, password, delimiter, user_id, iid) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
		}

		ob_start();
		var_dump($args);
		error_log(ob_get_contents());
		ob_end_clean();

		$rc->db->query(
			$sql,
			get_input_value('_ident_switch_form_enabled', RCUBE_INPUT_POST),
			get_input_value('_ident_switch_form_label', RCUBE_INPUT_POST),
			get_input_value('_ident_switch_form_host', RCUBE_INPUT_POST),
			get_input_value('_ident_switch_form_secure', RCUBE_INPUT_POST),
			get_input_value('_ident_switch_form_port', RCUBE_INPUT_POST),
			get_input_value('_ident_switch_form_username', RCUBE_INPUT_POST),
			get_input_value('_ident_switch_form_password', RCUBE_INPUT_POST),
			get_input_value('_ident_switch_form_delimiter', RCUBE_INPUT_POST),
			$rc->user->ID,
			$args['id']
		);

		return $args;
	}

	function on_identity_delete($args)
	{
		$rc = rcmail::get_instance();

		$sql = 'DELETE FROM ' . $rc->db->table_name($this->table) . ' WHERE iid = ? AND user_id = ?';
		$rc->db->query($sql, $args['id'], $rc->user->ID);

		// TODO: Affected rows count for log

		return $args;
	}

	function on_switch()
	{
		$rc = rcmail::get_instance();

		$identId = rcube_utils::get_input_value('_ident-id', RCUBE_INPUT_POST);
		error_log($identId);

		if (-1 == $identId)
		{ // Switch to main account
		}
		else
		{
			$sql = 'SELECT server, username, password FROM ' . $rc->db->table_name($this->table) . ' WHERE id = ? AND user_id = ?';
			$q = $rc->db->query($sql, $identId ,$rc->user->ID);
			$r = $rc->db->fetch_assoc($q);
			if (is_array($r))
			{
				// Parse server URI
				$pth = parse_url($r['server']);
				$ssl = false;
				if ($pth['scheme'] == 'ssl' || $pth['scheme'] == 'tls')
				{
					$ssl = true;
					if (!$pth['port'])
						$pth['port'] = 993; // Default SSL/TLS port here!
				}
				if (!$pth['port'])
					$pth['port'] = 143; // Default port here!

				$_SESSION['storage_host'] = $pth['host'] ? $pth['host'] : $pth['path'];
				$_SESSION['storage_ssl'] = $ssl;
				$_SESSION['storage_port'] = $pth['port'];
				$_SESSION['username'] = $r['username'];
				$_SESSION['password'] = $r['password'];

				$rc->session->remove('folders');
				$rc->output->redirect(
					array(
						'_task' => 'mail',
						'_mbox' => rcube_utils::get_input_value('_mbox', RCUBE_INPUT_GET),
					)
				);
			}
			else
			{
				// TODO: Show message in browser
				error_log('Requested account not found!');
			}
		}
	}
}
