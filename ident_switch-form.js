/*
 * This is part of identity_imap plugin
 */

$(function() {
	$("INPUT[name='_ident_switch.form.enabled']").change();
	$("SELECT[name='_ident_switch.form.secure']").change();
	plugin_switchIdent_processPreconfig();
});

