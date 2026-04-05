<?php
/**
 * Quantum Telegraph - Backend Admin Interface
 * Handles API Keys, Icecast Settings, Station Management, Uploads, AI Metadata, Matomo, and Push.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ==========================================
// 1. HELPER: GENERATE EZSTREAM XML (MODERN v1.x)
// ==========================================
function melle_vr_generate_ezstream_xml($station_name) {
    $base_dir = wp_normalize_path(wp_upload_dir()['basedir'] . '/qrq_radio_assets');
    $station_dir = $base_dir . '/' . $station_name;
    $xml_file = $station_dir . '/ezstream.xml';
    $playlist_file = $station_dir . '/playlist.txt';

    $icecast_host = get_option('qrq_icecast_server_url', 'http://localhost:8000');
    $icecast_pass = get_option('qrq_icecast_admin_pass', 'hackme');

    $host_parsed = parse_url($icecast_host);
    $hostname = $host_parsed['host'] ?? 'localhost';
    $port = $host_parsed['port'] ?? 8000;

    $xml_content = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
<ezstream>
    <servers>
        <server>
            <hostname>{$hostname}</hostname>
            <port>{$port}</port>
            <password>{$icecast_pass}</password>
        </server>
    </servers>
    <streams>
        <stream>
            <public>Yes</public>
            <mountpoint>/{$station_name}</mountpoint>
            <format>MP3</format>
            <stream_name>Quantum Telegraph: {$station_name}</stream_name>
            <stream_url>http://qrjournal.org</stream_url>
            <stream_genre>Cosmic</stream_genre>
            <stream_description>Subatomic Broadcast from Ridgecrest</stream_description>
            <stream_bitrate>128</stream_bitrate>
            <stream_channels>2</stream_channels>
            <stream_samplerate>44100</stream_samplerate>
        </stream>
    </streams>
    <intakes>
        <intake>
            <type>playlist</type>
            <filename>{$playlist_file}</filename>
            <shuffle>Yes</shuffle>
            <stream_once>No</stream_once>
        </intake>
    </intakes>
</ezstream>";

    $result = file_put_contents($xml_file, $xml_content);
    if ($result !== false) {
        chmod($xml_file, 0640);
    }
    return $result;
}

// ==========================================
// 2. HELPER: RECURSIVE DIRECTORY DELETION
// ==========================================
function melle_vr_rmdir_recursive($dir) {
    if (!file_exists($dir)) return true;
    if (!is_dir($dir)) return unlink($dir);
    foreach (scandir($dir) as $item) {
        if ($item == '.' || $item == '..') continue;
        if (!melle_vr_rmdir_recursive($dir . DIRECTORY_SEPARATOR . $item)) return false;
    }
    return rmdir($dir);
}

// ==========================================
// 3. REGISTER MENU & SETTINGS
// ==========================================
add_action('admin_menu', 'melle_vr_register_admin_menu');
add_action('admin_init', 'melle_vr_register_settings');

function melle_vr_register_admin_menu() {
    // [FIX] Changed callback function name to avoid redeclaration conflict with admin.php
    add_menu_page('Quantum Telegraph Settings', 'Quantum Telegraph', 'manage_options', 'qrq_settings', 'qrq_main_settings_page_html', 'dashicons-admin-site-alt3', 80);
}

function melle_vr_register_settings() {
    register_setting('melle_vr_settings_group', 'melle_vr_gemini_api_key');
    register_setting('melle_vr_settings_group', 'qrq_openai_api_key');
    register_setting('melle_vr_settings_group', 'qrq_icecast_json_url');
    register_setting('melle_vr_settings_group', 'qrq_icecast_server_url');
    register_setting('melle_vr_settings_group', 'qrq_icecast_admin_user');
    register_setting('melle_vr_settings_group', 'qrq_icecast_admin_pass');
    register_setting('melle_vr_settings_group', 'melle_vr_vr_allowed_mounts');
    register_setting('melle_vr_settings_group', 'melle_vr_vr_excluded_mounts');
    register_setting('melle_vr_settings_group', 'melle_vr_radio_allowed_mounts');
    register_setting('melle_vr_settings_group', 'melle_vr_radio_excluded_mounts');
    register_setting('melle_vr_settings_group', 'melle_vr_restricted_mounts');
    register_setting('melle_vr_settings_group', 'melle_vr_required_roles');
    register_setting('melle_vr_settings_group', 'melle_vr_required_products');
    register_setting('melle_vr_settings_group', 'melle_vr_store_link');
    register_setting('melle_vr_settings_group', 'melle_vr_matomo_url');
    register_setting('melle_vr_settings_group', 'melle_vr_matomo_site_id');
    
    // [NEW] Added Home URL setting for the PWA
    register_setting('melle_vr_settings_group', 'melle_vr_home_url');
    
    // New Push Notification Settings
    register_setting('melle_vr_settings_group', 'qrq_push_api_key');
    register_setting('melle_vr_settings_group', 'qrq_push_app_id');
    register_setting('melle_vr_settings_group', 'qrq_push_enabled');
}

// ==========================================
// 4. HANDLE FORM SUBMISSIONS
// ==========================================
function melle_vr_handle_admin_actions() {
    if (!current_user_can('manage_options')) return;

    $base_dir = wp_normalize_path(wp_upload_dir()['basedir'] . '/qrq_radio_assets');
    if (!file_exists($base_dir)) {
        wp_mkdir_p($base_dir);
        file_put_contents($base_dir . '/.htaccess', "Options -Indexes\n");
    }

    if (isset($_POST['melle_create_station']) && check_admin_referer('melle_vr_station_action')) {
        $new_station = sanitize_title($_POST['new_station_name']);
        if (!empty($new_station)) {
            $station_path = $base_dir . '/' . $new_station;
            if (!file_exists($station_path)) {
                wp_mkdir_p($station_path);
                file_put_contents($station_path . '/playlist.txt', ""); 
                melle_vr_generate_ezstream_xml($new_station);
                echo '<div class="notice notice-success is-dismissible"><p>Station created and XML initialized for /' . esc_html($new_station) . '!</p></div>';
            } else {
                echo '<div class="notice notice-error is-dismissible"><p>Station already exists!</p></div>';
            }
        }
    }

    if (isset($_POST['melle_delete_station']) && check_admin_referer('melle_vr_station_action')) {
        $del_station = sanitize_title($_POST['station_to_delete']);
        $station_path = $base_dir . '/' . $del_station;
        if (!empty($del_station) && is_dir($station_path)) {
            melle_vr_rmdir_recursive($station_path);
            echo '<div class="notice notice-success is-dismissible"><p>Station /' . esc_html($del_station) . ' and all associated tracks have been completely purged.</p></div>';
        }
    }

    if (isset($_POST['melle_rebuild_xml']) && check_admin_referer('melle_vr_rebuild_action')) {
        $stations = array_filter(glob($base_dir . '/*'), 'is_dir');
        foreach ($stations as $s) {
            melle_vr_generate_ezstream_xml(basename($s));
        }
        echo '<div class="notice notice-info is-dismissible"><p>All station XML configurations have been reconstructed.</p></div>';
    }

    if (isset($_POST['melle_upload_file']) && check_admin_referer('melle_vr_upload_action')) {
        $target_station = sanitize_title($_POST['target_station']);
        if (!empty($target_station) && !empty($_FILES['station_file']['name'])) {
            $station_path = $base_dir . '/' . $target_station;
            if (file_exists($station_path)) {
                $file = $_FILES['station_file'];
                $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                
                if ($file_ext !== 'mp3') {
                    echo '<div class="notice notice-error is-dismissible"><p>Only MP3 files are allowed.</p></div>';
                } else {
                    $safe_filename = sanitize_file_name($file['name']);
                    $destination = $station_path . '/' . $safe_filename;
                    
                    if (move_uploaded_file($file['tmp_name'], $destination)) {
                        $playlist_file = $station_path . '/playlist.txt';
                        $current_playlist = file_exists($playlist_file) ? file_get_contents($playlist_file) : "";
                        $current_playlist .= $destination . "\n";
                        file_put_contents($playlist_file, $current_playlist);
                        echo '<div class="notice notice-success is-dismissible"><p>File uploaded successfully to ' . esc_html($target_station) . '!</p></div>';
                    } else {
                        echo '<div class="notice notice-error is-dismissible"><p>File upload failed. Check folder permissions.</p></div>';
                    }
                }
            }
        }
    }
    
    if (isset($_POST['melle_delete_track']) && check_admin_referer('melle_vr_track_action')) {
        $channel = sanitize_title($_POST['channel']);
        $filename = sanitize_file_name($_POST['filename']);
        $filepath = $base_dir . '/' . $channel . '/' . $filename;
        
        if (file_exists($filepath)) {
            unlink($filepath); 
            
            $playlist_file = $base_dir . '/' . $channel . '/playlist.txt';
            if (file_exists($playlist_file)) {
                $lines = file($playlist_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                $lines = array_filter($lines, function($line) use ($filepath) {
                    return trim($line) !== $filepath;
                });
                file_put_contents($playlist_file, implode("\n", $lines) . "\n");
            }
            echo '<div class="notice notice-success is-dismissible"><p>Track permanently deleted.</p></div>';
        }
    }

    // Save Station Product Reqs
    if (isset($_POST['melle_save_station_reqs']) && check_admin_referer('melle_vr_station_reqs_action')) {
        $station = sanitize_title($_POST['station_name']);
        $product_ids = sanitize_text_field($_POST['station_product_ids']);
        $reqs = get_option('melle_vr_station_reqs', []);
        $reqs[$station] = $product_ids;
        update_option('melle_vr_station_reqs', $reqs);
        echo '<div class="notice notice-success is-dismissible"><p>Access requirements updated for /' . esc_html($station) . '.</p></div>';
    }
}

// ==========================================
// 5. FETCH LIVE ICECAST STATE
// ==========================================
function melle_vr_get_active_mounts() {
    $json_url = get_option('qrq_icecast_json_url', 'https://qrjournal.org/status-json.xsl');
    $active_mounts = [];
    
    $response = wp_remote_get($json_url, ['timeout' => 5, 'sslverify' => false]);
    if (!is_wp_error($response)) {
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        if (isset($data['icestats']['source'])) {
            $sources = is_array($data['icestats']['source']) && isset($data['icestats']['source'][0]) ? $data['icestats']['source'] : [$data['icestats']['source']];
            foreach ($sources as $src) {
                if (isset($src['listenurl'])) {
                    $parts = explode('/', $src['listenurl']);
                    $active_mounts[] = end($parts);
                }
            }
        }
    }
    return $active_mounts;
}

// ==========================================
// 6. ADMIN PAGE UI 
// ==========================================
// [FIX] Renamed to qrq_main_settings_page_html to avoid conflict
function qrq_main_settings_page_html() {
    if (!current_user_can('manage_options')) return;
    
    melle_vr_handle_admin_actions();

    $base_dir = wp_normalize_path(wp_upload_dir()['basedir'] . '/qrq_radio_assets');
    $stations = file_exists($base_dir) ? array_filter(glob($base_dir . '/*'), 'is_dir') : [];
    $active_mounts = melle_vr_get_active_mounts();
    $ajax_nonce = wp_create_nonce('qrq_manage_assets');
    $station_reqs = get_option('melle_vr_station_reqs', []);
    ?>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>

    <style>
        #wpbody-content { padding-bottom: 0; width: 100% !important; max-width: 100% !important; overflow-x: hidden; }
        .melle-admin-wrap { max-width: 100% !important; width: 100% !important; padding-right: 20px; margin-top: 20px; box-sizing: border-box; }
        .melle-admin-wrap .card { max-width: 100% !important; width: 100% !important; }
        .melle-admin-wrap input[type="text"], 
        .melle-admin-wrap input[type="password"], 
        .melle-admin-wrap input[type="url"], 
        .melle-admin-wrap input[type="number"], 
        .melle-admin-wrap input[type="date"], 
        .melle-admin-wrap select { max-width: 100%; padding: 0.375rem 0.75rem; }
        .drag-handle { cursor: grab; }
        .drag-handle:active { cursor: grabbing; }
        .sortable-ghost { opacity: 0.4; background-color: #f8f9fa; }
        
        @media screen and (max-width: 1024px) {
            #wpcontent, #wpbody-content { padding-left: 0 !important; padding-right: 0 !important; width: 100% !important; max-width: 100% !important; }
            .melle-admin-wrap { padding-left: 15px !important; padding-right: 15px !important; }
            .modal-dialog.modal-lg { max-width: 95% !important; width: 95% !important; margin: 10px auto; }
            .row { margin-left: 0; margin-right: 0; }
            .melle-admin-wrap .card { padding: 15px !important; }
        }
    </style>

    <div class="wrap melle-admin-wrap" data-bs-theme="light">
        <div class="d-flex align-items-center mb-4 border-bottom pb-3">
            <div class="bg-primary text-white rounded p-3 me-3 shadow-sm">
                <i class="dashicons dashicons-admin-site-alt3" style="font-size: 2rem; width: 32px; height: 32px;"></i>
            </div>
            <div>
                <h1 class="h2 fw-bold text-dark mb-0">Quantum Telegraph Interface Control</h1>
                <p class="text-muted mb-0">Manage network settings, stations, AI metadata, and subatomic broadcasts.</p>
            </div>
        </div>

        <div class="card shadow-sm border-0 mb-4" style="background: #fff8e1; border-left: 4px solid #ffc107 !important;">
            <div class="card-body p-3">
                <h5 class="fw-bold text-dark mb-2"><i class="dashicons dashicons-admin-tools"></i> Emergency Repair Tools</h5>
                <p class="text-muted small mb-3">If your CLI says <strong>"no configuration"</strong> or a stream is in a crash loop, force-write the missing XML configuration files here.</p>
                <form method="post" class="d-inline">
                    <?php wp_nonce_field('melle_vr_rebuild_action'); ?>
                    <button type="submit" name="melle_rebuild_xml" class="btn btn-sm btn-warning fw-bold text-dark shadow-sm">Reconstruct All XML Configs</button>
                </form>
            </div>
        </div>

        <ul class="nav nav-pills mb-4" id="adminTabs" role="tablist">
            <li class="nav-item"><button class="nav-link active fw-bold px-4" data-bs-toggle="pill" data-bs-target="#streams" type="button">Stream States</button></li>
            <li class="nav-item"><button class="nav-link fw-bold px-4" data-bs-toggle="pill" data-bs-target="#settings" type="button">Core Settings</button></li>
            <li class="nav-item"><button class="nav-link fw-bold px-4" data-bs-toggle="pill" data-bs-target="#stations" type="button">Station Setup</button></li>
            <li class="nav-item"><button class="nav-link fw-bold px-4" data-bs-toggle="pill" data-bs-target="#metadata" type="button">Track & Metadata Manager</button></li>
        </ul>

        <div class="tab-content" id="adminTabsContent">
            
            <div class="tab-pane fade show active" id="streams">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white border-bottom fw-bold text-primary py-3">
                        <i class="dashicons dashicons-controls-play me-1"></i> Live Stream State Management
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($stations)) : ?>
                            <div class="p-4"><div class="alert alert-info mb-0">No active stations.</div></div>
                        <?php else : ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="ps-4">Station Mount Point</th>
                                            <th>Status</th>
                                            <th class="text-end pe-4">Controls</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($stations as $station) : 
                                            $name = basename($station);
                                            $is_active = in_array($name, $active_mounts);
                                            $status_badge = $is_active ? '<span class="badge bg-success"><i class="dashicons dashicons-rss"></i> Live</span>' : '<span class="badge bg-secondary"><i class="dashicons dashicons-hidden"></i> Offline</span>';
                                        ?>
                                        <tr>
                                            <td class="ps-4 fw-bold">/<?php echo esc_html($name); ?></td>
                                            <td><?php echo $status_badge; ?></td>
                                            <td class="text-end pe-4">
                                                <button class="btn btn-sm btn-outline-secondary fw-bold ms-2" type="button" data-bs-toggle="collapse" data-bs-target="#cli-<?php echo esc_attr($name); ?>">
                                                    <i class="dashicons dashicons-editor-code" style="margin-top:3px;"></i> Systemd CLI
                                                </button>
                                            </td>
                                        </tr>
                                        <tr class="collapse bg-light" id="cli-<?php echo esc_attr($name); ?>">
                                            <td colspan="3" class="p-4 border-bottom">
                                                <p class="small text-muted fw-bold mb-2 text-uppercase"><i class="dashicons dashicons-warning"></i> Systemd Service Commands</p>
                                                
                                                <div class="mb-3">
                                                    <label class="form-label small fw-bold text-dark">Start Stream</label>
                                                    <div class="input-group input-group-sm">
                                                        <input type="text" class="form-control font-monospace" value="sudo /bin/systemctl start ezstream@<?php echo esc_attr($name); ?> 2>&1" readonly id="cmd-start-<?php echo esc_attr($name); ?>">
                                                        <button class="btn btn-outline-secondary btn-copy" type="button" data-clipboard-target="#cmd-start-<?php echo esc_attr($name); ?>"><i class="dashicons dashicons-admin-page"></i> Copy</button>
                                                    </div>
                                                </div>

                                                <div class="mb-3">
                                                    <label class="form-label small fw-bold text-dark">Restart Stream</label>
                                                    <div class="input-group input-group-sm">
                                                        <input type="text" class="form-control font-monospace" value="sudo /bin/systemctl restart ezstream@<?php echo esc_attr($name); ?> 2>&1" readonly id="cmd-restart-<?php echo esc_attr($name); ?>">
                                                        <button class="btn btn-outline-secondary btn-copy" type="button" data-clipboard-target="#cmd-restart-<?php echo esc_attr($name); ?>"><i class="dashicons dashicons-admin-page"></i> Copy</button>
                                                    </div>
                                                </div>

                                                <div class="mb-0">
                                                    <label class="form-label small fw-bold text-dark">Stop Stream</label>
                                                    <div class="input-group input-group-sm">
                                                        <input type="text" class="form-control font-monospace" value="sudo /bin/systemctl stop ezstream@<?php echo esc_attr($name); ?> 2>&1" readonly id="cmd-stop-<?php echo esc_attr($name); ?>">
                                                        <button class="btn btn-outline-secondary btn-copy" type="button" data-clipboard-target="#cmd-stop-<?php echo esc_attr($name); ?>"><i class="dashicons dashicons-admin-page"></i> Copy</button>
                                                    </div>
                                                </div>
                                                
                                                <hr class="border-secondary my-3">
                                                <p class="small text-danger fw-bold mb-2 text-uppercase"><i class="dashicons dashicons-lock"></i> Stream Access Control (WooCommerce)</p>
                                                <form method="post" action="">
                                                    <?php wp_nonce_field('melle_vr_station_reqs_action'); ?>
                                                    <input type="hidden" name="station_name" value="<?php echo esc_attr($name); ?>">
                                                    <div class="input-group input-group-sm mb-2">
                                                        <span class="input-group-text fw-bold border-danger bg-white text-danger">Required Product IDs</span>
                                                        <input type="text" class="form-control border-danger" name="station_product_ids" value="<?php echo esc_attr($station_reqs[$name] ?? ''); ?>" placeholder="e.g., 123, 456 (Leave blank for public)">
                                                        <button type="submit" name="melle_save_station_reqs" class="btn btn-outline-danger fw-bold">Save Access</button>
                                                    </div>
                                                    <p class="small text-muted mb-0">If set, users must purchase at least one of these comma-separated product IDs to unlock this specific stream.</p>
                                                </form>

                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="tab-pane fade" id="settings">
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white border-bottom fw-bold text-primary py-3">
                        <i class="dashicons dashicons-admin-network me-1"></i> API & Network Configuration
                    </div>
                    <div class="card-body p-4 bg-light">
                        <form method="post" action="options.php">
                            <?php settings_fields('melle_vr_settings_group'); ?>
                            
                            <h5 class="fw-bold mb-3 border-bottom pb-2">Cognitive Engine API Keys</h5>
                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Google Gemini API Key</label>
                                    <input type="password" class="form-control border-secondary shadow-sm" name="melle_vr_gemini_api_key" value="<?php echo esc_attr(get_option('melle_vr_gemini_api_key')); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">OpenAI API Key (Fallback)</label>
                                    <input type="password" class="form-control border-secondary shadow-sm" name="qrq_openai_api_key" value="<?php echo esc_attr(get_option('qrq_openai_api_key')); ?>">
                                </div>
                            </div>
                            
                            <h5 class="fw-bold mb-3 border-bottom pb-2 mt-4 text-success"><i class="dashicons dashicons-megaphone"></i> Push Notifications (0-to-1 Alert)</h5>
                            <?php
                                $push_enabled = get_option('qrq_push_enabled') == '1';
                                $push_badge = $push_enabled ? '<span class="badge bg-success ms-2">Active</span>' : '<span class="badge bg-secondary ms-2">Disabled</span>';
                            ?>
                            <p class="small text-muted mb-3">Alert subscribers when a Pioneer enters an empty frequency. Automatically deep-links to the active channel. <?php echo $push_badge; ?></p>
                            <div class="row g-3 mb-4">
                                <div class="col-md-12 mb-2">
                                    <div class="form-check form-switch border p-3 rounded bg-white shadow-sm d-inline-block">
                                        <input class="form-check-input" type="checkbox" id="push_toggle" name="qrq_push_enabled" value="1" <?php checked(1, get_option('qrq_push_enabled'), true); ?>>
                                        <label class="form-check-label fw-bold text-dark ms-2" for="push_toggle">Enable Network Notifications</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">PushNotifications.io App ID</label>
                                    <input type="text" class="form-control border-secondary shadow-sm" name="qrq_push_app_id" value="<?php echo esc_attr(get_option('qrq_push_app_id')); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">REST API Key</label>
                                    <input type="password" class="form-control border-secondary shadow-sm" name="qrq_push_api_key" value="<?php echo esc_attr(get_option('qrq_push_api_key')); ?>">
                                </div>
                            </div>
                            
                            <h5 class="fw-bold mb-3 border-bottom pb-2 mt-4 text-info"><i class="dashicons dashicons-chart-pie"></i> Telemetry & Analytics (Matomo)</h5>
                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Matomo Server URL</label>
                                    <input type="url" class="form-control border-secondary shadow-sm" name="melle_vr_matomo_url" value="<?php echo esc_attr(get_option('melle_vr_matomo_url')); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Matomo Site ID</label>
                                    <input type="number" class="form-control border-secondary shadow-sm" name="melle_vr_matomo_site_id" value="<?php echo esc_attr(get_option('melle_vr_matomo_site_id')); ?>">
                                </div>
                            </div>

                            <h5 class="fw-bold mb-3 border-bottom pb-2 mt-4 text-danger"><i class="dashicons dashicons-lock"></i> Advanced Access Controls</h5>
                            <div class="row g-3 mb-4">
                                <div class="col-md-12">
                                    <label class="form-label fw-bold">Restricted Mounts</label>
                                    <input type="text" class="form-control border-secondary shadow-sm" name="melle_vr_restricted_mounts" value="<?php echo esc_attr(get_option('melle_vr_restricted_mounts')); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Required Roles to Access</label>
                                    <input type="text" class="form-control border-secondary shadow-sm" name="melle_vr_required_roles" value="<?php echo esc_attr(get_option('melle_vr_required_roles')); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Required WooCommerce Product IDs</label>
                                    <input type="text" class="form-control border-secondary shadow-sm" name="melle_vr_required_products" value="<?php echo esc_attr(get_option('melle_vr_required_products')); ?>">
                                </div>
                                
                                <div class="col-md-12 mt-3">
                                    <label class="form-label fw-bold text-success"><i class="dashicons dashicons-cart"></i> Premium Content Store Link (Upsell)</label>
                                    <input type="url" class="form-control border-secondary shadow-sm" name="melle_vr_store_link" value="<?php echo esc_attr(get_option('melle_vr_store_link')); ?>" placeholder="https://yourstore.com/shop">
                                    <p class="small text-muted mt-1">If a user only has access to default streams, this link will be presented in the network panel to upsell premium tracks.</p>
                                </div>
                                
                                <div class="col-md-12 mt-3">
                                    <label class="form-label fw-bold text-primary"><i class="dashicons dashicons-admin-home"></i> PWA Home URL</label>
                                    <input type="text" class="form-control border-secondary shadow-sm" name="melle_vr_home_url" value="<?php echo esc_attr(get_option('melle_vr_home_url', '/community')); ?>" placeholder="/community">
                                    <p class="small text-muted mt-1">The destination for the Home button in the PWA interface.</p>
                                </div>
                            </div>

                            <h5 class="fw-bold mb-3 border-bottom pb-2 mt-4 text-warning"><i class="dashicons dashicons-visibility"></i> Stream Visibility & Filtering</h5>
                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">VR Game: Allowed Mounts</label>
                                    <input type="text" class="form-control border-secondary shadow-sm" name="melle_vr_vr_allowed_mounts" value="<?php echo esc_attr(get_option('melle_vr_vr_allowed_mounts')); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">VR Game: Excluded Mounts</label>
                                    <input type="text" class="form-control border-secondary shadow-sm" name="melle_vr_vr_excluded_mounts" value="<?php echo esc_attr(get_option('melle_vr_vr_excluded_mounts', 'admin, fallback')); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Radio Player: Allowed Mounts</label>
                                    <input type="text" class="form-control border-secondary shadow-sm" name="melle_vr_radio_allowed_mounts" value="<?php echo esc_attr(get_option('melle_vr_radio_allowed_mounts')); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Radio Player: Excluded Mounts</label>
                                    <input type="text" class="form-control border-secondary shadow-sm" name="melle_vr_radio_excluded_mounts" value="<?php echo esc_attr(get_option('melle_vr_radio_excluded_mounts', 'admin, fallback')); ?>">
                                </div>
                            </div>

                            <h5 class="fw-bold mb-3 border-bottom pb-2 mt-4">Icecast Broadcast Settings</h5>
                            <div class="row g-3 mb-4">
                                <div class="col-md-12">
                                    <label class="form-label fw-bold">Icecast Status JSON URL</label>
                                    <input type="url" class="form-control border-secondary shadow-sm" name="qrq_icecast_json_url" value="<?php echo esc_attr(get_option('qrq_icecast_json_url', 'https://qrjournal.org/status-json.xsl')); ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-bold">Icecast Server Address</label>
                                    <input type="text" class="form-control border-secondary shadow-sm" name="qrq_icecast_server_url" value="<?php echo esc_attr(get_option('qrq_icecast_server_url', 'http://localhost:8000')); ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-bold">Admin Username</label>
                                    <input type="text" class="form-control border-secondary shadow-sm" name="qrq_icecast_admin_user" value="<?php echo esc_attr(get_option('qrq_icecast_admin_user', 'admin')); ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-bold">Admin Password</label>
                                    <input type="password" class="form-control border-secondary shadow-sm" name="qrq_icecast_admin_pass" value="<?php echo esc_attr(get_option('qrq_icecast_admin_pass')); ?>">
                                </div>
                            </div>

                            <div class="mt-4">
                                <?php submit_button('Save Configuration', 'btn btn-primary px-4 shadow-sm'); ?>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="tab-pane fade" id="stations">
                <div class="row g-4">
                    <div class="col-md-4">
                        <div class="card shadow-sm border-0 h-100">
                            <div class="card-header bg-white border-bottom fw-bold text-primary py-3">
                                <i class="dashicons dashicons-plus-alt2 me-1"></i> Initialize New Station
                            </div>
                            <div class="card-body p-4 bg-light">
                                <form method="post" action="">
                                    <?php wp_nonce_field('melle_vr_station_action'); ?>
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Station Mount/Name</label>
                                        <input type="text" class="form-control border-secondary shadow-sm" name="new_station_name" placeholder="e.g., ambient-loop" required>
                                    </div>
                                    <button type="submit" name="melle_create_station" class="btn btn-success w-100 shadow-sm fw-bold">Create Station</button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="card shadow-sm border-0 h-100">
                            <div class="card-header bg-white border-bottom fw-bold text-primary py-3">
                                <i class="dashicons dashicons-upload me-1"></i> Asset Uploader
                            </div>
                            <div class="card-body p-4 bg-light">
                                <?php if (empty($stations)) : ?>
                                    <div class="alert alert-warning">Please create a station first.</div>
                                <?php else : ?>
                                    <form method="post" action="" enctype="multipart/form-data">
                                        <?php wp_nonce_field('melle_vr_upload_action'); ?>
                                        <div class="mb-3">
                                            <label class="form-label fw-bold">Target Station</label>
                                            <select class="form-select border-secondary shadow-sm" name="target_station" required>
                                                <option value="" disabled selected>Select a station...</option>
                                                <?php foreach ($stations as $station) : ?>
                                                    <option value="<?php echo esc_attr(basename($station)); ?>"><?php echo esc_html(basename($station)); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label fw-bold">MP3 Audio File</label>
                                            <input type="file" class="form-control border-secondary shadow-sm" name="station_file" accept=".mp3" required>
                                        </div>
                                        <button type="submit" name="melle_upload_file" class="btn btn-primary w-100 shadow-sm fw-bold">Upload Asset</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="card shadow-sm border-0 h-100">
                            <div class="card-header bg-danger text-white border-bottom fw-bold py-3">
                                <i class="dashicons dashicons-trash me-1"></i> Manage Stations
                            </div>
                            <div class="card-body p-4 bg-light">
                                <?php if (empty($stations)) : ?>
                                    <div class="alert alert-warning">No stations exist.</div>
                                <?php else : ?>
                                    <ul class="list-group list-group-flush shadow-sm">
                                        <?php foreach ($stations as $station) : 
                                            $name = basename($station);
                                        ?>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <strong class="text-dark">/<?php echo esc_html($name); ?></strong>
                                            <button type="button" class="btn btn-sm btn-outline-danger fw-bold btn-delete-station" data-station="<?php echo esc_attr($name); ?>">
                                                Delete
                                            </button>
                                        </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="tab-pane fade" id="metadata">
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white border-bottom fw-bold text-primary py-3">
                        <i class="dashicons dashicons-playlist-audio me-1"></i> Station Playlists & AI Generation
                    </div>
                    <div class="card-body p-4 bg-light">
                        <?php if (empty($stations)) : ?>
                            <div class="alert alert-warning">No stations found.</div>
                        <?php else : ?>
                            <div class="accordion shadow-sm" id="stationAccordion">
                                <?php foreach ($stations as $index => $station) : 
                                    $channel = basename($station);
                                    $meta_file = $station . '/metadata.json';
                                    $meta_data = file_exists($meta_file) ? json_decode(file_get_contents($meta_file), true) : [];
                                    $files = array_map('basename', glob($station . '/*.mp3'));
                                ?>
                                    <div class="accordion-item">
                                        <h2 class="accordion-header" id="heading-<?php echo $index; ?>">
                                            <button class="accordion-button <?php echo $index === 0 ? '' : 'collapsed'; ?> fw-bold" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-<?php echo $index; ?>">
                                                <i class="dashicons dashicons-album me-2 text-secondary"></i> /<?php echo esc_html($channel); ?> 
                                                <span class="badge bg-primary ms-auto"><?php echo count($files); ?> Tracks</span>
                                            </button>
                                        </h2>
                                        <div id="collapse-<?php echo $index; ?>" class="accordion-collapse collapse <?php echo $index === 0 ? 'show' : ''; ?>" data-bs-parent="#stationAccordion">
                                            <div class="accordion-body p-0">
                                                <ul class="list-group list-group-flush sortable-playlist" data-channel="<?php echo esc_attr($channel); ?>">
                                                    <?php foreach ($files as $file) : 
                                                        $meta = $meta_data[$file] ?? [];
                                                        $display_title = !empty($meta['title']) ? esc_html($meta['title']) : esc_html($file);
                                                        $encoded_meta = htmlspecialchars(json_encode($meta), ENT_QUOTES, 'UTF-8');
                                                    ?>
                                                        <li class="list-group-item d-flex justify-content-between align-items-center bg-white" data-filename="<?php echo esc_attr($file); ?>">
                                                            <div class="d-flex align-items-center">
                                                                <i class="dashicons dashicons-menu drag-handle text-muted me-3" style="cursor: grab;"></i>
                                                                <div>
                                                                    <strong class="d-block text-dark"><?php echo $display_title; ?></strong>
                                                                    <small class="text-muted font-monospace"><?php echo esc_html($file); ?></small>
                                                                </div>
                                                            </div>
                                                            <div>
                                                                <form method="post" action="" class="d-inline" onsubmit="return confirm('Are you sure you want to permanently delete this track?');">
                                                                    <?php wp_nonce_field('melle_vr_track_action'); ?>
                                                                    <input type="hidden" name="channel" value="<?php echo esc_attr($channel); ?>">
                                                                    <input type="hidden" name="filename" value="<?php echo esc_attr($file); ?>">
                                                                    <button type="submit" name="melle_delete_track" class="btn btn-sm btn-outline-danger fw-bold me-1" title="Delete Track">
                                                                        <i class="dashicons dashicons-trash" style="margin-top: 3px;"></i>
                                                                    </button>
                                                                </form>
                                                                
                                                                <button type="button" class="btn btn-sm btn-outline-info fw-bold btn-edit-meta" 
                                                                    data-channel="<?php echo esc_attr($channel); ?>" 
                                                                    data-filename="<?php echo esc_attr($file); ?>" 
                                                                    data-meta="<?php echo $encoded_meta; ?>">
                                                                    <i class="dashicons dashicons-edit"></i> Edit / AI
                                                                </button>
                                                            </div>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        </div> 
    </div>

    <div class="modal fade" id="deleteStationModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg border-top border-danger border-4">
                <div class="modal-header bg-white">
                    <h5 class="modal-title fw-bold text-danger"><i class="dashicons dashicons-warning"></i> Graceful Teardown Required</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body bg-light">
                    <p class="mb-3 text-dark">You are about to delete the station <strong id="del-station-name-display" class="text-danger fs-5"></strong> and purge all of its audio files.</p>
                    <p class="mb-3 text-dark fw-bold">Before deleting these files, you MUST stop the background service on your server to prevent crash loops and log spam. Run the following commands in your terminal:</p>
                    
                    <div class="p-3 bg-dark rounded mb-4">
                        <div class="input-group input-group-sm mb-2">
                            <span class="input-group-text bg-secondary text-white border-secondary">1. Stop & Disable</span>
                            <input type="text" class="form-control font-monospace bg-dark text-success border-secondary" id="cmd-del-1" readonly>
                            <button class="btn btn-secondary btn-copy" type="button" data-clipboard-target="#cmd-del-1">Copy</button>
                        </div>
                        <div class="input-group input-group-sm mb-2">
                            <span class="input-group-text bg-secondary text-white border-secondary">2. Remove Symlink</span>
                            <input type="text" class="form-control font-monospace bg-dark text-success border-secondary" id="cmd-del-2" readonly>
                            <button class="btn btn-secondary btn-copy" type="button" data-clipboard-target="#cmd-del-2">Copy</button>
                        </div>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-secondary text-white border-secondary">3. Clear System Cache</span>
                            <input type="text" class="form-control font-monospace bg-dark text-success border-secondary" id="cmd-del-3" value="sudo systemctl daemon-reload && sudo systemctl reset-failed" readonly>
                            <button class="btn btn-secondary btn-copy" type="button" data-clipboard-target="#cmd-del-3">Copy</button>
                        </div>
                    </div>

                    <form method="post" id="deleteStationForm">
                        <?php wp_nonce_field('melle_vr_station_action'); ?>
                        <input type="hidden" name="station_to_delete" id="del-station-input">
                        
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="confirm-cli-run" required>
                            <label class="form-check-label fw-bold text-danger" for="confirm-cli-run">
                                I confirm that I have run the above CLI commands and the service is stopped.
                            </label>
                        </div>
                </div>
                <div class="modal-footer bg-white border-top">
                    <button type="button" class="btn btn-outline-secondary fw-bold" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="melle_delete_station" class="btn btn-danger fw-bold px-4" id="btn-confirm-delete" disabled>Delete Station Files</button>
                </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="metaModal" tabindex="-1" aria-hidden="true" data-bs-theme="light">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-dark text-white border-0">
                    <h5 class="modal-title fw-bold"><i class="dashicons dashicons-edit text-info"></i> Asset Configuration</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body bg-light">
                    <form id="metaForm">
                        <input type="hidden" id="meta-channel" name="channel">
                        <input type="hidden" id="meta-filename" name="filename">
                        
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-dark">Track Title</label>
                                <input type="text" class="form-control shadow-sm" id="meta-title" name="title">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-dark">Artist</label>
                                <input type="text" class="form-control shadow-sm" id="meta-artist" name="artist">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-dark">Album</label>
                                <input type="text" class="form-control shadow-sm" id="meta-album" name="album">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-dark">Publish Date</label>
                                <input type="date" class="form-control shadow-sm" id="meta-date" name="publish_date">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-dark">WC Product ID</label>
                                <input type="text" class="form-control shadow-sm" id="meta-product-id" name="product_id" placeholder="e.g., 1234">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-dark">YouTube / Video URL</label>
                                <input type="url" class="form-control shadow-sm" id="meta-video" name="video">
                            </div>
                        </div>

                        <div class="card border-info mb-4 shadow-sm">
                            <div class="card-header bg-info text-dark fw-bold d-flex justify-content-between align-items-center">
                                <span><i class="dashicons dashicons-superhero"></i> AI Engine Control</span>
                                <select id="meta-ai-engine" name="ai_engine" class="form-select form-select-sm w-auto d-inline-block">
                                    <option value="models/gemini-2.5-flash">Gemini 2.5 Flash (Fallback)</option>
                                    <option value="gpt">OpenAI GPT-4o</option>
                                </select>
                            </div>
                            <div class="card-body">
                                <div class="d-flex gap-2 mb-3">
                                    <button type="button" id="btn-generate-insight" class="btn btn-primary fw-bold w-100"><i class="dashicons dashicons-format-aside"></i> Upload Audio & Transcribe Lyrics (Takes 30-60s)</button>
                                </div>
                                <label class="form-label fw-bold text-dark">AI Insight, Analysis & Transcribed Lyrics</label>
                                <textarea class="form-control shadow-sm" id="meta-insight" name="ai_insight" rows="8"></textarea>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold text-dark">Creator / Liner Notes</label>
                            <textarea class="form-control shadow-sm" id="meta-notes" name="notes" rows="3"></textarea>
                        </div>

                        <div class="d-flex gap-4 p-3 bg-white border rounded shadow-sm">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="meta-show-notes" name="show_notes" value="true">
                                <label class="form-check-label fw-bold" for="meta-show-notes">Show Notes</label>
                            </div>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="meta-show-video" name="show_video" value="true">
                                <label class="form-check-label fw-bold" for="meta-show-video">Show Video</label>
                            </div>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="meta-is-public" name="is_public" value="true">
                                <label class="form-check-label fw-bold" for="meta-is-public">Public Track</label>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer bg-white border-top">
                    <div id="meta-status" class="me-auto fw-bold"></div>
                    <button type="button" class="btn btn-outline-secondary fw-bold" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-success fw-bold px-4" id="btn-save-meta">Save Configuration</button>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            
            // --- DYNAMIC AI MODEL LOADER ---
            function loadDynamicAIModels() {
                const selectElement = document.getElementById('meta-ai-engine');
                if (!selectElement) return;
                selectElement.innerHTML = '<option value="">Fetching live models...</option>';
                selectElement.disabled = true;

                fetch(ajaxurl + '?action=qrq_get_ai_models')
                    .then(res => res.json())
                    .then(response => {
                        if (response.success && response.data.length > 0) {
                            selectElement.innerHTML = '';
                            selectElement.disabled = false;
                            response.data.forEach(model => {
                                const option = document.createElement('option');
                                option.value = model.id;
                                option.textContent = model.name;
                                selectElement.appendChild(option);
                            });
                            const flashOpt = Array.from(selectElement.options).find(opt => opt.value.includes('gemini-2.5-flash'));
                            if (flashOpt) selectElement.value = flashOpt.value;
                        } else {
                            selectElement.innerHTML = '<option value="models/gemini-2.5-flash">Gemini 2.5 Flash (Fallback)</option><option value="gpt">OpenAI GPT-4o</option>';
                            selectElement.disabled = false;
                        }
                    })
                    .catch(err => {
                        selectElement.innerHTML = '<option value="models/gemini-2.5-flash">Gemini 2.5 Flash (Fallback)</option><option value="gpt">OpenAI GPT-4o</option>';
                        selectElement.disabled = false;
                    });
            }
            loadDynamicAIModels();

            // --- MODAL INITIALIZATION ---
            const metaModalEl = document.getElementById('metaModal');
            document.body.appendChild(metaModalEl); 
            const metaModal = new bootstrap.Modal(metaModalEl);

            const deleteModalEl = document.getElementById('deleteStationModal');
            document.body.appendChild(deleteModalEl);
            const deleteModal = new bootstrap.Modal(deleteModalEl);

            // --- STATION DELETION LOGIC ---
            document.querySelectorAll('.btn-delete-station').forEach(btn => {
                btn.addEventListener('click', () => {
                    const station = btn.dataset.station;
                    document.getElementById('del-station-name-display').textContent = '/' + station;
                    document.getElementById('del-station-input').value = station;
                    
                    document.getElementById('cmd-del-1').value = `sudo systemctl stop ezstream@${station} && sudo systemctl disable ezstream@${station}`;
                    document.getElementById('cmd-del-2').value = `sudo rm /etc/ezstream/${station}.xml`;
                    
                    document.getElementById('confirm-cli-run').checked = false;
                    document.getElementById('btn-confirm-delete').disabled = true;
                    
                    deleteModal.show();
                });
            });

            document.getElementById('confirm-cli-run').addEventListener('change', function() {
                document.getElementById('btn-confirm-delete').disabled = !this.checked;
            });

            // --- SORTABLE PLAYLIST ---
            document.querySelectorAll('.sortable-playlist').forEach(el => {
                new Sortable(el, {
                    handle: '.drag-handle',
                    animation: 150,
                    onEnd: function(evt) {
                        const list = evt.to;
                        const channel = list.dataset.channel;
                        const files = Array.from(list.children).map(li => li.dataset.filename);
                        
                        const formData = new FormData();
                        formData.append('action', 'qrq_update_playlist_order');
                        formData.append('channel', channel);
                        formData.append('nonce', '<?php echo $ajax_nonce; ?>');
                        files.forEach(f => formData.append('files[]', f));

                        fetch(ajaxurl, { method: 'POST', body: formData })
                            .then(res => res.json())
                            .then(data => {
                                if(!data.success) {
                                    alert('Failed to update order. Check folder write permissions on your server.');
                                }
                            }).catch(err => {
                                alert('Network error while sorting. See console.');
                            });
                    }
                });
            });
            
            // --- EDIT METADATA ---
            document.querySelectorAll('.btn-edit-meta').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    const channel = btn.dataset.channel;
                    const filename = btn.dataset.filename;
                    const meta = JSON.parse(btn.dataset.meta || '{}');
                    
                    document.getElementById('meta-channel').value = channel;
                    document.getElementById('meta-filename').value = filename;
                    document.getElementById('meta-title').value = meta.title || '';
                    document.getElementById('meta-artist').value = meta.artist || '';
                    document.getElementById('meta-album').value = meta.album || '';
                    document.getElementById('meta-date').value = meta.publish_date || '';
                    document.getElementById('meta-product-id').value = meta.product_id || '';
                    document.getElementById('meta-video').value = meta.video || '';
                    document.getElementById('meta-notes').value = meta.notes || '';
                    document.getElementById('meta-insight').value = meta.ai_insight || '';
                    
                    document.getElementById('meta-show-notes').checked = meta.show_notes === true;
                    document.getElementById('meta-show-video').checked = meta.show_video === true;
                    document.getElementById('meta-is-public').checked = meta.is_public === true;
                    
                    document.getElementById('meta-status').innerHTML = '';
                    metaModal.show();
                });
            });

            document.getElementById('btn-save-meta').addEventListener('click', () => {
                const form = document.getElementById('metaForm');
                const formData = new FormData(form);
                formData.append('action', 'qrq_save_metadata');
                formData.append('nonce', '<?php echo $ajax_nonce; ?>');

                const status = document.getElementById('meta-status');
                status.innerHTML = '<span class="text-info"><i class="dashicons dashicons-update spin"></i> Saving...</span>';

                fetch(ajaxurl, { method: 'POST', body: formData })
                    .then(res => res.json())
                    .then(data => {
                        if(data.success) {
                            status.innerHTML = '<span class="text-success"><i class="dashicons dashicons-yes-alt"></i> Saved!</span>';
                            setTimeout(() => { metaModal.hide(); location.reload(); }, 1000);
                        } else {
                            status.innerHTML = '<span class="text-danger">Error: ' + data.data + '</span>';
                        }
                    }).catch(err => {
                        status.innerHTML = '<span class="text-danger">Network Error</span>';
                    });
            });

            // --- GENERATE AI INSIGHT ---
            document.getElementById('btn-generate-insight').addEventListener('click', () => {
                const btn = document.getElementById('btn-generate-insight');
                const channel = document.getElementById('meta-channel').value;
                const filename = document.getElementById('meta-filename').value;
                const engine = document.getElementById('meta-ai-engine').value;
                const insightBox = document.getElementById('meta-insight');

                btn.disabled = true;
                btn.innerHTML = '<i class="dashicons dashicons-update spin"></i> Uploading Audio & Transcribing... Please Wait.';

                const formData = new FormData();
                formData.append('action', 'qrq_generate_ai_insight');
                formData.append('channel', channel);
                formData.append('filename', filename);
                formData.append('ai_engine', engine);
                formData.append('nonce', '<?php echo $ajax_nonce; ?>');

                fetch(ajaxurl, { method: 'POST', body: formData })
                    .then(res => res.json())
                    .then(data => {
                        btn.disabled = false;
                        btn.innerHTML = '<i class="dashicons dashicons-format-aside"></i> Upload Audio & Transcribe Lyrics (Takes 30-60s)';
                        if(data.success) {
                            insightBox.value = data.data.text;
                        } else {
                            alert(data.data || 'AI generation failed. Ensure your Gemini API Key is saved in Core Settings.');
                        }
                    }).catch(err => {
                        btn.disabled = false;
                        btn.innerHTML = '<i class="dashicons dashicons-warning"></i> Network Error';
                    });
            });

            // --- COPY BUTTON LOGIC ---
            document.querySelectorAll('.btn-copy').forEach(btn => {
                btn.addEventListener('click', () => {
                    const targetId = btn.getAttribute('data-clipboard-target');
                    const input = document.querySelector(targetId);
                    if (input) {
                        input.select();
                        input.setSelectionRange(0, 99999);
                        navigator.clipboard.writeText(input.value).then(() => {
                            const originalHTML = btn.innerHTML;
                            btn.innerHTML = 'Copied!';
                            btn.classList.add('bg-success', 'text-white');
                            setTimeout(() => { 
                                btn.innerHTML = originalHTML; 
                                btn.classList.remove('bg-success', 'text-white');
                            }, 2000);
                        }).catch(err => {
                            console.error("Failed to copy text: ", err);
                        });
                    }
                });
            });
        });
    </script>
    <?php
}
