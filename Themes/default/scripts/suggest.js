// This file contains javascript associated with an autosuggest control
function smc_AutoSuggest(oOptions)
{
	this.opt = oOptions;

	// Store the handle to the text box.
	this.oTextHandle = document.getElementById(this.opt.sControlId);
	this.oRealTextHandle = null;

	this.oSuggestDivHandle = null;
	this.sLastSearch = '';
	this.sLastDirtySearch = '';
	this.oSelectedDiv = null;
	this.aCache = [];
	this.aDisplayData = [];

	this.sRetrieveURL = this.opt.sRetrieveURL || '%scripturl%action=suggest;suggest_type=%suggest_type%;search=%search%;%sessionVar%=%sessionID%;xml;time=%time%';
	this.oRetrieveTokens = {
		scripturl: smf_prepareScriptUrl(smf_scripturl),
		suggest_type: this.opt.sSearchType,
		sessionVar: this.opt.sSessionVar,
		sessionID: this.opt.sSessionId
	};

	// How many objects can we show at once?
	this.iMaxDisplayQuantity = this.opt.iMaxDisplayQuantity || 15;

	// How many characters shall we start searching on?
	this.iMinimumSearchChars = this.opt.iMinimumSearchChars || 3;

	// Should selected items be added to a list?
	this.bItemList = this.opt.bItemList || false;

	// Are there any items that should be added in advance?
	this.aListItems = this.opt.aListItems || [];

	this.sItemTemplate = this.opt.sItemTemplate || '<input type="hidden" name="%post_name%[]" value="%item_id%" /><a href="%item_href%" target="_blank">%item_name%</a>&nbsp;<span class="main_icons delete" title="%delete_text%" tabindex="0"></span>';
	this.sTextDeleteItem = this.opt.sTextDeleteItem || '';
	this.oItemTokens = {
		'post_name': this.opt.sPostName,
		'images_url': smf_images_url,
		'delete_text': this.sTextDeleteItem
	};
	this.oCallback = {};
	this.bDoAutoAdd = false;
	this.iItemCount = 0;
	this.oHideTimer = null;
	this.bPositionComplete = false;
	this.iCurrentIndex = -1;

	// Just make sure the page is loaded before calling the init.
	window.addEventListener("load", this.init.bind(this));
}

// Initialize our autosuggest object, adds events and containers to the element we monitor
smc_AutoSuggest.prototype.init = function()
{
	// Create a div that'll contain the results later on.
	this.oSuggestDivHandle = document.createElement('div');
	this.oSuggestDivHandle.className = 'auto_suggest_div';
	document.body.appendChild(this.oSuggestDivHandle);

	// Create a backup text input.
	this.oRealTextHandle = document.createElement('input');
	this.oRealTextHandle.type = 'hidden';
	this.oRealTextHandle.name = this.oTextHandle.name;
	this.oRealTextHandle.value = this.oTextHandle.value;
	this.oTextHandle.form.appendChild(this.oRealTextHandle);

	// Disable autocomplete in any browser by obfuscating the name.
	this.oTextHandle.name = 'dummy_' + Math.floor(Math.random() * 1000000);
	this.oTextHandle.autocomplete = 'off';

	// Set up all the event monitoring
	this.oTextHandle.onkeydown = this.handleKey.bind(this);
	this.oTextHandle.oninput = this.autoSuggestUpdate.bind(this);
	this.oTextHandle.onblur = this.autoSuggestActualHide.bind(this);
	this.oTextHandle.onfocus = function()
	{
		if (this.oSuggestDivHandle.children.length)
			this.autoSuggestShow();
		else
			this.autoSuggestUpdate();
	}.bind(this);

	// Adding items to a list, then we need a place to insert them
	if (this.bItemList)
	{
		if ('sItemListContainerId' in this.opt)
			this.oItemList = document.getElementById(this.opt.sItemListContainerId);
		else
		{
			this.oItemList = document.createElement('div');
			this.oTextHandle.after(this.oItemList);
		}
	}

	if (this.aListItems.length > 0)
		for (var i = 0, n = this.aListItems.length; i < n; i++)
			this.addItemLink(this.aListItems[i].sItemId, this.aListItems[i].sItemName);

	return true;
}

// Was it an enter key - if so assume they are trying to select something.
smc_AutoSuggest.prototype.handleKey = function(oEvent)
{
	var iKeyPress = oEvent.keyCode;

	switch (iKeyPress)
	{
		// Tab.
		case 9:
			if (this.aDisplayData.length)
			{
				if (this.oSelectedDiv)
					this.itemClicked(this.oSelectedDiv);
				else
					this.handleSubmit();
			}
			break;

		// Enter.
		case 13:
			if (this.aDisplayData.length && this.oSelectedDiv)
			{
				this.itemClicked(this.oSelectedDiv);

				// Do our best to stop it submitting the form!
				return false;
			}
			break;

		// Up/Down arrow?
		case 38:
		case 40:
			if (this.aDisplayData.length && this.oSuggestDivHandle.style.visibility != 'hidden')
			{
				// Simulate modulo operator in mathematics, returning only unsigned values.
				const mod = (n, m) => (n % m + m) % m;

				// Calculate indexes baseed on the arrow key.
				var iNum = iKeyPress - 39;
				if (!this.oSelectedDiv && iNum == -1)
					iNum++;
				this.iCurrentIndex = mod((this.iCurrentIndex + iNum), this.aDisplayData.length);

				// Go up or down, wrapping around as needed.
				this.itemOver(this.aDisplayData[this.iCurrentIndex]);
			}
			break;
	}
}

smc_AutoSuggest.prototype.itemOver = function(oDiv)
{
	if (this.oSelectedDiv)
		this.oSelectedDiv.className = 'auto_suggest_item';

	this.oSelectedDiv = oDiv;
	this.iCurrentIndex = oDiv.iCurrentIndex;
	this.oSelectedDiv.className = 'auto_suggest_item_hover';
}

// Functions for integration.
smc_AutoSuggest.prototype.registerCallback = function(sCallbackType, fCallback)
{
	this.oCallback[sCallbackType] = fCallback;
}

// User hit submit?
smc_AutoSuggest.prototype.handleSubmit = function()
{
	var bReturnValue = true;
	var oFoundEntry = null;

	// Do we have something that matches the current text?
	for (var i = 0; i < this.aCache.length; i++)
	{
		if (this.sLastSearch.toLowerCase() == this.aCache[i].sItemName.toLowerCase().substr(0, this.sLastSearch.length))
		{
			// Exact match?
			if (this.sLastSearch.length == this.aCache[i].sItemName.length)
			{
				// This is the one!
				oFoundEntry = {
					sItemId: this.aCache[i].sItemId,
					sItemName: this.aCache[i].sItemName
				};
				break;
			}

			// Not an exact match, but it'll do for now.
			else
			{
				// If we have two matches don't find anything.
				if (oFoundEntry != null)
					bReturnValue = false;
				else
					oFoundEntry = {
						sItemId: this.aCache[i].sItemId,
						sItemName: this.aCache[i].sItemName
					};
			}
		}
	}

	if (oFoundEntry == null || bReturnValue == false)
		return bReturnValue;
	else
	{
		this.addItemLink(oFoundEntry.sItemId, oFoundEntry.sItemName, true);
		return false;
	}
}

// Positions the box correctly on the window.
smc_AutoSuggest.prototype.positionDiv = function()
{
	// Only do it once.
	if (this.bPositionComplete)
		return true;

	this.bPositionComplete = true;

	// Put the div under the text box.
	var aParentPos = smf_itemPos(this.oTextHandle);

	this.oSuggestDivHandle.style.left = aParentPos[0] + 'px';
	this.oSuggestDivHandle.style.top = (aParentPos[1] + this.oTextHandle.offsetHeight) + 'px';
	this.oSuggestDivHandle.style.width = this.oTextHandle.clientWidth + 'px';

	return true;
}

// Do something after clicking an item.
smc_AutoSuggest.prototype.itemClicked = function(oDiv)
{
	// Is there a div that we are populating?
	if (this.bItemList)
		this.addItemLink(oDiv.sItemId, oDiv.innerHTML);
	// Otherwise clear things down.
	else
		this.oTextHandle.value = oDiv.innerHTML.php_unhtmlspecialchars();

	this.oRealTextHandle.value = this.oTextHandle.value;
	this.autoSuggestActualHide();
	this.oSelectedDiv = null;
	this.iCurrentIndex = -1;
}

// Remove the last searched for name from the search box.
smc_AutoSuggest.prototype.removeLastSearchString = function ()
{
	// Remove the text we searched for from the div.
	var sTempText = this.oTextHandle.value.toLowerCase();
	var iStartString = sTempText.indexOf(this.sLastSearch.toLowerCase());
	// Just attempt to remove the bits we just searched for.
	if (iStartString != -1)
	{
		while (iStartString > 0)
		{
			if (sTempText.charAt(iStartString - 1) == '"' || sTempText.charAt(iStartString - 1) == ',' || sTempText.charAt(iStartString - 1) == ' ')
			{
				iStartString--;
				if (sTempText.charAt(iStartString - 1) == ',')
					break;
			}
			else
				break;
		}

		// Now remove anything from iStartString upwards.
		this.oTextHandle.value = this.oTextHandle.value.substr(0, iStartString);
	}
	// Just take it all.
	else
		this.oTextHandle.value = '';
}

// Add a result if not already done.
smc_AutoSuggest.prototype.addItemLink = function (sItemId, sItemName, bFromSubmit)
{
	// Increase the internal item count.
	this.iItemCount++;

	// If there's a callback then call it.
	if (typeof this.oCallback.onBeforeAddItem == 'function')
	{
		// If it returns false the item must not be added.
		if (!this.oCallback.onBeforeAddItem.call(this, sItemId))
			return;
	}
	// Backward compatibility; to be removed in the future
	else if (typeof this.oCallback.onBeforeAddItem == 'string')
	{
		// If it returns false the item must not be added.
		if (!window[this.oCallback.onBeforeAddItem].call(this, this.opt.sSelf, sItemId))
			return;
	}

	var oNewDiv = document.createElement('div');
	oNewDiv.id = 'suggest_' + this.opt.sSuggestId + '_' + sItemId;
	oNewDiv.innerHTML = this.sItemTemplate.easyReplace(this.oItemTokens).easyReplace({
		'item_id': sItemId,
		'item_href': smf_prepareScriptUrl(smf_scripturl) + this.opt.sURLMask.replace(/%item_id%/g, sItemId),
		'item_name': sItemName,
	});

	oNewDiv.getElementsByTagName('span')[0].addEventListener("click", this.deleteAddedItem.bind(this, oNewDiv.id));
	this.oItemList.appendChild(oNewDiv);

	// If there's a registered callback, call it.
	if (typeof this.oCallback.onAfterAddItem == 'function')
		this.oCallback.onAfterAddItem.call(this, oNewDiv.id);
	// Backward compatibility; to be removed in the future
	else if (typeof this.oCallback.onAfterAddItem == 'string')
		window[this.oCallback.onAfterAddItem].call(this, this.opt.sSelf, oNewDiv.id, this.iItemCount);

	// Clear the div a bit.
	this.removeLastSearchString();

	// If we came from a submit, and there's still more to go, turn on auto add for all the other things.
	this.bDoAutoAdd = this.oTextHandle.value != '' && bFromSubmit;

	// Update the fellow..
	this.autoSuggestUpdate();
}

// Delete an item that has been added, if at all?
smc_AutoSuggest.prototype.deleteAddedItem = function (sItemId)
{
	var oDiv = document.getElementById('suggest_' + this.opt.sSuggestId + '_' + sItemId);

	// Remove the div if it exists.
	if (oDiv)
	{
		this.oItemList.removeChild(oDiv);

		// Decrease the internal item count.
		this.iItemCount--;

		// If there's a registered callback, call it.
		if (typeof this.oCallback.onAfterDeleteItem == 'function')
			this.oCallback.onAfterDeleteItem.call(this);
		// Backward compatibility; to be removed in the future
		else if (typeof this.oCallback.onAfterDeleteItem == 'string')
			window[this.oCallback.onAfterDeleteItem].call(this, this.opt.sSelf, this.iItemCount);
	}

	return false;
}

// Hide the box.
smc_AutoSuggest.prototype.autoSuggestHide = function ()
{
	// Delay to allow events to propagate through....
	this.oHideTimer = setTimeout(this.autoSuggestActualHide.bind(this), 250);
}

// Do the actual hiding after a timeout.
smc_AutoSuggest.prototype.autoSuggestActualHide = function()
{
	this.oSuggestDivHandle.style.display = 'none';
	this.oSuggestDivHandle.style.visibility = 'hidden';
	this.oSelectedDiv = null;
}

// Show the box.
smc_AutoSuggest.prototype.autoSuggestShow = function()
{
	if (this.oHideTimer)
	{
		clearTimeout(this.oHideTimer);
		this.oHideTimer = false;
	}

	this.positionDiv();

	this.oSuggestDivHandle.style.visibility = 'visible';
	this.oSuggestDivHandle.style.display = '';
}

// Populate the actual div.
smc_AutoSuggest.prototype.populateDiv = function(aResults)
{
	if (!aResults)
	{
		this.oSuggestDivHandle.replaceChildren();
		this.aDisplayData = [];

		return false;
	}

	var aNewDisplayData = [];
	for (var i = 0, n = Math.min(aResults.length, this.iMaxDisplayQuantity); i < n; i++)
	{
		var oNewDivHandle = document.createElement('div');
		oNewDivHandle.iCurrentIndex = i;
		oNewDivHandle.sItemId = aResults[i].sItemId;
		oNewDivHandle.className = 'auto_suggest_item';
		oNewDivHandle.innerHTML = aResults[i].sItemName;
		oNewDivHandle.onmouseover = this.itemOver.bind(this, oNewDivHandle);
		oNewDivHandle.onclick = this.itemClicked.bind(this, oNewDivHandle);

		aNewDisplayData[i] = oNewDivHandle;
	}

	this.aDisplayData = aNewDisplayData;
	this.oSuggestDivHandle.replaceChildren(...aNewDisplayData);

	return true;
}

// Callback function for the XML request, should contain the list of users that match
smc_AutoSuggest.prototype.onSuggestionReceived = function (oXMLDoc)
{
	var sQuoteText = '',
		aItems = oXMLDoc.getElementsByTagName('item');

	// Go through each item received
	this.aCache = [];
	for (var i = 0; i < aItems.length; i++)
	{
		this.aCache[i] = {
			sItemId: aItems[i].getAttribute('id'),
			sItemName: aItems[i].childNodes[0].nodeValue
		};

		// If we're doing auto add and we find the exact person, then add them!
		if (this.bDoAutoAdd && this.sLastSearch == this.aCache[i].sItemName)
		{
			var oReturnValue = {
				sItemId: this.aCache[i].sItemId,
				sItemName: this.aCache[i].sItemName
			};
			this.aCache = [];
			return this.addItemLink(oReturnValue.sItemId, oReturnValue.sItemName, true);
		}
	}

	// Check we don't try to keep auto updating!
	this.bDoAutoAdd = false;

	// Populate the div.
	this.populateDiv(this.aCache);

	// Make sure we can see it - if we can.
	if (aItems.length == 0)
		this.autoSuggestHide();
	else
		this.autoSuggestShow();

	return true;
}

// Get a new suggestion.
smc_AutoSuggest.prototype.autoSuggestUpdate = function ()
{
	// If there's a callback then call it.
	if (typeof this.oCallback.onBeforeUpdate == 'function')
	{
		// If it returns false the item must not be added.
		if (!this.oCallback.onBeforeUpdate.call(this))
			return false;
	}
	// Backward compatibility; to be removed in the future
	else if (typeof this.oCallback.onBeforeUpdate == 'string')
	{
		// If it returns false the item must not be added.
		if (!window[this.oCallback.onBeforeUpdate].call(this, this.opt.sSelf))
			return false;
	}

	this.oRealTextHandle.value = this.oTextHandle.value;

	if (isEmptyText(this.oTextHandle))
	{
		this.aCache = [];
		this.populateDiv();
		this.autoSuggestHide();

		return true;
	}

	// Nothing changed?
	if (this.oTextHandle.value == this.sLastDirtySearch)
		return true;
	this.sLastDirtySearch = this.oTextHandle.value;

	// We're only actually interested in the last string.
	var sSearchString = this.oTextHandle.value.replace(/^("[^"]+",[ ]*)+/, '').replace(/^([^,]+,[ ]*)+/, '');
	if (sSearchString.substr(0, 1) == '"')
		sSearchString = sSearchString.substr(1);

	// Stop replication ASAP.
	var sRealLastSearch = this.sLastSearch;
	this.sLastSearch = sSearchString;

	// Either nothing or we've completed a sentance.
	if (sSearchString == '' || sSearchString.substr(sSearchString.length - 1) == '"')
	{
		this.populateDiv();
		return true;
	}

	// Nothing new?
	if (sRealLastSearch == sSearchString)
		return true;

	// Too small?
	else if (sSearchString.length < this.iMinimumSearchChars)
	{
		this.aCache = [];
		this.autoSuggestHide();
		return true;
	}
	else if (sSearchString.substr(0, sRealLastSearch.length) == sRealLastSearch)
	{
		// Instead of hitting the server again, just narrow down the results...
		var aNewCache = [];
		var j = 0;
		var sLowercaseSearch = sSearchString.toLowerCase();
		for (var k = 0; k < this.aCache.length; k++)
		{
			if (this.aCache[k].sItemName.substr(0, sSearchString.length).toLowerCase() == sLowercaseSearch)
				aNewCache[j++] = this.aCache[k];
		}

		this.aCache = [];
		if (aNewCache.length != 0)
		{
			this.aCache = aNewCache;
			// Repopulate.
			this.populateDiv(this.aCache);

			// Check it can be seen.
			this.autoSuggestShow();

			return true;
		}
	}

	// Clean the text handle.
	sSearchString = sSearchString.php_to8bit().php_urlencode();

	// Get the document.
	var sRetrieveURL = this.sRetrieveURL.easyReplace(this.oRetrieveTokens).easyReplace({
		search: sSearchString,
		time: new Date().getTime()
	});
	getXMLDocument.call(this, sRetrieveURL, this.onSuggestionReceived);

	return true;
}