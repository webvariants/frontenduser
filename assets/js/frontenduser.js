jQuery(function($) {
   $('#type').change(function() {
		var required = wv16.attributesPerType[$(this).val()], i = 0, len = required.length;
		if (!required) { required = []; }

		$('div.wv16_attribute_row').hide();

		// Die benötigten Felder wieder anzeigen

		for (; i < len; ++i) {
			$('#wv16_attribute'+required[i]+'_row').show();
		}
	});
});
