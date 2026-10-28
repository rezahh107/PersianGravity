( function () {
	'use strict';
	function fallback( value ) {
		const input = document.createElement( 'textarea' );
		const active = document.activeElement;
		input.value = value;
		input.setAttribute( 'readonly', '' );
		input.style.position = 'fixed';
		input.style.opacity = '0';
		document.body.appendChild( input );
		input.select();
		let copied = false;
		try { copied = document.execCommand( 'copy' ); } catch ( error ) { copied = false; }
		input.remove();
		if ( active && typeof active.focus === 'function' ) { active.focus(); }
		return copied;
	}
	async function copy( button ) {
		const sample = button.closest( '.pgr-help-sample' );
		const code = sample && sample.querySelector( 'pre code' );
		const status = sample && sample.querySelector( '.pgr-help-sample__status' );
		if ( ! code || ! status ) { return; }
		let copied = false;
		if ( navigator.clipboard && navigator.clipboard.writeText ) {
			try { await navigator.clipboard.writeText( code.textContent ); copied = true; } catch ( error ) { /* Fallback below. */ }
		}
		if ( ! copied ) { copied = fallback( code.textContent ); }
		status.textContent = copied ? button.dataset.pgrCopySuccess : button.dataset.pgrCopyFailure;
	}
	document.addEventListener( 'click', function ( event ) {
		const button = event.target.closest( '.pgr-help [data-pgr-copy]' );
		if ( button ) { copy( button ); }
	} );
}() );
