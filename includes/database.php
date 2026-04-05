<?php
/**
 * Quantum Telegraph - Database Architecture
 * Handles custom table creation and maintenance for spatial-neural state syncing.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Creates the custom table for storing player scores and activity.
 */
function melle_vr_create_table() {
    global $wpdb;
    
    // We use the original prefix to ensure existing data is not lost during the rebrand
    $table_name = $wpdb->prefix . 'melle_vr_players';
    $charset_collate = $wpdb->get_charset_collate();

    // SQL to create the table
    // UNIQUE KEY on player_name ensures we don't get duplicate entries for one user
    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        player_name varchar(50) NOT NULL,
        channel varchar(100) DEFAULT 'melle' NOT NULL,
        score int(11) DEFAULT 0 NOT NULL,
        runes_caught int(11) DEFAULT 0 NOT NULL,
        collected_runes text NOT NULL,
        pos_x float DEFAULT 0 NOT NULL,
        pos_y float DEFAULT 0 NOT NULL,
        pos_z float DEFAULT -3 NOT NULL,
        rot_y float DEFAULT 0 NOT NULL,
        color varchar(6) DEFAULT '00ffff' NOT NULL,
        shield_active tinyint(1) DEFAULT 0 NOT NULL,
        current_rune varchar(10) DEFAULT '' NOT NULL,
        catchphrase varchar(255) DEFAULT '' NOT NULL,
        last_active datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY player_name (player_name)
    ) $charset_collate;";

    // This file is required to run the dbDelta function
    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    
    // Executes the SQL and handles table versioning
    dbDelta( $sql );
}

/**
 * Cleanup function: Removes players who haven't pinged the server in 30 seconds.
 * This is called by the API 'GET' request to keep the leaderboard "live".
 */
function melle_vr_cleanup_inactive_players() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'melle_vr_players';
    
    // Calculate the cutoff time (30 seconds ago)
    $cutoff = date('Y-m-d H:i:s', time() - 30);
    
    // Delete players whose last_active timestamp is older than the cutoff
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM $table_name WHERE last_active < %s",
            $cutoff
        )
    );
}
