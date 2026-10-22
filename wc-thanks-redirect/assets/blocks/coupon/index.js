( function ( blocks, element, blockEditor, i18n ) {
	var el            = element.createElement;
	var __             = i18n.__;
	var useBlockProps = blockEditor.useBlockProps;

	blocks.registerBlockType( 'wctr/coupon', {
		edit: function () {
			var blockProps = useBlockProps( {
				style: {
					padding: '12px',
					border: '1px dashed #ccd0d4',
					background: '#f6f7f7',
					color: '#646970',
				},
			} );

			return el(
				'div',
				blockProps,
				el( 'p', {}, __( 'Post-Purchase Coupon — the configured coupon message appears here after checkout.', 'wc-thanks-redirect' ) )
			);
		},
		save: function () {
			return null;
		},
	} );
} )( window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.i18n );
