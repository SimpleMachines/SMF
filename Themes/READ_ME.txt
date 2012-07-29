
 The Great Readme. Someone might even read it.
 =============================================
 
 July 18th, 2012. Let's see if we can kill GitHub!
 
 Most of the stuff I've done for looks is CSS changes only.
 Markup changes were limited to bug fixes, consistency across templates, or useful features.
 The markup changes are as small as possible and should all be bulletproof.
 
 TEMPLATES:
 ----------
 
 1/ index.template.php:			Significant markup revisions. All very good ones.
								Trust me on this, or at least please discuss it.

 2/ BoardIndex.template.php:	Cleanup and buglet fixes. Some useful tweaks.

 3/ Calendar.template.php:		Cleanup and buglet fixes. Improved looks via CSS.

 4/ GenericMenu.template.php:	Added chosen class to third level for consistency. That's about it IIRC.

 5/ Help.template.php:			Minor cleanup (old spans, etc). Improved looks via CSS.

 6/ MessageIndex.template.php:	Cleanup and buglet fixes. Some useful tweaks.

 7/ Recent.template.php:		Cleanup and buglet fixes. Some useful tweaks.

 8/ Stats.template.php:			Minor cleanup. Improved looks via CSS only.
								Went to town on statsbar styling. Much better IMO. Polls are the same.

 LANGUAGES:
 ----------
 
 1/ index.english.php			Chnaged the last post string to something cleaner.
 
 SCRIPTS:
 --------

 1/ scripts/theme.js:			Removed old stuff that was only for adding :hover pseudo class to IE6.

 CSS:
 ----
 
 1/ index.css, of course.		Lots of stuff there. Combination of bug fixes, less crap, and better looks.

 2/ admin.css, too.				Just a bit here and there.
 
 OTHER:
 ------

 1/ Assorted images:			Old big nasty sprites are gone.
								New smaller ones are much better, and no more hhtp requests than before. YAY!

								Have changed message icons to 18x18 instead of 16x16.
								They really do need a little extra grunt with the other stuff around them.
								20x20 was too big. 16x16 is too small in that situation.

 PROBLEMS (meh):
 ===============
 
 1/	Currently not sure WTF is going on the the drop menu third levels being clipped in IE, Chrome and Opera.
	Will find a solution, but CSS3 transitions is not it. Tried those, and the results were less than pleasing.
	I know the problem is related to the js, as it disappears when js is disabled. CSS seems fine, AFAICT.

 2/	Haven't tackled rtl.css yet, but it shouldn't be hard to sort.
 
 3/ Oh yeah, the stuff I don't know about yet. You get that. Hey ho.
 
 ===================================================================
 
 Later: got rid of all the old spans and ie6 header stuff in the rest of the templates.
 Also ditched the remaining middletext and normaltext classes. Yay! Less crap.
 