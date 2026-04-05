<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * MVR_Asset_Manager Class
 *
 * Handles the enqueuing of all scripts and styles for the plugin.
 * Fixes JavaScript module loading errors by adding `type="module"` to script tags.
 */
class MVR_Asset_Manager {

	/**
	 * Script handles that should be loaded as ES modules.
	 *
	 * @var array
	 */
	private $module_script_handles = [
		'mvr-app',
		'mvr-blueprint-main',
	];

	/**
	 * Constructor to hook into WordPress.
	 */
	public function __construct() {
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend_assets' ] );
		// The script_loader_tag filter passes 3 arguments: $tag, $handle, and $src.
		add_filter( 'script_loader_tag', [ $this, 'add_module_type_attribute' ], 10, 3 );
	}

	/**
	 * Enqueue scripts and styles for the front end.
	 */
	public function enqueue_frontend_assets() {
		// Enqueue Styles
		wp_enqueue_style(
			'mvr-style',
			MELLE_VR_URL . 'assets/css/style.css',
			[],
			'4.0'
		);

		wp_enqueue_style(
			'mvr-blueprint-style',
			MELLE_VR_URL . 'assets/css/blueprint-style.css',
			[],
			'4.0'
		);

		// Enqueue JavaScript Module Entry Points
		// Note: We only enqueue the main entry point files. The browser will handle loading
		// imported modules (like ui.js, network.js, etc.) automatically.

		wp_enqueue_script(
			'mvr-app',
			MELLE_VR_URL . 'assets/js/app.js',
			[], // Dependencies are handled by the browser via ES module `import` statements.
			'4.0',
			true // Load in the footer.
		);

		wp_enqueue_script(
			'mvr-blueprint-main',
			MELLE_VR_URL . 'assets/js/blueprint-main.js',
			[],
			'4.0',
			true
		);

		// Pass data from PHP to our main application script.
		wp_localize_script(
			'mvr-app',
			'melleVrData', // This object will be available as `window.melleVrData` in JS.
			[
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'nonce'       => wp_create_nonce( 'mvr_ajax_nonce' ),
				'apiUrl'      => esc_url_raw( rest_url( 'melle-vr/v1/' ) ),
			]
		);
	}

	/**
	 * Adds the type="module" attribute to script tags for our designated module scripts.
	 * This is the fix for 'Unexpected keyword export' and 'import call expects' errors.
	 *
	 * @param string $tag    The complete <script> tag.
	 * @param string $handle The script's registered handle.
	 * @param string $src    The script's source URL (unused in this function, but required by the filter).
	 * @return string The modified script tag.
	 */
	public function add_module_type_attribute( $tag, $handle, $src ) {
		if ( in_array( $handle, $this->module_script_handles, true ) ) {
			// This is a more robust way to add the module type attribute.
			// It avoids issues if other attributes (like defer, async) are present.
			return str_replace( '<script ', '<script type="module" ', $tag );
		}
		return $tag;
	}
}
