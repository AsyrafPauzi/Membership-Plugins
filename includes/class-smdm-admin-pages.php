<?php
class SMDM_Admin_Pages {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menus' ) );
	}

	public function register_menus() {
		add_menu_page( 'Member Manager', 'Member Manager', 'manage_options', 'smdm-app', array( $this, 'render_app_shell' ), 'dashicons-groups', 25 );
	}

	private function get_category_label() {
		$label = get_option( 'smdm_category_label', __( 'Category', 'smdm' ) );
		$label = is_string( $label ) ? trim( $label ) : '';
		return '' !== $label ? $label : __( 'Category', 'smdm' );
	}

	/**
	 * GET params for member list filters & pagination (admin).
	 *
	 * @return array{smdm_pp:string,smdm_pg:int,smdm_q_name:string,smdm_q_ic:string,smdm_q_state:string,smdm_q_agency:string}
	 */
	private function get_members_list_request_params() {
		$allowed_pp = array( '5', '10', '25', '50', '100', 'all' );
		$pp         = isset( $_GET['smdm_pp'] ) ? sanitize_text_field( wp_unslash( $_GET['smdm_pp'] ) ) : '25'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! in_array( $pp, $allowed_pp, true ) ) {
			$pp = '25';
		}
		$pg = isset( $_GET['smdm_pg'] ) ? max( 1, (int) $_GET['smdm_pg'] ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		return array(
			'smdm_pp'       => $pp,
			'smdm_pg'       => $pg,
			'smdm_q_name'   => isset( $_GET['smdm_q_name'] ) ? sanitize_text_field( wp_unslash( $_GET['smdm_q_name'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'smdm_q_ic'     => isset( $_GET['smdm_q_ic'] ) ? sanitize_text_field( wp_unslash( $_GET['smdm_q_ic'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'smdm_q_state'  => isset( $_GET['smdm_q_state'] ) ? sanitize_text_field( wp_unslash( $_GET['smdm_q_state'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'smdm_q_agency' => isset( $_GET['smdm_q_agency'] ) ? sanitize_text_field( wp_unslash( $_GET['smdm_q_agency'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		);
	}

	/**
	 * Build admin members tab URL preserving list state.
	 *
	 * @param array<string,mixed> $overrides Query args to set/replace (empty values removed unless false preserved).
	 */
	private function members_list_url( $overrides = array() ) {
		$base = admin_url( 'admin.php' );
		$p    = $this->get_members_list_request_params();
		$args = array(
			'page'           => 'smdm-app',
			'tab'            => 'members',
			'smdm_pp'        => $p['smdm_pp'],
			'smdm_pg'        => $p['smdm_pg'] > 1 ? $p['smdm_pg'] : '',
			'smdm_q_name'    => $p['smdm_q_name'],
			'smdm_q_ic'      => $p['smdm_q_ic'],
			'smdm_q_state'   => $p['smdm_q_state'],
			'smdm_q_agency'  => $p['smdm_q_agency'],
		);
		$args = array_merge( $args, $overrides );
		$args = array_filter(
			$args,
			function ( $v ) {
				return '' !== $v && null !== $v;
			}
		);
		return add_query_arg( $args, $base );
	}

	/**
	 * @param array{smdm_pp:string,smdm_pg:int,smdm_q_name:string,smdm_q_ic:string,smdm_q_state:string,smdm_q_agency:string} $p
	 * @return WP_Query
	 */
	private function query_members_list( $p ) {
		$posts_per_page = ( 'all' === $p['smdm_pp'] ) ? -1 : (int) $p['smdm_pp'];
		$args           = array(
			'post_type'              => 'member',
			'post_status'            => 'publish',
			'posts_per_page'         => $posts_per_page,
			'paged'                  => ( 'all' === $p['smdm_pp'] ) ? 1 : $p['smdm_pg'],
			'orderby'                => 'title',
			'order'                  => 'ASC',
			'no_found_rows'          => ( 'all' === $p['smdm_pp'] ),
			'update_post_meta_cache' => true,
			'update_post_term_cache' => false,
		);

		if ( '' !== $p['smdm_q_name'] ) {
			$args['s'] = $p['smdm_q_name'];
		}

		$meta_clauses = array( 'relation' => 'AND' );

		$append_meta_or = function ( $field_id, $needle ) use ( &$meta_clauses ) {
			if ( '' === $needle || ! SMDM_Field_Schema::get_field_by_id( $field_id ) ) {
				return;
			}
			$like  = '*' . $needle . '*';
			$parts = array(
				'relation' => 'OR',
				array(
					'key'     => SMDM_Field_Schema::meta_key_for( $field_id ),
					'value'   => $like,
					'compare' => 'LIKE',
				),
			);
			$legacy = SMDM_Field_Schema::legacy_meta_key( $field_id );
			if ( $legacy ) {
				$parts[] = array(
					'key'     => $legacy,
					'value'   => $like,
					'compare' => 'LIKE',
				);
			}
			$meta_clauses[] = $parts;
		};

		$append_meta_or( 'ic_number', $p['smdm_q_ic'] );
		$append_meta_or( 'state', $p['smdm_q_state'] );

		if ( '' !== $p['smdm_q_agency'] ) {
			$like   = '*' . $p['smdm_q_agency'] . '*';
			$agency = array( 'relation' => 'OR' );
			foreach ( array( 'agensi', 'institusi' ) as $fid ) {
				if ( SMDM_Field_Schema::get_field_by_id( $fid ) ) {
					$agency[] = array(
						'key'     => SMDM_Field_Schema::meta_key_for( $fid ),
						'value'   => $like,
						'compare' => 'LIKE',
					);
				}
			}
			if ( count( $agency ) > 1 ) {
				$meta_clauses[] = $agency;
			}
		}

		if ( count( $meta_clauses ) > 1 ) {
			$args['meta_query'] = $meta_clauses;
		}

		return new WP_Query( $args );
	}

	public function render_app_shell() {
		$active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'dashboard';
		$member_id  = isset( $_GET['member_id'] ) ? intval( $_GET['member_id'] ) : 0;
		$notice     = '';

		if ( $member_id > 0 ) {
			$active_tab = 'edit_member';
		}

		if ( in_array( $active_tab, array( 'blast', 'settings' ), true ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=smdm-app&tab=dashboard' ) );
			exit;
		}

		if ( isset( $_POST['smdm_seed_dummy_action'] ) ) {
			check_admin_referer( 'smdm_seed_dummy', 'smdm_seed_nonce' );
			if ( class_exists( 'SMDM_Field_Schema' ) ) {
				SMDM_Field_Schema::seed_dummy_members( true );
				$notice = '<div class="smdm-alert success">' . esc_html__( '5 dummy members were generated.', 'smdm' ) . '</div>';
			}
		}

		$tabs = array(
			'dashboard'  => array( 'label' => __( 'Dashboard', 'smdm' ), 'icon' => 'dashicons-dashboard' ),
			'members'    => array( 'label' => __( 'Member List', 'smdm' ), 'icon' => 'dashicons-admin-users' ),
			'fields'     => array( 'label' => __( 'Form Fields', 'smdm' ), 'icon' => 'dashicons-editor-table' ),
			'categories' => array( 'label' => __( 'Categories', 'smdm' ), 'icon' => 'dashicons-category' ),
			'import'     => array( 'label' => __( 'Import/Export', 'smdm' ), 'icon' => 'dashicons-database-export' ),
		);

		$members_nav_active = ( 'members' === $active_tab || 'add_member' === $active_tab || 'edit_member' === $active_tab );
		?>
		<div class="smdm-app-wrapper" id="smdm-app-root">
			<button type="button" class="smdm-nav-toggle" id="smdm-nav-toggle" aria-expanded="false" aria-controls="smdm-sidebar">
				<span class="dashicons dashicons-menu-alt3"></span>
				<span class="smdm-nav-toggle-text"><?php esc_html_e( 'Menu', 'smdm' ); ?></span>
			</button>
			<div class="smdm-sidebar-overlay" id="smdm-sidebar-overlay" hidden></div>
			<div class="smdm-sidebar" id="smdm-sidebar">
				<div class="smdm-logo-section">
					<span class="dashicons dashicons-groups"></span>
					<h2><?php esc_html_e( 'MEMBER MANAGER', 'smdm' ); ?></h2>
				</div>
				<?php foreach ( $tabs as $slug => $data ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=smdm-app&tab=' . rawurlencode( $slug ) ) ); ?>" class="smdm-nav-item <?php echo ( $active_tab === $slug || ( 'members' === $slug && $members_nav_active ) ) ? 'active' : ''; ?>">
						<span class="dashicons <?php echo esc_attr( $data['icon'] ); ?>"></span>
						<?php echo esc_html( $data['label'] ); ?>
					</a>
				<?php endforeach; ?>
			</div>

			<div class="smdm-main">
				<div class="smdm-header-bar">
					<h1>
						<?php
						if ( 'edit_member' === $active_tab ) {
							esc_html_e( 'Edit Member', 'smdm' );
						} elseif ( 'add_member' === $active_tab ) {
							esc_html_e( 'Create New Member', 'smdm' );
						} elseif ( 'categories' === $active_tab ) {
							esc_html_e( 'Category Manager', 'smdm' );
						} elseif ( 'fields' === $active_tab ) {
							esc_html_e( 'Form Fields', 'smdm' );
						} elseif ( isset( $tabs[ $active_tab ] ) ) {
							echo esc_html( $tabs[ $active_tab ]['label'] );
						} else {
							esc_html_e( 'Member Manager', 'smdm' );
						}
						?>
					</h1>
					<div class="smdm-user-info"><?php echo esc_html( wp_get_current_user()->display_name ); ?></div>
				</div>

				<div class="smdm-content-area">
					<?php echo $notice; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php
					switch ( $active_tab ) {
						case 'dashboard':
							$this->render_dashboard_content();
							break;
						case 'members':
							$this->render_members_list();
							break;
						case 'fields':
							$this->render_field_builder();
							break;
						case 'categories':
							$this->render_categories_manager();
							break;
						case 'add_member':
							$this->render_custom_member_form();
							break;
						case 'edit_member':
							$this->render_custom_member_form( $member_id );
							break;
						case 'import':
							SMDM_Import_Export::instance()->render_page_custom();
							break;
					}
					?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Dynamic field definitions UI + save.
	 */
	private function render_field_builder() {
		if ( isset( $_POST['smdm_save_category_label'] ) ) {
			check_admin_referer( 'smdm_category_label_action', 'smdm_category_label_nonce' );
			$new_label = isset( $_POST['smdm_category_label'] ) ? sanitize_text_field( wp_unslash( $_POST['smdm_category_label'] ) ) : __( 'Category', 'smdm' );
			update_option( 'smdm_category_label', $new_label, false );
			echo '<div class="smdm-alert success">' . esc_html__( 'Category label updated.', 'smdm' ) . '</div>';
		}

		if ( isset( $_POST['smdm_schema_save'] ) ) {
			check_admin_referer( 'smdm_schema', 'smdm_nonce' );
			$order = isset( $_POST['smdm_field_order'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['smdm_field_order'] ) ) : array();
			$rows  = isset( $_POST['smdm_field_b'] ) && is_array( $_POST['smdm_field_b'] ) ? wp_unslash( $_POST['smdm_field_b'] ) : array();
			$built = array();
			$o     = 0;
			foreach ( $order as $fid ) {
				if ( empty( $rows[ $fid ] ) || ! is_array( $rows[ $fid ] ) ) {
					continue;
				}
				$r = $rows[ $fid ];
				$req                = ! empty( $r['required'] );
				$is_name            = ! empty( $r['is_name_field'] );
				$is_primary         = ! empty( $r['is_primary_email'] );
				$type               = isset( $r['type'] ) ? sanitize_key( $r['type'] ) : 'text';
				if ( 'full_name' === $fid ) {
					$req     = true;
					$is_name = true;
				}
				if ( 'email' === $fid ) {
					$is_primary = true;
					$type       = 'email';
				}
				if ( 'account_status' === $fid ) {
					$req  = true;
					$type = 'status';
				}
				$built[] = array(
					'id'                      => $fid,
					'label'                   => isset( $r['label'] ) ? sanitize_text_field( $r['label'] ) : $fid,
					'type'                    => $type,
					'section'                 => isset( $r['section'] ) ? sanitize_key( $r['section'] ) : 'custom',
					'order'                   => $o++,
					'required'                => $req,
					'placeholder'             => isset( $r['placeholder'] ) ? sanitize_text_field( $r['placeholder'] ) : '',
					'options'                 => isset( $r['options_text'] ) ? array_filter( array_map( 'trim', explode( "\n", sanitize_textarea_field( $r['options_text'] ) ) ) ) : array(),
					'is_name_field'          => $is_name,
					'is_primary_email'       => $is_primary,
					'show_in_directory'       => ! empty( $r['show_in_directory'] ),
					'show_in_directory_modal' => ! empty( $r['show_in_directory_modal'] ),
					'filterable'              => ! empty( $r['filterable'] ),
					'show_in_list'            => ! empty( $r['show_in_list'] ),
				);
			}
			$errs = SMDM_Field_Schema::save_schema( $built );
			if ( ! empty( $errs ) ) {
				echo '<div class="smdm-alert" style="background:#fee2e2;color:#991b1b;">' . esc_html( implode( ' ', $errs ) ) . '</div>';
			} else {
				echo '<div class="smdm-alert success">' . esc_html__( 'Field configuration saved.', 'smdm' ) . '</div>';
			}
		}

		if ( isset( $_POST['smdm_add_custom_field'] ) ) {
			check_admin_referer( 'smdm_schema', 'smdm_nonce' );
			$label   = isset( $_POST['new_field_label'] ) ? sanitize_text_field( wp_unslash( $_POST['new_field_label'] ) ) : '';
			$type    = isset( $_POST['new_field_type'] ) ? sanitize_key( wp_unslash( $_POST['new_field_type'] ) ) : 'text';
			$section = isset( $_POST['new_field_section'] ) ? sanitize_key( wp_unslash( $_POST['new_field_section'] ) ) : 'custom';
			if ( '' !== $label ) {
				$new  = SMDM_Field_Schema::build_new_field_row( $label, $type, $section );
				$all  = array_merge( SMDM_Field_Schema::get_sorted_fields(), array( $new ) );
				$errs = SMDM_Field_Schema::save_schema( $all );
				if ( ! empty( $errs ) ) {
					echo '<div class="smdm-alert" style="background:#fee2e2;color:#991b1b;">' . esc_html( implode( ' ', $errs ) ) . '</div>';
				} else {
					echo '<div class="smdm-alert success">' . esc_html__( 'New field added. Review flags and save if needed.', 'smdm' ) . '</div>';
				}
			}
		}

		if ( isset( $_GET['smdm_delete_field'] ) && isset( $_GET['_wpnonce'] ) ) {
			if ( wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'smdm_delete_field' ) ) {
				$df = sanitize_key( wp_unslash( $_GET['smdm_delete_field'] ) );
				if ( ! in_array( $df, array( 'full_name', 'email', 'account_status' ), true ) ) {
					$errs = SMDM_Field_Schema::delete_field_by_id( $df );
					if ( ! empty( $errs ) ) {
						echo '<div class="smdm-alert" style="background:#fee2e2;color:#991b1b;">' . esc_html( implode( ' ', $errs ) ) . '</div>';
					} else {
						echo '<div class="smdm-alert success">' . esc_html__( 'Field removed.', 'smdm' ) . '</div>';
					}
				}
			}
		}

		$fields         = SMDM_Field_Schema::get_sorted_fields();
		$section_labels = SMDM_Field_Schema::section_labels();
		$base           = admin_url( 'admin.php?page=smdm-app&tab=fields' );
		$category_label = $this->get_category_label();
		?>
		<div class="smdm-content-card">
			<form method="post" class="smdm-category-label-form">
				<?php wp_nonce_field( 'smdm_category_label_action', 'smdm_category_label_nonce' ); ?>
				<div class="form-group">
					<label for="smdm_category_label"><?php esc_html_e( 'Category field label', 'smdm' ); ?></label>
					<div class="smdm-category-label-row">
						<input type="text" id="smdm_category_label" name="smdm_category_label" value="<?php echo esc_attr( $category_label ); ?>" />
						<button type="submit" name="smdm_save_category_label" class="smdm-btn secondary"><?php esc_html_e( 'Save label', 'smdm' ); ?></button>
					</div>
				</div>
			</form>
			<p class="smdm-help-intro"><?php esc_html_e( 'Define which information you collect for each member. New fields appear on the member form, in export (by column id), and optionally on the public directory.', 'smdm' ); ?></p>
			<p class="smdm-muted-block"><?php esc_html_e( 'Drag rows using the handle in the first column to reorder fields, then click save.', 'smdm' ); ?></p>
			<form method="post" class="smdm-field-builder-form">
				<?php wp_nonce_field( 'smdm_schema', 'smdm_nonce' ); ?>
				<div class="smdm-field-builder-scroll">
					<table class="smdm-custom-table smdm-field-def-table">
						<thead>
							<tr>
								<th class="smdm-col-order"><?php esc_html_e( 'Order', 'smdm' ); ?></th>
								<th class="smdm-col-id"><?php esc_html_e( 'Id', 'smdm' ); ?></th>
								<th class="smdm-col-label"><?php esc_html_e( 'Label', 'smdm' ); ?></th>
								<th class="smdm-col-type"><?php esc_html_e( 'Type', 'smdm' ); ?></th>
								<th class="smdm-col-section"><?php esc_html_e( 'Section', 'smdm' ); ?></th>
								<th class="smdm-col-flags"><?php esc_html_e( 'Flags', 'smdm' ); ?></th>
								<th class="smdm-col-options"><?php esc_html_e( 'Select options (one per line)', 'smdm' ); ?></th>
								<th class="smdm-col-actions"><?php esc_html_e( 'Actions', 'smdm' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php
							foreach ( $fields as $f ) :
								$fid = $f['id'];
								$protected = in_array( $fid, array( 'full_name', 'email', 'account_status' ), true );
								?>
								<tr class="smdm-field-row" data-field-id="<?php echo esc_attr( $fid ); ?>">
									<td class="smdm-col-order">
										<input type="hidden" class="smdm-order-input" name="smdm_field_order[]" value="<?php echo esc_attr( $fid ); ?>" />
										<button type="button" class="smdm-drag-handle" draggable="true" aria-label="<?php esc_attr_e( 'Drag to reorder', 'smdm' ); ?>" title="<?php esc_attr_e( 'Drag to reorder', 'smdm' ); ?>">
											<span class="dashicons dashicons-move"></span>
										</button>
										<span class="smdm-order-index"></span>
									</td>
									<td class="smdm-col-id"><code><?php echo esc_html( $fid ); ?></code></td>
									<td class="smdm-col-label">
										<input type="text" name="smdm_field_b[<?php echo esc_attr( $fid ); ?>][label]" value="<?php echo esc_attr( $f['label'] ); ?>" class="smdm-input-compact" />
									</td>
									<td class="smdm-col-type">
										<select name="smdm_field_b[<?php echo esc_attr( $fid ); ?>][type]" <?php disabled( $protected ); ?>>
											<?php foreach ( SMDM_Field_Schema::allowed_types() as $t ) : ?>
												<option value="<?php echo esc_attr( $t ); ?>" <?php selected( $f['type'], $t ); ?>><?php echo esc_html( SMDM_Field_Schema::type_admin_label( $t ) ); ?></option>
											<?php endforeach; ?>
										</select>
									</td>
									<td class="smdm-col-section">
										<select name="smdm_field_b[<?php echo esc_attr( $fid ); ?>][section]">
											<?php foreach ( $section_labels as $sk => $sl ) : ?>
												<option value="<?php echo esc_attr( $sk ); ?>" <?php selected( $f['section'], $sk ); ?>><?php echo esc_html( $sl ); ?></option>
											<?php endforeach; ?>
										</select>
									</td>
									<td class="smdm-flag-cells smdm-col-flags">
										<label><input type="checkbox" name="smdm_field_b[<?php echo esc_attr( $fid ); ?>][required]" value="1" <?php checked( ! empty( $f['required'] ) ); ?> <?php disabled( 'full_name' === $fid ); ?> /> <?php esc_html_e( 'Required', 'smdm' ); ?></label>
										<label><input type="checkbox" name="smdm_field_b[<?php echo esc_attr( $fid ); ?>][is_name_field]" value="1" <?php checked( ! empty( $f['is_name_field'] ) ); ?> <?php disabled( 'full_name' !== $fid ); ?> /> <?php esc_html_e( 'Name→title', 'smdm' ); ?></label>
										<label><input type="checkbox" name="smdm_field_b[<?php echo esc_attr( $fid ); ?>][is_primary_email]" value="1" <?php checked( ! empty( $f['is_primary_email'] ) ); ?> <?php disabled( 'email' !== $fid ); ?> /> <?php esc_html_e( 'Primary email', 'smdm' ); ?></label>
										<label><input type="checkbox" name="smdm_field_b[<?php echo esc_attr( $fid ); ?>][show_in_directory]" value="1" <?php checked( ! empty( $f['show_in_directory'] ) ); ?> /> <?php esc_html_e( 'Directory card', 'smdm' ); ?></label>
										<label><input type="checkbox" name="smdm_field_b[<?php echo esc_attr( $fid ); ?>][show_in_directory_modal]" value="1" <?php checked( ! empty( $f['show_in_directory_modal'] ) ); ?> /> <?php esc_html_e( 'Modal', 'smdm' ); ?></label>
										<label><input type="checkbox" name="smdm_field_b[<?php echo esc_attr( $fid ); ?>][filterable]" value="1" <?php checked( ! empty( $f['filterable'] ) ); ?> /> <?php esc_html_e( 'Filter', 'smdm' ); ?></label>
										<label><input type="checkbox" name="smdm_field_b[<?php echo esc_attr( $fid ); ?>][show_in_list]" value="1" <?php checked( ! empty( $f['show_in_list'] ) ); ?> /> <?php esc_html_e( 'Admin list', 'smdm' ); ?></label>
									</td>
									<td class="smdm-col-options">
										<textarea name="smdm_field_b[<?php echo esc_attr( $fid ); ?>][options_text]" rows="3" class="smdm-textarea-compact" placeholder="<?php esc_attr_e( 'Option A', 'smdm' ); ?>"><?php echo esc_textarea( implode( "\n", $f['options'] ?? array() ) ); ?></textarea>
										<input type="text" name="smdm_field_b[<?php echo esc_attr( $fid ); ?>][placeholder]" value="<?php echo esc_attr( $f['placeholder'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Placeholder', 'smdm' ); ?>" class="smdm-input-compact" style="margin-top:6px;" />
									</td>
									<td class="smdm-col-actions">
										<?php if ( ! $protected ) : ?>
											<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'smdm_delete_field', $fid, $base ), 'smdm_delete_field' ) ); ?>" class="smdm-btn-small" style="color:#b91c1c;" onclick="return confirm('<?php echo esc_js( __( 'Delete this field? Values stay in the database but will be hidden until you add a field with the same id.', 'smdm' ) ); ?>');"><?php esc_html_e( 'Delete', 'smdm' ); ?></a>
										<?php else : ?>
											<span class="smdm-muted"><?php esc_html_e( 'Core', 'smdm' ); ?></span>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
				<div class="smdm-sticky-actions">
					<button type="submit" name="smdm_schema_save" class="smdm-btn"><?php esc_html_e( 'Save field configuration', 'smdm' ); ?></button>
				</div>
			</form>
		</div>

		<div class="smdm-content-card">
			<h3 class="smdm-form-section-title"><?php esc_html_e( 'Add custom field', 'smdm' ); ?></h3>
			<form method="post" class="smdm-form-grid smdm-add-field-row">
				<?php wp_nonce_field( 'smdm_schema', 'smdm_nonce' ); ?>
				<div class="form-group">
					<label><?php esc_html_e( 'Label', 'smdm' ); ?></label>
					<input type="text" name="new_field_label" required placeholder="<?php esc_attr_e( 'e.g. Membership tier', 'smdm' ); ?>" />
				</div>
				<div class="form-group">
					<label><?php esc_html_e( 'Type', 'smdm' ); ?></label>
					<select name="new_field_type">
										<?php foreach ( SMDM_Field_Schema::allowed_types() as $t ) : ?>
											<option value="<?php echo esc_attr( $t ); ?>"><?php echo esc_html( SMDM_Field_Schema::type_admin_label( $t ) ); ?></option>
										<?php endforeach; ?>
					</select>
				</div>
				<div class="form-group">
					<label><?php esc_html_e( 'Section', 'smdm' ); ?></label>
					<select name="new_field_section">
						<?php foreach ( $section_labels as $sk => $sl ) : ?>
							<option value="<?php echo esc_attr( $sk ); ?>"><?php echo esc_html( $sl ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="form-group smdm-span-2" style="align-self:end;">
					<button type="submit" name="smdm_add_custom_field" class="smdm-btn"><?php esc_html_e( 'Add field', 'smdm' ); ?></button>
				</div>
			</form>
		</div>
		<?php
	}

	private function render_categories_manager() {
		if ( isset( $_GET['delete_cat'] ) ) {
			check_admin_referer( 'smdm_delete_cat' );
			wp_delete_term( intval( $_GET['delete_cat'] ), 'member_category' );
			echo '<div class="smdm-alert success">' . esc_html__( 'Category deleted successfully.', 'smdm' ) . '</div>';
		}

		if ( isset( $_POST['add_new_cat'] ) ) {
			check_admin_referer( 'smdm_add_cat', 'smdm_nonce' );
			$cat_name = sanitize_text_field( wp_unslash( $_POST['cat_name'] ) );
			if ( ! empty( $cat_name ) ) {
				$result = wp_insert_term( $cat_name, 'member_category' );
				if ( is_wp_error( $result ) ) {
					echo '<div class="smdm-alert" style="background:#fee2e2; color:#991b1b;">' . esc_html( $result->get_error_message() ) . '</div>';
				} else {
					echo '<div class="smdm-alert success">' . esc_html__( 'Category created.', 'smdm' ) . '</div>';
				}
			}
		}

		$categories = get_terms( array( 'taxonomy' => 'member_category', 'hide_empty' => false ) );
		?>
		<div class="smdm-stats-grid smdm-cat-grid">
			<div class="smdm-content-card">
				<h3 class="smdm-form-section-title"><?php esc_html_e( 'Add New Category', 'smdm' ); ?></h3>
				<form method="post">
					<?php wp_nonce_field( 'smdm_add_cat', 'smdm_nonce' ); ?>
					<div class="form-group">
						<label><?php esc_html_e( 'Category Name', 'smdm' ); ?></label>
						<input type="text" name="cat_name" placeholder="<?php esc_attr_e( 'e.g. VIP Members', 'smdm' ); ?>" required>
					</div>
					<div style="margin-top:20px;">
						<button type="submit" name="add_new_cat" class="smdm-btn" style="width:100%;"><?php esc_html_e( 'Create Category', 'smdm' ); ?></button>
					</div>
				</form>
			</div>

			<div class="smdm-content-card">
				<h3 class="smdm-form-section-title"><?php esc_html_e( 'Existing Categories', 'smdm' ); ?></h3>
				<table class="smdm-custom-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Category Name', 'smdm' ); ?></th>
							<th><?php esc_html_e( 'Count', 'smdm' ); ?></th>
							<th style="text-align:right"><?php esc_html_e( 'Actions', 'smdm' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php
						if ( $categories ) :
							foreach ( $categories as $cat ) :
								$delete_url = wp_nonce_url( admin_url( 'admin.php?page=smdm-app&tab=categories&delete_cat=' . intval( $cat->term_id ) ), 'smdm_delete_cat' );
								?>
								<tr>
									<td><strong><?php echo esc_html( $cat->name ); ?></strong></td>
									<td><?php echo intval( $cat->count ); ?> <?php esc_html_e( 'Members', 'smdm' ); ?></td>
									<td style="text-align:right">
										<a href="<?php echo esc_url( $delete_url ); ?>" class="smdm-btn-small" style="color:#ef4444; border-color:#fee2e2;" onclick="return confirm('<?php echo esc_js( __( 'Delete this category?', 'smdm' ) ); ?>');"><?php esc_html_e( 'Delete', 'smdm' ); ?></a>
									</td>
								</tr>
							<?php endforeach; else : ?>
							<tr><td colspan="3" style="text-align:center; padding:30px;"><?php esc_html_e( 'No categories yet.', 'smdm' ); ?></td></tr>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
		</div>
		<?php
	}

	private function render_custom_member_form( $id = 0 ) {
		$schema_fields = SMDM_Field_Schema::get_sorted_fields();
		if ( empty( $schema_fields ) ) {
			SMDM_Field_Schema::maybe_migrate();
			$schema_fields = SMDM_Field_Schema::get_sorted_fields();
		}

		$form_errors = array();

		if ( isset( $_POST['save_member_action'] ) ) {
			check_admin_referer( 'smdm_member_form', 'smdm_nonce' );

			$name_id = SMDM_Field_Schema::get_name_field_id();
			$name_raw = isset( $_POST['smdm_cf'][ $name_id ] ) ? sanitize_text_field( wp_unslash( $_POST['smdm_cf'][ $name_id ] ) ) : '';

			foreach ( $schema_fields as $field ) {
				if ( ! empty( $field['is_name_field'] ) ) {
					continue;
				}
				if ( empty( $field['required'] ) ) {
					continue;
				}
				$v = isset( $_POST['smdm_cf'][ $field['id'] ] ) ? wp_unslash( $_POST['smdm_cf'][ $field['id'] ] ) : '';
				if ( 'checkbox' === $field['type'] ) {
					$v = ! empty( $_POST['smdm_cf'][ $field['id'] ] ) ? '1' : '';
				}
				$san = SMDM_Field_Schema::sanitize_value_for_field( $field, is_string( $v ) ? $v : '' );
				if ( '' === $san || null === $san ) {
					$form_errors[] = sprintf(
						/* translators: %s field label */
						__( '“%s” is required.', 'smdm' ),
						isset( $field['label'] ) ? $field['label'] : $field['id']
					);
				}
			}

			if ( '' === $name_raw ) {
				$form_errors[] = __( 'Full name is required.', 'smdm' );
			}

			if ( empty( $form_errors ) ) {
				$member_data = array(
					'post_title'  => $name_raw,
					'post_type'   => 'member',
					'post_status' => 'publish',
				);
				if ( $id > 0 ) {
					$member_data['ID'] = $id;
					wp_update_post( $member_data );
				} else {
					$id = wp_insert_post( $member_data );
				}

				if ( ! is_wp_error( $id ) && $id ) {
					SMDM_Field_Schema::save_member_fields_from_post( $id, $schema_fields );
					if ( isset( $_POST['m_cat'] ) ) {
						wp_set_object_terms( $id, (int) $_POST['m_cat'], 'member_category' );
					}
					echo '<div class="smdm-alert success">' . esc_html__( 'Member saved successfully!', 'smdm' ) . ' <a href="' . esc_url( admin_url( 'admin.php?page=smdm-app&tab=members' ) ) . '">' . esc_html__( 'View list', 'smdm' ) . '</a></div>';
				}
			} else {
				echo '<div class="smdm-alert" style="background:#fee2e2;color:#991b1b;">' . esc_html( implode( ' ', $form_errors ) ) . '</div>';
			}
		}

		$post         = ( $id > 0 ) ? get_post( $id ) : null;
		$current_cat  = ( $id > 0 ) ? wp_get_object_terms( $id, 'member_category', array( 'fields' => 'ids' ) ) : array();
		$categories   = get_terms( array( 'taxonomy' => 'member_category', 'hide_empty' => false ) );
		$section_keys = SMDM_Field_Schema::section_labels();

		$by_section = array();
		foreach ( $schema_fields as $field ) {
			$sec = isset( $field['section'] ) ? $field['section'] : 'custom';
			if ( 'system' === $sec ) {
				continue;
			}
			$by_section[ $sec ][] = $field;
		}
		?>
		<div class="smdm-content-card smdm-member-form-card">
			<form method="post" class="smdm-member-dynamic-form">
				<?php wp_nonce_field( 'smdm_member_form', 'smdm_nonce' ); ?>

				<?php foreach ( $section_keys as $sec => $sec_label ) : ?>
					<?php if ( empty( $by_section[ $sec ] ) || 'system' === $sec ) { continue; } ?>
					<div class="smdm-form-section" data-section="<?php echo esc_attr( $sec ); ?>">
						<h3 class="smdm-form-section-title"><?php echo esc_html( $sec_label ); ?></h3>
						<div class="smdm-form-grid">
							<?php
							foreach ( $by_section[ $sec ] as $field ) :
								if ( ! empty( $field['is_name_field'] ) ) {
									$val = $post ? $post->post_title : '';
								} else {
									$val = SMDM_Field_Schema::get_member_value( $id, $field );
								}
								$span = ( in_array( $field['type'], array( 'textarea', 'state_ms', 'image', 'image_gallery' ), true ) ) ? 'smdm-span-2' : '';
								SMDM_Field_Schema::render_field_input( $field, $val, 'smdm_cf', $span );
							endforeach;
							?>
						</div>
					</div>
				<?php endforeach; ?>

				<div class="smdm-form-section" data-section="system">
					<h3 class="smdm-form-section-title"><?php echo esc_html( $section_keys['system'] ); ?></h3>
					<div class="smdm-form-grid">
						<?php
						foreach ( $schema_fields as $field ) {
							if ( 'system' !== $field['section'] ) {
								continue;
							}
							$val = SMDM_Field_Schema::get_member_value( $id, $field );
							SMDM_Field_Schema::render_field_input( $field, $val );
						}
						?>
						<div class="form-group">
							<label for="m_cat"><?php echo esc_html( $this->get_category_label() ); ?></label>
							<select name="m_cat" id="m_cat">
								<option value=""><?php echo esc_html( sprintf( __( 'Select %s', 'smdm' ), $this->get_category_label() ) ); ?></option>
								<?php foreach ( $categories as $cat ) : ?>
									<option value="<?php echo esc_attr( (string) $cat->term_id ); ?>" <?php selected( (int) ( $current_cat[0] ?? 0 ), (int) $cat->term_id ); ?>>
										<?php echo esc_html( $cat->name ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</div>
					</div>
				</div>

				<div class="smdm-sticky-actions smdm-member-actions">
					<button type="submit" name="save_member_action" class="smdm-btn"><?php esc_html_e( 'Save member', 'smdm' ); ?></button>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=smdm-app&tab=members' ) ); ?>" class="smdm-btn secondary" style="text-decoration:none;"><?php esc_html_e( 'Cancel', 'smdm' ); ?></a>
				</div>
			</form>
		</div>
		<?php
	}

	private function render_members_list() {
		if ( isset( $_POST['smdm_bulk_delete_members'] ) ) {
			check_admin_referer( 'smdm_members_bulk', 'smdm_members_bulk_nonce' );
			if ( current_user_can( 'manage_options' ) && ! empty( $_POST['member_ids'] ) && is_array( $_POST['member_ids'] ) ) {
				$ids     = array_map( 'intval', wp_unslash( $_POST['member_ids'] ) );
				$ids     = array_values( array_unique( array_filter( $ids ) ) );
				$deleted = 0;
				foreach ( $ids as $pid ) {
					if ( $pid <= 0 ) {
						continue;
					}
					$post_check = get_post( $pid );
					if ( ! $post_check || 'member' !== $post_check->post_type ) {
						continue;
					}
					wp_delete_post( $pid, true );
					if ( ! get_post( $pid ) ) {
						++$deleted;
					}
				}
				if ( $deleted > 0 ) {
					echo '<div class="smdm-alert success">' . esc_html( sprintf( /* translators: %d: number of members deleted */ _n( '%d member deleted.', '%d members deleted.', $deleted, 'smdm' ), $deleted ) ) . '</div>';
				}
			}
		}

		if ( isset( $_GET['delete_member'] ) ) {
			$member_to_delete = intval( $_GET['delete_member'] );
			check_admin_referer( 'smdm_delete_member_' . $member_to_delete );
			if ( $member_to_delete > 0 ) {
				wp_delete_post( $member_to_delete, true );
				echo '<div class="smdm-alert success">' . esc_html__( 'Member deleted successfully.', 'smdm' ) . '</div>';
			}
		}

		$list_params = $this->get_members_list_request_params();
		$list_query  = $this->query_members_list( $list_params );
		$members     = $list_query->posts;

		$ic_field     = SMDM_Field_Schema::get_field_by_id( 'ic_number' );
		$state_field  = SMDM_Field_Schema::get_field_by_id( 'state' );
		$agency_field = SMDM_Field_Schema::get_field_by_id( 'agensi' );
		if ( ! $agency_field ) {
			$agency_field = SMDM_Field_Schema::get_field_by_id( 'institusi' );
		}

		$list_fields = array();
		foreach ( SMDM_Field_Schema::get_sorted_fields() as $f ) {
			if ( in_array( $f['id'], array( 'full_name', 'email', 'account_status' ), true ) ) {
				continue;
			}
			if ( in_array( $f['id'], array( 'ic_number', 'state', 'institusi', 'agensi' ), true ) ) {
				continue;
			}
			if ( ! empty( $f['show_in_list'] ) ) {
				$list_fields[] = $f;
			}
		}

		$colspan = 1 + 1; // bulk + name
		if ( $ic_field ) {
			++$colspan;
		}
		if ( $state_field ) {
			++$colspan;
		}
		if ( $agency_field ) {
			++$colspan;
		}
		$colspan += count( $list_fields ) + 1 + 1; // status + actions

		$bulk_form_action = $this->members_list_url( array( 'smdm_pg' => ( $list_params['smdm_pg'] > 1 ? $list_params['smdm_pg'] : '' ) ) );
		?>
		<div class="smdm-list-actions">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=smdm-app&tab=add_member' ) ); ?>" class="smdm-btn"><?php esc_html_e( '+ Add New Member', 'smdm' ); ?></a>
		</div>

		<form method="get" class="smdm-member-filters" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
			<input type="hidden" name="page" value="smdm-app" />
			<input type="hidden" name="tab" value="members" />
			<div class="smdm-member-filters-grid">
				<div class="form-group">
					<label for="smdm_q_name"><?php esc_html_e( 'Name', 'smdm' ); ?></label>
					<input type="text" id="smdm_q_name" name="smdm_q_name" value="<?php echo esc_attr( $list_params['smdm_q_name'] ); ?>" class="smdm-input-compact" placeholder="<?php esc_attr_e( 'Search name…', 'smdm' ); ?>" />
				</div>
				<div class="form-group">
					<label for="smdm_q_ic"><?php esc_html_e( 'IC', 'smdm' ); ?></label>
					<input type="text" id="smdm_q_ic" name="smdm_q_ic" value="<?php echo esc_attr( $list_params['smdm_q_ic'] ); ?>" class="smdm-input-compact" placeholder="<?php esc_attr_e( 'IC / passport…', 'smdm' ); ?>" />
				</div>
				<div class="form-group">
					<label for="smdm_q_state"><?php esc_html_e( 'State', 'smdm' ); ?></label>
					<input type="text" id="smdm_q_state" name="smdm_q_state" value="<?php echo esc_attr( $list_params['smdm_q_state'] ); ?>" class="smdm-input-compact" placeholder="<?php esc_attr_e( 'State…', 'smdm' ); ?>" />
				</div>
				<div class="form-group">
					<label for="smdm_q_agency"><?php esc_html_e( 'Agensi', 'smdm' ); ?></label>
					<input type="text" id="smdm_q_agency" name="smdm_q_agency" value="<?php echo esc_attr( $list_params['smdm_q_agency'] ); ?>" class="smdm-input-compact" placeholder="<?php esc_attr_e( 'Agensi / institusi…', 'smdm' ); ?>" />
				</div>
				<div class="form-group smdm-filter-per-page">
					<label for="smdm_pp"><?php esc_html_e( 'Per page', 'smdm' ); ?></label>
					<select id="smdm_pp" name="smdm_pp">
						<?php foreach ( array( '5' => '5', '10' => '10', '25' => '25', '50' => '50', '100' => '100', 'all' => __( 'All', 'smdm' ) ) as $val => $lab ) : ?>
							<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $list_params['smdm_pp'], $val ); ?>><?php echo esc_html( (string) $lab ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="form-group smdm-filter-actions">
					<label class="smdm-filter-actions-spacer">&nbsp;</label>
					<div class="smdm-filter-buttons">
						<button type="submit" class="smdm-btn"><?php esc_html_e( 'Apply filters', 'smdm' ); ?></button>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=smdm-app&tab=members' ) ); ?>" class="smdm-btn secondary" style="text-decoration:none;"><?php esc_html_e( 'Reset', 'smdm' ); ?></a>
					</div>
				</div>
			</div>
		</form>

		<div class="smdm-content-card smdm-member-list-wrap">
			<form method="post" action="<?php echo esc_url( $bulk_form_action ); ?>" class="smdm-members-bulk-form" data-msg-select-one="<?php echo esc_attr( __( 'Select at least one member.', 'smdm' ) ); ?>" data-msg-confirm-bulk="<?php echo esc_attr( __( 'Delete the selected members permanently? This cannot be undone.', 'smdm' ) ); ?>">
				<?php wp_nonce_field( 'smdm_members_bulk', 'smdm_members_bulk_nonce' ); ?>
				<?php if ( ! empty( $members ) ) : ?>
				<div class="smdm-member-bulk-bar">
					<button type="submit" name="smdm_bulk_delete_members" value="1" class="smdm-btn smdm-btn-danger-outline"><?php esc_html_e( 'Delete selected', 'smdm' ); ?></button>
				</div>
				<?php endif; ?>
			<div class="smdm-member-table-scroll">
			<table class="smdm-custom-table smdm-member-table-desktop">
				<thead>
					<tr>
						<th class="smdm-col-bulk" scope="col">
							<input type="checkbox" class="smdm-member-bulk-select-all" title="<?php esc_attr_e( 'Select all', 'smdm' ); ?>" aria-label="<?php esc_attr_e( 'Select all members', 'smdm' ); ?>" <?php disabled( empty( $members ) ); ?> />
						</th>
						<th><?php esc_html_e( 'Name', 'smdm' ); ?></th>
						<?php if ( $ic_field ) : ?>
							<th><?php echo esc_html( $ic_field['label'] ); ?></th>
						<?php endif; ?>
						<?php if ( $state_field ) : ?>
							<th><?php echo esc_html( $state_field['label'] ); ?></th>
						<?php endif; ?>
						<?php if ( $agency_field ) : ?>
							<th><?php echo esc_html( $agency_field['label'] ); ?></th>
						<?php endif; ?>
						<?php foreach ( $list_fields as $lf ) : ?>
							<th><?php echo esc_html( $lf['label'] ); ?></th>
						<?php endforeach; ?>
						<th><?php esc_html_e( 'Status', 'smdm' ); ?></th>
						<th style="text-align:right"><?php esc_html_e( 'Actions', 'smdm' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php
					if ( $members ) :
						foreach ( $members as $m ) :
							$status = SMDM_Field_Schema::get_member_value( $m->ID, array( 'id' => 'account_status' ) );
							$del_url = wp_nonce_url(
								add_query_arg(
									array(
										'delete_member' => (int) $m->ID,
										'page'         => 'smdm-app',
										'tab'          => 'members',
										'smdm_pp'      => $list_params['smdm_pp'],
										'smdm_q_name'  => $list_params['smdm_q_name'],
										'smdm_q_ic'    => $list_params['smdm_q_ic'],
										'smdm_q_state' => $list_params['smdm_q_state'],
										'smdm_q_agency' => $list_params['smdm_q_agency'],
										'smdm_pg'      => $list_params['smdm_pg'] > 1 ? $list_params['smdm_pg'] : '',
									),
									admin_url( 'admin.php' )
								),
								'smdm_delete_member_' . (int) $m->ID
							);
							?>
							<tr>
								<td class="smdm-col-bulk">
									<input type="checkbox" class="smdm-member-bulk-cb" name="member_ids[]" value="<?php echo esc_attr( (string) $m->ID ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: member name */ __( 'Select %s', 'smdm' ), get_the_title( $m->ID ) ) ); ?>" />
								</td>
								<td><strong><?php echo esc_html( get_the_title( $m->ID ) ); ?></strong></td>
								<?php if ( $ic_field ) : ?>
									<td><?php echo esc_html( SMDM_Field_Schema::get_member_value( $m->ID, $ic_field ) ); ?></td>
								<?php endif; ?>
								<?php if ( $state_field ) : ?>
									<td><?php echo esc_html( SMDM_Field_Schema::get_member_value( $m->ID, $state_field ) ); ?></td>
								<?php endif; ?>
								<?php if ( $agency_field ) : ?>
									<td><?php echo esc_html( SMDM_Field_Schema::get_member_value( $m->ID, $agency_field ) ); ?></td>
								<?php endif; ?>
								<?php foreach ( $list_fields as $lf ) : ?>
									<td><?php echo esc_html( SMDM_Field_Schema::get_member_value( $m->ID, $lf ) ); ?></td>
								<?php endforeach; ?>
								<td><span class="smdm-status-badge <?php echo SMDM_Field_Schema::member_is_active( $m->ID ) ? 'active' : 'inactive'; ?>"><?php echo esc_html( $status ? $status : '—' ); ?></span></td>
								<td class="smdm-action-cell">
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=smdm-app&member_id=' . intval( $m->ID ) ) ); ?>" class="smdm-btn-small"><?php esc_html_e( 'Edit', 'smdm' ); ?></a>
									<a href="<?php echo esc_url( $del_url ); ?>" class="smdm-btn-small smdm-btn-danger-outline" onclick="return confirm('<?php echo esc_js( __( 'Delete this member permanently?', 'smdm' ) ); ?>');"><?php esc_html_e( 'Delete', 'smdm' ); ?></a>
								</td>
							</tr>
						<?php endforeach; else : ?>
						<tr><td colspan="<?php echo esc_attr( (string) $colspan ); ?>" style="text-align:center; padding:40px;"><?php esc_html_e( 'No members found.', 'smdm' ); ?></td></tr>
					<?php endif; ?>
				</tbody>
			</table>
			</div>

			<div class="smdm-member-cards">
				<?php
				if ( $members ) :
					foreach ( $members as $m ) :
						$status = SMDM_Field_Schema::get_member_value( $m->ID, array( 'id' => 'account_status' ) );
						$del_url = wp_nonce_url(
							add_query_arg(
								array(
									'delete_member' => (int) $m->ID,
									'page'         => 'smdm-app',
									'tab'          => 'members',
									'smdm_pp'      => $list_params['smdm_pp'],
									'smdm_q_name'  => $list_params['smdm_q_name'],
									'smdm_q_ic'    => $list_params['smdm_q_ic'],
									'smdm_q_state' => $list_params['smdm_q_state'],
									'smdm_q_agency' => $list_params['smdm_q_agency'],
									'smdm_pg'      => $list_params['smdm_pg'] > 1 ? $list_params['smdm_pg'] : '',
								),
								admin_url( 'admin.php' )
							),
							'smdm_delete_member_' . (int) $m->ID
						);
						?>
						<div class="smdm-member-card">
							<div class="smdm-member-card-head">
								<label class="smdm-member-card-select">
									<input type="checkbox" class="smdm-member-bulk-cb" name="member_ids[]" value="<?php echo esc_attr( (string) $m->ID ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: member name */ __( 'Select %s', 'smdm' ), get_the_title( $m->ID ) ) ); ?>" />
								</label>
								<strong><?php echo esc_html( get_the_title( $m->ID ) ); ?></strong>
								<span class="smdm-status-badge <?php echo SMDM_Field_Schema::member_is_active( $m->ID ) ? 'active' : 'inactive'; ?>"><?php echo esc_html( $status ? $status : '—' ); ?></span>
							</div>
							<div class="smdm-member-card-body">
								<?php if ( $ic_field ) : ?>
									<p><span class="smdm-muted"><?php echo esc_html( $ic_field['label'] ); ?></span><br><?php echo esc_html( SMDM_Field_Schema::get_member_value( $m->ID, $ic_field ) ); ?></p>
								<?php endif; ?>
								<?php if ( $state_field ) : ?>
									<p><span class="smdm-muted"><?php echo esc_html( $state_field['label'] ); ?></span><br><?php echo esc_html( SMDM_Field_Schema::get_member_value( $m->ID, $state_field ) ); ?></p>
								<?php endif; ?>
								<?php if ( $agency_field ) : ?>
									<p><span class="smdm-muted"><?php echo esc_html( $agency_field['label'] ); ?></span><br><?php echo esc_html( SMDM_Field_Schema::get_member_value( $m->ID, $agency_field ) ); ?></p>
								<?php endif; ?>
								<?php foreach ( $list_fields as $lf ) : ?>
									<p><span class="smdm-muted"><?php echo esc_html( $lf['label'] ); ?></span><br><?php echo esc_html( SMDM_Field_Schema::get_member_value( $m->ID, $lf ) ); ?></p>
								<?php endforeach; ?>
							</div>
							<div class="smdm-member-card-actions">
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=smdm-app&member_id=' . intval( $m->ID ) ) ); ?>" class="smdm-btn-small" style="width:100%;text-align:center;"><?php esc_html_e( 'Edit', 'smdm' ); ?></a>
								<a href="<?php echo esc_url( $del_url ); ?>" class="smdm-btn-small smdm-btn-danger-outline" style="width:100%;text-align:center;" onclick="return confirm('<?php echo esc_js( __( 'Delete this member permanently?', 'smdm' ) ); ?>');"><?php esc_html_e( 'Delete', 'smdm' ); ?></a>
							</div>
						</div>
					<?php endforeach; endif; ?>
			</div>
			</form>

			<?php if ( 'all' !== $list_params['smdm_pp'] && $list_query->max_num_pages > 1 ) : ?>
				<nav class="smdm-member-pagination" aria-label="<?php esc_attr_e( 'Member list pagination', 'smdm' ); ?>">
					<?php
					$big  = 999999999;
					$base = str_replace( (string) $big, '%#%', esc_url( add_query_arg( 'smdm_pg', $big, remove_query_arg( 'smdm_pg', $this->members_list_url() ) ) ) );
					echo wp_kses_post(
						paginate_links(
							array(
								'base'      => $base,
								'format'    => '',
								'current'   => max( 1, $list_params['smdm_pg'] ),
								'total'     => $list_query->max_num_pages,
								'type'      => 'list',
								'prev_text' => '&laquo;',
								'next_text' => '&raquo;',
							)
						)
					);
					?>
				</nav>
			<?php endif; ?>
		</div>
		<?php
	}

	private function render_dashboard_content() {
		global $wpdb;

		$total_members = wp_count_posts( 'member' )->publish;
		$active_count  = ( new WP_Query(
			array(
				'post_type'      => 'member',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => array(
					'relation' => 'OR',
					array(
						'key'     => '_member_status',
						'value'   => array( SMDM_Field_Schema::get_active_status_literal(), 'Active' ),
						'compare' => 'IN',
					),
					array(
						'key'     => SMDM_Field_Schema::meta_key_for( 'account_status' ),
						'value'   => array( SMDM_Field_Schema::get_active_status_literal(), 'Active' ),
						'compare' => 'IN',
					),
				),
			)
		) )->found_posts;
		$inactive_count = max( 0, $total_members - $active_count );
		$total_cats     = wp_count_terms( 'member_category' );

		$growth_data   = array();
		$growth_labels = array();
		for ( $i = 5; $i >= 0; $i-- ) {
			$month = gmdate( 'Y-m', strtotime( '-' . $i . ' months' ) );
			$count = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(ID) FROM $wpdb->posts WHERE post_type = 'member' AND post_status = 'publish' AND post_date LIKE %s",
					$month . '%'
				)
			);
			$growth_labels[] = gmdate( 'M Y', strtotime( '-' . $i . ' months' ) );
			$growth_data[]   = (int) $count;
		}

		$categories = get_terms( array( 'taxonomy' => 'member_category', 'hide_empty' => false ) );
		$cat_labels = array();
		$cat_counts = array();
		foreach ( $categories as $cat ) {
			$cat_labels[] = $cat->name;
			$cat_counts[] = $cat->count;
		}

		echo '<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>';
		?>

		<div class="smdm-stats-grid smdm-stats-grid-3">
			<div class="smdm-stat-card"><h4><?php esc_html_e( 'Total Database', 'smdm' ); ?></h4><div class="value"><?php echo intval( $total_members ); ?></div></div>
			<div class="smdm-stat-card"><h4><?php esc_html_e( 'Active Members', 'smdm' ); ?></h4><div class="value"><?php echo intval( $active_count ); ?></div></div>
			<div class="smdm-stat-card"><h4><?php esc_html_e( 'Categories', 'smdm' ); ?></h4><div class="value"><?php echo intval( $total_cats ); ?></div></div>
		</div>

		<div class="smdm-form-grid smdm-dash-charts">
			<div class="smdm-content-card">
				<h3 class="smdm-form-section-title"><?php esc_html_e( 'Registration Growth', 'smdm' ); ?></h3>
				<canvas id="growthChart" height="200"></canvas>
			</div>
			<div class="smdm-content-card">
				<h3 class="smdm-form-section-title"><?php esc_html_e( 'Member Composition', 'smdm' ); ?></h3>
				<div style="max-width: 250px; margin: 0 auto;">
					<canvas id="statusChart"></canvas>
				</div>
			</div>
		</div>

		<div class="smdm-content-card">
			<h3 class="smdm-form-section-title"><?php esc_html_e( 'Quick Actions', 'smdm' ); ?></h3>
			<div class="smdm-quick-actions">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=smdm-app&tab=add_member' ) ); ?>" class="smdm-btn" style="text-decoration:none;"><?php esc_html_e( 'Add Member', 'smdm' ); ?></a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=smdm-app&tab=members' ) ); ?>" class="smdm-btn secondary" style="text-decoration:none;"><?php esc_html_e( 'Member List', 'smdm' ); ?></a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=smdm-app&tab=fields' ) ); ?>" class="smdm-btn secondary" style="text-decoration:none;"><?php esc_html_e( 'Form Fields', 'smdm' ); ?></a>
				<form method="post" class="smdm-inline-form">
					<?php wp_nonce_field( 'smdm_seed_dummy', 'smdm_seed_nonce' ); ?>
					<button type="submit" name="smdm_seed_dummy_action" class="smdm-btn secondary"><?php esc_html_e( 'Generate 5 Dummy Members', 'smdm' ); ?></button>
				</form>
			</div>
		</div>

		<script>
		document.addEventListener('DOMContentLoaded', function() {
			new Chart(document.getElementById('growthChart'), {
				type: 'line',
				data: {
					labels: <?php echo wp_json_encode( $growth_labels ); ?>,
					datasets: [{
						label: '<?php echo esc_js( __( 'New Members', 'smdm' ) ); ?>',
						data: <?php echo wp_json_encode( $growth_data ); ?>,
						borderColor: '#2563eb',
						backgroundColor: 'rgba(37, 99, 235, 0.1)',
						fill: true,
						tension: 0.4
					}]
				},
				options: {
					responsive: true,
					plugins: { legend: { display: false } },
					scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
				}
			});
			new Chart(document.getElementById('statusChart'), {
				type: 'doughnut',
				data: {
					labels: ['<?php echo esc_js( SMDM_Field_Schema::get_active_status_literal() ); ?>', '<?php echo esc_js( SMDM_Field_Schema::get_inactive_status_literal() ); ?>'],
					datasets: [{
						data: [<?php echo intval( $active_count ); ?>, <?php echo intval( $inactive_count ); ?>],
						backgroundColor: ['#10b981', '#ef4444'],
						borderWidth: 0
					}]
				},
				options: {
					cutout: '70%',
					plugins: { legend: { position: 'bottom' } }
				}
			});
		});
		</script>
		<?php
	}
}
