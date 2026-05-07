( function ( $ ) {
	'use strict';

	function parseIds( val ) {
		if ( ! val || typeof val !== 'string' ) {
			return [];
		}
		return val
			.split( /[,|]/ )
			.map( function ( s ) {
				return parseInt( s.trim(), 10 );
			} )
			.filter( function ( n ) {
				return n > 0;
			} );
	}

	function uniqueJoin( ids ) {
		var seen = {};
		var out = [];
		ids.forEach( function ( id ) {
			if ( ! seen[ id ] ) {
				seen[ id ] = true;
				out.push( id );
			}
		} );
		return out.join( ',' );
	}

	function syncPreviewFromHidden( $wrap ) {
		var $hidden = $wrap.find( '.smdm-media-ids' );
		var ids = parseIds( $hidden.val() );
		var $list = $wrap.find( '.smdm-media-preview' );
		$list.find( '.smdm-media-tile' ).each( function () {
			var id = parseInt( $( this ).attr( 'data-id' ), 10 );
			if ( ids.indexOf( id ) === -1 ) {
				$( this ).remove();
			}
		} );
	}

	function appendTile( $list, id, url ) {
		if ( ! url ) {
			url =
				'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==';
		}
		var li = document.createElement( 'li' );
		li.className = 'smdm-media-tile';
		li.setAttribute( 'data-id', String( id ) );
		li.innerHTML =
			'<span class="smdm-media-thumb-wrap"><img src="" alt="" width="80" height="80" /></span><button type="button" class="button-link smdm-media-remove" aria-label="Remove">&times;</button>';
		li.querySelector( 'img' ).setAttribute( 'src', url );
		$list.append( li );
	}

	function renderPreviews( $wrap, csv ) {
		var ids = parseIds( csv );
		var $list = $wrap.find( '.smdm-media-preview' );
		$list.empty();
		if ( typeof wp === 'undefined' || ! wp.media || ! ids.length ) {
			return;
		}
		ids.forEach( function ( id ) {
			var attachment = wp.media.attachment( id );
			attachment.fetch().done( function () {
				var sizes = attachment.get( 'sizes' );
				var url =
					sizes && sizes.thumbnail ? sizes.thumbnail.url : attachment.get( 'url' );
				appendTile( $list, id, url || '' );
			} );
		} );
	}

	function openFrame( $wrap ) {
		if ( typeof wp === 'undefined' || ! wp.media ) {
			window.console.error(
				'SMDM: WordPress media (wp.media) is not available.'
			);
			return;
		}
		var multiple = $wrap.data( 'multiple' ) === 1 || $wrap.data( 'multiple' ) === '1';
		var $hidden = $wrap.find( '.smdm-media-ids' );
		var existing = parseIds( $hidden.val() );

		var frame = wp.media( {
			title: multiple ? 'Choose images' : 'Choose image',
			button: {
				text: multiple ? 'Add to gallery' : 'Use image',
			},
			multiple: multiple,
			library: { type: 'image' },
		} );

		frame.on( 'select', function () {
			var selection = frame.state().get( 'selection' );
			var newIds = [];
			selection.each( function ( att ) {
				newIds.push( att.id );
			} );
			var merged;
			if ( multiple ) {
				merged = uniqueJoin( existing.concat( newIds ) );
			} else {
				merged = newIds.length ? String( newIds[ 0 ] ) : '';
			}
			$hidden.val( merged );
			renderPreviews( $wrap, merged );
		} );

		frame.open();
	}

	$( function () {
		$( document ).on( 'click', '.smdm-media-add', function ( e ) {
			e.preventDefault();
			var $wrap = $( this ).closest( '.smdm-media-field' );
			openFrame( $wrap );
		} );

		$( document ).on( 'click', '.smdm-media-clear', function ( e ) {
			e.preventDefault();
			var $wrap = $( this ).closest( '.smdm-media-field' );
			$wrap.find( '.smdm-media-ids' ).val( '' );
			$wrap.find( '.smdm-media-preview' ).empty();
		} );

		$( document ).on( 'click', '.smdm-media-remove', function ( e ) {
			e.preventDefault();
			var $tile = $( this ).closest( '.smdm-media-tile' );
			var $wrap = $( this ).closest( '.smdm-media-field' );
			var id = parseInt( $tile.attr( 'data-id' ), 10 );
			$tile.remove();
			var $hidden = $wrap.find( '.smdm-media-ids' );
			var ids = parseIds( $hidden.val() ).filter( function ( n ) {
				return n !== id;
			} );
			$hidden.val( uniqueJoin( ids ) );
			syncPreviewFromHidden( $wrap );
		} );
	} );
} )( jQuery );
