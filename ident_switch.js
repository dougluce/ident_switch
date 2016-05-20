/*
 * This is part of identity_imap plugin
 */

$(function() {
	var $truName = $('.topright .username');
	if ($truName.size() > 0) {
		$sw = $('#plugin-ident_switch-account');
		if ($sw.size() > 0) {
			$sw.prependTo('.topright');
			$truName.hide();
			$('#plugin-ident_switch-account').show();
			console.log("Doing replace.");
		}
	}

	var $enFld = $("INPUT[name='_ident_switch.form.enabled']");
	if ($enFld.size() == 1)
		$enFld.change();
});

function plugin_switchIdent_enabled_onChange(e) {
	var $enFld = $("INPUT[name='_ident_switch.form.enabled']");
	$("INPUT[name!='_ident_switch.form.enabled']", $enFld.parents("FIELDSET")).prop("disabled", !$enFld.is(":checked"));
}

function plugin_switchIdent_switch(val) {
	console.log("qwe1");
	rcmail.http_post('plugin.ident_switch.switch', { '_ident-id': val });
}

function  plugin_switchIdent_fixIdent(iid) {
	if (parseInt(iid) > 0)
		$("#_from").val(iid);
}
