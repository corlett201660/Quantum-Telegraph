<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

// Hook into the admin menu
add_action( 'admin_menu', 'melle_vr_add_admin_menu' );

function melle_vr_add_admin_menu() {
    add_menu_page(
        'Quantum Telegraph Neural Maps',
        'Neural Maps',
        'manage_options',
        'melle-vr',
        'melle_vr_admin_page_html',
        'dashicons-controls-volumeon',
        85
    );
}

function melle_vr_admin_page_html() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $upload_dir = wp_upload_dir();
    $beatmaps_dir = $upload_dir['basedir'] . '/beatmaps';
    if ( ! file_exists( $beatmaps_dir ) ) {
        wp_mkdir_p( $beatmaps_dir );
    }

    $message = '';

    // Handle Deletion
    if ( isset( $_GET['delete_beatmap'] ) && check_admin_referer( 'delete_beatmap_' . $_GET['delete_beatmap'] ) ) {
        $file_to_delete = sanitize_file_name( $_GET['delete_beatmap'] );
        if ( unlink( trailingslashit( $beatmaps_dir ) . $file_to_delete ) ) {
            $message = '<div class="updated"><p>Data map deleted successfully.</p></div>';
        }
    }

    // Handle Form Submission
    if ( isset( $_POST['melle_vr_submit'] ) && check_admin_referer( 'melle_vr_admin_action', 'melle_vr_admin_nonce' ) ) {
        
        if ( isset( $_POST['gemini_api_key'] ) ) {
            update_option( 'melle_vr_gemini_api_key', sanitize_text_field( $_POST['gemini_api_key'] ) );
        }

        $api_key = get_option( 'melle_vr_gemini_api_key' );

        if ( ! empty( $_FILES['audio_file']['tmp_name'] ) && ! empty( $api_key ) ) {
            $message = melle_vr_process_audio_with_gemini( $_FILES['audio_file'], $api_key, $beatmaps_dir );
        } elseif ( ! empty( $_FILES['audio_file']['tmp_name'] ) && empty( $api_key ) ) {
            $message = '<div class="error"><p>Please save your Gemini API Key first before uploading a file.</p></div>';
        } else {
            $message = '<div class="updated"><p>Settings saved successfully.</p></div>';
        }
    }

    $current_api_key = get_option( 'melle_vr_gemini_api_key', '' );
    $existing_beatmaps = glob( $beatmaps_dir . '/*.json' );

    ?>
    <div class="wrap">
        <h1>Quantum Telegraph: Neural Link Manager</h1>
        <?php echo $message; ?>

        <div style="background: #fff; padding: 20px; border: 1px solid #ccc; margin-top: 20px; max-width: 800px;">
            <h2>1. Setup Google Gemini API</h2>
            <p>Enter your Gemini API key to enable AI file processing.</p>
            <form method="post" enctype="multipart/form-data" action="">
                <?php wp_nonce_field( 'melle_vr_admin_action', 'melle_vr_admin_nonce' ); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="gemini_api_key">Gemini API Key</label></th>
                        <td>
                            <input type="password" id="gemini_api_key" name="gemini_api_key" value="<?php echo esc_attr( $current_api_key ); ?>" class="regular-text" placeholder="AIzaSy..." />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="audio_file">Upload Audio Track or PDF Document (MP3/WAV/PDF)</label></th>
                        <td>
                            <input type="file" id="audio_file" name="audio_file" accept="audio/mpeg, audio/wav, application/pdf" />
                            <p class="description">Processing may take 30-60 seconds. The file is instantly deleted from both your server and Gemini's servers once the JSON map is generated.</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button( 'Save & Generate Data Map', 'primary', 'melle_vr_submit' ); ?>
            </form>
        </div>

        <div style="background: #fff; padding: 20px; border: 1px solid #ccc; margin-top: 20px; max-width: 800px;">
            <h2>2. Existing Neural Maps</h2>
            <p>Ensure the JSON filename exactly matches the track title played on your Icecast server (special characters removed, lowercase).</p>
            <?php if ( empty( $existing_beatmaps ) ) : ?>
                <p><em>No mapped data generated yet.</em></p>
            <?php else : ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Filename</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $existing_beatmaps as $file ) : 
                            $filename = basename( $file );
                            $delete_url = wp_nonce_url( admin_url( 'admin.php?page=melle-vr&delete_beatmap=' . urlencode( $filename ) ), 'delete_beatmap_' . $filename );
                        ?>
                            <tr>
                                <td><strong><?php echo esc_html( $filename ); ?></strong></td>
                                <td>
                                    <a href="<?php echo esc_url( wp_upload_dir()['baseurl'] . '/beatmaps/' . $filename ); ?>" target="_blank">View JSON</a> | 
                                    <a href="<?php echo esc_url( $delete_url ); ?>" style="color: red;" onclick="return confirm('Are you sure you want to delete this mapped data?');">Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

function melle_vr_process_audio_with_gemini( $file, $api_key, $beatmaps_dir ) {
    set_time_limit( 300 ); 

    $file_path = $file['tmp_name'];
    $file_name = sanitize_file_name( $file['name'] );
    $mime_type = mime_content_type( $file_path );
    $file_size = filesize( $file_path );

    $is_pdf = (strpos($mime_type, 'pdf') !== false);

    // Step 1: Initialize Resumable Upload
    $init_url = "https://generativelanguage.googleapis.com/upload/v1beta/files?key={$api_key}";
    $init_args = array(
        'headers' => array(
            'X-Goog-Upload-Protocol'              => 'resumable',
            'X-Goog-Upload-Command'               => 'start',
            'X-Goog-Upload-Header-Content-Length' => $file_size,
            'X-Goog-Upload-Header-Content-Type'   => $mime_type,
            'Content-Type'                        => 'application/json'
        ),
        'body'    => json_encode( array( 'file' => array( 'display_name' => $file_name ) ) ),
        'timeout' => 30
    );

    $init_response = wp_remote_post( $init_url, $init_args );
    if ( is_wp_error( $init_response ) ) return '<div class="error"><p>Failed to connect to Gemini API (Init Step).</p></div>';

    $upload_url = wp_remote_retrieve_header( $init_response, 'x-goog-upload-url' );
    if ( empty( $upload_url ) ) return '<div class="error"><p>Gemini API rejected the upload request.</p></div>';

    // Step 2: Upload the raw bytes
    $upload_args = array(
        'headers' => array(
            'X-Goog-Upload-Protocol' => 'resumable',
            'X-Goog-Upload-Command'  => 'upload',
            'X-Goog-Upload-Offset'   => '0',
            'Content-Length'         => $file_size,
        ),
        'body'    => file_get_contents( $file_path ),
        'timeout' => 120
    );

    $upload_response = wp_remote_post( $upload_url, $upload_args );
    
    // Explicitly delete local temporary file immediately after sending bytes
    @unlink( $file_path );

    if ( is_wp_error( $upload_response ) ) return '<div class="error"><p>Failed to upload file bytes to Gemini.</p></div>';

    $upload_body = json_decode( wp_remote_retrieve_body( $upload_response ), true );
    $file_uri = isset( $upload_body['file']['uri'] ) ? $upload_body['file']['uri'] : '';
    $gemini_file_name = isset( $upload_body['file']['name'] ) ? $upload_body['file']['name'] : '';

    if ( empty( $file_uri ) ) return '<div class="error"><p>Failed to retrieve Gemini File URI.</p></div>';

    // Step 3: Ask Gemini to process the file depending on its type
    if ( $is_pdf ) {
        $prompt = "Read this document. Extract the main themes or chapters. Respond ONLY with a raw, valid JSON array of objects. Do not use markdown blocks. Example format: [{\"time\": 1, \"theme\": \"Introduction\"}, {\"time\": 2, \"theme\": \"Main Concept\"}]";
    } else {
        $prompt = "Listen to this audio track. Identify the timestamps (in seconds, e.g., 14.5) for the most prominent beats, heavy bass kicks, or major synth stabs in the song. Respond ONLY with a raw, valid JSON array of objects. Do not use markdown blocks. Example format: [{\"time\": 12.5}, {\"time\": 13.0}, {\"time\": 14.2}]";
    }
    
    $gen_url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-pro:generateContent?key={$api_key}";
    $gen_args = array(
        'headers' => array( 'Content-Type' => 'application/json' ),
        'body'    => json_encode( array(
            'contents' => array(
                array(
                    'parts' => array(
                        array( 'fileData' => array( 'fileUri' => $file_uri, 'mimeType' => $mime_type ) ),
                        array( 'text' => $prompt )
                    )
                )
            )
        ) ),
        'timeout' => 120
    );

    $gen_response = wp_remote_post( $gen_url, $gen_args );
    
    // Step 4: Instantly Delete the file from Gemini's servers to protect privacy/storage
    if ( ! empty( $gemini_file_name ) ) {
        $delete_url = "https://generativelanguage.googleapis.com/v1beta/{$gemini_file_name}?key={$api_key}";
        wp_remote_request( $delete_url, array( 'method' => 'DELETE' ) );
    }

    if ( is_wp_error( $gen_response ) ) return '<div class="error"><p>Failed to process file with Gemini.</p></div>';

    $gen_body = json_decode( wp_remote_retrieve_body( $gen_response ), true );
    $ai_text = isset( $gen_body['candidates'][0]['content']['parts'][0]['text'] ) ? $gen_body['candidates'][0]['content']['parts'][0]['text'] : '';

    if ( empty( $ai_text ) ) return '<div class="error"><p>Gemini returned an empty response.</p></div>';

    $ai_text = trim( $ai_text );
    if ( strpos( $ai_text, '```json' ) === 0 ) $ai_text = substr( $ai_text, 7, -3 );
    elseif ( strpos( $ai_text, '```' ) === 0 ) $ai_text = substr( $ai_text, 3, -3 );

    if ( json_decode( trim( $ai_text ) ) === null ) return '<div class="error"><p>Gemini generated invalid JSON format. Try uploading again.</p></div>';

    $base_name = pathinfo( $file_name, PATHINFO_FILENAME );
    $sanitized_name = preg_replace( '/[^a-z0-9]/i', '_', strtolower( $base_name ) );
    
    $save_path = trailingslashit( $beatmaps_dir ) . $sanitized_name . '.json';
    file_put_contents( $save_path, trim( $ai_text ) );

    return '<div class="updated"><p>Success! Data processed, immediately deleted from servers, and map saved as <strong>' . $sanitized_name . '.json</strong></p></div>';
}
