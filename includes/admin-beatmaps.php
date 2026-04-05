<?php
/**
 * Admin Beatmap Manager - Quantum Telegraph v4.0
 * Features: Resumable Uploads, Gemini 2.5 Pro Integration, Audio/PDF Transcription.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_action('admin_menu', 'melle_vr_beatmap_menu');
add_action('admin_enqueue_scripts', 'melle_vr_admin_assets');

function melle_vr_admin_assets($hook) {
    if ('toplevel_page_melle-vr-beatmaps' !== $hook) return;
    wp_enqueue_media();
}

function melle_vr_beatmap_menu() {
    add_menu_page('Spatial Suite', 'Spatial Suite', 'manage_options', 'melle-vr-beatmaps', 'melle_vr_beatmap_page', 'dashicons-performance', 81);
}

function melle_vr_beatmap_page() {
    $path = wp_normalize_path(wp_upload_dir()['basedir'] . '/beatmaps/');
    if (!file_exists($path)) {
        wp_mkdir_p($path);
    }

    // Handle Deletion
    if (isset($_GET['del']) && check_admin_referer('del_beatmap')) {
        $del_file = $path . sanitize_file_name($_GET['del']);
        if(file_exists($del_file)) {
            unlink($del_file);
        }
        echo '<div class="notice notice-success is-dismissible"><p>Map purged from neural storage.</p></div>';
    }

    // Save Beatmap/Extraction
    if (isset($_POST['save_beatmap']) && check_admin_referer('save_melle_beatmap')) {
        $song_title = sanitize_text_field($_POST['song_title']);
        $json_data  = wp_unslash($_POST['beatmap_json']);
        $filename   = strtolower(preg_replace('/[^a-z0-9]/i', '_', $song_title)) . '.json';
        file_put_contents($path . $filename, $json_data);
        echo '<div class="notice notice-success is-dismissible"><p>Data <strong>' . esc_html($filename) . '</strong> activated.</p></div>';
    }

    $tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'generator';
    
    // Close PHP explicitly to render the HTML wrapper safely
    ?>
    <div class="wrap melle-admin-wrap" style="max-width: 100% !important; padding-right: 20px;">
        <h1 class="wp-heading-inline">Quantum Telegraph: Spatial Suite</h1>
        <h2 class="nav-tab-wrapper">
            <a href="?page=melle-vr-beatmaps&tab=generator" class="nav-tab <?php echo $tab == 'generator' ? 'nav-tab-active' : ''; ?>">Generator & Extractor</a>
            <a href="?page=melle-vr-beatmaps&tab=library" class="nav-tab <?php echo $tab == 'library' ? 'nav-tab-active' : ''; ?>">Library</a>
        </h2>

        <?php if ($tab == 'generator') : ?>
            <div class="card" style="max-width: 1000px; padding: 20px; margin-top: 20px;">
                <h2>Gemini 2.5 Pro Analysis & Transcription</h2>
                <p>Upload or select an audio track OR a PDF document to extract its contents in one pass.</p>
                <div style="padding:30px; border:2px dashed #0088cc; text-align:center; background:#f9f9f9; margin-bottom: 20px; border-radius: 8px;">
                    <button id="melle-upload-btn" class="button button-secondary button-hero">Select Audio or PDF Source</button>
                    <p id="melle-file-info" style="margin-top: 15px; font-family: monospace; font-weight: bold;">No file staged.</p>
                </div>

                <p class="submit">
                    <button id="melle-gen-btn" class="button button-primary button-hero" disabled>🚀 Process with Gemini 2.5 Pro</button>
                    <span id="melle-spinner" style="display:none; margin-left:15px; color: #0088cc; font-weight: bold;"><span class="spinner is-active" style="float:none; margin-top:-3px;"></span> Forging Neural Map & Transcribing... (Takes 30-60s)</span>
                </p>

                <form method="post">
                    <?php wp_nonce_field('save_melle_beatmap'); ?>
                    <table class="form-table">
                        <tr>
                            <th>Target Mount / Title</th>
                            <td>
                                <input type="text" name="song_title" id="melle-title" class="regular-text" placeholder="e.g. ambient-loop">
                                <p class="description">Identifier for the processed data output.</p>
                            </td>
                        </tr>
                        <tr>
                            <th>Extracted JSON & Text</th>
                            <td><textarea name="beatmap_json" id="melle-json" rows="15" style="width:100%; font-family:monospace; background: #111; color: #0f0; padding: 10px;"></textarea></td>
                        </tr>
                    </table>
                    <input type="submit" name="save_beatmap" class="button button-primary" value="Commit JSON Map">
                </form>
            </div>
        <?php else : ?>
            <div class="card" style="margin-top: 20px; padding: 20px;">
                <h2>Active Neural Maps</h2>
                <table class="wp-list-table widefat fixed striped">
                    <thead><tr><th>JSON File</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php 
                        $files = glob($path . "*.json");
                        if (empty($files)): ?>
                            <tr><td colspan="2">No beatmaps or documents generated yet.</td></tr>
                        <?php else:
                            foreach ($files as $f) : $name = basename($f); ?>
                                <tr>
                                    <td><code><?php echo esc_html($name); ?></code></td>
                                    <td>
                                        <a href="<?php echo esc_url(wp_upload_dir()['baseurl'] . '/beatmaps/' . $name); ?>" target="_blank" class="button button-small">View</a>
                                        <a href="<?php echo wp_nonce_url('?page=melle-vr-beatmaps&tab=library&del='.$name, 'del_beatmap'); ?>" class="button button-link-delete" onclick="return confirm('Delete this mapped file?');">Delete</a>
                                    </td>
                                </tr>
                            <?php endforeach; 
                        endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <script>
    jQuery(document).ready(function($){
        var uploader;
        $('#melle-upload-btn').click(function(e){
            e.preventDefault();
            if(uploader){ uploader.open(); return; }
            
            // Allow both audio and PDF attachments via WordPress Media Library UI
            uploader = wp.media({ title: 'Select Audio or Document', button: { text: 'Stage File' }, multiple: false })
            .on('select', function(){
                var att = uploader.state().get('selection').first().toJSON();
                $('#melle-file-info').text('Staged: ' + att.filename);
                $('#melle-title').val(att.filename.replace(/\.[^/.]+$/, "").toLowerCase());
                $('#melle-gen-btn').data('id', att.id).prop('disabled', false);
            }).open();
        });

        $('#melle-gen-btn').click(function(e){
            e.preventDefault();
            var btn = $(this); 
            btn.prop('disabled', true); 
            $('#melle-spinner').show();
            $('#melle-json').val('Uploading and analyzing bytes. Stand by...');

            $.post(ajaxurl, { 
                action: 'melle_vr_gemini_analysis', 
                attachment_id: btn.data('id'),
                nonce: '<?php echo wp_create_nonce("melle_vr_analysis_nonce"); ?>'
            }, function(res){
                if(res.success) {
                    try {
                        var data = typeof res.data === 'string' ? JSON.parse(res.data) : res.data;
                        $('#melle-json').val(JSON.stringify(data, null, 2));
                    } catch(e){ 
                        $('#melle-json').val(res.data); 
                    }
                } else {
                    alert('Error: ' + (res.data || 'Analysis failed.'));
                    $('#melle-json').val('Error: ' + (res.data || 'Analysis failed.'));
                }
                $('#melle-spinner').hide(); 
                btn.prop('disabled', false);
            }).fail(function() {
                alert('Server timeout or network error. Check error logs.');
                $('#melle-spinner').hide(); 
                btn.prop('disabled', false);
                $('#melle-json').val('Server error during processing.');
            });
        });
    });
    </script>
    <?php
}

// --- AJAX Handler for Gemini Resumable Upload & Transcription ---
add_action('wp_ajax_melle_vr_gemini_analysis', 'melle_vr_ajax_gemini_analysis');
function melle_vr_ajax_gemini_analysis() {
    if (!current_user_can('manage_options') || !check_ajax_referer('melle_vr_analysis_nonce', 'nonce', false)) {
        wp_send_json_error('Unauthorized.');
    }

    $api_key = get_option('melle_vr_gemini_api_key');
    if (empty($api_key)) wp_send_json_error('Gemini API Key missing. Please set it in Core Settings.');

    $attachment_id = isset($_POST['attachment_id']) ? intval($_POST['attachment_id']) : 0;
    if (!$attachment_id) wp_send_json_error('No valid attachment ID provided.');

    $file_path = get_attached_file($attachment_id);
    if (!file_exists($file_path)) wp_send_json_error('File not found on server.');

    $file_name = basename($file_path);
    $mime_type = mime_content_type($file_path);
    $file_size = filesize($file_path);

    // Check if the uploaded file is a PDF Document
    $is_pdf = (strpos($mime_type, 'pdf') !== false);

    // 1. Resumable Upload Init
    $init_url = "https://generativelanguage.googleapis.com/upload/v1beta/files?key={$api_key}";
    $init_args = [
        'headers' => [
            'X-Goog-Upload-Protocol'              => 'resumable',
            'X-Goog-Upload-Command'               => 'start',
            'X-Goog-Upload-Header-Content-Length' => $file_size,
            'X-Goog-Upload-Header-Content-Type'   => $mime_type,
            'Content-Type'                        => 'application/json'
        ],
        'body'    => json_encode(['file' => ['display_name' => $file_name]]),
        'timeout' => 30
    ];

    $init_response = wp_remote_post($init_url, $init_args);
    if (is_wp_error($init_response)) wp_send_json_error('Failed to initialize Gemini upload: ' . $init_response->get_error_message());

    $upload_url = wp_remote_retrieve_header($init_response, 'x-goog-upload-url');
    if (empty($upload_url)) wp_send_json_error('Gemini rejected the upload request.');

    // 2. Upload Raw Bytes
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
    if (is_wp_error($upload_response)) wp_send_json_error('Failed to upload bytes: ' . $upload_response->get_error_message());

    $upload_body = json_decode(wp_remote_retrieve_body($upload_response), true);
    $file_uri = $upload_body['file']['uri'] ?? '';
    $gemini_file_name = $upload_body['file']['name'] ?? '';

    if (empty($file_uri)) {
        wp_send_json_error('Failed to retrieve Gemini File URI. API response: ' . wp_remote_retrieve_body($upload_response));
    }

    // 3. Process the file based on its MIME type
    if ($is_pdf) {
        $prompt = "Read this document. Extract the core text, identify any key themes, and return a JSON object with 'beatmap' as an empty array and 'lyrics' containing the extracted document text.\n\n";
        $prompt .= "Respond ONLY with a raw, valid JSON object containing two keys. 'beatmap' must be an array, and 'lyrics' must be a string containing the transcription. Do not use markdown blocks. Example:\n";
        $prompt .= "{\"beatmap\": [], \"lyrics\": \"The extracted words go here\"}";
    } else {
        $prompt = "Listen to this audio track. Perform two tasks:\n";
        $prompt .= "1. Beatmap: Identify the timestamps (in seconds, e.g., 14.5) for the most prominent beats, heavy bass kicks, or major synth stabs.\n";
        $prompt .= "2. Transcription: Transcribe the core lyrics of this song.\n\n";
        $prompt .= "Respond ONLY with a raw, valid JSON object containing two keys. 'beatmap' must be an array of objects like [{\"time\": 12.5}], and 'lyrics' must be a string containing the transcription. Do not use markdown blocks. Example:\n";
        $prompt .= "{\"beatmap\": [{\"time\": 1.0}], \"lyrics\": \"The transcribed words go here\"}";
    }

    // Using Gemini 2.5 Pro specifically for deeper context and extraction accuracy
    $gen_url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-pro:generateContent?key={$api_key}";
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
            ],
            'generationConfig' => [
                'responseMimeType' => 'application/json'
            ]
        ]),
        'timeout' => 120
    ];

    $gen_response = wp_remote_post($gen_url, $gen_args);

    // 4. Delete file from Gemini immediately for privacy and cleanup
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

    // Clean up markdown if any slipped through
    $ai_text = trim($ai_text);
    $ai_text = preg_replace('/^```json\s*/i', '', $ai_text);
    $ai_text = preg_replace('/\s*```$/i', '', $ai_text);

    wp_send_json_success($ai_text);
}
