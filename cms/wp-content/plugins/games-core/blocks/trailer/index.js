( function ( wp ) {
	const { registerBlockType } = wp.blocks;
	const { createElement: el } = wp.element;
	const { useBlockProps } = wp.blockEditor;
	const { useSelect } = wp.data;
	const { __ } = wp.i18n;

	registerBlockType( 'games-core/trailer', {
		edit() {
			const url = useSelect( ( select ) => ( select( 'core/editor' ).getEditedPostAttribute( 'meta' ) || {} ).trailer_url, [] );

			return el(
				'div',
				useBlockProps( { style: { padding: '1.5em', border: '1px dashed #ccc', textAlign: 'center' } } ),
				url
					? el( 'span', null, __( 'Trailer: ', 'games-core' ), el( 'a', { href: url, target: '_blank' }, url ) )
					: el( 'span', null, __( 'Add a YouTube URL under Game details → Trailer to show it here.', 'games-core' ) )
			);
		},
		save: () => null,
	} );
} )( window.wp );
