<?php
/**
 * Media helpers: attachment IDs for member images and lossless-oriented optimization.
 */
class SMDM_Media {

	/**
	 * Parse comma- or pipe-separated attachment ids from stored meta.
	 *
	 * @param mixed $raw String or array fragment.
	 * @return int[]
	 */
	public static function parse_attachment_ids( $raw ) {
		if ( is_array( $raw ) ) {
			$parts = $raw;
		} else {
			$s = is_string( $raw ) ? $raw : '';
			$s = str_replace( '|', ',', $s );
			$parts = array_filter( array_map( 'trim', explode( ',', $s ) ) );
		}
		$ids = array();
		foreach ( $parts as $p ) {
			$n = absint( $p );
			if ( $n > 0 ) {
				$ids[] = $n;
			}
		}
		return array_values( array_unique( $ids ) );
	}

	/**
	 * @param int $attachment_id Attachment post ID.
	 */
	public static function is_image_attachment( $attachment_id ) {
		$attachment_id = (int) $attachment_id;
		if ( $attachment_id <= 0 ) {
			return false;
		}
		$post = get_post( $attachment_id );
		if ( ! $post || 'attachment' !== $post->post_type ) {
			return false;
		}
		return wp_attachment_is_image( $attachment_id );
	}

	/**
	 * Strip metadata and re-save at maximum quality where applicable so file size drops
	 * without intentionally reducing visible quality (PNG/GIF remain lossless; JPEG uses quality 100).
	 * Runs once per attachment unless the flag meta is cleared.
	 *
	 * @param int $attachment_id Attachment post ID.
	 * @return bool Whether optimization ran or was already done / skipped as non-image.
	 */
	public static function optimize_attachment_lossless( $attachment_id ) {
		$attachment_id = (int) $attachment_id;
		if ( $attachment_id <= 0 || ! self::is_image_attachment( $attachment_id ) ) {
			return false;
		}

		if ( '1' === get_post_meta( $attachment_id, '_smdm_lossless_opt', true ) ) {
			return true;
		}

		$path = get_attached_file( $attachment_id );
		if ( ! $path || ! is_readable( $path ) || ! is_writable( $path ) ) {
			return false;
		}

		$mime = get_post_mime_type( $attachment_id );
		$ok_mimes = array(
			'image/jpeg',
			'image/pjpeg',
			'image/png',
			'image/gif',
			'image/webp',
		);
		if ( ! in_array( $mime, $ok_mimes, true ) ) {
			update_post_meta( $attachment_id, '_smdm_lossless_opt', '1' );
			return true;
		}

		if ( class_exists( 'Imagick' ) ) {
			try {
				$im = new Imagick( $path );
				$im->stripImage();
				if ( 'image/jpeg' === $mime || 'image/pjpeg' === $mime ) {
					$im->setImageCompressionQuality( 100 );
				}
				$im->writeImage( $path );
				$im->clear();
				$im->destroy();
				update_post_meta( $attachment_id, '_smdm_lossless_opt', '1' );
				if ( function_exists( 'wp_generate_attachment_metadata' ) ) {
					require_once ABSPATH . 'wp-admin/includes/image.php';
					wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $path ) );
				}
				return true;
			} catch ( Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
				// Fall through to WP image editor.
			}
		}

		$editor = wp_get_image_editor( $path );
		if ( is_wp_error( $editor ) ) {
			return false;
		}
		$editor->set_quality( 100 );
		$saved = $editor->save( $path );
		if ( is_wp_error( $saved ) ) {
			return false;
		}

		update_post_meta( $attachment_id, '_smdm_lossless_opt', '1' );
		require_once ABSPATH . 'wp-admin/includes/image.php';
		wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $path ) );

		return true;
	}
}
