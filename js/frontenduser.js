var wv16 = {};

(function($) {

	wv16.changeDatatype = function(selectbox)
	{
		$('div[id^=datatype_form_]').hide();
		$('#datatype_form_'+$(selectbox).val()).show();
	};
	
	wv16.changeUserType = function(selectbox)
	{
		var required = wv16.attrsPerType[$(selectbox).val()];
		if (!required) required = [];
		
		// Formulare erstmal verstecken
		$('#wv2_meta_form div[id^=property_container]').hide();
		
		// Die benötigten anzeigen
		$.each(required, function(idx, id) { $('div#property_container_'+id).show() });
	};
	
	wv16.handleTableDrop = function(table, row, newPosition)
	{
		var attributeID = row.id.substring(4);
		$('td', $(row)).css('font-style', 'italic');
		
		$.post('index.php?page=frontenduser&subpage=attributes&func=shift&'+
			'id='+attributeID+'&position='+newPosition,
			function(response) { $('#attr'+attributeID+' td').css('font-style', 'normal'); }
		);
	};
	
	wv2.toggleForm = function()
	{
		var container = $('#wv2_meta_form');
		var link      = $('#wv2_meta_form_toggler');
		
		if (!container.is(':visible')) {
			container.show();
			link.attr('title', 'Metainformationen ausblenden').attr('src', 'include/addons/metainfoex/images/up.png');
			$.cookie('wv2_display_form','1', {expires: 30});
		}
		else {
			container.hide();
			link.attr('title', 'Metainformationen einblenden').attr('src', 'include/addons/metainfoex/images/down.png');
			$.cookie('wv2_display_form','0', {expires: 30});
		}
	};

})(jQuery);
