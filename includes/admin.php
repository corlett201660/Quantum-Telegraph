<?php
/**
 * Admin Settings Page for Quantum Telegraph & Blueprint of the Cosmos
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

add_action( 'admin_menu', 'melle_vr_add_admin_menu' );
add_action( 'admin_init', 'melle_vr_settings_init' );

function melle_vr_add_admin_menu() {
    add_options_page( 
        'Quantum Telegraph', 
        'Quantum Telegraph', 
        'manage_options', 
        'melle_vr_settings', 
        'melle_vr_options_page' 
    );
}

function melle_vr_settings_init() {
    // General Routing & Store
    register_setting( 'melle_vr_settings_group', 'melle_vr_home_url' );
    register_setting( 'melle_vr_settings_group', 'melle_vr_store_link' );
    register_setting( 'melle_vr_settings_group', 'qrq_is_live' );
    
    // Icecast Server Configuration (NEW)
    register_setting( 'melle_vr_settings_group', 'qrq_icecast_json_url' );
    register_setting( 'melle_vr_settings_group', 'melle_vr_icecast_base_url' );
    register_setting( 'melle_vr_settings_group', 'qrq_stream_url' );

    // Access & Mount Restrictions
    register_setting( 'melle_vr_settings_group', 'melle_vr_vr_allowed_mounts' );
    register_setting( 'melle_vr_settings_group', 'melle_vr_vr_excluded_mounts' );
    register_setting( 'melle_vr_settings_group', 'melle_vr_radio_allowed_mounts' );
    register_setting( 'melle_vr_settings_group', 'melle_vr_radio_excluded_mounts' );
    register_setting( 'melle_vr_settings_group', 'melle_vr_restricted_mounts' );
    register_setting( 'melle_vr_settings_group', 'melle_vr_required_roles' );
    register_setting( 'melle_vr_settings_group', 'melle_vr_required_products' );
    // Note: station_reqs handles complex arrays if you are saving specific WC products per mount
}

function melle_vr_options_page() {
    ?>
    <div class="wrap">
        <h1><i class="fas fa-satellite-dish"></i> Quantum Telegraph Configuration</h1>
        <form action="options.php" method="post">
            <?php
            settings_fields( 'melle_vr_settings_group' );
            do_settings_sections( 'melle_vr_settings_group' );
            ?>
            
            <h2><i class="fas fa-link"></i> General Routing & Store</h2>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">PWA Home URL</th>
                    <td>
                        <input type="text" name="melle_vr_home_url" value="<?php echo esc_attr( get_option('melle_vr_home_url', '/community') ); ?>" class="regular-text" />
                        <p class="description">Where the "Home" buttons route users safely (e.g., /community).</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Premium Store Link</th>
                    <td>
                        <input type="text" name="melle_vr_store_link" value="<?php echo esc_attr( get_option('melle_vr_store_link', '') ); ?>" class="regular-text" />
                        <p class="description">Fallback WooCommerce store link for locked tracks.</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Live Broadcast Toast</th>
                    <td>
                        <select name="qrq_is_live">
                            <option value="1" <?php selected( get_option('qrq_is_live'), '1' ); ?>>Active (Show Toast)</option>
                            <option value="0" <?php selected( get_option('qrq_is_live'), '0' ); ?>>Offline (Hide Toast)</option>
                        </select>
                        <p class="description">Toggle the global "Listen Live" corner toast across the WordPress theme.</p>
                    </td>
                </tr>
            </table>

            <hr>
            <h2><i class="fas fa-broadcast-tower"></i> Icecast Server Configuration</h2>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Icecast JSON URL</th>
                    <td>
                        <input type="text" name="qrq_icecast_json_url" value="<?php echo esc_attr( get_option('qrq_icecast_json_url', 'https://qrjournal.org/status-json.xsl') ); ?>" class="regular-text" />
                        <p class="description">The absolute URL to your Icecast status-json.xsl file (used for live polling and metadata).</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Icecast Base URL (Mounts)</th>
                    <td>
                        <input type="text" name="melle_vr_icecast_base_url" value="<?php echo esc_attr( get_option('melle_vr_icecast_base_url', 'https://qrjournal.org/icecast/') ); ?>" class="regular-text" />
                        <p class="description">The base URL prefix for your streams (e.g., <code>https://qrjournal.org/icecast/</code>). <strong>Must include the trailing slash.</strong></p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Default Fallback Stream</th>
                    <td>
                        <input type="text" name="qrq_stream_url" value="<?php echo esc_attr( get_option('qrq_stream_url', 'https://qrjournal.org/stream') ); ?>" class="regular-text" />
                        <p class="description">The default audio stream URL used if specific mounts fail to load in the radio player.</p>
                    </td>
                </tr>
            </table>

            <hr>
            <h2><i class="fas fa-shield-alt"></i> Access & Mount Restrictions</h2>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">VR Allowed Mounts</th>
                    <td>
                        <input type="text" name="melle_vr_vr_allowed_mounts" value="<?php echo esc_attr( get_option('melle_vr_vr_allowed_mounts', '') ); ?>" class="regular-text" />
                        <p class="description">Comma-separated list of mounts EXCLUSIVELY allowed in VR (leave blank to allow all).</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">VR Excluded Mounts</th>
                    <td>
                        <input type="text" name="melle_vr_vr_excluded_mounts" value="<?php echo esc_attr( get_option('melle_vr_vr_excluded_mounts', 'admin, fallback') ); ?>" class="regular-text" />
                        <p class="description">Comma-separated list of mounts strictly hidden from VR.</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Radio Excluded Mounts</th>
                    <td>
                        <input type="text" name="melle_vr_radio_excluded_mounts" value="<?php echo esc_attr( get_option('melle_vr_radio_excluded_mounts', 'admin, fallback') ); ?>" class="regular-text" />
                        <p class="description">Comma-separated list of mounts strictly hidden from the Radio Player.</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Global Restricted Mounts</th>
                    <td>
                        <input type="text" name="melle_vr_restricted_mounts" value="<?php echo esc_attr( get_option('melle_vr_restricted_mounts', '') ); ?>" class="regular-text" />
                        <p class="description">Comma-separated mounts that require a specific user role or global WooCommerce product purchase.</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Required Roles (Global Bypass)</th>
                    <td>
                        <input type="text" name="melle_vr_required_roles" value="<?php echo esc_attr( get_option('melle_vr_required_roles', '') ); ?>" class="regular-text" />
                        <p class="description">Comma-separated list of roles (e.g., 'subscriber, premium') that bypass restrictions entirely.</p>
                    </td>
                </tr>
            </table>

            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}
