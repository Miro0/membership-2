jQuery( document ).ready(function( $ ) {
	
	$( '#ms-create-child-form' ).validate({
		onkeyup: false,
		errorClass: 'ms-validation-error',
		rules: {
			'name': {
				'required': true,
			}
		}
	});
	
});
