<?php
class SMDM_Import_Export {

	/** @var self|null */
	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_post_smdm_download_import_template', array( $this, 'handle_download_template_get' ) );
	}

	public function render_page_custom() {
		if ( isset( $_POST['smdm_export_csv'] ) ) {
			$this->handle_export();
		}
		if ( isset( $_POST['smdm_import_csv'] ) ) {
			$this->handle_import();
		}
		$header_row           = $this->get_export_header_row();
		$template_download_url = wp_nonce_url(
			admin_url( 'admin-post.php?action=smdm_download_import_template' ),
			'smdm_download_import_template'
		);
		?>
		<div class="smdm-ie-page">
			<div class="smdm-content-card">
				<h3 class="smdm-form-section-title"><?php esc_html_e( 'Export Database', 'smdm' ); ?></h3>
				<p class="smdm-muted-block"><?php esc_html_e( 'CSV columns match your Form Fields tab: the member name field id (e.g. full_name), Category, Status, then every other field id in the order you saved.', 'smdm' ); ?></p>
				<p class="smdm-muted-block"><code><?php echo esc_html( implode( ',', $header_row ) ); ?></code></p>
				<form method="post">
					<?php wp_nonce_field( 'smdm_ie_action', 'smdm_ie_nonce' ); ?>
					<button type="submit" name="smdm_export_csv" class="smdm-btn" style="background:#64748b;"><?php esc_html_e( 'Download CSV Export', 'smdm' ); ?></button>
				</form>

				<div class="smdm-ie-section">
					<h3 class="smdm-form-section-title"><?php esc_html_e( 'Import Members', 'smdm' ); ?></h3>
					<p class="smdm-muted-block"><?php esc_html_e( 'Download a fresh .csv template built from your current form fields (column headers use each field id). Fill rows under that header row, save as CSV, then upload. Old files using the first column title "Name" (instead of your name field id) still import correctly.', 'smdm' ); ?></p>
					<p class="smdm-ie-template-actions">
						<a href="<?php echo esc_url( $template_download_url ); ?>" class="smdm-btn" style="background:#0f766e;text-decoration:none;display:inline-block;"><?php esc_html_e( 'Download import template (CSV)', 'smdm' ); ?></a>
					</p>
					<form method="post" enctype="multipart/form-data" class="smdm-ie-import-form">
						<?php wp_nonce_field( 'smdm_ie_action', 'smdm_ie_nonce' ); ?>
						<input type="file" name="csv_file" accept=".csv,text/csv,.txt,application/vnd.ms-excel" required class="smdm-file-input">
						<button type="submit" name="smdm_import_csv" class="smdm-btn"><?php esc_html_e( 'Upload & Import CSV', 'smdm' ); ?></button>
					</form>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * CSV header row for export, import template, and expected import mapping (non-legacy).
	 * Uses the saved name field id (e.g. full_name) so headers match the Form Fields list.
	 *
	 * @return string[]
	 */
	private function get_export_header_row() {
		$fields  = SMDM_Field_Schema::get_sorted_fields();
		$name_id = 'full_name';
		foreach ( $fields as $f ) {
			if ( ! empty( $f['is_name_field'] ) && ! empty( $f['id'] ) ) {
				$name_id = $f['id'];
				break;
			}
		}
		$row0 = array( $name_id, 'Category', 'Status' );
		foreach ( $fields as $f ) {
			if ( ! empty( $f['is_name_field'] ) ) {
				continue;
			}
			$row0[] = $f['id'];
		}
		return $row0;
	}

	/**
	 * Resolve which CSV column holds the member display name (title).
	 *
	 * @param array<string,int> $map Lowercase header => column index.
	 * @return int|null
	 */
	private function get_name_column_index( $map ) {
		$try = array( 'name' );
		foreach ( SMDM_Field_Schema::get_sorted_fields() as $f ) {
			if ( ! empty( $f['is_name_field'] ) && ! empty( $f['id'] ) ) {
				$try[] = strtolower( $f['id'] );
				break;
			}
		}
		$try = array_unique( $try );
		foreach ( $try as $key ) {
			if ( isset( $map[ $key ] ) ) {
				return (int) $map[ $key ];
			}
		}
		return null;
	}

	/**
	 * admin-post handler: sends template CSV as a file download (no full page reload).
	 */
	public function handle_download_template_get() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to download this template.', 'smdm' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'smdm_download_import_template' );
		$this->send_import_template_csv();
	}

	/**
	 * UTF-8 BOM so Excel and similar tools open the file as UTF-8.
	 */
	private function csv_download_headers( $filename ) {
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Content-Description: File Transfer' );
		header( 'X-Content-Type-Options: nosniff' );
	}

	/**
	 * Strip UTF-8 BOM from first header cell so column keys match (e.g. "name" not "\xEF\xBB\xBFname").
	 *
	 * @param array $header Header row from fgetcsv.
	 * @return array
	 */
	private function normalize_csv_header_row( $header ) {
		if ( ! is_array( $header ) || ! isset( $header[0] ) ) {
			return $header;
		}
		$header[0] = (string) $header[0];
		if ( '' !== $header[0] && strncmp( $header[0], "\xEF\xBB\xBF", 3 ) === 0 ) {
			$header[0] = substr( $header[0], 3 );
		}
		return $header;
	}

	private function send_import_template_csv() {
		if ( headers_sent() ) {
			wp_die( esc_html__( 'Could not send CSV: output already started.', 'smdm' ) );
		}

		$this->csv_download_headers( 'members_import_template_' . gmdate( 'Y-m-d' ) . '.csv' );

		$output = fopen( 'php://output', 'w' );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- raw UTF-8 BOM for CSV consumers.
		fwrite( $output, "\xEF\xBB\xBF" );
		$header = $this->get_export_header_row();
		fputcsv( $output, $header );
		$blank = array_fill( 0, count( $header ), '' );
		fputcsv( $output, $blank );
		fclose( $output );
		exit;
	}

	private function handle_export() {
		check_admin_referer( 'smdm_ie_action', 'smdm_ie_nonce' );

		$this->csv_download_headers( 'members_export_' . gmdate( 'Y-m-d' ) . '.csv' );

		$output = fopen( 'php://output', 'w' );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- raw UTF-8 BOM for CSV consumers.
		fwrite( $output, "\xEF\xBB\xBF" );
		$fields = SMDM_Field_Schema::get_sorted_fields();
		fputcsv( $output, $this->get_export_header_row() );

		$members = get_posts( array( 'post_type' => 'member', 'posts_per_page' => -1, 'post_status' => 'publish' ) );
		foreach ( $members as $m ) {
			$cats = wp_get_post_terms( $m->ID, 'member_category', array( 'fields' => 'names' ) );
			$line = array(
				$m->post_title,
				implode( '|', $cats ),
				SMDM_Field_Schema::get_member_value( $m->ID, array( 'id' => 'account_status' ) ),
			);
			foreach ( $fields as $f ) {
				if ( ! empty( $f['is_name_field'] ) ) {
					continue;
				}
				$line[] = SMDM_Field_Schema::get_member_value( $m->ID, $f );
			}
			fputcsv( $output, $line );
		}
		fclose( $output );
		exit;
	}

	/**
	 * Detect legacy CSV (fixed column order).
	 */
	private function is_legacy_csv_header( $header_row ) {
		if ( ! is_array( $header_row ) || count( $header_row ) < 12 ) {
			return false;
		}
		$norm = array_map(
			function ( $c ) {
				return strtolower( trim( (string) $c ) );
			},
			$header_row
		);
		$legacy = array( 'name', 'ic', 'gender', 'dob', 'email', 'phone', 'address', 'postcode', 'city', 'state', 'category', 'status' );
		for ( $i = 0; $i < 12; $i++ ) {
			if ( ! isset( $norm[ $i ] ) || $norm[ $i ] !== $legacy[ $i ] ) {
				return false;
			}
		}
		return true;
	}

	private function handle_import() {
		check_admin_referer( 'smdm_ie_action', 'smdm_ie_nonce' );

		if ( empty( $_FILES['csv_file'] ) || ! is_array( $_FILES['csv_file'] ) ) {
			echo '<div class="smdm-alert" style="background:#fee2e2;color:#991b1b;">' . esc_html__( 'No file uploaded.', 'smdm' ) . '</div>';
			return;
		}

		$file = $_FILES['csv_file']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( ! isset( $file['error'] ) || UPLOAD_ERR_OK !== (int) $file['error'] || empty( $file['tmp_name'] ) ) {
			echo '<div class="smdm-alert" style="background:#fee2e2;color:#991b1b;">' . esc_html__( 'Upload failed. Try a smaller file or save again as CSV.', 'smdm' ) . '</div>';
			return;
		}

		$orig_name = isset( $file['name'] ) ? wp_unslash( $file['name'] ) : '';
		$ext       = strtolower( pathinfo( $orig_name, PATHINFO_EXTENSION ) );
		if ( ! in_array( $ext, array( 'csv', 'txt' ), true ) ) {
			echo '<div class="smdm-alert" style="background:#fee2e2;color:#991b1b;">' . esc_html__( 'Please upload a comma-separated file saved with a .csv extension (or .txt). In Excel use Save As → CSV UTF-8 (comma delimited) (*.csv) if you see that option.', 'smdm' ) . '</div>';
			return;
		}

		$tmp = $file['tmp_name'];
		if ( ! is_uploaded_file( $tmp ) ) {
			echo '<div class="smdm-alert" style="background:#fee2e2;color:#991b1b;">' . esc_html__( 'Invalid upload.', 'smdm' ) . '</div>';
			return;
		}

		$handle = fopen( $tmp, 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( ! $handle ) {
			echo '<div class="smdm-alert" style="background:#fee2e2;color:#991b1b;">' . esc_html__( 'Could not read file.', 'smdm' ) . '</div>';
			return;
		}

		$header = fgetcsv( $handle );
		if ( ! $header ) {
			fclose( $handle );
			echo '<div class="smdm-alert" style="background:#fee2e2;color:#991b1b;">' . esc_html__( 'Empty CSV.', 'smdm' ) . '</div>';
			return;
		}

		$header = $this->normalize_csv_header_row( $header );

		$legacy = $this->is_legacy_csv_header( $header );
		$map    = array();
		if ( ! $legacy ) {
			foreach ( $header as $i => $col ) {
				$map[ strtolower( trim( (string) $col ) ) ] = (int) $i;
			}
		}

		$fields = SMDM_Field_Schema::get_sorted_fields();
		$count  = 0;

		$legacy_col = array(
			'ic_number'       => 1,
			'gender'          => 2,
			'date_of_birth'   => 3,
			'email'           => 4,
			'phone'           => 5,
			'address'         => 6,
			'postcode'        => 7,
			'city'            => 8,
			'state'           => 9,
			'account_status'  => 11,
		);

		while ( ( $data = fgetcsv( $handle ) ) !== false ) {
			if ( $legacy ) {
				$name = isset( $data[0] ) ? sanitize_text_field( $data[0] ) : '';
				if ( '' === $name ) {
					continue;
				}
				$post_id = wp_insert_post(
					array(
						'post_title'  => $name,
						'post_type'   => 'member',
						'post_status' => 'publish',
					),
					true
				);
				if ( is_wp_error( $post_id ) || ! $post_id ) {
					continue;
				}
				foreach ( $fields as $field ) {
					if ( ! empty( $field['is_name_field'] ) ) {
						continue;
					}
					$fid = $field['id'];
					if ( ! isset( $legacy_col[ $fid ] ) ) {
						continue;
					}
					$idx = $legacy_col[ $fid ];
					$raw = isset( $data[ $idx ] ) ? $data[ $idx ] : '';
					$val = SMDM_Field_Schema::sanitize_value_for_field( $field, is_string( $raw ) ? $raw : '' );
					update_post_meta( $post_id, SMDM_Field_Schema::meta_key_for( $fid ), $val );
					$leg = SMDM_Field_Schema::legacy_meta_key( $fid );
					if ( $leg ) {
						update_post_meta( $post_id, $leg, $val );
					}
				}
				if ( ! empty( $data[10] ) ) {
					wp_set_object_terms( $post_id, sanitize_text_field( $data[10] ), 'member_category' );
				}
				++$count;
				continue;
			}

			$name_idx = $this->get_name_column_index( $map );
			if ( null === $name_idx || empty( $data[ $name_idx ] ) ) {
				continue;
			}
			$name    = sanitize_text_field( $data[ $name_idx ] );
			$post_id = wp_insert_post(
				array(
					'post_title'  => $name,
					'post_type'   => 'member',
					'post_status' => 'publish',
				),
				true
			);
			if ( is_wp_error( $post_id ) || ! $post_id ) {
				continue;
			}
			foreach ( $fields as $field ) {
				if ( ! empty( $field['is_name_field'] ) ) {
					continue;
				}
				$fid_key = strtolower( $field['id'] );
				if ( ! isset( $map[ $fid_key ] ) ) {
					continue;
				}
				$col = (int) $map[ $fid_key ];
				$raw = isset( $data[ $col ] ) ? $data[ $col ] : '';
				$val = SMDM_Field_Schema::sanitize_value_for_field( $field, is_string( $raw ) ? $raw : '' );
				update_post_meta( $post_id, SMDM_Field_Schema::meta_key_for( $field['id'] ), $val );
				$leg = SMDM_Field_Schema::legacy_meta_key( $field['id'] );
				if ( $leg ) {
					update_post_meta( $post_id, $leg, $val );
				}
			}
			if ( isset( $map['category'] ) && ! empty( $data[ $map['category'] ] ) ) {
				wp_set_object_terms( $post_id, sanitize_text_field( $data[ $map['category'] ] ), 'member_category' );
			}
			++$count;
		}
		fclose( $handle );

		echo '<div class="smdm-alert success">' . esc_html( sprintf( /* translators: %d imported rows */ __( 'Imported %d members.', 'smdm' ), $count ) ) . '</div>';
	}
}
