<?php
/**
 * Blueprint of the Cosmos - Core Engine
 * Merged into Quantum Telegraph v4.0
 * Features: Ultimate Member Integration, CPT Author Support, and Library Shortcodes.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class MelleVR_Blueprint_Core {

    public function __construct() {
        add_action( 'init', [ $this, 'register_blueprint_cpt' ] );
        add_action( 'add_meta_boxes', [ $this, 'add_blueprint_meta_boxes' ] );
        add_action( 'save_post_blueprint', [ $this, 'save_blueprint_meta_data' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts_styles' ] );
        add_filter( 'template_include', [ $this, 'load_blueprint_template' ], 99 );

        // Admin Settings & AJAX
        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
        add_action( 'admin_init', [ $this, 'settings_init' ] );
        
        // Story Generation Hooks
        add_action( 'wp_ajax_blueprint_generate_story', [ $this, 'handle_blueprint_generation' ] );
        add_action( 'wp_ajax_nopriv_blueprint_generate_story', [ $this, 'handle_blueprint_generation' ] );

        // Audio Script (Podcast) Generation Hooks
        add_action( 'wp_ajax_blueprint_generate_audio_script', [ $this, 'handle_audio_script_generation' ] );
        add_action( 'wp_ajax_nopriv_blueprint_generate_audio_script', [ $this, 'handle_audio_script_generation' ] );

        // Save Endpoints
        add_action( 'wp_ajax_blueprint_save_play', [ $this, 'handle_save_play' ] );
        add_action( 'wp_ajax_blueprint_save_wordcloud', [ $this, 'handle_save_wordcloud' ] );
        add_action( 'wp_ajax_blueprint_save_pdf_to_media', [ $this, 'handle_save_pdf_to_media' ] );

        // Anagram Generation Hooks for Rune Integration
        add_action( 'wp_ajax_blueprint_generate_words_from_runes', [ $this, 'handle_rune_word_generation' ] );
        add_action( 'wp_ajax_nopriv_blueprint_generate_words_from_runes', [ $this, 'handle_rune_word_generation' ] );

        // Shortcodes & Ultimate Member
        add_shortcode( 'blueprint_library', [ $this, 'render_blueprint_library_shortcode' ] );
        add_filter( 'um_profile_tabs', [ $this, 'add_um_blueprint_tab' ], 800 );
        add_action( 'um_profile_content_blueprints_default', [ $this, 'render_um_blueprint_tab_content' ] );
    }

    // ==========================================
    // 1. CPT & META BOXES
    // ==========================================
    public function register_blueprint_cpt() {
        $labels = [
            'name'                  => 'Blueprints',
            'singular_name'         => 'Blueprint',
            'menu_name'             => 'Blueprints',
            'add_new'               => 'Add New',
            'add_new_item'          => 'Add New Blueprint',
            'edit_item'             => 'Edit Blueprint',
            'view_item'             => 'View Blueprint',
            'all_items'             => 'All Blueprints',
        ];

        $args = [
            'labels'             => $labels,
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => true,
            'rewrite'            => [ 'slug' => 'blueprint' ],
            'capability_type'    => 'post',
            'has_archive'        => true,
            'hierarchical'       => false,
            'menu_position'      => null,
            'menu_icon'          => 'dashicons-admin-site-alt3',
            // ADDED 'author' and 'editor' to assign to users and save content natively
            'supports'           => [ 'title', 'editor', 'thumbnail', 'author', 'custom-fields' ],
        ];

        register_post_type( 'blueprint', $args );
    }

    public function add_blueprint_meta_boxes() {
        add_meta_box( 'blueprint_config_meta', 'Blueprint Configuration', [ $this, 'render_blueprint_meta_box' ], 'blueprint', 'normal', 'high' );
        add_meta_box( 'blueprint_visibility_meta', 'Network Visibility', [ $this, 'render_visibility_meta_box' ], 'blueprint', 'side', 'default' );
    }

    public function render_visibility_meta_box( $post ) {
        wp_nonce_field( 'blueprint_save_visibility', 'blueprint_visibility_nonce' );
        $is_public = get_post_meta( $post->ID, '_blueprint_is_public', true );
        ?>
        <p>
            <input type="checkbox" id="blueprint_is_public" name="blueprint_is_public" value="1" <?php checked($is_public, '1'); ?> />
            <label for="blueprint_is_public">Make this Codex Publicly Visible</label>
        </p>
        <p class="description">If unchecked, this will only appear in the author's private Ultimate Member profile.</p>
        <?php
    }

    public function render_blueprint_meta_box( $post ) {
        wp_nonce_field( 'blueprint_save_meta_box_data', 'blueprint_meta_box_nonce' );
        $config = get_post_meta( $post->ID, '_blueprint_config', true ) ?: [];
        
        $play_title = $config['play_title'] ?? 'Blueprint of the Cosmos';
        $glossary = $config['glossary'] ?? '[]';
        $inspirations = $config['inspirations'] ?? '[]';
        $options = $config['options'] ?? [];
        ?>
        <div style="display:flex; flex-direction:column; gap:15px;">
            <div>
                <label for="play_title"><strong>Play Title:</strong></label><br>
                <input type="text" id="play_title" name="play_title" value="<?php echo esc_attr( $play_title ); ?>" style="width:100%;" />
            </div>
            <div>
                <label for="glossary"><strong>Glossary (JSON format):</strong></label><br>
                <textarea id="glossary" name="glossary" rows="5" style="width:100%; font-family:monospace;"><?php echo esc_textarea( $glossary ); ?></textarea>
            </div>
            <div>
                <label for="inspirations"><strong>Inspirations (JSON format):</strong></label><br>
                <textarea id="inspirations" name="inspirations" rows="5" style="width:100%; font-family:monospace;"><?php echo esc_textarea( $inspirations ); ?></textarea>
            </div>
            <div>
                <label for="blueprint_dialogue_length"><strong>Dialogue Length:</strong></label><br>
                <select name="blueprint_dialogue_length" id="blueprint_dialogue_length">
                    <option value="standard" <?php selected( $options['dialogueLength'] ?? 'standard', 'standard' ); ?>>Standard</option>
                    <option value="expansive" <?php selected( $options['dialogueLength'] ?? 'standard', 'expansive' ); ?>>Expansive</option>
                </select>
            </div>
        </div>
        <?php
    }

    public function save_blueprint_meta_data( $post_id ) {
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        if ( ! current_user_can( 'edit_post', $post_id ) ) return;
        
        if ( isset( $_POST['blueprint_visibility_nonce'] ) && wp_verify_nonce( $_POST['blueprint_visibility_nonce'], 'blueprint_save_visibility' ) ) {
            $is_public = isset($_POST['blueprint_is_public']) ? '1' : '0';
            update_post_meta( $post_id, '_blueprint_is_public', $is_public );
        }

        if ( ! isset( $_POST['blueprint_meta_box_nonce'] ) || ! wp_verify_nonce( $_POST['blueprint_meta_box_nonce'], 'blueprint_save_meta_box_data' ) ) return;

        $config = [];
        $config['play_title'] = isset( $_POST['play_title'] ) ? sanitize_text_field( $_POST['play_title'] ) : 'Blueprint of the Cosmos';
        
        if ( isset( $_POST['glossary'] ) ) { 
            $glossary_json = wp_unslash( $_POST['glossary'] ); 
            if ( is_string( $glossary_json ) && is_array( json_decode( $glossary_json, true ) ) && ( json_last_error() === JSON_ERROR_NONE ) ) { 
                $config['glossary'] = $glossary_json; 
            } 
        }
        
        if ( isset( $_POST['inspirations'] ) ) { 
            $inspirations_json = wp_unslash( $_POST['inspirations'] ); 
            if ( is_string( $inspirations_json ) && is_array( json_decode( $inspirations_json, true ) ) && ( json_last_error() === JSON_ERROR_NONE ) ) { 
                $config['inspirations'] = $inspirations_json; 
            } 
        }

        $config['options']['dialogueLength'] = isset( $_POST['blueprint_dialogue_length'] ) ? sanitize_text_field( $_POST['blueprint_dialogue_length'] ) : 'standard';

        update_post_meta( $post_id, '_blueprint_config', $config );
    }

    // ==========================================
    // 2. ULTIMATE MEMBER INTEGRATION
    // ==========================================
    public function add_um_blueprint_tab( $tabs ) {
        $tabs['blueprints'] = [
            'name'   => 'My Codexes',
            'icon'   => 'um-faicon-dna',
            'custom' => true
        ];
        return $tabs;
    }

    public function render_um_blueprint_tab_content( $args ) {
        $user_id = um_profile_id();
        echo $this->get_user_blueprints_html( $user_id );
    }

    // ==========================================
    // 3. SHORTCODE: [blueprint_library]
    // ==========================================
    public function render_blueprint_library_shortcode( $atts ) {
        $a = shortcode_atts(['user_id' => get_current_user_id()], $atts);
        
        ob_start();
        ?>
        <style>
            :root {
                --bp-lib-bg: rgba(15, 15, 15, 0.95);
                --bp-lib-border: rgba(0, 242, 255, 0.3);
                --bp-lib-text: #ffffff;
            }
            html[data-theme="light"], body[data-theme="light"] {
                --bp-lib-bg: rgba(255, 255, 255, 0.95);
                --bp-lib-border: rgba(0, 141, 179, 0.3);
                --bp-lib-text: #111111;
            }
            .bp-lib-card {
                background: var(--bp-lib-bg) !important;
                color: var(--bp-lib-text) !important;
                border: 1px solid var(--bp-lib-border);
                border-radius: 12px;
                padding: 20px;
                backdrop-filter: blur(10px);
                transition: transform 0.3s ease, box-shadow 0.3s ease;
                height: 100%;
            }
            .bp-lib-card:hover {
                transform: translateY(-5px);
                box-shadow: 0 10px 25px rgba(0, 242, 255, 0.2);
            }
            .bp-lib-title {
                color: #00f2ff;
                font-weight: 800;
                margin-bottom: 10px;
                font-size: 1.2rem;
            }
            html[data-theme="light"] .bp-lib-title { color: #008db3; }
        </style>

        <div class="blueprint-library-container mt-4">
            
            <?php if ( is_user_logged_in() ) : ?>
                <h3 class="mb-4" style="color: #ff0055; font-weight: 900;"><i class="fas fa-fingerprint me-2"></i> Your Personal Codexes</h3>
                <?php echo $this->get_user_blueprints_html( get_current_user_id(), false ); ?>
                <hr class="my-5" style="border-color: var(--bp-lib-border);">
            <?php endif; ?>

            <h3 class="mb-4" style="color: #00f2ff; font-weight: 900;"><i class="fas fa-globe me-2"></i> Public Archives</h3>
            <div class="row g-4">
                <?php
                $public_query = new WP_Query([
                    'post_type'      => 'blueprint',
                    'post_status'    => 'publish',
                    'posts_per_page' => 12,
                    'meta_key'       => '_blueprint_is_public',
                    'meta_value'     => '1'
                ]);

                if ( $public_query->have_posts() ) {
                    while ( $public_query->have_posts() ) {
                        $public_query->the_post();
                        $this->render_single_blueprint_card( get_the_ID() );
                    }
                    wp_reset_postdata();
                } else {
                    echo '<div class="col-12"><div class="alert alert-info bg-transparent border-info text-info">No public codexes have been published to the network yet.</div></div>';
                }
                ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    private function get_user_blueprints_html( $user_id, $show_empty_msg = true ) {
        $query = new WP_Query([
            'post_type'      => 'blueprint',
            'post_status'    => 'publish',
            'author'         => $user_id,
            'posts_per_page' => -1
        ]);

        ob_start();
        echo '<div class="row g-4">';
        if ( $query->have_posts() ) {
            while ( $query->have_posts() ) {
                $query->the_post();
                $this->render_single_blueprint_card( get_the_ID() );
            }
            wp_reset_postdata();
        } else {
            if ($show_empty_msg) {
                echo '<div class="col-12"><div class="alert alert-warning bg-transparent border-warning text-warning">This Pioneer has not generated any neural codexes yet.</div></div>';
            }
        }
        echo '</div>';
        return ob_get_clean();
    }

    private function render_single_blueprint_card( $post_id ) {
        $is_public = get_post_meta( $post_id, '_blueprint_is_public', true );
        $author_id = get_post_field( 'post_author', $post_id );
        $author_name = get_the_author_meta( 'display_name', $author_id );
        $pdf_url = get_post_meta( $post_id, '_blueprint_pdf_url', true );
        ?>
        <div class="col-md-6 col-lg-4">
            <div class="bp-lib-card d-flex flex-column">
                <div class="bp-lib-title"><?php echo get_the_title( $post_id ); ?></div>
                <div class="small mb-3" style="color: var(--bp-lib-text); opacity: 0.7;">
                    <i class="fas fa-user-astronaut me-1"></i> Generated by <?php echo esc_html( $author_name ); ?><br>
                    <i class="fas fa-clock me-1"></i> <?php echo get_the_date( '', $post_id ); ?>
                </div>
                
                <div class="mt-auto pt-3 border-top" style="border-color: var(--bp-lib-border);">
                    <div class="d-flex justify-content-between align-items-center">
                        <?php if ( $is_public == '1' ) : ?>
                            <span class="badge bg-info text-dark">Publicly Visible</span>
                        <?php else : ?>
                            <span class="badge bg-danger text-white"><i class="fas fa-lock"></i> Private</span>
                        <?php endif; ?>
                        
                        <div>
                            <a href="<?php echo get_permalink( $post_id ); ?>" class="btn btn-sm btn-outline-info rounded-pill">View Core</a>
                            <?php if ( $pdf_url ) : ?>
                                <a href="<?php echo esc_url( $pdf_url ); ?>" target="_blank" class="btn btn-sm btn-info text-dark rounded-pill ms-1" title="Download PDF Codex"><i class="fas fa-file-pdf"></i></a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    // ==========================================
    // 4. AJAX ENDPOINTS (INCL. PDF SAVING)
    // ==========================================
    public function handle_save_pdf_to_media() {
        if ( ! is_user_logged_in() ) wp_send_json_error( 'Must be logged in to save codexes.' );
        
        if ( empty( $_FILES['pdf_file'] ) ) wp_send_json_error( 'No PDF file detected.' );

        require_once( ABSPATH . 'wp-admin/includes/image.php' );
        require_once( ABSPATH . 'wp-admin/includes/file.php' );
        require_once( ABSPATH . 'wp-admin/includes/media.php' );

        $attachment_id = media_handle_upload( 'pdf_file', 0 );

        if ( is_wp_error( $attachment_id ) ) {
            wp_send_json_error( $attachment_id->get_error_message() );
        }

        $pdf_url = wp_get_attachment_url( $attachment_id );
        
        // If a post ID was passed, attach this PDF URL to it
        $post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
        if ( $post_id ) {
            update_post_meta( $post_id, '_blueprint_pdf_url', $pdf_url );
        }

        wp_send_json_success( [ 'url' => $pdf_url, 'attachment_id' => $attachment_id ] );
    }

    public function handle_save_play() {
        if ( ! is_user_logged_in() ) wp_send_json_error( 'Must be logged in to save codexes.' );

        $post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
        $play_content = isset( $_POST['play_content'] ) ? wp_kses_post( wp_unslash( $_POST['play_content'] ) ) : '';
        $play_title = isset( $_POST['play_title'] ) ? sanitize_text_field( $_POST['play_title'] ) : 'Untitled Codex';

        if ( empty( $play_content ) ) wp_send_json_error( 'No content provided.' );

        if ( $post_id ) {
            // Update existing post
            $result = wp_update_post([
                'ID'           => $post_id,
                'post_content' => $play_content
            ]);
        } else {
            // Create NEW post assigned to current user
            $result = wp_insert_post([
                'post_type'    => 'blueprint',
                'post_title'   => $play_title . ' - ' . date('Y-m-d'),
                'post_content' => $play_content,
                'post_status'  => 'publish',
                'post_author'  => get_current_user_id()
            ]);

            // Default to private so they don't accidentally leak their stuff
            if ( ! is_wp_error( $result ) ) {
                update_post_meta( $result, '_blueprint_is_public', '0' );
            }
        }

        if ( is_wp_error( $result ) ) wp_send_json_error( $result->get_error_message() );

        wp_send_json_success( [ 'message' => 'Play Codex written to neural storage.', 'post_id' => $result ] );
    }

    public function enqueue_scripts_styles() {
        if ( is_singular( 'blueprint' ) ) {
            wp_enqueue_style( 'bootstrap-css', 'https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css', [], '5.3.0' );
            wp_enqueue_style( 'blueprint-main-style', MELLE_VR_URL . 'assets/css/blueprint-style.css', [], time() );
            
            wp_enqueue_script( 'bootstrap-bundle', 'https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js', [], '5.3.0', true );
            
            wp_enqueue_script( 'dayjs', 'https://cdnjs.cloudflare.com/ajax/libs/dayjs/1.11.10/dayjs.min.js', [], '1.11.10', true );
            wp_enqueue_script( 'dayjs-utc', 'https://cdnjs.cloudflare.com/ajax/libs/dayjs/1.11.10/plugin/utc.min.js', ['dayjs'], '1.11.10', true );
            wp_enqueue_script( 'dayjs-timezone', 'https://cdnjs.cloudflare.com/ajax/libs/dayjs/1.11.10/plugin/timezone.min.js', ['dayjs', 'dayjs-utc'], '1.11.10', true );
            
            wp_add_inline_script( 'dayjs-timezone', 'dayjs.extend(window.dayjs_plugin_utc); dayjs.extend(window.dayjs_plugin_timezone);' );
            
            wp_enqueue_script( 'blueprint-main-script', MELLE_VR_URL . 'assets/js/blueprint-main.js', ['jquery', 'bootstrap-bundle', 'dayjs', 'dayjs-utc', 'dayjs-timezone'], rand(10000, 99999), true );

            wp_localize_script( 'blueprint-main-script', 'blueprintAjax', [
                'ajaxurl' => admin_url( 'admin-ajax.php' )
            ]);
        }
    }

    public function load_blueprint_template( $template ) {
        if ( is_singular( 'blueprint' ) ) {
            $plugin_template = MELLE_VR_PATH . 'includes/blueprint-template.php';
            if ( file_exists( $plugin_template ) ) {
                return $plugin_template;
            }
        }
        return $template;
    }

    public function add_admin_menu() {
        add_submenu_page( 'edit.php?post_type=blueprint', 'Blueprint Settings', 'Settings', 'manage_options', 'blueprint-settings', [ $this, 'settings_page_html' ] );
    }

    public function settings_init() {
        register_setting( 'blueprint_settings_group', 'blueprint_gemini_api_key' );
        add_settings_section( 'blueprint_api_section', 'API Configuration', null, 'blueprint-settings' );
        add_settings_field( 'blueprint_gemini_api_key', 'Gemini API Key', [ $this, 'render_api_key_field' ], 'blueprint-settings', 'blueprint_api_section' );
    }

    public function render_api_key_field() {
        $api_key = get_option( 'blueprint_gemini_api_key' );
        echo '<input type="password" name="blueprint_gemini_api_key" value="' . esc_attr( $api_key ) . '" size="50" />';
        echo '<p class="description">Required for AI Story Generation.</p>';
    }

    public function settings_page_html() {
        if ( ! current_user_can( 'manage_options' ) ) return;
        ?>
        <div class="wrap">
            <h1>Quantum Telegraph: Blueprint Settings</h1>
            <form action="options.php" method="post">
                <?php
                settings_fields( 'blueprint_settings_group' );
                do_settings_sections( 'blueprint-settings' );
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    public function handle_blueprint_generation() {
        $api_key = get_option( 'blueprint_gemini_api_key' );
        if ( empty( $api_key ) ) $api_key = get_option( 'melle_vr_gemini_api_key' ); 

        if ( empty( $api_key ) ) wp_send_json_error( 'API Key missing in settings.' );

        $prompt = isset( $_POST['prompt'] ) ? sanitize_textarea_field( wp_unslash( $_POST['prompt'] ) ) : '';
        if ( empty( $prompt ) ) wp_send_json_error( 'No prompt provided.' );

        $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key={$api_key}";
        
        $response = wp_remote_post( $endpoint, [
            'headers' => [ 'Content-Type' => 'application/json' ],
            'body'    => json_encode([ 'contents' => [ [ 'parts' => [ [ 'text' => $prompt ] ] ] ] ]),
            'timeout' => 45
        ]);

        if ( is_wp_error( $response ) ) wp_send_json_error( $response->get_error_message() );

        $response_code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );
        $decoded_body = json_decode( $body, true );

        if ( $response_code !== 200 ) {
            $error_msg = isset($decoded_body['error']['message']) ? $decoded_body['error']['message'] : 'API Error ' . $response_code;
            wp_send_json_error( $error_msg );
        }

        $ai_text = isset( $decoded_body['candidates'][0]['content']['parts'][0]['text'] ) ? $decoded_body['candidates'][0]['content']['parts'][0]['text'] : '';
        if ( empty( $ai_text ) ) wp_send_json_error( 'Gemini returned an empty response.' );

        wp_send_json_success( [ 'story' => $ai_text ] );
    }

    public function handle_audio_script_generation() {
        $api_key = get_option( 'blueprint_gemini_api_key' );
        if ( empty( $api_key ) ) $api_key = get_option( 'melle_vr_gemini_api_key' ); 

        if ( empty( $api_key ) ) wp_send_json_error( 'API Key missing in settings.' );

        $play_text = isset( $_POST['play_text'] ) ? sanitize_textarea_field( wp_unslash( $_POST['play_text'] ) ) : '';
        if ( empty( $play_text ) ) wp_send_json_error( 'No play text provided.' );

        $system_prompt = "You are an AI system that generates podcast scripts. Read the provided play. Generate a conversational, highly engaging deep-dive between two hosts, Alex and Sam. They should discuss the play's themes, cosmic implications, and the neural/spatial context. The tone should be fascinated and slightly philosophical. Return the output STRICTLY as a JSON array of objects, where each object has a 'speaker' key (either 'Alex' or 'Sam') and a 'text' key containing their spoken line. Do not use markdown blocks. Return only raw JSON.";

        $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key={$api_key}";
        
        $request_body = [
            'systemInstruction' => [ 'parts' => [ [ 'text' => $system_prompt ] ] ],
            'contents' => [ [ 'parts' => [ [ 'text' => "Here is the play: \n\n" . $play_text ] ] ] ],
            'generationConfig' => [ 'responseMimeType' => 'application/json' ]
        ];

        $response = wp_remote_post( $endpoint, [
            'headers' => [ 'Content-Type' => 'application/json' ],
            'body'    => json_encode( $request_body ),
            'timeout' => 60 
        ]);

        if ( is_wp_error( $response ) ) wp_send_json_error( $response->get_error_message() );

        $response_code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );
        $decoded_body = json_decode( $body, true );

        if ( $response_code !== 200 ) {
            $error_msg = isset($decoded_body['error']['message']) ? $decoded_body['error']['message'] : 'API Error ' . $response_code;
            wp_send_json_error( $error_msg );
        }

        $ai_text = isset( $decoded_body['candidates'][0]['content']['parts'][0]['text'] ) ? $decoded_body['candidates'][0]['content']['parts'][0]['text'] : '';
        if ( empty( $ai_text ) ) wp_send_json_error( 'Gemini returned an empty response.' );

        $audio_script = json_decode( $ai_text, true );
        if ( json_last_error() !== JSON_ERROR_NONE ) wp_send_json_error( 'Gemini failed to return valid JSON. Output was: ' . $ai_text );

        wp_send_json_success( [ 'script' => $audio_script ] );
    }

    public function handle_rune_word_generation() {
        $api_key = get_option( 'blueprint_gemini_api_key' );
        if ( empty( $api_key ) ) $api_key = get_option( 'melle_vr_gemini_api_key' ); 
        
        $letters = isset( $_POST['letters'] ) ? sanitize_text_field( $_POST['letters'] ) : '';

        if ( empty( $api_key ) || empty( $letters ) ) wp_send_json_error( 'Neural link disconnected: Missing API key or letters.' );

        $prompt = "You are an anagram engine. Using only the letters provided here: '{$letters}', generate a list of 10 valid English words that can be spelled using ONLY a combination of these exact letters. Respond ONLY with a raw JSON array of strings, without any markdown formatting or code blocks. Example: [\"word\", \"door\", \"row\"]";

        $gen_url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key={$api_key}";
        
        $gen_args = [
            'headers' => [ 'Content-Type' => 'application/json' ],
            'body'    => json_encode( [ 'contents' => [ [ 'parts' => [ [ 'text' => $prompt ] ] ] ] ] ),
            'timeout' => 30
        ];

        $response = wp_remote_post( $gen_url, $gen_args );
        
        if ( is_wp_error( $response ) ) wp_send_json_error( 'Gemini API connection failed.' );

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        $ai_text = isset($body['candidates'][0]['content']['parts'][0]['text']) ? $body['candidates'][0]['content']['parts'][0]['text'] : '[]';
        
        $ai_text = trim( str_replace( ['```json', '```'], '', $ai_text ) );
        $decoded_words = json_decode( $ai_text, true );

        if ( json_last_error() !== JSON_ERROR_NONE || !is_array($decoded_words) ) wp_send_json_error( 'Failed to parse neural response into valid sequence.' );
        
        wp_send_json_success( $decoded_words );
    }

    public function handle_save_wordcloud() {
        $post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
        $wordcloud_data = isset( $_POST['wordcloud_data'] ) ? sanitize_text_field( wp_unslash( $_POST['wordcloud_data'] ) ) : '';

        if ( ! $post_id || empty( $wordcloud_data ) ) wp_send_json_error( 'Invalid data provided.' );

        $result = update_post_meta( $post_id, '_blueprint_wordcloud_data', $wordcloud_data );

        if ( $result !== false ) {
            wp_send_json_success( 'Word cloud parameters synchronized.' );
        } else {
            wp_send_json_error( 'Failed to save word cloud data.' );
        }
    }
}

new MelleVR_Blueprint_Core();
