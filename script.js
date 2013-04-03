jQuery( document ).ready( function( $ ){
	$( 'form.' + slh_info.classname + ' input[type="submit"]' )
	.each( function() {
		var form = this.form;
		$( this ).click( function() {
			var to      = form.recipient.value;
			var uri     = document.location.pathname + document.location.search;
			var message = form.message.value;
			var code    = form.captcha.value;

			$.post(
				slh_info.ajax_url,
				{
					'action':   slh_info.action,
					'to':       to,
					'uri':      uri,
					'message':  message,
					'code':     code
				},
				function( response ) {
					response = $.parseJSON( response );
					alert( response.message );
				}
			).error( function() {
				alert( slh_info.error_message );
			});

			return false; // prevent form submission
		});
	});
});
