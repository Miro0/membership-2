jQuery( document ).ready( function() {
	//global functions defined in ms-functions.js
	jQuery( '#comment' ).change( function() { ms_functions.ajax_update( this ) } );

	jQuery( '#menu_id' ).change( function() {
		jQuery( '#ms-menu-form' ).submit();
	});


});
