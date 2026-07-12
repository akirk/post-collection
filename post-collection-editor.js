( function ( wp, settings ) {
	if ( ! wp || ! settings || ! settings.sourceUrl ) {
		return;
	}

	var registerPlugin = wp.plugins && wp.plugins.registerPlugin;
	var createElement = wp.element && wp.element.createElement;
	var Button = wp.components && wp.components.Button;
	var PluginDocumentSettingPanel = wp.editPost && wp.editPost.PluginDocumentSettingPanel;
	var __ = wp.i18n && wp.i18n.__;

	if ( ! registerPlugin || ! createElement || ! Button || ! PluginDocumentSettingPanel ) {
		return;
	}

	var labels = settings.i18n || {};
	var sourceUrl = settings.sourceUrl;

	function translate( text ) {
		return __ ? __( text, 'post-collection' ) : text;
	}

	function archiveLinks() {
		var encodedSourceUrl = encodeURIComponent( sourceUrl );

		return [
			{
				label: labels.waybackSnapshots || translate( 'Wayback Snapshots' ),
				url: 'https://web.archive.org/web/*/' + encodedSourceUrl,
			},
			{
				label: labels.archiveIs || translate( 'Archive.is' ),
				url: 'https://archive.is/?run=1&url=' + encodedSourceUrl,
			},
		];
	}

	function PostArchivesPanel() {
		return createElement(
			PluginDocumentSettingPanel,
			{
				name: 'post-collection-archives',
				title: labels.panelTitle || translate( 'Post Archives' ),
				className: 'post-collection-archives-panel',
			},
			createElement(
				'p',
				{},
				labels.archiveLinksLabel || translate( 'Find archived copies of the original article URL.' )
			),
			createElement(
				'div',
				{ style: { display: 'grid', gap: '8px' } },
				archiveLinks().map( function ( archive ) {
					return createElement(
						Button,
						{
							key: archive.label,
							variant: 'secondary',
							isSecondary: true,
							href: archive.url,
							target: '_blank',
							rel: 'noreferrer noopener',
						},
						archive.label
					);
				} )
			)
		);
	}

	registerPlugin( 'post-collection-archives', {
		render: PostArchivesPanel,
		icon: 'archive',
	} );
} )( window.wp, window.postCollectionEditor );
