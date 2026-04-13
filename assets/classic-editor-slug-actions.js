( function( config ) {
	if ( ! config || ! window.fetch || ! document ) {
		return;
	}

	var buttonClass = 'simula-generate-slug-button';
	var inFlight = false;

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

	function updateClassicSlugUi( slug ) {
		var normalizedSlug = slug || '';
		var editableField = document.getElementById( 'new-post-slug' );
		if ( editableField ) {
			editableField.value = normalizedSlug;
		}

		var hiddenField = document.getElementById( 'post_name' );
		if ( hiddenField ) {
			hiddenField.value = normalizedSlug;
		}

		var samplePermalink = document.getElementById( 'sample-permalink' );
		if ( samplePermalink && samplePermalink.textContent ) {
			var parts = samplePermalink.textContent.split( '/' );
			if ( parts.length ) {
				parts[ parts.length - 1 ] = normalizedSlug;
				samplePermalink.textContent = parts.join( '/' );
			}
		}

		var slugFull = document.getElementById( 'editable-post-name-full' );
		if ( slugFull ) {
			slugFull.textContent = normalizedSlug;
		}
	}

	function setButtonBusy( button, busy ) {
		if ( ! button ) {
			return;
		}

		button.disabled = !! busy;
		button.textContent = busy
			? ( ( config.labels && config.labels.generating ) || 'Generating friendly slug...' )
			: ( ( config.labels && config.labels.generate ) || 'Generate friendly slug' );
	}

	function handleSuccess( payload, noticeNode ) {
		if ( payload.message ) {
			renderStatusNotice( payload.message );
		}

		if ( payload.divergence && payload.divergence.current_slug ) {
			updateClassicSlugUi( payload.divergence.current_slug );
		}

		if ( payload.divergence && ! payload.divergence.should_show_notice && noticeNode ) {
			noticeNode.remove();
		}
	}

	function runSlugAction( actionName, postId, noticeNode, buttonNode ) {
		if ( inFlight || ! actionName || ! postId ) {
			return;
		}

		inFlight = true;
		setButtonBusy( buttonNode, true );

		var body = new window.URLSearchParams();
		body.append( 'action', config.ajaxAction );
		body.append( 'nonce', config.ajaxNonce );
		body.append( 'post_id', postId );
		body.append( 'simula_slug_action', actionName );

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
			} )
			.finally( function() {
				inFlight = false;
				setButtonBusy( buttonNode, false );
			} );
	}

	function ensureGenerateButton() {
		var postId = config.postId || '';
		if ( ! postId ) {
			return;
		}

		if ( document.querySelector( '.' + buttonClass ) ) {
			return;
		}

		var target = document.getElementById( 'edit-slug-box' ) ||
			document.getElementById( 'sample-permalink' ) ||
			document.querySelector( '.edit-slug-box' );
		if ( ! target || ! target.parentNode ) {
			return;
		}

		var button = document.createElement( 'button' );
		button.type = 'button';
		button.className = 'button button-secondary ' + buttonClass;
		button.textContent = ( config.labels && config.labels.generate ) || 'Generate friendly slug';
		button.style.marginTop = '8px';
		button.addEventListener( 'click', function() {
			runSlugAction( config.generateAction || '', postId, null, button );
		} );

		var container = document.createElement( 'p' );
		container.className = 'simula-generate-slug-wrap';
		container.appendChild( button );
		target.parentNode.insertBefore( container, target.nextSibling );
	}

	document.addEventListener( 'DOMContentLoaded', ensureGenerateButton );
	new window.MutationObserver( ensureGenerateButton ).observe( document.body, {
		childList: true,
		subtree: true,
	} );

	document.addEventListener( 'click', function( event ) {
		var link = event.target.closest( '.simula-slug-action' );
		if ( ! link ) {
			return;
		}

		event.preventDefault();

		var noticeNode = link.closest( '.simula-slug-divergence-notice' );
		runSlugAction(
			link.getAttribute( 'data-action-name' ) || '',
			link.getAttribute( 'data-post-id' ) || '',
			noticeNode,
			null
		);
	} );
} )( window.simulaFriendlySlugsClassicEditor );
