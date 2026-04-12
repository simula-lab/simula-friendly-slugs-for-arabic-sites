( function( wp, config ) {
	if ( ! wp || ! config || ! wp.domReady || ! wp.data ) {
		return;
	}

	var domReady = wp.domReady;
	var dispatch = wp.data.dispatch;

	function buildAction( label, url ) {
		return {
			label: label,
			url: url,
		};
	}

	domReady( function() {
		var notices = dispatch( 'core/notices' );
		if ( ! notices ) {
			return;
		}

		if ( config.statusMessage && config.statusMessage.text ) {
			notices.createNotice(
				config.statusMessage.type || 'info',
				config.statusMessage.text,
				{
					id: config.statusNoticeId,
					isDismissible: true,
				}
			);
		}

		if ( ! config.divergence || ! config.divergence.should_show_notice ) {
			return;
		}

		var actionUrls = config.divergence.action_urls || {};
		var labels = config.labels || {};
		var message = [
			labels.title || '',
			labels.body || '',
			labels.current || 'Current slug:',
			config.divergence.current_slug || '',
			labels.suggested || 'Suggested slug:',
			config.divergence.suggested_slug || '',
		].join( ' ' );

		notices.createNotice(
			'warning',
			message,
			{
				id: config.noticeId,
				isDismissible: true,
				actions: [
					buildAction( labels.keep || 'Keep current slug', actionUrls.keep_current_slug ),
					buildAction( labels.useFriendly || 'Use friendly slug', actionUrls.use_friendly_slug ),
					buildAction( labels.regenerate || 'Regenerate friendly slug', actionUrls.regenerate_friendly_slug ),
				].filter( function( action ) {
					return !! action.url;
				} ),
			}
		);
	} );
} )( window.wp, window.simulaFriendlySlugsBlockEditor );
