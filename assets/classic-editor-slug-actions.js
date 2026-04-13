( function( config ) {
	if ( ! config || ! window.fetch || ! document ) {
		return;
	}

	function removeNotices( selector ) {
		document.querySelectorAll( selector ).forEach( function( node ) {
			node.remove();
		} );
	}

	function renderStatusNotice( message ) {
		if ( ! message || ! message.text ) {
			return;
		}

		removeNotices( '.simula-slug-status-notice' );
		var notice = document.createElement( 'div' );
		notice.className = 'notice notice-' + ( message.type || 'info' ) + ' is-dismissible simula-slug-status-notice';
		notice.innerHTML = '<p>' + message.text + '</p>';

		var anchor = document.querySelector( '.wrap h1, .wrap h2' );
		if ( anchor && anchor.parentNode ) {
			anchor.parentNode.insertBefore( notice, anchor.nextSibling );
		}
	}

	function handleSuccess( payload, noticeNode ) {
		if ( payload.message ) {
			renderStatusNotice( payload.message );
		}

		if ( payload.divergence && ! payload.divergence.should_show_notice && noticeNode ) {
			noticeNode.remove();
		}
	}

	document.addEventListener( 'click', function( event ) {
		var link = event.target.closest( '.simula-slug-action' );
		if ( ! link ) {
			return;
		}

		event.preventDefault();

		var noticeNode = link.closest( '.simula-slug-divergence-notice' );
		var body = new window.URLSearchParams();
		body.append( 'action', config.ajaxAction );
		body.append( 'nonce', config.ajaxNonce );
		body.append( 'post_id', link.getAttribute( 'data-post-id' ) || '' );
		body.append( 'simula_slug_action', link.getAttribute( 'data-action-name' ) || '' );

		window.fetch( config.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
			},
			body: body.toString(),
		} )
			.then( function( response ) {
				return response.json();
			} )
			.then( function( payload ) {
				if ( ! payload || ! payload.success || ! payload.data ) {
					return;
				}

				handleSuccess( payload.data, noticeNode );
			} );
	} );
} )( window.simulaFriendlySlugsClassicEditor );
