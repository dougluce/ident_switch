/*
 * This is part of identity_imap plugin
 */
$(function() {
	if ($('#plugin-ident_switch-account').size() > 0) {
		$('#plugin-ident_switch-account').prependTo('.topright');
		$('.topright .username').hide();
		$('#plugin-ident_switch-account').show();
	}
});

function plugin_switchIdent_switch(val) {
	console.log("qwe1");
	rcmail.http_post('plugin.ident_switch.switch', { '_ident-id': val });
}
