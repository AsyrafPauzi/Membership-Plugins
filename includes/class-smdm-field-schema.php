<?php
/**
 * Dynamic member field definitions and value helpers.
 */
class SMDM_Field_Schema {

	const OPTION_KEY     = 'smdm_field_schema';
	const SCHEMA_VERSION = 1;
	const META_PREFIX    = '_smdm_f_';
	const MAX_FILTERABLE = 5;

	/** @var array|null */
	private static $malaysia_states = null;

	/**
	 * Allowed field types.
	 */
	public static function allowed_types() {
		return array( 'text', 'textarea', 'email', 'tel', 'number', 'date', 'select', 'checkbox', 'state_ms', 'status' );
	}

	public static function get_malaysia_states() {
		if ( null === self::$malaysia_states ) {
			self::$malaysia_states = array(
				'Johor',
				'Kedah',
				'Kelantan',
				'Melaka',
				'Negeri Sembilan',
				'Pahang',
				'Penang',
				'Perak',
				'Perlis',
				'Sabah',
				'Sarawak',
				'Selangor',
				'Terengganu',
				'W.P. Kuala Lumpur',
				'W.P. Labuan',
				'W.P. Putrajaya',
			);
		}
		return self::$malaysia_states;
	}

	/**
	 * Raw option: version + fields array.
	 */
	public static function get_raw() {
		$raw = get_option( self::OPTION_KEY, null );
		if ( ! is_array( $raw ) || empty( $raw['fields'] ) || ! is_array( $raw['fields'] ) ) {
			return null;
		}
		return $raw;
	}

	public static function get_fields() {
		$raw = self::get_raw();
		if ( ! $raw ) {
			return array();
		}
		return self::normalize_fields( $raw['fields'] );
	}

	public static function get_sorted_fields() {
		$fields = self::get_fields();
		usort(
			$fields,
			function ( $a, $b ) {
				$oa = isset( $a['order'] ) ? (int) $a['order'] : 0;
				$ob = isset( $b['order'] ) ? (int) $b['order'] : 0;
				return $oa - $ob;
			}
		);
		return $fields;
	}

	public static function get_field_by_id( $id ) {
		foreach ( self::get_sorted_fields() as $f ) {
			if ( isset( $f['id'] ) && $f['id'] === $id ) {
				return $f;
			}
		}
		return null;
	}

	public static function meta_key_for( $field_id ) {
		return self::META_PREFIX . $field_id;
	}

	/**
	 * Legacy meta key map for reads when dynamic value empty.
	 */
	public static function legacy_meta_key( $field_id ) {
		$map = array(
			'ic_number'       => '_member_ic',
			'gender'          => '_member_gender',
			'date_of_birth'   => '_member_dob',
			'email'           => '_member_email',
			'phone'           => '_member_phone',
			'address'         => '_member_address',
			'postcode'        => '_member_postcode',
			'city'            => '_member_city',
			'state'           => '_member_state',
			'account_status'  => '_member_status',
		);
		return isset( $map[ $field_id ] ) ? $map[ $field_id ] : null;
	}

	/**
	 * Read value for a member post (dynamic meta, then legacy).
	 */
	public static function get_member_value( $post_id, $field ) {
		if ( ! is_array( $field ) || empty( $field['id'] ) ) {
			return '';
		}
		$id      = $field['id'];
		$dynamic = get_post_meta( $post_id, self::meta_key_for( $id ), true );
		if ( '' !== $dynamic && null !== $dynamic && false !== $dynamic ) {
			return $dynamic;
		}
		$legacy = self::legacy_meta_key( $id );
		if ( $legacy ) {
			return get_post_meta( $post_id, $legacy, true );
		}
		if ( ! empty( $field['is_name_field'] ) ) {
			$p = get_post( $post_id );
			return $p ? $p->post_title : '';
		}
		return '';
	}

	/**
	 * Default schema used on first install / migration seed.
	 */
	public static function default_schema_fields() {
		$o = 0;
		return array(
			array(
				'id'             => 'full_name',
				'label'          => __( 'Full Name', 'smdm' ),
				'type'           => 'text',
				'section'        => 'personal',
				'order'          => $o++,
				'required'       => true,
				'placeholder'    => '',
				'options'        => array(),
				'is_name_field'  => true,
				'is_primary_email' => false,
				'show_in_directory' => true,
				'show_in_directory_modal' => true,
				'filterable'     => false,
				'show_in_list'   => false,
			),
			array(
				'id'             => 'ic_number',
				'label'          => __( 'IC / Passport Number', 'smdm' ),
				'type'           => 'text',
				'section'        => 'personal',
				'order'          => $o++,
				'required'       => false,
				'placeholder'    => 'e.g. 900101015522',
				'options'        => array(),
				'is_name_field'  => false,
				'is_primary_email' => false,
				'show_in_directory' => false,
				'show_in_directory_modal' => true,
				'filterable'     => false,
				'show_in_list'   => false,
			),
			array(
				'id'             => 'gender',
				'label'          => __( 'Gender', 'smdm' ),
				'type'           => 'select',
				'section'        => 'personal',
				'order'          => $o++,
				'required'       => false,
				'placeholder'    => '',
				'options'        => array( 'Male', 'Female' ),
				'is_name_field'  => false,
				'is_primary_email' => false,
				'show_in_directory' => false,
				'show_in_directory_modal' => true,
				'filterable'     => false,
				'show_in_list'   => false,
			),
			array(
				'id'             => 'date_of_birth',
				'label'          => __( 'Date of Birth', 'smdm' ),
				'type'           => 'date',
				'section'        => 'personal',
				'order'          => $o++,
				'required'       => false,
				'placeholder'    => '',
				'options'        => array(),
				'is_name_field'  => false,
				'is_primary_email' => false,
				'show_in_directory' => false,
				'show_in_directory_modal' => true,
				'filterable'     => false,
				'show_in_list'   => false,
			),
			array(
				'id'             => 'email',
				'label'          => __( 'Email Address', 'smdm' ),
				'type'           => 'email',
				'section'        => 'contact',
				'order'          => $o++,
				'required'       => false,
				'placeholder'    => 'email@example.com',
				'options'        => array(),
				'is_name_field'  => false,
				'is_primary_email' => true,
				'show_in_directory' => true,
				'show_in_directory_modal' => true,
				'filterable'     => false,
				'show_in_list'   => true,
			),
			array(
				'id'             => 'phone',
				'label'          => __( 'Phone Number', 'smdm' ),
				'type'           => 'tel',
				'section'        => 'contact',
				'order'          => $o++,
				'required'       => false,
				'placeholder'    => '+60123456789',
				'options'        => array(),
				'is_name_field'  => false,
				'is_primary_email' => false,
				'show_in_directory' => true,
				'show_in_directory_modal' => true,
				'filterable'     => false,
				'show_in_list'   => false,
			),
			array(
				'id'             => 'pelaksana_utama',
				'label'          => __( 'Pelaksana Utama', 'smdm' ),
				'type'           => 'text',
				'section'        => 'custom',
				'order'          => $o++,
				'required'       => false,
				'placeholder'    => '',
				'options'        => array(),
				'is_name_field'  => false,
				'is_primary_email' => false,
				'show_in_directory' => false,
				'show_in_directory_modal' => true,
				'filterable'     => false,
				'show_in_list'   => true,
			),
			array(
				'id'             => 'program',
				'label'          => __( 'Program', 'smdm' ),
				'type'           => 'text',
				'section'        => 'custom',
				'order'          => $o++,
				'required'       => false,
				'placeholder'    => '',
				'options'        => array(),
				'is_name_field'  => false,
				'is_primary_email' => false,
				'show_in_directory' => true,
				'show_in_directory_modal' => true,
				'filterable'     => true,
				'show_in_list'   => true,
			),
			array(
				'id'             => 'jenis_pelaksanaan',
				'label'          => __( 'Jenis Pelaksanaan', 'smdm' ),
				'type'           => 'text',
				'section'        => 'custom',
				'order'          => $o++,
				'required'       => false,
				'placeholder'    => '',
				'options'        => array(),
				'is_name_field'  => false,
				'is_primary_email' => false,
				'show_in_directory' => false,
				'show_in_directory_modal' => true,
				'filterable'     => false,
				'show_in_list'   => false,
			),
			array(
				'id'             => 'tahun',
				'label'          => __( 'Tahun', 'smdm' ),
				'type'           => 'number',
				'section'        => 'custom',
				'order'          => $o++,
				'required'       => false,
				'placeholder'    => '',
				'options'        => array(),
				'is_name_field'  => false,
				'is_primary_email' => false,
				'show_in_directory' => true,
				'show_in_directory_modal' => true,
				'filterable'     => true,
				'show_in_list'   => true,
			),
			array(
				'id'             => 'penganjuran',
				'label'          => __( 'Penganjuran', 'smdm' ),
				'type'           => 'text',
				'section'        => 'custom',
				'order'          => $o++,
				'required'       => false,
				'placeholder'    => '',
				'options'        => array(),
				'is_name_field'  => false,
				'is_primary_email' => false,
				'show_in_directory' => false,
				'show_in_directory_modal' => true,
				'filterable'     => false,
				'show_in_list'   => false,
			),
			array(
				'id'             => 'batch_plan',
				'label'          => __( 'Batch / Plan', 'smdm' ),
				'type'           => 'text',
				'section'        => 'custom',
				'order'          => $o++,
				'required'       => false,
				'placeholder'    => '',
				'options'        => array(),
				'is_name_field'  => false,
				'is_primary_email' => false,
				'show_in_directory' => false,
				'show_in_directory_modal' => true,
				'filterable'     => false,
				'show_in_list'   => false,
			),
			array(
				'id'             => 'daerah',
				'label'          => __( 'Daerah', 'smdm' ),
				'type'           => 'text',
				'section'        => 'custom',
				'order'          => $o++,
				'required'       => false,
				'placeholder'    => '',
				'options'        => array(),
				'is_name_field'  => false,
				'is_primary_email' => false,
				'show_in_directory' => true,
				'show_in_directory_modal' => true,
				'filterable'     => true,
				'show_in_list'   => true,
			),
			array(
				'id'             => 'nilai_peserta',
				'label'          => __( 'Nilai Peserta', 'smdm' ),
				'type'           => 'number',
				'section'        => 'custom',
				'order'          => $o++,
				'required'       => false,
				'placeholder'    => '',
				'options'        => array(),
				'is_name_field'  => false,
				'is_primary_email' => false,
				'show_in_directory' => false,
				'show_in_directory_modal' => true,
				'filterable'     => false,
				'show_in_list'   => true,
			),
			array(
				'id'             => 'no_kp',
				'label'          => __( 'No KP', 'smdm' ),
				'type'           => 'text',
				'section'        => 'personal',
				'order'          => $o++,
				'required'       => false,
				'placeholder'    => '',
				'options'        => array(),
				'is_name_field'  => false,
				'is_primary_email' => false,
				'show_in_directory' => false,
				'show_in_directory_modal' => true,
				'filterable'     => false,
				'show_in_list'   => false,
			),
			array(
				'id'             => 'jawatan',
				'label'          => __( 'Jawatan', 'smdm' ),
				'type'           => 'text',
				'section'        => 'custom',
				'order'          => $o++,
				'required'       => false,
				'placeholder'    => '',
				'options'        => array(),
				'is_name_field'  => false,
				'is_primary_email' => false,
				'show_in_directory' => false,
				'show_in_directory_modal' => true,
				'filterable'     => false,
				'show_in_list'   => false,
			),
			array(
				'id'             => 'status_jawatan',
				'label'          => __( 'Status Jawatan', 'smdm' ),
				'type'           => 'select',
				'section'        => 'custom',
				'order'          => $o++,
				'required'       => false,
				'placeholder'    => '',
				'options'        => array( 'Tetap', 'Kontrak', 'Sambilan', 'Lain-lain' ),
				'is_name_field'  => false,
				'is_primary_email' => false,
				'show_in_directory' => false,
				'show_in_directory_modal' => true,
				'filterable'     => false,
				'show_in_list'   => false,
			),
			array(
				'id'             => 'institusi',
				'label'          => __( 'Institusi', 'smdm' ),
				'type'           => 'text',
				'section'        => 'custom',
				'order'          => $o++,
				'required'       => false,
				'placeholder'    => '',
				'options'        => array(),
				'is_name_field'  => false,
				'is_primary_email' => false,
				'show_in_directory' => false,
				'show_in_directory_modal' => true,
				'filterable'     => false,
				'show_in_list'   => false,
			),
			array(
				'id'             => 'tarikh_mula',
				'label'          => __( 'Tarikh Mula', 'smdm' ),
				'type'           => 'date',
				'section'        => 'custom',
				'order'          => $o++,
				'required'       => false,
				'placeholder'    => '',
				'options'        => array(),
				'is_name_field'  => false,
				'is_primary_email' => false,
				'show_in_directory' => false,
				'show_in_directory_modal' => true,
				'filterable'     => false,
				'show_in_list'   => false,
			),
			array(
				'id'             => 'tarikh_tamat',
				'label'          => __( 'Tarikh Tamat', 'smdm' ),
				'type'           => 'date',
				'section'        => 'custom',
				'order'          => $o++,
				'required'       => false,
				'placeholder'    => '',
				'options'        => array(),
				'is_name_field'  => false,
				'is_primary_email' => false,
				'show_in_directory' => false,
				'show_in_directory_modal' => true,
				'filterable'     => false,
				'show_in_list'   => false,
			),
			array(
				'id'             => 'tempoh_hari',
				'label'          => __( 'Tempoh (Hari)', 'smdm' ),
				'type'           => 'number',
				'section'        => 'custom',
				'order'          => $o++,
				'required'       => false,
				'placeholder'    => '',
				'options'        => array(),
				'is_name_field'  => false,
				'is_primary_email' => false,
				'show_in_directory' => false,
				'show_in_directory_modal' => true,
				'filterable'     => false,
				'show_in_list'   => false,
			),
			array(
				'id'             => 'jenis_pembiayaan',
				'label'          => __( 'Jenis Pembiayaan', 'smdm' ),
				'type'           => 'text',
				'section'        => 'custom',
				'order'          => $o++,
				'required'       => false,
				'placeholder'    => '',
				'options'        => array(),
				'is_name_field'  => false,
				'is_primary_email' => false,
				'show_in_directory' => false,
				'show_in_directory_modal' => true,
				'filterable'     => false,
				'show_in_list'   => false,
			),
			array(
				'id'             => 'jumlah_pembiayaan',
				'label'          => __( 'Jumlah Pembiayaan', 'smdm' ),
				'type'           => 'number',
				'section'        => 'custom',
				'order'          => $o++,
				'required'       => false,
				'placeholder'    => '',
				'options'        => array(),
				'is_name_field'  => false,
				'is_primary_email' => false,
				'show_in_directory' => false,
				'show_in_directory_modal' => true,
				'filterable'     => false,
				'show_in_list'   => false,
			),
			array(
				'id'             => 'catatan',
				'label'          => __( 'Catatan', 'smdm' ),
				'type'           => 'textarea',
				'section'        => 'custom',
				'order'          => $o++,
				'required'       => false,
				'placeholder'    => '',
				'options'        => array(),
				'is_name_field'  => false,
				'is_primary_email' => false,
				'show_in_directory' => false,
				'show_in_directory_modal' => true,
				'filterable'     => false,
				'show_in_list'   => false,
			),
			array(
				'id'             => 'address',
				'label'          => __( 'Street Address', 'smdm' ),
				'type'           => 'textarea',
				'section'        => 'address',
				'order'          => $o++,
				'required'       => false,
				'placeholder'    => '',
				'options'        => array(),
				'is_name_field'  => false,
				'is_primary_email' => false,
				'show_in_directory' => false,
				'show_in_directory_modal' => true,
				'filterable'     => false,
				'show_in_list'   => false,
			),
			array(
				'id'             => 'postcode',
				'label'          => __( 'Postcode', 'smdm' ),
				'type'           => 'text',
				'section'        => 'address',
				'order'          => $o++,
				'required'       => false,
				'placeholder'    => '',
				'options'        => array(),
				'is_name_field'  => false,
				'is_primary_email' => false,
				'show_in_directory' => false,
				'show_in_directory_modal' => true,
				'filterable'     => false,
				'show_in_list'   => false,
			),
			array(
				'id'             => 'city',
				'label'          => __( 'City', 'smdm' ),
				'type'           => 'text',
				'section'        => 'address',
				'order'          => $o++,
				'required'       => false,
				'placeholder'    => '',
				'options'        => array(),
				'is_name_field'  => false,
				'is_primary_email' => false,
				'show_in_directory' => true,
				'show_in_directory_modal' => true,
				'filterable'     => true,
				'show_in_list'   => false,
			),
			array(
				'id'             => 'state',
				'label'          => __( 'State', 'smdm' ),
				'type'           => 'state_ms',
				'section'        => 'address',
				'order'          => $o++,
				'required'       => false,
				'placeholder'    => '',
				'options'        => array(),
				'is_name_field'  => false,
				'is_primary_email' => false,
				'show_in_directory' => true,
				'show_in_directory_modal' => true,
				'filterable'     => true,
				'show_in_list'   => true,
			),
			array(
				'id'             => 'account_status',
				'label'          => __( 'Status', 'smdm' ),
				'type'           => 'status',
				'section'        => 'system',
				'order'          => $o++,
				'required'       => true,
				'placeholder'    => '',
				'options'        => array( 'Aktif', 'Tidak aktif' ),
				'is_name_field'  => false,
				'is_primary_email' => false,
				'show_in_directory' => false,
				'show_in_directory_modal' => false,
				'filterable'     => false,
				'show_in_list'   => true,
			),
		);
	}

	/**
	 * Normalize and validate field row defaults.
	 */
	public static function normalize_fields( $fields ) {
		$out = array();
		foreach ( $fields as $f ) {
			if ( empty( $f['id'] ) || ! is_string( $f['id'] ) ) {
				continue;
			}
			$f['id'] = sanitize_key( $f['id'] );
			if ( '' === $f['id'] ) {
				continue;
			}
			$type = isset( $f['type'] ) ? sanitize_key( $f['type'] ) : 'text';
			if ( ! in_array( $type, self::allowed_types(), true ) ) {
				$type = 'text';
			}
			$out[] = array(
				'id'                      => $f['id'],
				'label'                   => isset( $f['label'] ) ? sanitize_text_field( $f['label'] ) : $f['id'],
				'type'                    => $type,
				'section'                 => isset( $f['section'] ) ? sanitize_key( $f['section'] ) : 'custom',
				'order'                   => isset( $f['order'] ) ? (int) $f['order'] : 0,
				'required'                => ! empty( $f['required'] ),
				'placeholder'             => isset( $f['placeholder'] ) ? sanitize_text_field( $f['placeholder'] ) : '',
				'options'                 => self::normalize_options( $f['options'] ?? array() ),
				'is_name_field'          => ! empty( $f['is_name_field'] ),
				'is_primary_email'       => ! empty( $f['is_primary_email'] ),
				'show_in_directory'       => ! empty( $f['show_in_directory'] ),
				'show_in_directory_modal' => ! empty( $f['show_in_directory_modal'] ),
				'filterable'              => ! empty( $f['filterable'] ),
				'show_in_list'            => ! empty( $f['show_in_list'] ),
			);
		}
		return $out;
	}

	private static function normalize_options( $options ) {
		if ( ! is_array( $options ) ) {
			if ( is_string( $options ) ) {
				$options = array_filter( array_map( 'trim', explode( "\n", $options ) ) );
			} else {
				$options = array();
			}
		}
		$clean = array();
		foreach ( $options as $opt ) {
			$opt = sanitize_text_field( (string) $opt );
			if ( '' !== $opt ) {
				$clean[] = $opt;
			}
		}
		return $clean;
	}

	/**
	 * Validate full fields array (unique ids, one name, one primary email, filterable cap).
	 */
	public static function validate_fields( $fields ) {
		$errors  = array();
		$ids     = array();
		$names   = 0;
		$emails  = 0;
		$filters = 0;

		foreach ( $fields as $f ) {
			if ( empty( $f['id'] ) ) {
				continue;
			}
			if ( isset( $ids[ $f['id'] ] ) ) {
				$errors[] = sprintf( __( 'Duplicate field id: %s', 'smdm' ), $f['id'] );
			}
			$ids[ $f['id'] ] = true;
			if ( ! empty( $f['is_name_field'] ) ) {
				++$names;
			}
			if ( ! empty( $f['is_primary_email'] ) ) {
				if ( isset( $f['type'] ) && 'email' !== $f['type'] ) {
					$errors[] = __( 'Primary email field must be type "email".', 'smdm' );
				}
				++$emails;
			}
			if ( ! empty( $f['filterable'] ) ) {
				++$filters;
			}
		}

		if ( 1 !== $names ) {
			$errors[] = __( 'Exactly one field must be marked as the member name (maps to title).', 'smdm' );
		}
		if ( 1 !== $emails ) {
			$errors[] = __( 'Exactly one field must be marked as primary email (for blasts).', 'smdm' );
		}
		if ( $filters > self::MAX_FILTERABLE ) {
			$errors[] = sprintf(
				/* translators: %d max filterable fields */
				__( 'At most %d fields can be filterable on the directory.', 'smdm' ),
				self::MAX_FILTERABLE
			);
		}

		return $errors;
	}

	public static function save_schema( $fields ) {
		$fields = self::normalize_fields( $fields );
		$errs   = self::validate_fields( $fields );
		if ( ! empty( $errs ) ) {
			return $errs;
		}
		update_option(
			self::OPTION_KEY,
			array(
				'version' => self::SCHEMA_VERSION,
				'fields'  => $fields,
			),
			false
		);
		return array();
	}

	/**
	 * Unique slug from label for new custom fields.
	 */
	public static function generate_unique_id( $label, $existing_ids ) {
		$base = sanitize_title( $label );
		if ( '' === $base ) {
			$base = 'custom_field';
		}
		$id = substr( $base, 0, 48 );
		$i  = 2;
		while ( in_array( $id, $existing_ids, true ) ) {
			$suffix = '-' . $i;
			$id      = substr( $base, 0, 48 - strlen( $suffix ) ) . $suffix;
			++$i;
		}
		return $id;
	}

	/**
	 * Append a new field (does not save until validate passes).
	 */
	public static function build_new_field_row( $label, $type, $section = 'custom' ) {
		$type = sanitize_key( $type );
		if ( ! in_array( $type, self::allowed_types(), true ) ) {
			$type = 'text';
		}
		$section = sanitize_key( $section );
		if ( ! isset( self::section_labels()[ $section ] ) ) {
			$section = 'custom';
		}
		$existing = wp_list_pluck( self::get_fields(), 'id' );
		$id       = self::generate_unique_id( $label, $existing );
		$maxo     = 0;
		foreach ( self::get_fields() as $f ) {
			$maxo = max( $maxo, (int) ( $f['order'] ?? 0 ) );
		}
		return array(
			'id'                      => $id,
			'label'                   => sanitize_text_field( $label ),
			'type'                    => $type,
			'section'                 => $section,
			'order'                   => $maxo + 1,
			'required'                => false,
			'placeholder'             => '',
			'options'                 => array(),
			'is_name_field'          => false,
			'is_primary_email'       => false,
			'show_in_directory'       => false,
			'show_in_directory_modal' => true,
			'filterable'              => false,
			'show_in_list'            => false,
		);
	}

	public static function delete_field_by_id( $field_id ) {
		$field_id = sanitize_key( $field_id );
		$fields   = self::get_fields();
		$new      = array();
		foreach ( $fields as $f ) {
			if ( $f['id'] !== $field_id ) {
				$new[] = $f;
			}
		}
		return self::save_schema( $new );
	}

	/**
	 * Swap order with neighbour in sorted list.
	 */
	public static function move_field_order( $field_id, $direction ) {
		$field_id  = sanitize_key( $field_id );
		$direction = 'down' === $direction ? 'down' : 'up';
		$fields    = self::get_sorted_fields();
		$idx       = null;
		foreach ( $fields as $i => $f ) {
			if ( $f['id'] === $field_id ) {
				$idx = $i;
				break;
			}
		}
		if ( null === $idx ) {
			return array( __( 'Field not found.', 'smdm' ) );
		}
		$swap = ( 'up' === $direction ) ? $idx - 1 : $idx + 1;
		if ( $swap < 0 || $swap >= count( $fields ) ) {
			return array();
		}
		$o1                         = (int) $fields[ $idx ]['order'];
		$o2                         = (int) $fields[ $swap ]['order'];
		$fields[ $idx ]['order']    = $o2;
		$fields[ $swap ]['order']   = $o1;
		return self::save_schema( $fields );
	}

	/**
	 * Sanitize incoming POST value for a field definition.
	 */
	public static function sanitize_value_for_field( $field, $raw ) {
		$type = isset( $field['type'] ) ? $field['type'] : 'text';
		switch ( $type ) {
			case 'email':
				return sanitize_email( is_string( $raw ) ? $raw : '' );
			case 'textarea':
				return sanitize_textarea_field( is_string( $raw ) ? $raw : '' );
			case 'number':
				return is_numeric( $raw ) ? (string) ( 0 + $raw ) : '';
			case 'date':
				$s = sanitize_text_field( is_string( $raw ) ? $raw : '' );
				return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $s ) ? $s : '';
			case 'checkbox':
				return ! empty( $raw ) ? '1' : '';
			case 'select':
			case 'status':
				$s    = sanitize_text_field( is_string( $raw ) ? $raw : '' );
				$opts = isset( $field['options'] ) && is_array( $field['options'] ) ? $field['options'] : array();
				if ( 'status' === $type && empty( $opts ) ) {
					$opts = array( self::get_active_status_literal(), self::get_inactive_status_literal() );
				}
				if ( 'Active' === $s ) {
					$s = self::get_active_status_literal();
				} elseif ( 'Inactive' === $s ) {
					$s = self::get_inactive_status_literal();
				}
				return in_array( $s, $opts, true ) ? $s : '';
			case 'state_ms':
				$s = sanitize_text_field( is_string( $raw ) ? $raw : '' );
				return in_array( $s, self::get_malaysia_states(), true ) ? $s : '';
			case 'tel':
			case 'text':
			default:
				return sanitize_text_field( is_string( $raw ) ? $raw : '' );
		}
	}

	/**
	 * Persist member field values from $_POST['smdm_cf'][id].
	 */
	public static function save_member_fields_from_post( $post_id, $schema_fields ) {
		foreach ( $schema_fields as $field ) {
			$id = $field['id'];
			if ( ! empty( $field['is_name_field'] ) ) {
				continue;
			}
			if ( 'checkbox' === $field['type'] ) {
				$raw = ! empty( $_POST['smdm_cf'][ $id ] ) ? '1' : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			} else {
				$raw = isset( $_POST['smdm_cf'][ $id ] ) ? wp_unslash( $_POST['smdm_cf'][ $id ] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			}
			$val = self::sanitize_value_for_field( $field, $raw );
			update_post_meta( $post_id, self::meta_key_for( $id ), $val );

			$legacy = self::legacy_meta_key( $id );
			if ( $legacy ) {
				update_post_meta( $post_id, $legacy, $val );
			}
		}
	}

	public static function get_primary_email_field_id() {
		foreach ( self::get_sorted_fields() as $f ) {
			if ( ! empty( $f['is_primary_email'] ) ) {
				return $f['id'];
			}
		}
		return 'email';
	}

	public static function get_name_field_id() {
		foreach ( self::get_sorted_fields() as $f ) {
			if ( ! empty( $f['is_name_field'] ) ) {
				return $f['id'];
			}
		}
		return 'full_name';
	}

	/**
	 * Active status stored value (directory, blasts, meta).
	 */
	public static function get_active_status_literal() {
		return 'Aktif';
	}

	/**
	 * Inactive status stored value.
	 */
	public static function get_inactive_status_literal() {
		return 'Tidak aktif';
	}

	public static function member_is_active( $post_id ) {
		$st = self::get_member_value( $post_id, array( 'id' => 'account_status' ) );
		if ( '' === $st ) {
			$st = get_post_meta( $post_id, '_member_status', true );
		}
		$st = is_string( $st ) ? trim( $st ) : '';
		if ( '' === $st ) {
			return false;
		}
		if ( self::get_active_status_literal() === $st ) {
			return true;
		}
		// Legacy English before one-time migration.
		return 'Active' === $st;
	}

	/**
	 * Run once: seed schema and optionally copy legacy meta to dynamic keys.
	 */
	public static function maybe_migrate() {
		if ( self::get_raw() ) {
			return;
		}
		$fields = self::default_schema_fields();
		update_option(
			self::OPTION_KEY,
			array(
				'version' => self::SCHEMA_VERSION,
				'fields'  => $fields,
			),
			false
		);

		$members = get_posts(
			array(
				'post_type'      => 'member',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);
		foreach ( $members as $pid ) {
			foreach ( $fields as $field ) {
				if ( ! empty( $field['is_name_field'] ) ) {
					continue;
				}
				$id     = $field['id'];
				$legacy = self::legacy_meta_key( $id );
				if ( $legacy ) {
					$v = get_post_meta( $pid, $legacy, true );
					if ( '' !== $v && null !== $v ) {
						update_post_meta( $pid, self::meta_key_for( $id ), $v );
					}
				}
			}
		}
	}

	/**
	 * One-time: Malay status labels + meta/schema updates; legacy English still read in member_is_active until migrated.
	 */
	public static function maybe_migrate_malay_status() {
		if ( get_option( 'smdm_malay_status_v1', false ) ) {
			return;
		}
		$raw = self::get_raw();
		if ( $raw && ! empty( $raw['fields'] ) && is_array( $raw['fields'] ) ) {
			foreach ( $raw['fields'] as &$f ) {
				if ( isset( $f['id'] ) && 'account_status' === $f['id'] ) {
					$f['options'] = array( self::get_active_status_literal(), self::get_inactive_status_literal() );
				}
			}
			unset( $f );
			$fields = self::normalize_fields( $raw['fields'] );
			update_option(
				self::OPTION_KEY,
				array(
					'version' => self::SCHEMA_VERSION,
					'fields'  => $fields,
				),
				false
			);
		}

		global $wpdb;
		$pairs = array(
			'Active'   => self::get_active_status_literal(),
			'Inactive' => self::get_inactive_status_literal(),
		);
		foreach ( $pairs as $old => $new ) {
			$wpdb->update( $wpdb->postmeta, array( 'meta_value' => $new ), array( 'meta_key' => '_member_status', 'meta_value' => $old ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update( $wpdb->postmeta, array( 'meta_value' => $new ), array( 'meta_key' => self::meta_key_for( 'account_status' ), 'meta_value' => $old ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		}

		update_option( 'smdm_malay_status_v1', 1, false );
	}

	/**
	 * One-time: email field is optional (not everyone has email).
	 */
	public static function maybe_relax_email_optional() {
		if ( get_option( 'smdm_email_optional_v1', false ) ) {
			return;
		}
		$raw = self::get_raw();
		if ( $raw && ! empty( $raw['fields'] ) && is_array( $raw['fields'] ) ) {
			$changed = false;
			foreach ( $raw['fields'] as &$f ) {
				if ( isset( $f['id'] ) && 'email' === $f['id'] ) {
					$f['required'] = false;
					$changed       = true;
					break;
				}
			}
			unset( $f );
			if ( $changed ) {
				$fields = self::normalize_fields( $raw['fields'] );
				update_option(
					self::OPTION_KEY,
					array(
						'version' => self::SCHEMA_VERSION,
						'fields'  => $fields,
					),
					false
				);
			}
		}
		update_option( 'smdm_email_optional_v1', 1, false );
	}

	/**
	 * Seed demo data for fresh installs (only once, only when empty).
	 */
	public static function seed_dummy_members( $force = false ) {
		$already_seeded = get_option( 'smdm_dummy_seeded', false );
		if ( ! $force && $already_seeded ) {
			return;
		}
		$existing = get_posts(
			array(
				'post_type'      => 'member',
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
			)
		);
		if ( ! $force && ! empty( $existing ) ) {
			update_option( 'smdm_dummy_seeded', 1, false );
			return;
		}

		$demo_rows = array(
			array(
				'full_name'          => 'Ahmad Firdaus',
				'email'              => 'ahmad.firdaus@example.com',
				'phone'              => '+60112223344',
				'program'            => 'Pembangunan Komuniti',
				'pelaksana_utama'    => 'UPKT',
				'jenis_pelaksanaan'  => 'Latihan',
				'tahun'              => '2024',
				'state'              => 'Selangor',
				'daerah'             => 'Petaling',
				'no_kp'              => '900101105555',
				'jawatan'            => 'Pegawai',
				'status_jawatan'     => 'Tetap',
				'institusi'          => 'Majlis Bandaraya',
				'account_status'     => self::get_active_status_literal(),
				'category'           => 'Pembangunan Komuniti',
			),
			array(
				'full_name'          => 'Siti Nadhirah',
				'email'              => 'siti.nadhirah@example.com',
				'phone'              => '+60137778899',
				'program'            => 'Latihan ICT',
				'pelaksana_utama'    => 'IKDM',
				'jenis_pelaksanaan'  => 'Bengkel',
				'tahun'              => '2025',
				'state'              => 'Johor',
				'daerah'             => 'Johor Bahru',
				'no_kp'              => '920320015432',
				'jawatan'            => 'Eksekutif',
				'status_jawatan'     => 'Kontrak',
				'institusi'          => 'Agensi Digital',
				'account_status'     => self::get_active_status_literal(),
				'category'           => 'Latihan ICT',
			),
			array(
				'full_name'          => 'Mohd Iqbal',
				'email'              => 'mohd.iqbal@example.com',
				'phone'              => '+60198887766',
				'program'            => 'Program TVET',
				'pelaksana_utama'    => 'TVET',
				'jenis_pelaksanaan'  => 'Pensijilan',
				'tahun'              => '2023',
				'state'              => 'Perak',
				'daerah'             => 'Ipoh',
				'no_kp'              => '880707085555',
				'jawatan'            => 'Juruteknik',
				'status_jawatan'     => 'Tetap',
				'institusi'          => 'Institut Latihan',
				'account_status'     => self::get_active_status_literal(),
				'category'           => 'Program TVET',
			),
			array(
				'full_name'          => 'Nur Aisyah',
				'email'              => 'nur.aisyah@example.com',
				'phone'              => '+60125554433',
				'program'            => 'Pembangunan Wanita',
				'pelaksana_utama'    => 'UPKT',
				'jenis_pelaksanaan'  => 'Seminar',
				'tahun'              => '2024',
				'state'              => 'Penang',
				'daerah'             => 'Seberang Perai',
				'no_kp'              => '940505046789',
				'jawatan'            => 'Penyelaras',
				'status_jawatan'     => 'Sambilan',
				'institusi'          => 'NGO Komuniti',
				'account_status'     => self::get_active_status_literal(),
				'category'           => 'Pembangunan Wanita',
			),
			array(
				'full_name'          => 'Raj Kumar',
				'email'              => 'raj.kumar@example.com',
				'phone'              => '+60163334455',
				'program'            => 'Keusahawanan Belia',
				'pelaksana_utama'    => 'IKDM',
				'jenis_pelaksanaan'  => 'Kursus',
				'tahun'              => '2025',
				'state'              => 'W.P. Kuala Lumpur',
				'daerah'             => 'Kuala Lumpur',
				'no_kp'              => '910101105432',
				'jawatan'            => 'Usahawan',
				'status_jawatan'     => 'Lain-lain',
				'institusi'          => 'Persatuan Belia',
				'account_status'     => self::get_active_status_literal(),
				'category'           => 'Keusahawanan Belia',
			),
		);

		$schema = self::get_sorted_fields();
		foreach ( $demo_rows as $row ) {
			$post_id = wp_insert_post(
				array(
					'post_title'  => $row['full_name'],
					'post_type'   => 'member',
					'post_status' => 'publish',
				),
				true
			);
			if ( is_wp_error( $post_id ) || ! $post_id ) {
				continue;
			}
			foreach ( $schema as $field ) {
				$fid = $field['id'];
				if ( ! isset( $row[ $fid ] ) || ! empty( $field['is_name_field'] ) ) {
					continue;
				}
				$val = self::sanitize_value_for_field( $field, $row[ $fid ] );
				update_post_meta( $post_id, self::meta_key_for( $fid ), $val );
				$legacy = self::legacy_meta_key( $fid );
				if ( $legacy ) {
					update_post_meta( $post_id, $legacy, $val );
				}
			}
			if ( ! empty( $row['category'] ) ) {
				$term = term_exists( $row['category'], 'member_category' );
				if ( ! $term ) {
					$term = wp_insert_term( $row['category'], 'member_category' );
				}
				if ( ! is_wp_error( $term ) ) {
					$term_id = is_array( $term ) ? (int) $term['term_id'] : (int) $term;
					wp_set_object_terms( $post_id, $term_id, 'member_category' );
				}
			}
		}
		update_option( 'smdm_dummy_seeded', 1, false );
	}

	/**
	 * Section labels for admin form.
	 */
	public static function section_labels() {
		return array(
			'personal' => __( 'Personal Information', 'smdm' ),
			'contact'  => __( 'Contact Details', 'smdm' ),
			'address'  => __( 'Address Information', 'smdm' ),
			'system'   => __( 'System', 'smdm' ),
			'custom'   => __( 'Additional Fields', 'smdm' ),
		);
	}

	public static function render_field_input( $field, $value, $input_name = 'smdm_cf', $extra_class = '' ) {
		$id    = $field['id'];
		$name = $input_name . '[' . $id . ']';
		$req   = ! empty( $field['required'] ) ? ' required' : '';
		$ph    = isset( $field['placeholder'] ) ? $field['placeholder'] : '';
		$type  = $field['type'];
		$label = isset( $field['label'] ) ? $field['label'] : $id;

		$wrap = 'form-group' . ( $extra_class ? ' ' . sanitize_html_class( $extra_class ) : '' );
		echo '<div class="' . esc_attr( $wrap ) . '">';
		echo '<label for="smdm_cf_' . esc_attr( $id ) . '">' . esc_html( $label );
		if ( ! empty( $field['required'] ) ) {
			echo ' <span class="smdm-req">*</span>';
		}
		echo '</label>';

		switch ( $type ) {
			case 'textarea':
				echo '<textarea id="smdm_cf_' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" rows="3" placeholder="' . esc_attr( $ph ) . '"' . $req . '>' . esc_textarea( $value ) . '</textarea>';
				break;
			case 'select':
			case 'status':
				$opts = 'status' === $type && empty( $field['options'] ) ? array( self::get_active_status_literal(), self::get_inactive_status_literal() ) : $field['options'];
				echo '<select id="smdm_cf_' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '"' . $req . '>';
				if ( ! $field['required'] ) {
					echo '<option value="">' . esc_html__( '— Select —', 'smdm' ) . '</option>';
				}
				foreach ( $opts as $opt ) {
					printf(
						'<option value="%s" %s>%s</option>',
						esc_attr( $opt ),
						selected( $value, $opt, false ),
						esc_html( $opt )
					);
				}
				echo '</select>';
				break;
			case 'checkbox':
				printf(
					'<label class="smdm-checkbox-label"><input type="checkbox" id="smdm_cf_%1$s" name="%2$s" value="1" %3$s /> %4$s</label>',
					esc_attr( $id ),
					esc_attr( $name ),
					checked( $value, '1', false ),
					esc_html__( 'Yes', 'smdm' )
				);
				break;
			case 'state_ms':
				echo '<select id="smdm_cf_' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '"' . $req . '>';
				echo '<option value="">' . esc_html__( 'Select State', 'smdm' ) . '</option>';
				foreach ( self::get_malaysia_states() as $st ) {
					printf( '<option value="%s" %s>%s</option>', esc_attr( $st ), selected( $value, $st, false ), esc_html( $st ) );
				}
				echo '</select>';
				break;
			case 'date':
				printf(
					'<input type="date" id="smdm_cf_%1$s" name="%2$s" value="%3$s" placeholder="%4$s"%5$s />',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( $value ),
					esc_attr( $ph ),
					$req
				);
				break;
			case 'number':
				printf(
					'<input type="number" step="any" id="smdm_cf_%1$s" name="%2$s" value="%3$s" placeholder="%4$s"%5$s />',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( $value ),
					esc_attr( $ph ),
					$req
				);
				break;
			case 'email':
				printf(
					'<input type="email" id="smdm_cf_%1$s" name="%2$s" value="%3$s" placeholder="%4$s"%5$s />',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( $value ),
					esc_attr( $ph ),
					$req
				);
				break;
			case 'tel':
				printf(
					'<input type="tel" id="smdm_cf_%1$s" name="%2$s" value="%3$s" placeholder="%4$s"%5$s />',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( $value ),
					esc_attr( $ph ),
					$req
				);
				break;
			default:
				printf(
					'<input type="text" id="smdm_cf_%1$s" name="%2$s" value="%3$s" placeholder="%4$s"%5$s />',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( $value ),
					esc_attr( $ph ),
					$req
				);
		}
		echo '</div>';
	}
}
