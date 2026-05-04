<?php
/**
 * Plugin Name: Simple Member Database Manager
 * Description: A simple, lightweight member management system for admin use only.
 * Version: 1.4.7
 * Author: Asyraf Digital
 * Text Domain: smdm
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants
define( 'SMDM_VERSION', '1.4.7' );
define( 'SMDM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SMDM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Include required files
require_once SMDM_PLUGIN_DIR . 'includes/class-smdm-field-schema.php';
require_once SMDM_PLUGIN_DIR . 'includes/class-smdm-post-type.php';
require_once SMDM_PLUGIN_DIR . 'includes/class-smdm-taxonomy.php';
require_once SMDM_PLUGIN_DIR . 'includes/class-smdm-meta-boxes.php';
require_once SMDM_PLUGIN_DIR . 'includes/class-smdm-admin-pages.php';
require_once SMDM_PLUGIN_DIR . 'includes/class-smdm-import-export.php';
require_once SMDM_PLUGIN_DIR . 'includes/class-smdm-frontend.php';



/**
 * Main Plugin Class
 */
class Simple_Member_Database_Manager {

	/**
	 * Instance of this class.
	 *
	 * @var object
	 */
	private static $instance = null;

	/**
	 * Return an instance of this class.
	 *
	 * @return object A single instance of this class.
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	private function __construct() {
		// Initialize classes
		new SMDM_Post_Type();
		new SMDM_Taxonomy();
		new SMDM_Meta_Boxes();
		new SMDM_Admin_Pages();
		SMDM_Import_Export::instance();
		new SMDM_Frontend();

		// Register activation hook
		register_activation_hook( __FILE__, array( $this, 'activate' ) );

		add_action( 'plugins_loaded', array( $this, 'maybe_upgrade_schema' ), 5 );

		// Enqueue admin styles
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_styles' ) );
	}

	/**
	 * Activation hook callback
	 */
	public function activate() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'member_email_logs';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			date_sent datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
			subject text NOT NULL,
			total_recipients int(11) NOT NULL,
			status varchar(20) NOT NULL,
			PRIMARY KEY  (id)
		) $charset_collate;";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );

		if ( class_exists( 'SMDM_Field_Schema' ) ) {
			SMDM_Field_Schema::maybe_migrate();
			SMDM_Field_Schema::seed_dummy_members();
		}
	}

	/**
	 * Ensure field schema exists after plugin updates (not only on activate).
	 */
	public function maybe_upgrade_schema() {
		if ( class_exists( 'SMDM_Field_Schema' ) ) {
			SMDM_Field_Schema::maybe_migrate();
			SMDM_Field_Schema::maybe_migrate_malay_status();
			SMDM_Field_Schema::maybe_relax_email_optional();
		}
	}

	/**
	 * Enqueue admin styles
	 */
	public function enqueue_admin_styles( $hook ) {
		wp_enqueue_style( 'smdm-admin-style', SMDM_PLUGIN_URL . 'assets/css/admin-style.css', array(), SMDM_VERSION );
		wp_enqueue_style( 'smdm-frontend-style', SMDM_PLUGIN_URL . 'assets/css/frontend-style.css', array(), SMDM_VERSION );
		if ( false !== strpos( (string) $hook, 'smdm-app' ) ) {
			wp_enqueue_script( 'smdm-admin-shell', SMDM_PLUGIN_URL . 'assets/js/admin-shell.js', array(), SMDM_VERSION, true );
		}
	}
}

// Initialize the plugin
Simple_Member_Database_Manager::get_instance();
