var wv16 = (function($) {
	var wv16 = {
		changeDatatype: function(selectbox) {
			$('div[id^=datatype_form_]').hide();
			$('#datatype_form_'+$(selectbox).val()).show();
		},

		changeUserType: function(selectbox) {
			var required = wv16.attributesPerType[$(selectbox).val()];
			if (!required) required = [];

			// Formulare erstmal verstecken
			$('#wv2_meta_form div[id^=property_container]').hide();

			// Die benötigten anzeigen
			$.each(required, function(idx, id) { $('div#property_container_'+id).show() });
		},

		handleTableDrop: function(table, row, newPosition) {
			var attributeID = row.id.substring(4);
			$('td', $(row)).css('font-style', 'italic');

			$.post('index.php?page=frontenduser&subpage=attributes&func=shift&'+
				'id='+attributeID+'&position='+newPosition,
				function(response) { $('#attr'+attributeID+' td').css('font-style', 'normal'); }
			);
		}
	};

	return wv16;
})(jQuery);
