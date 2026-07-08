$(document).ready(function()
{
	$('#icon_count_input').change(function ()
	{
		var select_box = $('select#icon_image_input option:selected').val();
		var icon_count = $('#icon_count_input');
		if (icon_count.val() == 0 && select_box != 'blank.png')
			icon_count.val(1);

		if (icon_count.val().length > 2)
			icon_count.val(99);
	});

	$('#icon_image_input').change(function ()
	{
		refreshIconPreview();
	});

	refreshIconPreview();
});

function refreshIconPreview()
{
	// Get the icon count element.
	var icon_count = $('#icon_count_input');
	var select_box = $('select#icon_image_input').val();

	// If it's empty, set it to 1.
	if (icon_count.val() == 0 && select_box != 'blank.png')
		icon_count.val(1);

	// Update the icon preview.
	$('#icon_preview').attr('src', smf_default_theme_url + '/images/membericons/' + select_box);
}