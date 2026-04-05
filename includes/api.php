<?php
/**
 * Quantum Telegraph - REST API Endpoints
 * Handles multiplayer synchronization, global rune counters, and URL extraction.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'rest_api_init', 'melle_vr_register_rest_routes' );

function melle_vr_register_rest_routes() {
    $namespace = 'melle-vr/v1';

    // 1. GET & POST SCORES (High-Frequency Polling)
    register_rest_route( $namespace, '/scores', [
        [
            'methods'  => WP_REST_Server::READABLE,
            'callback' => 'melle_vr_get_scores',
            'permission_callback' => '__return_true',
        ],
        [
            'methods'  => WP_REST_Server::CREATABLE,
            'callback' => 'melle_vr_update_score',
            'permission_callback' => '__return_true',
        ],
        [
            'methods'  => WP_REST_Server::DELETABLE,
            'callback' => 'melle_vr_remove_player',
            'permission_callback' => '__return_true',
        ]
    ]);

    // 2. VALIDATE USERNAME
    register_rest_route( $namespace, '/validate-username', [
        'methods'  => WP_REST_Server::CREATABLE,
        'callback' => 'melle_vr_validate_username',
        'permission_callback' => '__return_true',
    ]);

    // 3. CRAWL & EXTRACT ENDGAME KEYWORDS
    register_rest_route( $namespace, '/crawl', [
        'methods'  => WP_REST_Server::CREATABLE,
        'callback' => 'melle_vr_crawl_url',
        'permission_callback' => '__return_true',
    ]);
}

// ==========================================
// 1. GET SCORES & TRIGGER ETHERIC ARRAY
// ==========================================
function melle_vr_get_scores( WP_REST_Request $request ) {
    $players = get_transient( 'melle_vr_live_players' );
    if ( ! is_array( $players ) ) {
        $players = [];
    }

    $global_runes = (int) get_option( 'melle_vr_global_runes', 0 );
    
    // Construct the response payload
    $response = [
        'players'              => $players,
        'global_runes'         => $global_runes,
        'etheric_interception' => false,
        'etheric_words'        => []
    ];

    // Check if the global circuit has reached capacity
    if ( $global_runes >= 100 ) {
        $response['etheric_interception'] = true;
        
        // Hardcoded array for immediate fallback, but ideally pulled from AI
        $fallback_words = ['RESONANCE', 'FREQUENCY', 'ANOMALY', 'QUANTUM', 'PARTICLE', 'OBSERVER', 'ETHERIC', 'TRANSMISSION'];
        shuffle($fallback_words);
        
        // Select 3 to 5 random words for the lexicon burst
        $response['etheric_words'] = array_slice($fallback_words, 0, mt_rand(3, 5));
        
        // Reset the global counter back to zero
        update_option( 'melle_vr_global_runes', 0 );
    }

    return rest_ensure_response( $response );
}

// ==========================================
// 2. POST SCORES & UPDATE AVATAR PHYSICS
// ==========================================
function melle_vr_update_score( WP_REST_Request $request ) {
    $params = $request->get_json_params();
    $name   = sanitize_text_field( $params['name'] ?? '' );

    if ( empty( $name ) ) {
        return new WP_Error( 'missing_name', 'Particle ID is required.', ['status' => 400] );
    }

    $players = get_transient( 'melle_vr_live_players' );
    if ( ! is_array( $players ) ) {
        $players = [];
    }

    // Calculate if the player caught new runes to update the global server count
    $old_caught = isset( $players[$name] ) ? (int) $players[$name]['runes_caught'] : 0;
    $new_caught = (int) ( $params['runes_caught'] ?? 0 );
    
    if ( $new_caught > $old_caught ) {
        $global_runes = (int) get_option( 'melle_vr_global_runes', 0 );
        $global_runes += ( $new_caught - $old_caught );
        // Cap it at 100 so the GET request can catch it and trigger the event
        if ($global_runes > 100) $global_runes = 100; 
        update_option( 'melle_vr_global_runes', $global_runes );
    }

    // Update player state
    $players[$name] = [
        'channel'       => sanitize_text_field( $params['channel'] ?? 'melle' ),
        'score'         => (int) ( $params['score'] ?? 0 ),
        'runes_caught'  => $new_caught,
        'pos_x'         => (float) ( $params['pos_x'] ?? 0 ),
        'pos_y'         => (float) ( $params['pos_y'] ?? 0 ),
        'pos_z'         => (float) ( $params['pos_z'] ?? 0 ),
        'rot_y'         => (float) ( $params['rot_y'] ?? 0 ),
        'color'         => sanitize_text_field( $params['color'] ?? '00ffff' ),
        'shield_active' => (int) ( $params['shield_active'] ?? 0 ),
        'current_rune'  => sanitize_text_field( $params['current_rune'] ?? '' ),
        'catchphrase'   => sanitize_text_field( $params['catchphrase'] ?? '' ),
        'last_active'   => time()
    ];

    // Housekeeping: Remove players who haven't pinged the server in 10 seconds
    $current_time = time();
    foreach ( $players as $key => $data ) {
        if ( $current_time - $data['last_active'] > 10 ) {
            unset( $players[$key] );
        }
    }

    // Store transient with a 15-second expiration to auto-clear memory if server crashes
    set_transient( 'melle_vr_live_players', $players, 15 );

    return rest_ensure_response( ['success' => true] );
}

// ==========================================
// 3. REMOVE PLAYER (On Disconnect)
// ==========================================
function melle_vr_remove_player( WP_REST_Request $request ) {
    $params = $request->get_json_params();
    $name   = sanitize_text_field( $params['name'] ?? '' );

    if ( ! empty( $name ) ) {
        $players = get_transient( 'melle_vr_live_players' );
        if ( is_array( $players ) && isset( $players[$name] ) ) {
            unset( $players[$name] );
            set_transient( 'melle_vr_live_players', $players, 15 );
        }
    }

    return rest_ensure_response( ['success' => true] );
}

// ==========================================
// 4. AI USERNAME MODERATION 
// ==========================================
function melle_vr_validate_username( WP_REST_Request $request ) {
    $params = $request->get_json_params();
    $username = sanitize_text_field( $params['username'] ?? '' );
    
    $safe = true;
    
    // Simple fast-fail blocklist (Add more words as needed)
    $blocklist = ['admin', 'root', 'system', 'moderator', 'fuck', 'shit', 'bitch'];
    
    foreach ( $blocklist as $bad_word ) {
        if ( stripos( $username, $bad_word ) !== false ) {
            $safe = false;
            break;
        }
    }

    return rest_ensure_response( [
        'safe'     => $safe,
        'username' => $username
    ]);
}

// ==========================================
// 5. CRAWL ENDGAME URL (Extract Keywords)
// ==========================================
function melle_vr_crawl_url( WP_REST_Request $request ) {
    $params = $request->get_json_params();
    $url    = esc_url_raw( $params['url'] ?? '' );

    if ( empty( $url ) ) {
        return new WP_Error( 'missing_url', 'URL parameter is required.', ['status' => 400] );
    }

    // Attempt to ping the target URL
    $response = wp_remote_get( $url, ['timeout' => 5, 'sslverify' => false] );

    if ( is_wp_error( $response ) ) {
        return rest_ensure_response( ['success' => false, 'error' => 'Target unreachable.'] );
    }

    $body = wp_remote_retrieve_body( $response );
    $keywords = [];

    // Simple fast extraction: Look for H1, H2, or strong tags to pull relevant buzzwords
    preg_match_all( '/<(?:h1|h2|h3|strong|b)[^>]*>(.*?)<\/(?:h1|h2|h3|strong|b)>/is', $body, $matches );
    
    if ( ! empty( $matches[1] ) ) {
        $raw_text = implode( ' ', $matches[1] );
        $raw_text = strip_tags( $raw_text );
        $words = str_word_count( $raw_text, 1 );
        
        // Filter words length > 4
        foreach( $words as $word ) {
            if ( strlen( $word ) > 4 ) {
                $keywords[] = strtoupper( sanitize_text_field( $word ) );
            }
        }
    }
    
    // If no strong tags were found, fallback to randomized contextual keywords
    if ( empty( $keywords ) ) {
        $keywords = ['ISOCHRONIC', 'MODULATION', 'SYNTHESIS', 'ELEVATION', 'HARMONIC', 'REVERB'];
    }

    // Filter duplicates and return 4-6 random extracted words
    $keywords = array_unique( $keywords );
    shuffle( $keywords );
    $final_words = array_slice( $keywords, 0, mt_rand(4, 6) );

    return rest_ensure_response( [
        'success'  => true,
        'keywords' => $final_words
    ]);
}
