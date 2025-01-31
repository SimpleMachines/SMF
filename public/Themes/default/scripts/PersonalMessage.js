
// Handle the JavaScript surrounding personal messages send form.
function smf_PersonalMessageSend(oOptions)
{
	this.opt = oOptions;
	this.oBccDiv = null;
	this.oBccDiv2 = null;
	this.oToAutoSuggest = null;
	this.oBccAutoSuggest = null;
	this.oToListContainer = null;
	this.init();
}

smf_PersonalMessageSend.prototype.init = function()
{
	if (!this.opt.bBccShowByDefault)
	{
		// Hide the BCC control.
		this.oBccDiv = document.getElementById(this.opt.sBccDivId);
		this.oBccDiv.style.display = 'none';
		this.oBccDiv2 = document.getElementById(this.opt.sBccDivId2);
		this.oBccDiv2.style.display = 'none';

		// Show the link to bet the BCC control back.
		var oBccLinkContainer = document.getElementById(this.opt.sBccLinkContainerId);
		oBccLinkContainer.style.display = '';
		setInnerHTML(oBccLinkContainer, this.opt.sShowBccLinkTemplate);

		// Make the link show the BCC control.
		var oBccLink = document.getElementById(this.opt.sBccLinkId);
		oBccLink.instanceRef = this;
		oBccLink.onclick = function () {
			this.instanceRef.showBcc();
			return false;
		};
	}

	var oToControl = document.getElementById(this.opt.sToControlId);
	this.oToAutoSuggest = new smc_AutoSuggest({
		sSelf: this.opt.sSelf + '.oToAutoSuggest',
		sSessionId: this.opt.sSessionId,
		sSessionVar: this.opt.sSessionVar,
		sSuggestId: 'to_suggest',
		sControlId: this.opt.sToControlId,
		sSearchType: 'member',
		sPostName: 'recipient_to',
		sURLMask: 'action=profile;u=%item_id%',
		sTextDeleteItem: this.opt.sTextDeleteItem,
		bItemList: true,
		sItemListContainerId: 'to_item_list_container',
		aListItems: this.opt.aToRecipients
	});
	this.oToAutoSuggest.registerCallback('onBeforeAddItem', this.opt.sSelf + '.callbackAddItem');

	this.oBccAutoSuggest = new smc_AutoSuggest({
		sSelf: this.opt.sSelf + '.oBccAutoSuggest',
		sSessionId: this.opt.sSessionId,
		sSessionVar: this.opt.sSessionVar,
		sSuggestId: 'bcc_suggest',
		sControlId: this.opt.sBccControlId,
		sSearchType: 'member',
		sPostName: 'recipient_bcc',
		sURLMask: 'action=profile;u=%item_id%',
		sTextDeleteItem: this.opt.sTextDeleteItem,
		bItemList: true,
		sItemListContainerId: 'bcc_item_list_container',
		aListItems: this.opt.aBccRecipients
	});
	this.oBccAutoSuggest.registerCallback('onBeforeAddItem', this.opt.sSelf + '.callbackAddItem');

}

smf_PersonalMessageSend.prototype.showBcc = function()
{
	// No longer hide it, show it to the world!
	this.oBccDiv.style.display = '';
	this.oBccDiv2.style.display = '';
}


// Prevent items to be added twice or to both the 'To' and 'Bcc'.
smf_PersonalMessageSend.prototype.callbackAddItem = function(oAutoSuggestInstance, sSuggestId)
{
	this.oToAutoSuggest.deleteAddedItem(sSuggestId);
	this.oBccAutoSuggest.deleteAddedItem(sSuggestId);

	return true;
}
