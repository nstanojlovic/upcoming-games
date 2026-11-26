( function ( wp ) {
	const { registerBlockType } = wp.blocks;
	const { createElement: el } = wp.element;
	const { useBlockProps, InspectorControls } = wp.blockEditor;
	const { PanelBody, TextControl } = wp.components;
	const { useSelect } = wp.data;
	const { __ } = wp.i18n;

	function daysUntil( date ) {
		const ms = new Date( date + 'T00:00:00' ) - new Date().setHours( 0, 0, 0, 0 );
		return Math.round( ms / 86400000 );
	}

	registerBlockType( 'games-core/countdown', {
		edit( { attributes, setAttributes } ) {
			const meta = useSelect( ( select ) => select( 'core/editor' ).getEditedPostAttribute( 'meta' ) || {}, [] );
			const date = meta.release_date;

			let text;
			if ( ! date || meta.release_tba ) {
				text = __( 'Release date to be announced', 'games-core' );
			} else {
				const days = daysUntil( date );
				text = days > 0 ? `${ attributes.label } ${ days } ${ days === 1 ? 'day' : 'days' }` : __( 'Out now', 'games-core' );
			}

			return el(
				'div',
				useBlockProps( { className: 'games-countdown' } ),
				el( InspectorControls, null,
					el( PanelBody, { title: __( 'Countdown', 'games-core' ) },
						el( TextControl, {
							label: __( 'Label before the number of days', 'games-core' ),
							value: attributes.label,
							onChange: ( label ) => setAttributes( { label } ),
						} )
					)
				),
				el( 'strong', null, text ),
				date ? el( 'span', { style: { marginLeft: '0.5em', opacity: 0.7 } }, date ) : null
			);
		},
		save: () => null,
	} );
} )( window.wp );
