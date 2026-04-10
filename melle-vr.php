<?php
/**
 * Plugin Name: Quantum Telegraph & Blueprint of the Cosmos
 * Description: A WebXR audio visualizer, spatial-neural core, and cosmic story engine. Unified v4.0.
 * Version: 4.0
 * Author: Brandon Corlett
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

// ==========================================
// 1. GLOBAL CONSTANTS
// ==========================================
define( 'MELLE_VR_PATH', plugin_dir_path( __FILE__ ) );
define( 'MELLE_VR_URL', plugin_dir_url( __FILE__ ) );

// ==========================================
// 2. DATABASE INITIALIZATION
// ==========================================
if ( file_exists( MELLE_VR_PATH . 'includes/database.php' ) ) {
    require_once MELLE_VR_PATH . 'includes/database.php';
    // Register table creation on plugin activation
    register_activation_hook( __FILE__, 'melle_vr_create_table' );
}

// ==========================================
// 3. CORE MODULE INCLUDES
// ==========================================
// Array of all frontend and server-side logic files
$core_files = [
    'includes/ajax-handlers.php',
    'includes/api.php',
    'includes/shortcode.php',
    'includes/isochronic-shortcode.php',
    'includes/blueprint-core.php',
    'includes/today-note.php',
    'includes/collab-hub.php',
    'includes/tutorial.php',
    'includes/player-ui.php',
    'includes/asset-manager.php'
];

foreach ( $core_files as $file ) {
    if ( file_exists( MELLE_VR_PATH . $file ) ) {
        require_once MELLE_VR_PATH . $file;
    }
}

// ==========================================
// 4. ADMIN MODULE INCLUDES
// ==========================================
if ( is_admin() ) {
    $admin_files = [
        'includes/admin.php',
        'includes/admin-menu.php',
        'includes/admin-interface.php',
        'includes/admin-beatmaps.php'
    ];
    foreach ( $admin_files as $file ) {
        if ( file_exists( MELLE_VR_PATH . $file ) ) {
            require_once MELLE_VR_PATH . $file;
        }
    }
}

// ==========================================
// 5. ASSET MANAGER & ES6 MODULE FIX
// ==========================================
// Note: If using the MVR_Asset_Manager class from asset-manager.php, 
// we initialize it here. Fallbacks are included below.
if ( class_exists( 'MVR_Asset_Manager' ) ) {
    new MVR_Asset_Manager();
} else {
    add_action( 'wp_enqueue_scripts', 'melle_vr_enqueue_frontend_assets' );
    add_filter( 'script_loader_tag', 'melle_vr_add_module_type_attribute', 10, 3 );
}

function melle_vr_enqueue_frontend_assets() {
    // Enqueue Styles
    wp_enqueue_style( 'mvr-style', MELLE_VR_URL . 'assets/css/style.css', [], '4.0' );
    wp_enqueue_style( 'mvr-blueprint-style', MELLE_VR_URL . 'assets/css/blueprint-style.css', [], '4.0' );

    // Enqueue JS Entry Points
     wp_enqueue_script( 'mvr-app', MELLE_VR_URL . 'assets/js/app.js', [], time(), true );
    wp_enqueue_script( 'mvr-blueprint-main', MELLE_VR_URL . 'assets/js/blueprint-main.js', [], time(), true );


    // Localize Data to pass PHP variables to JS seamlessly
    wp_localize_script( 'mvr-app', 'melleVrData', [
        'ajaxUrl' => admin_url( 'admin-ajax.php' ),
        'nonce'   => wp_create_nonce( 'mvr_ajax_nonce' ),
        'apiUrl'  => esc_url_raw( rest_url( 'melle-vr/v1/' ) )
    ]);
}

/**
 * Intercepts the script tag generation and injects type="module" 
 * for our specific JS files.
 */
function melle_vr_add_module_type_attribute( $tag, $handle, $src ) {
    $module_handles = [ 'mvr-app', 'mvr-blueprint-main' ];
    
    if ( in_array( $handle, $module_handles, true ) ) {
        return str_replace( '<script ', '<script type="module" ', $tag );
    }
    
    return $tag;
}
