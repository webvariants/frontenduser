var wv16 = (function($) {
	return {
		changeDatatype: function(selectbox) {
			$('div[id^=datatype_form_]').hide();
			$('#datatype_form_'+$(selectbox).val()).show();
		},

		changeUserType: function() {
			var required = wv16.attributesPerType[$(this).val()], i = 0, len = required.length;
			if (!required) { required = []; }

			$('div.wv16_attribute_row').hide();

			// Die benötigten Felder wieder anzeigen

			for (; i < len; ++i) {
				$('#wv16_attribute'+required[i]+'_row').show();
			}
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
})(jQuery);

jQuery(function($) {
   $('#type').change(wv16.changeUserType);
});
