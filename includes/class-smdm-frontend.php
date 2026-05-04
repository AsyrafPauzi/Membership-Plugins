<?php
class SMDM_Frontend {

	public function __construct() {
		add_shortcode( 'member_directory', array( $this, 'render_directory' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
	}

	public function enqueue_frontend_assets() {
		wp_enqueue_style( 'smdm-frontend-style', SMDM_PLUGIN_URL . 'assets/css/frontend-style.css', array(), SMDM_VERSION );
		wp_enqueue_script( 'smdm-frontend-js', SMDM_PLUGIN_URL . 'assets/js/frontend.js', array( 'jquery' ), SMDM_VERSION, true );
		wp_enqueue_style( 'dashicons' );
	}

	public function render_directory( $atts ) {
		$search = isset( $_GET['m_search'] ) ? sanitize_text_field( wp_unslash( $_GET['m_search'] ) ) : '';
		$cat    = isset( $_GET['m_cat'] ) ? sanitize_text_field( wp_unslash( $_GET['m_cat'] ) ) : '';
		$category_label = get_option( 'smdm_category_label', __( 'Category', 'smdm' ) );
		$category_label = is_string( $category_label ) && '' !== trim( $category_label ) ? trim( $category_label ) : __( 'Category', 'smdm' );

		$filter_get = isset( $_GET['smdm_filter'] ) && is_array( $_GET['smdm_filter'] ) ? wp_unslash( $_GET['smdm_filter'] ) : array();

		$paged = get_query_var( 'paged' ) ? (int) get_query_var( 'paged' ) : 1;

		$meta_query = array(
			'relation' => 'AND',
			array(
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
		);

		$schema = SMDM_Field_Schema::get_sorted_fields();
		foreach ( $schema as $field ) {
			if ( empty( $field['filterable'] ) ) {
				continue;
			}
			$fid = $field['id'];
			if ( empty( $filter_get[ $fid ] ) ) {
				continue;
			}
			$raw = sanitize_text_field( $filter_get[ $fid ] );
			if ( '' === $raw ) {
				continue;
			}
			$compare = in_array( $field['type'], array( 'text', 'textarea', 'tel', 'email' ), true ) ? 'LIKE' : '=';
			$meta_query[] = array(
				'key'     => SMDM_Field_Schema::meta_key_for( $fid ),
				'value'   => $raw,
				'compare' => $compare,
			);
		}

		$args = array(
			'post_type'      => 'member',
			'posts_per_page' => 12,
			'paged'          => $paged,
			's'              => $search,
			'meta_query'     => $meta_query,
		);

		if ( ! empty( $cat ) ) {
			$args['tax_query'] = array(
				array(
					'taxonomy' => 'member_category',
					'field'    => 'slug',
					'terms'    => $cat,
				),
			);
		}

		$query = new WP_Query( $args );

		$filterable_fields = array();
		foreach ( $schema as $field ) {
			if ( ! empty( $field['filterable'] ) ) {
				$filterable_fields[] = $field;
			}
		}

		ob_start();
		?>
		<div class="smdm-directory-wrapper">
			<div class="smdm-directory-header">
				<form method="get" class="smdm-modern-filter" action="">
					<div class="smdm-input-group">
						<span class="dashicons dashicons-search"></span>
						<input type="text" name="m_search" placeholder="<?php esc_attr_e( 'Search name…', 'smdm' ); ?>" value="<?php echo esc_attr( $search ); ?>">
					</div>

					<div class="smdm-input-group">
						<span class="dashicons dashicons-category"></span>
						<select name="m_cat">
							<option value=""><?php echo esc_html( sprintf( __( 'All %s', 'smdm' ), $category_label ) ); ?></option>
							<?php
							$categories = get_terms( array( 'taxonomy' => 'member_category', 'hide_empty' => true ) );
							foreach ( $categories as $c ) {
								printf(
									'<option value="%s" %s>%s</option>',
									esc_attr( $c->slug ),
									selected( $cat, $c->slug, false ),
									esc_html( $c->name )
								);
							}
							?>
						</select>
					</div>

					<?php foreach ( $filterable_fields as $ff ) : ?>
						<?php
						$fv = isset( $filter_get[ $ff['id'] ] ) ? sanitize_text_field( $filter_get[ $ff['id'] ] ) : '';
						?>
						<div class="smdm-input-group">
							<span class="dashicons dashicons-filter"></span>
							<?php if ( 'state_ms' === $ff['type'] || ( 'select' === $ff['type'] && ! empty( $ff['options'] ) ) ) : ?>
								<select name="smdm_filter[<?php echo esc_attr( $ff['id'] ); ?>]">
									<option value=""><?php echo esc_html( sprintf( /* translators: %s field label */ __( 'All %s', 'smdm' ), $ff['label'] ) ); ?></option>
									<?php
									$opts = ( 'state_ms' === $ff['type'] ) ? SMDM_Field_Schema::get_malaysia_states() : $ff['options'];
									foreach ( $opts as $opt ) {
										printf(
											'<option value="%s" %s>%s</option>',
											esc_attr( $opt ),
											selected( $fv, $opt, false ),
											esc_html( $opt )
										);
									}
									?>
								</select>
							<?php else : ?>
								<input type="text" name="smdm_filter[<?php echo esc_attr( $ff['id'] ); ?>]" placeholder="<?php echo esc_attr( $ff['label'] ); ?>" value="<?php echo esc_attr( $fv ); ?>">
							<?php endif; ?>
						</div>
					<?php endforeach; ?>

					<button type="submit" class="smdm-btn-primary"><?php esc_html_e( 'Filter', 'smdm' ); ?></button>
				</form>
			</div>

			<div class="smdm-modern-grid">
				<?php
				if ( $query->have_posts() ) :
					while ( $query->have_posts() ) :
						$query->the_post();
						$id = get_the_ID();

						$terms         = get_the_terms( $id, 'member_category' );
						$category_name = ( $terms && ! is_wp_error( $terms ) ) ? $terms[0]->name : __( 'Member', 'smdm' );

						$modal_rows = array();
						foreach ( $schema as $field ) {
							if ( empty( $field['show_in_directory_modal'] ) || ! empty( $field['is_name_field'] ) ) {
								continue;
							}
							$v = SMDM_Field_Schema::get_member_value( $id, $field );
							if ( '' === $v || null === $v ) {
								continue;
							}
							$modal_rows[] = array(
								'label' => $field['label'],
								'value' => (string) $v,
							);
						}

						$member_payload = array(
							'name'     => get_the_title(),
							'category' => $category_name,
							'rows'     => $modal_rows,
						);
						?>
						<div class="smdm-profile-card">
							<div class="smdm-card-header">
								<div class="smdm-avatar-circle"><?php echo esc_html( strtoupper( substr( get_the_title(), 0, 1 ) ) ); ?></div>
								<div class="smdm-status-dot"></div>
							</div>
							<div class="smdm-card-body">
								<span class="smdm-tag"><?php echo esc_html( $category_name ); ?></span>
								<h3><?php the_title(); ?></h3>
								<div class="smdm-card-info-list">
									<?php
									foreach ( $schema as $field ) {
										if ( empty( $field['show_in_directory'] ) || ! empty( $field['is_name_field'] ) ) {
											continue;
										}
										$v = SMDM_Field_Schema::get_member_value( $id, $field );
										if ( '' === $v ) {
											continue;
										}
										$icon = 'email' === $field['type'] ? 'email' : ( 'tel' === $field['type'] ? 'phone' : 'admin-site' );
										?>
										<p class="smdm-info-line">
											<span class="dashicons dashicons-<?php echo esc_attr( $icon ); ?>"></span>
											<?php echo esc_html( $v ); ?>
										</p>
										<?php
									}
									?>
								</div>
							</div>
							<div class="smdm-card-footer">
								<button type="button" class="smdm-view-details-btn" data-member="<?php echo esc_attr( wp_json_encode( $member_payload ) ); ?>">
									<?php esc_html_e( 'View Full Profile', 'smdm' ); ?>
								</button>
							</div>
						</div>
						<?php
					endwhile;
					wp_reset_postdata();
				else :
					?>
					<p class="smdm-no-results"><?php esc_html_e( 'No active members found matching those filters.', 'smdm' ); ?></p>
				<?php endif; ?>
			</div>

			<div id="smdm-modal" class="smdm-modal" style="display:none;">
				<div class="smdm-modal-content">
					<span class="smdm-close">&times;</span>
					<div id="smdm-modal-body"></div>
				</div>
			</div>

			<div class="smdm-modern-pagination">
				<?php
				$link_args = array_filter(
					array(
						'm_search' => $search,
						'm_cat'    => $cat,
					)
				);
				if ( ! empty( $filter_get ) ) {
					$clean_filters = array();
					foreach ( $filter_get as $fk => $fv ) {
						$clean_filters[ sanitize_key( $fk ) ] = sanitize_text_field( $fv );
					}
					if ( ! empty( $clean_filters ) ) {
						$link_args['smdm_filter'] = $clean_filters;
					}
				}
				echo wp_kses_post(
					paginate_links(
						array(
							'total'     => max( 1, (int) $query->max_num_pages ),
							'current'   => $paged,
							'add_args'  => $link_args,
						)
					)
				);
				?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}
}
