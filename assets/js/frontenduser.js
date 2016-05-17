jQuery(function($) {
	var i;

   $('#type').change(function() {
		var required = wv16.attributesPerType[$(this).val()], i = 0, len = required.length;
		if (!required) { required = []; }

		$('div.wv16_attribute_row').hide();

		// Die benötigten Felder wieder anzeigen

		for (; i < len; ++i) {
			$('#wv16_attribute'+required[i]+'_row').show();
		}
	});

	if (wv16.userSets.length > 1) {
		var
			legend  = $('#wv16attributes legend'),
			links   = [],
			setID   = 0,
			baseURL = './frontenduser/edit?id='+wv16.userID+'&setid=';

		legend.append('<span style="float:right;margin-right:10px">Sets:</span>');
		legend = legend.find('span');

		for (i = 0; i < wv16.userSets.length; ++i) {
			setID = wv16.userSets[i];

			legend.append('&nbsp;');

			if (setID === wv16.activeSet) {
				legend.append('<strong>'+setID+'</strong>');
			}
			else {
				legend.append($('<a href="#">' + setID + '</a>').attr('href', baseURL+setID));
			}
		}
	}
});
