<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// ==========================================
// HELPER UTILITY: GET ASSET DIRECTORY
// ==========================================
if (!function_exists('qrq_get_base_asset_dir')) {
    function qrq_get_base_asset_dir() {
        $dir = wp_normalize_path(wp_upload_dir()['basedir'] . '/qrq_radio_assets');
        if (!file_exists($dir)) { 
            wp_mkdir_p($dir); 
            file_put_contents($dir . '/.htaccess', "Options -Indexes\n"); 
        }
        return $dir;
    }
}

// ==========================================
// 1. SECURE AJAX PROXY FOR ICECAST JSON
// ==========================================
add_action('wp_ajax_qrq_get_meta', 'qrq_ajax_get_meta');
add_action('wp_ajax_nopriv_qrq_get_meta', 'qrq_ajax_get_meta');
function qrq_ajax_get_meta() {
    $json_url = get_option('qrq_icecast_json_url', 'https://qrjournal.org/status-json.xsl');
    if (empty($json_url)) { wp_send_json_error('No JSON URL configured.'); }
    
    $response = wp_remote_get($json_url, ['timeout' => 5, 'sslverify' => false]);
    if (is_wp_error($response)) { wp_send_json_error($response->get_error_message()); }
    
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    $custom_meta = [];
    $base_dir = qrq_get_base_asset_dir();
    $channels = array_filter(glob($base_dir . '/*'), 'is_dir');
    foreach($channels as $c) {
        $m_file = $c . '/metadata.json';
        if(file_exists($m_file)) {
            $custom_meta[basename($c)] = json_decode(file_get_contents($m_file), true);
        }
    }
    
    $data['custom_meta'] = $custom_meta;
    header('Access-Control-Allow-Origin: *');
    wp_send_json_success($data);
}

// ==========================================
// 2. QUERY AVAILABLE GEMINI MODELS (DYNAMIC)
// ==========================================
add_action('wp_ajax_qrq_get_ai_models', 'qrq_ajax_get_ai_models');
add_action('wp_ajax_nopriv_qrq_get_ai_models', 'qrq_ajax_get_ai_models');
function qrq_ajax_get_ai_models() {
    $api_key = trim(get_option('melle_vr_gemini_api_key', ''));
    if (empty($api_key)) {
        $api_key = trim(get_option('blueprint_gemini_api_key', ''));
    }

    if (empty($api_key)) {
        wp_send_json_error('Gemini API Key missing. Please configure in settings.');
    }

    $endpoint = "https://generativelanguage.googleapis.com/v1beta/models?key=" . rawurlencode($api_key);
    $response = wp_remote_get($endpoint, ['timeout' => 15, 'sslverify' => false]);
    
    if (is_wp_error($response)) {
        wp_send_json_error($response->get_error_message());
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);
    $models = [];
    
    if (isset($body['models'])) {
        foreach ($body['models'] as $model) {
            $methods = $model['supportedGenerationMethods'] ?? [];
            $name_lower = strtolower($model['name']);
            $version_lower = strtolower($model['version'] ?? '');
            
            if (in_array('generateContent', $methods)) {
                $is_stable = (strpos($name_lower, 'preview') === false && 
                              strpos($name_lower, 'experimental') === false && 
                              strpos($version_lower, 'preview') === false);
                
                if ($is_stable) {
                    $models[] = [
                        'id'   => $model['name'],
                        'name' => $model['displayName'] . ' (' . ($model['version'] ?? 'stable') . ')'
                    ];
                }
            }
        }
    }
    
    if (!empty(get_option('qrq_openai_api_key'))) {
        $models[] = [
            'id'   => 'gpt',
            'name' => 'OpenAI GPT-4o (Fallback)'
        ];
    }
    
    wp_send_json_success($models);
}

// ==========================================
// 3. CORE AI DISPATCHER (TEXT ONLY)
// ==========================================
function qrq_ai_text_post($engine, $prompt) {
    $headers = ['Content-Type: application/json'];
    $payload = [];
    $endpoint = '';

    if ($engine === 'gpt') {
        $api_key = trim(get_option('qrq_openai_api_key', ''));
        if (empty($api_key)) return ['error' => 'OpenAI Key Missing. Configure in Core Settings.'];
        
        $endpoint = 'https://api.openai.com/v1/chat/completions';
        $headers[] = 'Authorization: Bearer ' . $api_key;
        $payload = [
            "model" => "gpt-4o",
            "messages" => [ ["role" => "user", "content" => $prompt] ]
        ];
    } else {
        $api_key = trim(get_option('melle_vr_gemini_api_key', ''));
        if (empty($api_key)) return ['error' => 'Gemini Key Missing. Configure in Core Settings.'];

        $model = (strpos($engine, 'models/') === 0) ? $engine : 'models/gemini-2.5-flash';
        $model = ltrim($model, '/');
        
        $endpoint = "https://generativelanguage.googleapis.com/v1beta/" . $model . ":generateContent?key=" . rawurlencode($api_key);
        $payload = [
            "contents" => [[ "parts" => [ ["text" => $prompt] ] ]]
        ];
    }

    $json_payload = wp_json_encode($payload);
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $json_payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);

    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        $error = curl_error($ch);
        curl_close($ch);
        return ['error' => 'cURL Error: ' . $error];
    }
    curl_close($ch);

    $body = json_decode($response, true);
    
    if ($engine === 'gpt') {
        return isset($body['choices'][0]['message']['content']) ? ['text' => $body['choices'][0]['message']['content']] : ['error' => $body['error']['message'] ?? 'GPT Error'];
    } else {
        return isset($body['candidates'][0]['content']['parts'][0]['text']) ? ['text' => $body['candidates'][0]['content']['parts'][0]['text']] : ['error' => $body['error']['message'] ?? 'Gemini Error'];
    }
}

// ==========================================
// 4. METADATA & PLAYLIST MANAGEMENT HOOKS
// ==========================================
add_action('wp_ajax_qrq_save_metadata', 'qrq_ajax_save_metadata');
function qrq_ajax_save_metadata() {
    if (!current_user_can('manage_options') || !check_ajax_referer('qrq_manage_assets', 'nonce', false)) {
        wp_send_json_error('Unauthorized or expired session.');
    }

    $channel = sanitize_file_name($_POST['channel']);
    $filename = sanitize_file_name($_POST['filename']);
    
    if (empty($channel) || empty($filename)) wp_send_json_error('Missing file parameters.');

    $base_dir = qrq_get_base_asset_dir();
    $meta_file = $base_dir . '/' . $channel . '/metadata.json';
    $meta_data = file_exists($meta_file) ? json_decode(file_get_contents($meta_file), true) : [];

    $meta_data[$filename]['title'] = sanitize_text_field($_POST['title'] ?? '');
    $meta_data[$filename]['artist'] = sanitize_text_field($_POST['artist'] ?? '');
    $meta_data[$filename]['album'] = sanitize_text_field($_POST['album'] ?? '');
    $meta_data[$filename]['publish_date'] = sanitize_text_field($_POST['publish_date'] ?? '');
    $meta_data[$filename]['video'] = esc_url_raw($_POST['video'] ?? '');
    $meta_data[$filename]['notes'] = sanitize_textarea_field(wp_unslash($_POST['notes'] ?? ''));
    $meta_data[$filename]['ai_insight'] = sanitize_textarea_field(wp_unslash($_POST['ai_insight'] ?? ''));
    $meta_data[$filename]['product_id'] = sanitize_text_field($_POST['product_id'] ?? '');
    
    $meta_data[$filename]['show_notes'] = isset($_POST['show_notes']) ? true : false;
    $meta_data[$filename]['show_video'] = isset($_POST['show_video']) ? true : false;
    $meta_data[$filename]['is_public'] = isset($_POST['is_public']) ? true : false;

    if (file_put_contents($meta_file, json_encode($meta_data, JSON_PRETTY_PRINT))) {
        wp_send_json_success('Metadata successfully bound to track.');
    } else {
        wp_send_json_error('Server denied write permission. Check directory ownership.');
    }
}

// ==========================================
// 5. AUDIO UPLOAD & INSIGHT TRANSCRIPTION
// ==========================================
add_action('wp_ajax_qrq_generate_ai_insight', 'qrq_ajax_generate_ai_insight');
function qrq_ajax_generate_ai_insight() {
    if (!current_user_can('manage_options') && !check_ajax_referer('qrq_collab_action', 'nonce', false)) {
        if (!is_user_logged_in()) wp_send_json_error('Unauthorized.');
    }

    $channel = sanitize_file_name($_POST['channel']);
    $filename = sanitize_file_name($_POST['filename']);
    $engine = sanitize_text_field($_POST['ai_engine'] ?? 'models/gemini-2.5-flash');
    
    $api_key = get_option('melle_vr_gemini_api_key');
    if (empty($api_key)) wp_send_json_error('Gemini API Key missing. Please set it in Core Settings.');

    $base_dir = qrq_get_base_asset_dir();
    $file_path = $base_dir . '/' . $channel . '/' . $filename;
    
    if (!file_exists($file_path)) {
        wp_send_json_error('Audio file not found on server at: /' . $channel . '/' . $filename);
    }

    $meta_file = $base_dir . '/' . $channel . '/metadata.json';
    $meta_data = file_exists($meta_file) ? json_decode(file_get_contents($meta_file), true) : [];
    $custom_dj_context = isset($meta_data[$filename]['prompt']) ? $meta_data[$filename]['prompt'] : '';

    $file_size = filesize($file_path);
    $mime_type = 'audio/mpeg';

    $init_url = "https://generativelanguage.googleapis.com/upload/v1beta/files?key={$api_key}";
    $init_args = [
        'headers' => [
            'X-Goog-Upload-Protocol'              => 'resumable',
            'X-Goog-Upload-Command'               => 'start',
            'X-Goog-Upload-Header-Content-Length' => $file_size,
            'X-Goog-Upload-Header-Content-Type'   => $mime_type,
            'Content-Type'                        => 'application/json'
        ],
        'body'    => json_encode(['file' => ['display_name' => $filename]]),
        'timeout' => 30
    ];

    $init_response = wp_remote_post($init_url, $init_args);
    if (is_wp_error($init_response)) wp_send_json_error('Failed to initialize upload: ' . $init_response->get_error_message());

    $upload_url = wp_remote_retrieve_header($init_response, 'x-goog-upload-url');
    if (empty($upload_url)) wp_send_json_error('Gemini API rejected the upload request.');

    $upload_args = [
        'headers' => [
            'X-Goog-Upload-Protocol' => 'resumable',
            'X-Goog-Upload-Command'  => 'upload, finalize',
            'X-Goog-Upload-Offset'   => '0',
            'Content-Length'         => $file_size,
        ],
        'body'    => file_get_contents($file_path),
        'timeout' => 120
    ];

    $upload_response = wp_remote_post($upload_url, $upload_args);
    if (is_wp_error($upload_response)) wp_send_json_error('Failed to upload audio bytes: ' . $upload_response->get_error_message());

    $upload_body = json_decode(wp_remote_retrieve_body($upload_response), true);
    $file_uri = $upload_body['file']['uri'] ?? '';
    $gemini_file_name = $upload_body['file']['name'] ?? '';

    if (empty($file_uri)) {
        wp_send_json_error('Failed to retrieve Gemini File URI. API response: ' . wp_remote_retrieve_body($upload_response));
    }

    $prompt = "You are an AI named Maia serving as a cosmic music reviewer and AI DJ. Listen to this uploaded audio track.\n\n";
    if (!empty($custom_dj_context)) { $prompt .= "Additional Creator Context: \"{$custom_dj_context}\"\n\n"; }
    $prompt .= "Task 1: Provide a highly engaging, 2-paragraph insight about its spatial energy, mood, and narrative potential.\n";
    $prompt .= "Task 2: Transcribe the exact word-for-word lyrics of this song. If there are no lyrics, state that it is an instrumental.\n";
    $prompt .= "Format: Provide your 2-paragraph insight first, followed by a 'LYRICS:' section. Do not use markdown blocks, just raw text.";

    $model = (strpos($engine, 'models/') === 0) ? $engine : 'models/gemini-2.5-flash';
    $model = ltrim($model, '/');
    
    $gen_url = "https://generativelanguage.googleapis.com/v1beta/" . $model . ":generateContent?key={$api_key}";
    $gen_args = [
        'headers' => ['Content-Type' => 'application/json'],
        'body'    => json_encode([
            'contents' => [
                [
                    'parts' => [
                        ['fileData' => ['fileUri' => $file_uri, 'mimeType' => $mime_type]],
                        ['text' => $prompt]
                    ]
                ]
            ]
        ]),
        'timeout' => 120
    ];

    $gen_response = wp_remote_post($gen_url, $gen_args);

    if (!empty($gemini_file_name)) {
        wp_remote_request("https://generativelanguage.googleapis.com/v1beta/{$gemini_file_name}?key={$api_key}", ['method' => 'DELETE']);
    }

    if (is_wp_error($gen_response)) wp_send_json_error('Gemini processing failed: ' . $gen_response->get_error_message());

    $gen_body = json_decode(wp_remote_retrieve_body($gen_response), true);
    $ai_text = $gen_body['candidates'][0]['content']['parts'][0]['text'] ?? '';

    if (empty($ai_text)) {
        $err = $gen_body['error']['message'] ?? 'Unknown error';
        wp_send_json_error('Gemini returned an empty response. Details: ' . $err);
    }

    wp_send_json_success(['text' => trim($ai_text)]);
}

// ==========================================
// 6. PLAYLIST HOOKS
// ==========================================
add_action('wp_ajax_qrq_update_playlist_order', 'qrq_ajax_update_playlist_order');
function qrq_ajax_update_playlist_order() {
    if (!current_user_can('manage_options') || !check_ajax_referer('qrq_manage_assets', 'nonce', false)) {
        wp_send_json_error('Unauthorized.');
    }

    $channel = sanitize_file_name($_POST['channel']);
    $files = isset($_POST['files']) ? array_map('sanitize_file_name', $_POST['files']) : [];
    
    if (empty($channel) || empty($files)) wp_send_json_error('Missing parameters.');

    $base_dir = qrq_get_base_asset_dir();
    $playlist_file = $base_dir . '/' . $channel . '/playlist.txt';
    
    $playlist_content = "";
    foreach($files as $file) {
        $playlist_content .= $base_dir . '/' . $channel . '/' . $file . "\n";
    }
    
    if (file_put_contents($playlist_file, $playlist_content)) {
        wp_send_json_success('Playlist arrangement synchronized.');
    } else {
        wp_send_json_error('Failed to write to playlist.txt. Check folder permissions.');
    }
}

add_action('wp_ajax_qrq_add_collab_note', 'qrq_ajax_add_collab_note');
function qrq_ajax_add_collab_note() {
    if (!is_user_logged_in() || !check_ajax_referer('qrq_collab_action', 'nonce', false)) wp_send_json_error('Unauthorized');
    
    $channel = sanitize_title($_POST['channel']);
    $filename = sanitize_file_name($_POST['filename']);
    $note = sanitize_textarea_field(wp_unslash($_POST['note']));
    $user = wp_get_current_user();

    $meta_file = qrq_get_base_asset_dir() . '/' . $channel . '/metadata.json';
    $meta_data = file_exists($meta_file) ? json_decode(file_get_contents($meta_file), true) : [];
    
    $new_entry = [
        'user_id' => $user->ID, 
        'user'    => $user->display_name, 
        'time'    => current_time('mysql'), 
        'note'    => $note
    ];
    
    $meta_data[$filename]['collab_notes'][] = $new_entry;
    
    if (file_put_contents($meta_file, json_encode($meta_data, JSON_PRETTY_PRINT))) {
        wp_send_json_success($new_entry);
    } else {
        wp_send_json_error('Server failed to commit observation.');
    }
}


// ==========================================
// 7. PUSH NOTIFICATIONS API DISPATCHER
// ==========================================
function qrq_send_push_notification($channel) {
    $pn_api_key = get_option('qrq_push_api_key', '');
    $pn_app_id  = get_option('qrq_push_app_id', '');
    
    if (empty($pn_api_key) || empty($pn_app_id)) return false;

    // The Deep Link that drops them directly into the active channel
    $deep_link = home_url('/vr-suite/?channel=' . $channel);
    $channel_display = ucfirst(str_replace('-', ' ', $channel));

    $payload = [
        'app_id'   => $pn_app_id,
        'title'    => 'A Pioneer entered the Void! 血',
        'body'     => "Someone just connected to the {$channel_display} frequency. Tap to join them in VR.",
        'url'      => $deep_link,
        'target'   => 'all' 
    ];

    $args = [
        'headers' => [
            'Authorization' => 'Bearer ' . $pn_api_key,
            'Content-Type'  => 'application/json'
        ],
        'body'    => json_encode($payload),
        'timeout' => 15
    ];

    $response = wp_remote_post('https://api.pushnotifications.io/v1/notifications', $args);
    return !is_wp_error($response);
}

// ==========================================
// 8. COLLAB HUB & OBSERVATION SYNC (0->1)
// ==========================================
add_action('wp_ajax_qrq_collab_sync', 'qrq_ajax_collab_sync');
function qrq_ajax_collab_sync() {
    if (!is_user_logged_in() || !check_ajax_referer('qrq_collab_action', 'nonce', false)) wp_send_json_error('Unauthorized');
    
    $channel = sanitize_title($_POST['channel']);
    $filename = sanitize_file_name($_POST['filename']);
    $user_id = get_current_user_id();
    $now = time();

    // 1. Partner Sync Logic
    $partner_id = get_user_meta($user_id, 'qrq_partner_id', true);
    $partner_id = $partner_id ? $partner_id : 0;
    update_user_meta($user_id, 'qrq_last_active', $now);
    $p_active = get_user_meta($partner_id, 'qrq_last_active', true);
    
    $pair_id = $partner_id ? min($user_id, $partner_id) . '_' . max($user_id, $partner_id) : $user_id;

    // --- ENTANGLEMENT VIEW LOGIC ---
    if (isset($_POST['view_state'])) {
        $new_view = sanitize_text_field($_POST['view_state']);
        if (in_array($new_view, ['global', 'pair'])) {
            update_option("qrq_pair_view_{$pair_id}_{$filename}", $new_view);
        }
    }
    $shared_view = get_option("qrq_pair_view_{$pair_id}_{$filename}", 'global');
    // ------------------------------
    
    // 2. Global Channel "0 to 1" Tracker
    $active_users = get_option('qrq_active_users_' . $channel, []);
    $was_empty = true;

    if (is_array($active_users)) {
        foreach($active_users as $uid => $last_ping) {
            if ($now - $last_ping > 30) {
                unset($active_users[$uid]);
            } else {
                $was_empty = false;
            }
        }
    } else {
        $active_users = [];
    }

    $active_users[$user_id] = $now;
    update_option('qrq_active_users_' . $channel, $active_users);

    // 3. Fire Push Notification if threshold met
    $push_enabled = get_option('qrq_push_enabled') == '1';
    if ($push_enabled && $was_empty && !get_transient('qrq_push_cooldown_' . $channel)) {
        qrq_send_push_notification($channel);
        set_transient('qrq_push_cooldown_' . $channel, true, 30 * MINUTE_IN_SECONDS);
    }

    // 4. Fetch Collab Notes & Insights Fingerprint
    $meta_file = qrq_get_base_asset_dir() . '/' . $channel . '/metadata.json';
    $meta_data = file_exists($meta_file) ? json_decode(file_get_contents($meta_file), true) : [];
    
    // Generate a fingerprint of current insights to detect remote updates
    $insight_payload = [
        'g' => $meta_data[$filename]['ai_insight'] ?? '',
        'p' => $meta_data[$filename]['pair_insights'][$pair_id]['current'] ?? ''
    ];
    $data_fingerprint = md5(json_encode($insight_payload));

    // NEW: Return ALL notes, tagged with is_pair so the frontend CSS can filter them seamlessly
    $all_notes = [];
    if (isset($meta_data[$filename]['collab_notes'])) {
        foreach($meta_data[$filename]['collab_notes'] as $n) {
            $n_uid = isset($n['user_id']) ? $n['user_id'] : 0;
            $is_pair = ($n_uid == $user_id || $n_uid == $partner_id);
            $all_notes[] = [
                'user'           => $n['user'], 
                'time_formatted' => date('M j, g:i A', strtotime($n['time'])), 
                'note_safe'      => nl2br(esc_html($n['note'])),
                'is_pair'        => $is_pair
            ];
        }
    }
    
    $partner_online = ($p_active && ($now - $p_active < 15));
    wp_send_json_success([
        'partner_online' => $partner_online, 
        'notes'          => $all_notes,
        'shared_view'    => $shared_view,
        'fingerprint'    => $data_fingerprint
    ]);
}

// ==========================================
// 9. FRONTEND PUSH END-USER UI & SDK
// ==========================================
add_action('wp_head', 'qrq_frontend_push_sdk');
function qrq_frontend_push_sdk() {
    $pn_app_id = get_option('qrq_push_app_id', '');
    $push_enabled = get_option('qrq_push_enabled') == '1';
    
    if (empty($pn_app_id) || !$push_enabled) return;
    ?>
    <script async src="https://cdn.pushnotifications.io/v1/sdk.js"></script>
    <script>
        window.QRQPushManager = {
            init: function() {
                if (typeof PushNotifications !== 'undefined') {
                    PushNotifications.init({ appId: '<?php echo esc_js($pn_app_id); ?>' });
                    this.updateUI();
                }
            },
            subscribe: function() {
                if ('Notification' in window) {
                    Notification.requestPermission().then(permission => {
                        if (permission === 'granted' && typeof PushNotifications !== 'undefined') {
                            PushNotifications.subscribe().then(() => this.updateUI());
                        }
                        this.updateUI();
                    });
                }
            },
            unsubscribe: function() {
                if (typeof PushNotifications !== 'undefined') {
                    PushNotifications.unsubscribe().then(() => {
                        this.updateUI();
                    });
                }
            },
            updateUI: function() {
                const statusText = document.getElementById('qrq-push-status-text');
                const toggleBtn = document.getElementById('qrq-push-toggle-btn');
                
                if (!statusText || !toggleBtn) return;

                if (Notification.permission === 'granted') {
                    statusText.innerHTML = '<span style="color:#28a745;">泙 Receiving Network Alerts</span>';
                    toggleBtn.textContent = 'Disable Notifications';
                    toggleBtn.onclick = () => this.unsubscribe();
                } else if (Notification.permission === 'denied') {
                    statusText.innerHTML = '<span style="color:#dc3545;">閥 Blocked by Browser</span>';
                    toggleBtn.textContent = 'Unblock in Browser Settings';
                    toggleBtn.disabled = true;
                } else {
                    statusText.innerHTML = '<span style="color:#ffc107;">泯 Not Subscribed</span>';
                    toggleBtn.textContent = 'Enable Notifications';
                    toggleBtn.disabled = false;
                    toggleBtn.onclick = () => this.subscribe();
                }
            }
        };

        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(() => window.QRQPushManager.init(), 1000);
        });
    </script>
    <?php
}

add_shortcode('melle_push_settings', 'qrq_push_settings_shortcode');
function qrq_push_settings_shortcode() {
    if (get_option('qrq_push_enabled') != '1') return '';
    
    ob_start();
    ?>
    <div class="qrq-push-manager-widget" style="padding: 20px; border: 1px solid #e2e4e7; border-radius: 8px; background: #fff; max-width: 400px; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
        <h4 style="margin-top: 0; margin-bottom: 10px; color: #1d2327;">Network Frequencies</h4>
        <p style="font-size: 14px; color: #50575e; margin-bottom: 15px;">Receive a gentle alert when a Pioneer enters an empty frequency.</p>
        <div id="qrq-push-status-text" style="font-weight: bold; margin-bottom: 15px; font-size: 14px;">
            <span style="color:#888;">竢ｳ Checking browser status...</span>
        </div>
        <button id="qrq-push-toggle-btn" style="padding: 10px 20px; background: #2271b1; color: #fff; border: none; border-radius: 4px; cursor: pointer; font-weight: 600; width: 100%;">
            Loading...
        </button>
    </div>
    <script>
        if (window.QRQPushManager) window.QRQPushManager.updateUI();
    </script>
    <?php
    return ob_get_clean();
}

// ==========================================
// 10. NUCLEAR FOUC PREVENTER (LOADING CURTAIN)
// ==========================================
add_action('wp_head', 'qrq_vr_loading_curtain_css', 1);
function qrq_vr_loading_curtain_css() {
    $uri = $_SERVER['REQUEST_URI'];
    $is_target_page = (strpos($uri, '/vr-suite') !== false || $uri === '/' || $uri === '' || is_front_page());
    
    if (!$is_target_page) return;
    ?>
    <style id="qrq-nuclear-fouc-blocker">
        html, body {
            background-color: #050505 !important;
            overflow: hidden !important; 
            visibility: hidden !important; 
        }
        
        #qrq-vr-curtain {
            visibility: visible !important; 
            position: fixed;
            top: 0; left: 0;
            width: 100vw; height: 100vh;
            background-color: #050505;
            z-index: 99999999;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            transition: opacity 0.8s ease-out;
            color: #00ffcc;
            font-family: monospace;
        }

        .qrq-spinner {
            width: 50px; height: 50px;
            border: 3px solid rgba(0, 255, 204, 0.3);
            border-radius: 50%;
            border-top-color: #00ffcc;
            animation: qrq-spin 1s ease-in-out infinite;
            margin-bottom: 20px;
        }

        @keyframes qrq-spin { to { transform: rotate(360deg); } }
    </style>
    <?php
}

add_action('wp_body_open', 'qrq_vr_loading_curtain_html', 1);
add_action('wp_footer', 'qrq_vr_loading_curtain_html', 1); 
function qrq_vr_loading_curtain_html() {
    $uri = $_SERVER['REQUEST_URI'];
    $is_target_page = (strpos($uri, '/vr-suite') !== false || $uri === '/' || $uri === '' || is_front_page());
    
    if (!$is_target_page) return;
    
    static $printed = false;
    if ($printed) return;
    $printed = true;
    ?>
    <div id="qrq-vr-curtain">
        <div class="qrq-spinner"></div>
        <h2 style="margin:0; font-size:1.2rem; letter-spacing: 2px; text-transform: uppercase;">Initializing System Link...</h2>
        <p style="color: #666; font-size: 0.9rem; margin-top: 10px;">Stabilizing spatial frequencies</p>
    </div>

    <script>
        document.body.style.setProperty('visibility', 'visible', 'important');
        window.addEventListener('load', function() {
            const curtain = document.getElementById('qrq-vr-curtain');
            if (curtain) {
                curtain.style.opacity = '0';
                setTimeout(() => {
                    curtain.style.display = 'none';
                    curtain.remove();
                    document.body.style.setProperty('overflow', 'auto', 'important');
                }, 800); 
            }
        });
    </script>
    <?php
}
