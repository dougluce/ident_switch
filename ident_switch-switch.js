/*
 * This is part of identity_imap plugin
 */

$(function() {
	$sw = $('#plugin-ident_switch-account');
	isOk = false;

	switch (rcmail.env['skin']) {
		case 'larry':
			isOk = plugin_switchIdent_addCbLarry($sw);
			break;
		case 'classic':
			isOk = plugin_switchIdent_addCbClassic($sw);
			break;
	}

	if (isOk)
        $sw.show();
});

function plugin_switchIdent_addCbLarry($sw) {
	var $truName = $('.topright .username');
	if ($truName.length > 0) {
		if ($sw.length > 0) {
			$sw.prependTo('.topright');
			$truName.hide();

			return true;
		}
	}

	return false;
}

function plugin_switchIdent_addCbClassic($sw) {
	var $taskBar = $('#taskbar');
	if ($taskBar.length > 0) {
		$taskBar.prepend($sw);
		return true;
	}

	return false;
}

function plugin_switchIdent_switch(val) {
    rcmail.env.unread_counts = {};
    console.log(rcmail.env.unread_counts);
	rcmail.http_post('plugin.ident_switch.switch', { '_ident-id': val, '_mbox': rcmail.env.mailbox });
}

function  plugin_switchIdent_fixIdent(iid) {
	if (parseInt(iid) > 0)
		$("#_from").val(iid);
}
