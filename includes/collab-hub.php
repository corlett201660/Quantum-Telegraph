<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// ==========================================
// SHARED THEME CSS VARIABLES FOR SHORTCODES
// ==========================================
function qrq_print_theme_vars() {
    static $printed = false;
    if ($printed) return;
    ?>
    <style>
        :root {
            --qrq-bg: #111111;
            --qrq-panel: #1a1a1a;
            --qrq-text: #ffffff;
            --qrq-text-muted: #aaaaaa;
            --qrq-border: #333333;
            --qrq-primary: #00f2ff;
            --qrq-primary-hover: rgba(0, 242, 255, 0.1);
            --qrq-accent: #ff0055;
            --qrq-accent-hover: rgba(255, 0, 85, 0.1);
            --qrq-audio-filter: invert(1) hue-rotate(180deg);
            --qrq-shadow: 0 4px 10px rgba(0,0,0,0.3);
        }
        html[data-theme="light"] {
            --qrq-bg: transparent;
            --qrq-panel: #ffffff;
            --qrq-text: #212529;
            --qrq-text-muted: #6c757d;
            --qrq-border: #ced4da;
            --qrq-primary: #008db3;
            --qrq-primary-hover: rgba(0, 141, 179, 0.1);
            --qrq-accent: #d60047;
            --qrq-accent-hover: rgba(214, 0, 71, 0.1);
            --qrq-audio-filter: none;
            --qrq-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }
        @keyframes pulse-red-shortcode { 
            0% { box-shadow: 0 0 0 0 rgba(255,0,85,0.7); } 
            70% { box-shadow: 0 0 0 8px rgba(255,0,85,0); } 
            100% { box-shadow: 0 0 0 0 rgba(255,0,85,0); } 
        }
    </style>
    <?php
    $printed = true;
}

// ==========================================
// 1. SHORTCODES
// ==========================================

// SINGLE TRACK SHORTCODE
add_shortcode('qrq_track', function($atts) {
    qrq_print_theme_vars();
    $a = shortcode_atts(['channel' => '', 'file' => ''], $atts);
    if(empty($a['channel']) || empty($a['file'])) return 'Missing channel or file parameters.';
    
    $file_url = wp_upload_dir()['baseurl'] . '/qrq_radio_assets/' . $a['channel'] . '/' . $a['file'];
    $file_slug = preg_replace('/\.mp3$/i', '', $a['file']);
    $collab_url = home_url("/radio-player/track/{$a['channel']}/{$file_slug}");

    $meta_file = qrq_get_base_asset_dir() . '/' . $a['channel'] . '/metadata.json';
    $meta_data = file_exists($meta_file) ? json_decode(file_get_contents($meta_file), true) : [];
    
    $display_title = !empty($meta_data[$a['file']]['title']) ? str_replace('\\', '', $meta_data[$a['file']]['title']) : $a['file'];
    
    ob_start();
    ?>
    <div style="background: var(--qrq-panel); color: var(--qrq-text); padding: 15px; border-radius: 8px; border: 1px solid var(--qrq-border); font-family: sans-serif; max-width: 400px; box-shadow: var(--qrq-shadow); transition: all 0.3s ease;">
        <h4 style="margin: 0 0 10px 0; color: var(--qrq-primary); font-size: 16px;">[QRQ] <?php echo esc_html($display_title); ?></h4>
        <audio controls preload="metadata" src="<?php echo esc_url($file_url); ?>" style="width: 100%; height: 35px; outline: none; margin-bottom: 10px; filter: var(--qrq-audio-filter); transition: filter 0.3s ease;"></audio>
        <a href="<?php echo esc_url($collab_url); ?>" target="_blank" style="display: block; text-align: center; background: var(--qrq-primary); color: #fff; text-decoration: none; padding: 8px; border-radius: 4px; font-weight: bold; font-size: 12px; transition: background 0.2s;">ENTER COLLAB HUB</a>
    </div>
    <?php
    return ob_get_clean();
});

// PLAYLIST SHORTCODE
add_shortcode('qrq_playlist', function($atts) {
    qrq_print_theme_vars();
    $a = shortcode_atts(['channel' => ''], $atts);
    if(empty($a['channel'])) return 'Missing channel parameter.';
    
    $dir = qrq_get_base_asset_dir() . '/' . $a['channel'];
    if(!is_dir($dir)) return 'Channel not found.';
    
    $meta_file = $dir . '/metadata.json';
    $meta_data = file_exists($meta_file) ? json_decode(file_get_contents($meta_file), true) : [];
    
    $files = glob($dir . '/*.mp3');
    
    static $playlist_scripts_loaded = false;
    $modal_html = '';
    if (!$playlist_scripts_loaded) {
        $playlist_scripts_loaded = true;
        $modal_html = '
        <div id="qrq-video-modal" style="display:none; position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(0,0,0,0.95); z-index:999999; backdrop-filter:blur(10px); align-items:center; justify-content:center; flex-direction:column;">
            <button onclick="closeQrqVideo()" style="position:absolute; top:20px; right:30px; color:#fff; background:none; border:none; font-size:40px; cursor:pointer; font-weight:100; z-index:1000000;">&times;</button>
            <div style="width:90%; max-width:1200px; aspect-ratio:16/9; box-shadow:0 0 40px rgba(255,0,85,0.4);">
                <iframe id="qrq-yt-iframe" style="width:100%; height:100%; border:none; border-radius:12px;" allowfullscreen allow="autoplay; encrypted-media"></iframe>
            </div>
        </div>
        <script>
            function openQrqVideo(ytid) {
                if(!ytid) return;
                document.getElementById("qrq-yt-iframe").src = "https://www.youtube.com/embed/" + ytid + "?autoplay=1&rel=0";
                document.getElementById("qrq-video-modal").style.display = "flex";
                document.body.style.overflow = "hidden";
            }
            function closeQrqVideo() {
                document.getElementById("qrq-yt-iframe").src = "";
                document.getElementById("qrq-video-modal").style.display = "none";
                document.body.style.overflow = "auto";
            }
            function toggleQrqAudio(targetId) {
                const container = document.getElementById(targetId);
                const audio = container.querySelector("audio");
                if (container.style.display === "none" || container.style.display === "") {
                    container.style.display = "block";
                    audio.play();
                } else {
                    container.style.display = "none";
                    audio.pause();
                }
            }
        </script>
        ';
    }

    ob_start();
    echo $modal_html;
    ?>
    <div style="background: var(--qrq-panel); color: var(--qrq-text); padding: 15px; border-radius: 8px; border: 1px solid var(--qrq-border); font-family: sans-serif; max-width: 500px; box-shadow: var(--qrq-shadow); transition: all 0.3s ease;">
        <h3 style="margin: 0 0 15px 0; color: var(--qrq-primary); border-bottom: 1px solid var(--qrq-border); padding-bottom: 10px;">Channel: /<?php echo esc_html($a['channel']); ?></h3>
        <ul style="list-style: none; padding: 0; margin: 0;">
            <?php foreach($files as $file): 
                $basename = basename($file); 
                $file_slug = preg_replace('/\.mp3$/i', '', $basename);
                $collab_url = home_url("/radio-player/track/{$a['channel']}/{$file_slug}"); 
                $audio_url = wp_upload_dir()['baseurl'] . '/qrq_radio_assets/' . $a['channel'] . '/' . $basename;
                
                $meta = $meta_data[$basename] ?? [];
                
                $title = !empty($meta['title']) ? str_replace('\\', '', $meta['title']) : $basename;
                $artist = !empty($meta['artist']) ? esc_html(str_replace('\\', '', $meta['artist'])) . ' - ' : '';
                $album = !empty($meta['album']) ? ' (' . esc_html(str_replace('\\', '', $meta['album'])) . ')' : '';
                $publish_date = !empty($meta['publish_date']) ? esc_html($meta['publish_date']) : '';
                
                $video_url = !empty($meta['video']) ? esc_url($meta['video']) : '';
                $yt_id = '';
                if ($video_url && preg_match('/(?:youtu\.be\/|youtube\.com\/(?:embed\/|v\/|watch\?v=|watch\?.+&v=))((\w|-){11})/', $video_url, $matches)) {
                    $yt_id = $matches[1];
                }
            ?>
            <li style="display: flex; flex-direction: column; padding: 15px 0; border-bottom: 1px solid var(--qrq-border);">
                <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                    <div style="line-height: 1.3;">
                        <strong style="font-size: 15px; color: var(--qrq-text);"><?php echo esc_html($title); ?></strong><br>
                        <?php if ($artist || $album): ?>
                            <span style="font-size: 12px; color: var(--qrq-text-muted);"><?php echo $artist . $album; ?></span><br>
                        <?php endif; ?>
                        <?php if ($publish_date): ?>
                            <span style="font-size: 11px; color: var(--qrq-primary); display: inline-block; margin-top: 4px; font-family: monospace;">PUBLISHED: <?php echo $publish_date; ?></span>
                        <?php endif; ?>
                    </div>
                    <div style="display: flex; gap: 8px; align-items: center;">
                        
                        <button onclick="toggleQrqAudio('audio-<?php echo $file_slug; ?>')" style="background: var(--qrq-primary); color: #000; border: none; width: 28px; height: 28px; border-radius: 4px; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: filter 0.2s;" title="Play Track">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>
                        </button>

                        <?php if ($yt_id): ?>
                            <button onclick="openQrqVideo('<?php echo esc_js($yt_id); ?>')" style="background: var(--qrq-accent); color: #fff; border: none; width: 28px; height: 28px; border-radius: 4px; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: filter 0.2s;" title="Watch Video">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="15" rx="2" ry="2"></rect><polyline points="17 2 12 7 7 2"></polyline></svg>
                            </button>
                        <?php elseif ($video_url): ?>
                            <a href="<?php echo $video_url; ?>" target="_blank" style="background: var(--qrq-accent); color: #fff; text-decoration: none; width: 28px; height: 28px; border-radius: 4px; display: flex; align-items: center; justify-content: center; transition: filter 0.2s;" title="Watch Video">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="15" rx="2" ry="2"></rect><polyline points="17 2 12 7 7 2"></polyline></svg>
                            </a>
                        <?php endif; ?>

                        <a href="<?php echo esc_url($collab_url); ?>" target="_blank" style="color: var(--qrq-primary); text-decoration: none; font-size: 11px; border: 1px solid var(--qrq-primary); padding: 6px 10px; border-radius: 4px; white-space: nowrap; font-weight: bold;">Collab Hub</a>
                        <?php $vr_url = home_url('/vr-suite/?channel=' . $a['channel'] . '&track=' . $file_slug); ?>
                        <a href="<?php echo esc_url($vr_url); ?>" target="_blank" onclick="document.querySelector('#audio-<?php echo $file_slug; ?> audio').pause();" style="color: var(--qrq-accent); text-decoration: none; font-size: 11px; border: 1px solid var(--qrq-accent); padding: 6px 10px; border-radius: 4px; white-space: nowrap; font-weight: bold; margin-left: 2px;"><i class="fas fa-vr-cardboard"></i> VR</a>
                    </div>
                </div>
                <div id="audio-<?php echo $file_slug; ?>" style="display: none; width: 100%; margin-top: 15px;">
                    <audio controls preload="none" src="<?php echo esc_url($audio_url); ?>" style="width: 100%; height: 35px; filter: var(--qrq-audio-filter); outline: none;"></audio>
                </div>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php
    return ob_get_clean();
});

// LIVE STATION SHORTCODE
add_shortcode('qrq_station', function($atts) {
    qrq_print_theme_vars();
    $a = shortcode_atts(['channel' => ''], $atts);
    if(empty($a['channel'])) return 'Missing channel parameter.';

    $channel = sanitize_title($a['channel']);
    $stream_url = "https://qrjournal.org/icecast/" . $channel;
    
    $ajax_url = admin_url('admin-ajax.php');
    $uid = uniqid();

    ob_start();
    ?>
    <div style="background: var(--qrq-panel); color: var(--qrq-text); padding: 15px; border-radius: 8px; border: 1px solid var(--qrq-primary); font-family: sans-serif; max-width: 400px; box-shadow: 0 0 15px rgba(0,242,255,0.15); transition: all 0.3s ease;">
        <div style="display: flex; align-items: center; margin-bottom: 5px;">
            <h4 style="margin: 0; color: var(--qrq-primary); font-size: 16px; display: flex; align-items: center;">
                <span style="display: inline-block; width: 10px; height: 10px; background: var(--qrq-accent); border-radius: 50%; margin-right: 8px; animation: pulse-red-shortcode 1.5s infinite;"></span>
                LIVE STREAM: /<?php echo esc_html($channel); ?>
            </h4>
        </div>
        
        <div id="qrq-meta-<?php echo $uid; ?>" style="font-size: 11px; font-family: monospace; color: var(--qrq-text-muted); margin-bottom: 15px; text-transform: uppercase;">
            Synchronizing Telemetry...
        </div>

        <audio controls preload="none" src="<?php echo esc_url($stream_url); ?>" style="width: 100%; height: 40px; outline: none; filter: var(--qrq-audio-filter);"></audio>
    </div>

    <script>
    document.addEventListener("DOMContentLoaded", function() {
        const metaDiv = document.getElementById("qrq-meta-<?php echo $uid; ?>");
        const channel = "<?php echo esc_js($channel); ?>";
        const url = "<?php echo esc_js($ajax_url); ?>?action=qrq_get_meta";
        
        function fetchStationMeta() {
            fetch(url).then(res => res.json()).then(data => {
                if(data.success && data.data.icestats && data.data.icestats.source) {
                    let sources = data.data.icestats.source;
                    if(!Array.isArray(sources)) sources = [sources];
                    
                    let activeSource = sources.find(s => s.listenurl && s.listenurl.endsWith("/" + channel));
                    
                    if(activeSource && activeSource.title) {
                        let rawTitle = activeSource.title;
                        const channelMeta = data.data.custom_meta[channel] || {};
                        
                        let trackKey = Object.keys(channelMeta).find(k => k === rawTitle || k === rawTitle + '.mp3' || channelMeta[k].title === rawTitle);
                        
                        if(trackKey && channelMeta[trackKey]) {
                            let custom = channelMeta[trackKey];
                            rawTitle = custom.title || rawTitle;
                            if(custom.artist) rawTitle = custom.artist + " - " + rawTitle;
                        }
                        
                        metaDiv.innerText = "NOW PLAYING: " + rawTitle.replace(/\\/g, '');
                        metaDiv.style.color = "var(--qrq-primary)";
                    } else {
                        metaDiv.innerText = "LIVE BROADCAST / STANDBY";
                        metaDiv.style.color = "var(--qrq-accent)";
                    }
                }
            }).catch(e => { /* fail silently on network blip */ });
        }
        
        fetchStationMeta();
        setInterval(fetchStationMeta, 10000); 
    });
    </script>
    <?php
    return ob_get_clean();
});


// ==========================================
// 2. THE COLLAB HUB PAGE (DEDICATED ASSET)
// ==========================================
function qrq_render_collab_page($channel, $filename_slug) {
    $filename = $filename_slug . '.mp3';
    $base_dir = qrq_get_base_asset_dir();
    $file_path = $base_dir . '/' . $channel . '/' . $filename;
    
    if (!file_exists($file_path)) {
        wp_die("Track not found on server. Looking for: " . esc_html($file_path));
    }

    $meta_file = $base_dir . '/' . $channel . '/metadata.json';
    $meta_data = file_exists($meta_file) ? json_decode(file_get_contents($meta_file), true) : [];
    $track_meta = isset($meta_data[$filename]) ? $meta_data[$filename] : [];
    
    // --- DETERMINE TRACK VR ACCESS PERMISSIONS ---
    $restricted_mounts = array_filter(array_map('trim', explode(',', get_option('melle_vr_restricted_mounts', ''))));
    $req_roles         = array_filter(array_map('trim', explode(',', strtolower(get_option('melle_vr_required_roles', '')))));
    $station_reqs      = get_option('melle_vr_station_reqs', []);
    $track_product_id  = !empty($track_meta['product_id']) ? $track_meta['product_id'] : '';
    
    $is_locked = false;
    $user_has_role = false;
    $is_super = is_super_admin();
    
    if (is_user_logged_in()) {
        $user = wp_get_current_user();
        foreach ($user->roles as $role) {
            if (in_array(strtolower($role), $req_roles)) {
                $user_has_role = true; break;
            }
        }
    }
    
    // Check Global Restriction
    if (in_array($channel, $restricted_mounts) && !$is_super && !$user_has_role) {
        $is_locked = true;
    }
    
    // Check Station & Track WC Requirements
    $required_pids = [];
    if (!empty($station_reqs[$channel])) {
        $required_pids = array_merge($required_pids, array_filter(array_map('trim', explode(',', $station_reqs[$channel]))));
    }
    if (!empty($track_product_id)) {
        $required_pids[] = trim($track_product_id);
    }
    
    if (!empty($required_pids)) {
        $has_bought = false;
        if (is_user_logged_in() && function_exists('wc_customer_bought_product')) {
            $user = wp_get_current_user();
            foreach ($required_pids as $pid) {
                if (wc_customer_bought_product($user->user_email, $user->ID, $pid)) {
                    $has_bought = true;
                    break;
                }
            }
        }
        
        if (!$has_bought && !$is_super && !$user_has_role) {
            $is_locked = true;
        } else {
            $is_locked = false; // Explicit purchase unlocks it, overriding global restriction
        }
    }
    
    // Determine the Store Link for the Modal
    $purchase_url = '';
    if (!empty($track_product_id)) {
        $purchase_url = home_url('/?p=' . $track_product_id);
    } else {
        $purchase_url = get_option('melle_vr_store_link', home_url('/shop/'));
    }
    // ----------------------------------------------
    
    $raw_title = !empty($track_meta['title']) ? str_replace('\\', '', $track_meta['title']) : $filename;
    $display_title = esc_html($raw_title);
    $artist_display = !empty($track_meta['artist']) ? esc_html(str_replace('\\', '', $track_meta['artist'])) : '';

    $audio_url = wp_upload_dir()['baseurl'] . '/qrq_radio_assets/' . $channel . '/' . $filename;
    
    $video_url = '';
    if (!empty($track_meta['show_video']) && !empty($track_meta['video'])) {
        $video_url = $track_meta['video'];
        if (preg_match('/(?:youtu\.be\/|youtube\.com\/(?:embed\/|v\/|watch\?v=|watch\?.+&v=))((\w|-){11})/', $video_url, $matches)) {
            $video_url = 'https://www.youtube.com/embed/' . $matches[1] . '?rel=0';
        }
    }

    $attached_media = isset($track_meta['attached_media']) && is_array($track_meta['attached_media']) ? $track_meta['attached_media'] : [];
    $media_html = '';
    $docs_html = '';
    
    foreach ($attached_media as $asset_url) {
        $clean_path = parse_url($asset_url, PHP_URL_PATH);
        $ext = strtolower(pathinfo($clean_path, PATHINFO_EXTENSION));
        $asset_name = basename($clean_path);
        
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
            $media_html .= "<img src='" . esc_url($asset_url) . "' class='attached-media-img' alt='Attached Media'>";
        } elseif (in_array($ext, ['mp4', 'webm', 'mov'])) {
            $media_html .= "<video controls preload='metadata' src='" . esc_url($asset_url) . "' class='attached-media-vid' aria-hidden='true'></video>";
        } elseif (in_array($ext, ['m4a', 'mp3', 'wav', 'ogg'])) {
            $media_html .= "<div style='margin-bottom: 15px;'><strong style='color:var(--hub-primary); display:block; margin-bottom:5px; font-size:0.85rem;'>" . esc_html($asset_name) . "</strong><audio controls preload='metadata' src='" . esc_url($asset_url) . "' style='width: 100%; outline: none; filter: var(--hub-audio-filter);'></audio></div>";
        } else {
            $docs_html .= "<a href='" . esc_url($asset_url) . "' target='_blank' class='attached-media-doc'>
                <svg width='18' height='18' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' style='margin-right: 10px; color: var(--hub-accent);'><path d='M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z'></path><polyline points='14 2 14 8 20 8'></polyline><line x1='16' y1='13' x2='8' y2='13'></line><line x1='16' y1='17' x2='8' y2='17'></line><polyline points='10 9 9 9 8 9'></polyline></svg>
                " . esc_html($asset_name) . "
            </a>";
        }
    }

    $is_logged_in = is_user_logged_in();
    $current_user_id = $is_logged_in ? get_current_user_id() : 0;
    $partner_id = $is_logged_in ? get_user_meta($current_user_id, 'qrq_partner_id', true) : 0;
    $pair_id = $partner_id ? min($current_user_id, $partner_id) . '_' . max($current_user_id, $partner_id) : $current_user_id;

    $global_insight = !empty($track_meta['ai_insight']) ? $track_meta['ai_insight'] : '';
    $global_history = isset($track_meta['ai_insight_history']) ? $track_meta['ai_insight_history'] : [];
    
    $pair_insight = '';
    $pair_history = [];
    if ($is_logged_in) {
        $pair_insight = !empty($track_meta['pair_insights'][$pair_id]['current']) ? $track_meta['pair_insights'][$pair_id]['current'] : '';
        $pair_history = isset($track_meta['pair_insights'][$pair_id]['history']) ? $track_meta['pair_insights'][$pair_id]['history'] : [];
    }
    
    // UPDATED: Default view defaults to 'pair' if they have a partner
    $default_view = 'global';
    if ($is_logged_in) {
        $saved_view = get_option("qrq_pair_view_{$pair_id}_{$filename}");
        if ($saved_view) {
            $default_view = $saved_view;
        } elseif ($partner_id) {
            $default_view = 'pair';
        } else {
            $default_view = 'global';
        }
    }
    
    get_header('qrq');
    ?>
    <!DOCTYPE html>
    <html lang="en" data-theme="light"> 
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Collab Hub | <?php echo $display_title; ?></title>
        
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700&family=JetBrains+Mono&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
        
        <style>
            :root {
                --hub-bg: #050505;
                --hub-text: #f5f5f5;
                --hub-glass-bg: rgba(20,20,20,0.85);
                --hub-glass-border: rgba(255,255,255,0.15);
                --hub-panel-border: #444;
                --hub-primary: #00f2ff;
                --hub-primary-hover: rgba(0,242,255,0.1);
                --hub-accent: #ff0055;
                --hub-accent-hover: rgba(255,0,85,0.1);
                --hub-chat-bg: #222222;
                --hub-chat-meta: #aaaaaa;
                --hub-input-bg: #111;
                --hub-muted: #888;
                --hub-history-bg: rgba(0,0,0,0.2);
                --hub-audio-filter: invert(1) hue-rotate(180deg);
                --hub-shadow: none;
            }
            html[data-theme="light"] {
                --hub-bg: transparent; 
                --hub-text: #212529;
                --hub-glass-bg: rgba(255,255,255,0.95);
                --hub-glass-border: rgba(0,0,0,0.1);
                --hub-panel-border: #ced4da;
                --hub-primary: #008db3;
                --hub-primary-hover: rgba(0,141,179,0.1);
                --hub-accent: #d60047;
                --hub-accent-hover: rgba(214,0,71,0.1);
                --hub-chat-bg: #ffffff;
                --hub-chat-meta: #6c757d;
                --hub-input-bg: #ffffff;
                --hub-muted: #6c757d;
                --hub-history-bg: rgba(0,0,0,0.03);
                --hub-audio-filter: none;
                --hub-shadow: 0 4px 12px rgba(0,0,0,0.05);
            }

            body { background: var(--hub-bg); color: var(--hub-text); font-family: 'Inter', sans-serif; min-height: 100vh; padding: 20px 0; transition: background 0.4s ease, color 0.4s ease; }
            .glass-panel { background: var(--hub-glass-bg); backdrop-filter: blur(10px); border: 1px solid var(--hub-glass-border); border-radius: 16px; padding: 2rem; box-shadow: var(--hub-shadow); transition: background 0.4s ease, border-color 0.4s ease, box-shadow 0.4s ease; }
            
            .video-collapse { margin-bottom: 20px; }
            .video-collapse summary { cursor: pointer; color: var(--hub-accent); font-weight: bold; background: var(--hub-accent-hover); padding: 12px 15px; border-radius: 8px; border: 1px solid var(--hub-accent-hover); list-style: none; outline: none; transition: background 0.2s, color 0.2s; user-select: none; }
            .video-collapse summary::-webkit-details-marker { display: none; }
            .video-collapse summary:hover { filter: brightness(1.2); }
            .video-collapse summary::before { content: '▶'; display: inline-block; margin-right: 10px; transition: transform 0.2s; font-size: 0.9em; }
            .video-collapse[open] summary::before { transform: rotate(90deg); }
            .video-iframe-wrapper { aspect-ratio: 16/9; width: 100%; margin-top: 15px; border-radius: 8px; overflow: hidden; border: 1px solid var(--hub-panel-border); }

            .history-collapse { margin-top: 20px; }
            .history-collapse summary { cursor: pointer; color: var(--hub-muted); font-size: 0.85rem; font-weight: bold; padding: 10px; border-radius: 8px; border: 1px solid var(--hub-panel-border); background: var(--hub-primary-hover); list-style: none; outline: none; transition: background 0.2s; user-select: none; text-align: center;}
            .history-collapse summary::-webkit-details-marker { display: none; }
            .history-collapse summary:hover { filter: brightness(1.1); }
            .history-collapse[open] summary { border-bottom-left-radius: 0; border-bottom-right-radius: 0; border-bottom: none; }

            .ai-box { background: var(--hub-primary-hover); border: 1px solid var(--hub-primary); border-radius: 8px; padding: 20px; font-family: 'JetBrains Mono', monospace; font-size: 0.9rem; color: var(--hub-text); line-height: 1.6; transition: background 0.4s, border-color 0.4s; }
            .ai-box h1, .ai-box h2, .ai-box h3, .archived-display h1, .archived-display h2, .archived-display h3 { color: var(--hub-primary); margin-top: 20px; margin-bottom: 12px; font-weight: bold; }
            .ai-box h1, .archived-display h1 { font-size: 1.3rem; }
            .ai-box h2, .archived-display h2 { font-size: 1.15rem; border-bottom: 1px solid var(--hub-primary-hover); padding-bottom: 5px; }
            .ai-box h3, .archived-display h3 { font-size: 1rem; }
            .ai-box p, .archived-display p { margin-bottom: 15px; }
            .ai-box ul, .archived-display ul { padding-left: 20px; margin-bottom: 15px; }
            .ai-box li, .archived-display li { margin-bottom: 5px; }
            .ai-box strong, .archived-display strong { color: var(--hub-text); }
            
            .human-box-container { position: sticky; top: 20px; height: calc(100vh - 40px); }
            .human-box { background: var(--hub-history-bg); border: 1px solid var(--hub-panel-border); border-radius: 8px; padding: 15px; display: flex; flex-direction: column; height: 100%; transition: all 0.4s ease; }
            .status-dot { display: inline-block; width: 10px; height: 10px; border-radius: 50%; margin-left: 8px; background-color: #555; transition: all 0.3s ease; }
            .status-dot.online { background-color: #00ff00; box-shadow: 0 0 8px #00ff00; }
            
            .chat-window { flex-grow: 1; overflow-y: auto; margin-bottom: 15px; padding-right: 10px; }
            .chat-msg { margin-bottom: 12px; background: var(--hub-chat-bg); padding: 12px; border-radius: 6px; border-left: 3px solid var(--hub-accent); font-size: 0.95rem; color: var(--hub-text); box-shadow: var(--hub-shadow); animation: fadeIn 0.3s ease; transition: background 0.4s, color 0.4s; }
            .chat-msg.is-global { border-left-color: var(--hub-muted); }
            .chat-msg .meta { font-size: 0.75rem; color: var(--hub-chat-meta); margin-bottom: 6px; }
            @keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }
            
            /* DYNAMIC CHAT FILTERING */
            .chat-window[data-view="pair"] .is-global { display: none !important; }
            
            audio { width: 100%; filter: var(--hub-audio-filter); outline: none; transition: filter 0.4s ease; }
            
            .chat-window::-webkit-scrollbar { width: 6px; }
            .chat-window::-webkit-scrollbar-track { background: transparent; }
            .chat-window::-webkit-scrollbar-thumb { background: var(--hub-muted); border-radius: 3px; }

            .spinner { display: inline-block; width: 14px; height: 14px; border: 2px solid var(--hub-primary-hover); border-radius: 50%; border-top-color: var(--hub-primary); animation: spin 1s ease-in-out infinite; margin-right: 8px; vertical-align: middle; }
            @keyframes spin { to { transform: rotate(360deg); } }
            
            .insight-badge { font-size: 0.65rem; background: var(--hub-primary-hover); color: var(--hub-primary); padding: 3px 8px; border-radius: 12px; vertical-align: middle; margin-left: 10px; border: 1px solid var(--hub-primary-hover); text-transform: uppercase; letter-spacing: 0.5px;}

            .attached-media-img { width: 100%; border-radius: 8px; border: 1px solid var(--hub-panel-border); margin-bottom: 15px; }
            .attached-media-vid { width: 100%; border-radius: 8px; border: 1px solid var(--hub-panel-border); margin-bottom: 15px; }
            .attached-media-doc { display: flex; align-items: center; background: var(--hub-chat-bg); padding: 12px 15px; border-radius: 6px; border: 1px solid var(--hub-panel-border); color: var(--hub-text); text-decoration: none; margin-bottom: 10px; transition: filter 0.2s; box-shadow: var(--hub-shadow); }
            .attached-media-doc:hover { filter: brightness(0.9); }

            .hub-modal-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.85); backdrop-filter: blur(8px); z-index: 99999; display: flex; align-items: center; justify-content: center; opacity: 0; visibility: hidden; transition: all 0.3s ease; }
            .hub-modal-overlay.active { opacity: 1; visibility: visible; }
            .hub-modal { background: var(--hub-glass-bg); border: 1px solid var(--hub-accent); border-radius: 12px; padding: 2rem; max-width: 450px; width: 90%; box-shadow: 0 0 30px rgba(255,0,85,0.2); transform: translateY(20px); transition: transform 0.3s ease; }
            .hub-modal-overlay.active .hub-modal { transform: translateY(0); }
            .hub-modal h4 { color: var(--hub-accent); margin-top: 0; margin-bottom: 15px; font-weight: bold; display: flex; align-items: center; gap: 10px; }
            .hub-modal p { font-size: 0.9rem; margin-bottom: 15px; line-height: 1.6; }
            .hub-modal-actions { display: flex; justify-content: space-between; margin-top: 25px; gap: 10px; }
            .hub-btn-cancel { background: transparent; border: 1px solid var(--hub-muted); color: var(--hub-text); padding: 10px 15px; border-radius: 6px; cursor: pointer; transition: background 0.2s; font-size: 0.85rem;}
            .hub-btn-cancel:hover { background: var(--hub-history-bg); }
            .hub-btn-confirm { background: var(--hub-accent); border: 1px solid var(--hub-accent); color: #fff; padding: 10px 15px; border-radius: 6px; cursor: pointer; font-weight: bold; transition: filter 0.2s; font-size: 0.85rem;}
            .hub-btn-confirm:hover { filter: brightness(1.2); }
        </style>
    </head>
    <body>
        <div class="container" style="padding-bottom: 80px;"> 
            <div class="mb-4 text-center glass-panel" style="padding: 1.5rem;">
                <span class="badge" style="background: var(--hub-primary); color:#fff; padding: 5px 10px; border-radius: 4px; font-weight: bold;">COLLAB HUB</span>
                <h1 class="mt-2" style="font-weight: 900; margin-bottom: 0;"><?php echo $display_title; ?></h1>
                <?php if(!empty($artist_display)) echo "<p class='text-muted' style='color: var(--hub-muted) !important; margin-top: 5px;'>by " . $artist_display . "</p>"; ?>
                <?php if(!empty($track_meta['publish_date'])) echo "<p style='color: var(--hub-primary); font-size: 0.8rem; margin-top: 5px; font-family: monospace;'>RELEASED: " . esc_html($track_meta['publish_date']) . "</p>"; ?>
            </div>

            <div class="row g-4 align-items-start">
                
                <div class="col-lg-6">
                    <div class="glass-panel">
                        <h5 style="color: var(--hub-accent); font-weight:bold; margin-bottom: 15px;">Source Audio</h5>
                        <audio id="collab-audio" controls preload="metadata" src="<?php echo esc_url($audio_url); ?>" class="mb-4"></audio>
                        
                        <?php 
                        $vr_url = home_url('/vr-suite/?channel=' . $channel . '&track=' . $filename_slug); 
                        if ($is_locked): 
                        ?>
                            <a href="#" onclick="document.getElementById('collab-audio').pause(); document.getElementById('premiumUnlockModalOverlay').classList.add('active'); return false;" class="btn mt-3 mb-4" style="background: var(--hub-primary); color:#000; font-weight:bold; width: 100%; padding: 12px; border-radius: 6px; text-decoration: none; display: block; text-align: center; transition: filter 0.2s; box-shadow: 0 0 15px rgba(0,242,255,0.3);"><i class="fas fa-lock me-2"></i> Launch VR Mission (Access Restricted)</a>
                        <?php else: ?>
                            <a href="<?php echo esc_url($vr_url); ?>" target="_blank" onclick="document.getElementById('collab-audio').pause();" class="btn mt-3 mb-4" style="background: var(--hub-primary); color:#000; font-weight:bold; width: 100%; padding: 12px; border-radius: 6px; text-decoration: none; display: block; text-align: center; transition: filter 0.2s; box-shadow: 0 0 15px rgba(0,242,255,0.3);"><i class="fas fa-vr-cardboard me-2"></i> Launch VR Mission (Single Player Loop)</a>
                        <?php endif; ?>

                        <?php if ($video_url): ?>
                            <details class="video-collapse needs-observer">
                                <summary>View Reference Visuals (Video)</summary>
                                <div class="video-iframe-wrapper" aria-hidden="true" tabindex="-1">
                                    <iframe src="<?php echo esc_url($video_url); ?>" style="width:100%; height:100%; border:0;" allow="autoplay; encrypted-media" allowfullscreen tabindex="-1"></iframe>
                                </div>
                            </details>
                        <?php endif; ?>

                        <?php if (!empty($media_html) || !empty($docs_html)): ?>
                            <details class="video-collapse needs-observer">
                                <summary style="color: var(--hub-primary); background: var(--hub-primary-hover); border-color: var(--hub-primary-hover);">
                                    View Track Assets & Media (<?php echo count($attached_media); ?>)
                                </summary>
                                <div style="padding-top: 15px;">
                                    <?php echo $media_html; ?>
                                    <?php echo $docs_html; ?>
                                </div>
                            </details>
                        <?php endif; ?>
                        
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 30px; margin-bottom: 15px;">
                            <h5 style="color: var(--hub-primary); font-weight:bold; margin: 0; display: flex; align-items: center;">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="me-2" style="vertical-align: text-bottom;"><path d="M12 2a10 10 0 1 0 10 10H12V2z"></path><path d="M12 12 2.1 7.1"></path><path d="M12 12l9.9 4.9"></path></svg>
                                AI Insight
                                <?php if ($is_logged_in): ?>
                                    <select id="insight-view-toggle" style="margin-left: 10px; background: var(--hub-primary-hover); color: var(--hub-primary); border: 1px solid var(--hub-primary); border-radius: 4px; font-size: 0.75rem; padding: 2px 5px; outline: none; cursor: pointer;">
                                        <option value="global" <?php selected($default_view, 'global'); ?>>Global Insight</option>
                                        <option value="pair" <?php selected($default_view, 'pair'); ?>>Private Pair Insight</option>
                                    </select>
                                <?php else: ?>
                                    <span class="insight-badge">Global Insight</span>
                                <?php endif; ?>
                            </h5>
                            
                            <?php if ($is_logged_in): ?>
                            <div style="display: flex; gap: 10px;">
                                <button id="frontend-generate-ai" style="background: var(--hub-primary-hover); color: var(--hub-primary); border: 1px solid var(--hub-primary); padding: 5px 12px; border-radius: 4px; font-weight: bold; cursor: pointer; transition: filter 0.2s; font-size: 0.85rem;">
                                    Generate Pair Insight
                                </button>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="ai-box" id="ai-insight-display">
                            <em style='color:var(--hub-muted);'>Loading insights...</em>
                        </div>

                        <div id="insight-history-container"></div>

                    </div>
                </div>

                <div class="col-lg-6 human-box-container">
                    <div class="glass-panel human-box">
                        <h5 style="color: var(--hub-text); font-weight:bold; border-bottom: 1px solid var(--hub-panel-border); padding-bottom: 10px; margin-bottom: 15px; display: flex; align-items: center; justify-content: space-between;">
                            <span>Human Observation Deck</span>
                            <?php if ($is_logged_in): ?>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <select id="chat-view-toggle" style="background: var(--hub-primary-hover); color: var(--hub-text); border: 1px solid var(--hub-panel-border); border-radius: 4px; font-size: 0.75rem; padding: 2px 5px; outline: none; cursor: pointer;">
                                    <option value="global" <?php selected($default_view, 'global'); ?>>Global Observations</option>
                                    <option value="pair" <?php selected($default_view, 'pair'); ?>>Private Pair Observations</option>
                                </select>
                                <button id="export-session-pdf" class="btn btn-sm" style="background: var(--hub-primary-hover); color: var(--hub-primary); border: 1px solid var(--hub-primary); font-size: 0.7rem; font-weight: bold;"><i class="fas fa-file-pdf"></i> Export</button>
                                <span style="font-size: 0.75rem; font-weight: normal; color: var(--hub-muted); display: flex; align-items: center;">
                                    Partner: <span id="partner-status-dot" class="status-dot" title="Offline"></span>
                                </span>
                            </div>
                            <?php endif; ?>
                        </h5>
                        
                        <div class="chat-window" id="chat-window" data-view="<?php echo esc_attr($default_view); ?>">
                            <?php 
                                $rendered_notes_count = 0;

                                if(!empty($track_meta['collab_notes'])) {
                                    foreach($track_meta['collab_notes'] as $note) {
                                        $note_user_id = isset($note['user_id']) ? $note['user_id'] : 0;
                                        $is_pair = ($note_user_id == $current_user_id || $note_user_id == $partner_id);
                                        
                                        $msg_class = $is_pair ? 'chat-msg is-pair' : 'chat-msg is-global';
                                        
                                        echo "<div class='" . $msg_class . "'>";
                                        echo "<div class='meta'><strong>" . esc_html($note['user']) . "</strong> • " . esc_html(date('M j, Y g:i A', strtotime($note['time']))) . "</div>";
                                        echo "<div>" . nl2br(esc_html(wp_unslash($note['note']))) . "</div>";
                                        echo "</div>";
                                        
                                        $rendered_notes_count++;
                                    }
                                }
                                
                                if (!$is_logged_in) {
                                    echo "<div style='text-align:center; padding-top: 50px; color:var(--hub-muted);'>";
                                    echo "<svg width='40' height='40' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' style='margin-bottom:15px;'><rect x='3' y='11' width='18' height='11' rx='2' ry='2'></rect><path d='M7 11V7a5 5 0 0 1 10 0v4'></path></svg>";
                                    echo "<p>Collaboration tools are locked.<br>Please log in to view or participate.</p>";
                                    echo "</div>";
                                } elseif ($rendered_notes_count === 0) {
                                    echo "<p class='text-muted' id='no-notes' style='color:var(--hub-muted) !important;'>No observations recorded for this track yet. Be the first.</p>";
                                }
                            ?>
                        </div>

                        <div class="mt-auto pt-3 border-top" style="border-color: var(--hub-panel-border) !important;">
                            <?php if ($is_logged_in): ?>
                                <textarea id="collab-note-input" class="form-control mb-2" rows="3" placeholder="Record your perspective or reply..." style="background: var(--hub-input-bg); color: var(--hub-text); border: 1px solid var(--hub-panel-border); resize: none; width: 100%; padding: 10px; border-radius: 6px;"></textarea>
                                <button id="submit-collab" style="background: var(--hub-accent); color:#fff; font-weight:bold; width: 100%; border: none; padding: 10px; border-radius: 6px; cursor: pointer; transition: filter 0.2s;">Submit Observation</button>
                            <?php else: ?>
                                <?php $hub_login_url = class_exists('UM') ? um_get_core_page('login') : wp_login_url(home_url($_SERVER['REQUEST_URI'])); ?>
                                <a href="<?php echo esc_url($hub_login_url); ?>" class="btn" style="background: var(--hub-accent); color:#fff; font-weight:bold; width: 100%; padding: 12px; border-radius: 6px; text-decoration: none; display: block; text-align: center;">Log In to Collaborate</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="hub-modal-overlay" id="observerModalOverlay">
            <div class="hub-modal">
                <h4>
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg> 
                    The Observer Effect
                </h4>
                <p><strong>WARNING:</strong> Visuals fundamentally alter auditory perception.</p>
                <p>By revealing these visual assets, your interpretation of the music will be anchored to the creator's visual narrative, potentially collapsing your own unique creative perspective.</p>
                <p style="color: var(--hub-primary); font-weight: bold;">Do you wish to proceed and collapse the waveform?</p>
                <div class="hub-modal-actions">
                    <button class="hub-btn-cancel" id="observerCancel">Maintain Pure Audio</button>
                    <button class="hub-btn-confirm" id="observerConfirm">I Understand - Show Media</button>
                </div>
            </div>
        </div>

        <div class="hub-modal-overlay" id="premiumUnlockModalOverlay">
            <div class="hub-modal" style="border-color: #ffaa00; box-shadow: 0 0 30px rgba(255, 170, 0, 0.2);">
                <h4 style="color: #ffaa00;">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg> 
                    Access Restricted
                </h4>
                <p>This frequency is encrypted. You must acquire the necessary access credentials or expansion pack to launch this specific track in the Spatial-Neural Core.</p>
                <div class="hub-modal-actions" style="margin-top: 30px;">
                    <button class="hub-btn-cancel" onclick="document.getElementById('premiumUnlockModalOverlay').classList.remove('active');">Cancel</button>
                    <a href="<?php echo esc_url($purchase_url); ?>" class="hub-btn-confirm" style="background: #ffaa00; border-color: #ffaa00; color: #000; text-decoration: none; display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-shopping-cart" style="margin-right: 8px;"></i> Unlock Content
                    </a>
                </div>
            </div>
        </div>

        <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
        
        <script>
            window.collabInsights = {
                global: {
                    current: <?php echo json_encode($global_insight); ?>,
                    history: <?php echo json_encode($global_history); ?>
                },
                pair: {
                    current: <?php echo json_encode($pair_insight); ?>,
                    history: <?php echo json_encode($pair_history); ?>
                }
            };

            function renderInsightView(mode) {
                const data = window.collabInsights[mode];
                const displayArea = document.getElementById('ai-insight-display');
                const historyContainer = document.getElementById('insight-history-container');
                
                if (data.current && data.current.trim() !== '') {
                    let rawText = data.current.replace(/&amp;/g, '&').replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&quot;/g, '"').replace(/&#039;/g, "'");
                    displayArea.innerHTML = marked.parse(rawText);
                } else {
                    displayArea.innerHTML = "<i>No " + (mode === 'global' ? 'Global' : 'Private Pair') + " insights generated for this track yet.</i>";
                }
                
                if (data.history && data.history.length > 0) {
                    let historyHtml = '<details class="history-collapse"><summary>View Past Insights Archive (' + data.history.length + ')</summary><div style="border: 1px solid var(--hub-panel-border); border-top: none; border-bottom-left-radius: 8px; border-bottom-right-radius: 8px; padding: 15px; background: var(--hub-history-bg);">';
                    
                    const revHistory = [...data.history].reverse();
                    revHistory.forEach(item => {
                        let text = item.text.replace(/&amp;/g, '&').replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&quot;/g, '"').replace(/&#039;/g, "'");
                        let dateStr = "Unknown Date";
                        if(item.time) {
                            const d = new Date(item.time.replace(' ', 'T'));
                            if(!isNaN(d.getTime())) {
                                dateStr = d.toLocaleString('en-US', {month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit', hour12: true});
                            } else {
                                dateStr = item.time;
                            }
                        }
                        
                        historyHtml += '<div class="mb-4" style="border-bottom: 1px solid var(--hub-panel-border); padding-bottom: 15px;">';
                        historyHtml += '<div style="font-size: 0.8rem; color: var(--hub-muted); font-family: monospace; margin-bottom: 10px;">Version Date: ' + dateStr + '</div>';
                        historyHtml += '<div class="archived-display" style="font-family: \'JetBrains Mono\', monospace; font-size: 0.85rem; color: var(--hub-text);">' + marked.parse(text) + '</div>';
                        historyHtml += '</div>';
                    });
                    
                    historyHtml += '</div></details>';
                    historyContainer.innerHTML = historyHtml;
                    historyContainer.style.display = 'block';
                } else {
                    historyContainer.innerHTML = '';
                    historyContainer.style.display = 'none';
                }
            }
        </script>
        
        <script>
            document.documentElement.setAttribute('data-theme', localStorage.getItem('appTheme') || 'light');

            let currentChatCount = <?php echo intval($rendered_notes_count); ?>;
            let currentDataFingerprint = null; 

            const observerOverlay = document.getElementById('observerModalOverlay');
            const btnCancel = document.getElementById('observerCancel');
            const btnConfirm = document.getElementById('observerConfirm');
            let pendingDetailsElement = null;

            document.querySelectorAll('details.needs-observer').forEach(details => {
                details.querySelector('summary').addEventListener('click', function(e) {
                    const parentDetails = this.parentElement;
                    if (parentDetails.open) return; 
                    if (!parentDetails.hasAttribute('data-acknowledged')) {
                        e.preventDefault(); 
                        pendingDetailsElement = parentDetails;
                        observerOverlay.classList.add('active');
                    }
                });
            });

            btnCancel.addEventListener('click', () => {
                observerOverlay.classList.remove('active');
                pendingDetailsElement = null;
            });

            btnConfirm.addEventListener('click', () => {
                observerOverlay.classList.remove('active');
                if (pendingDetailsElement) {
                    pendingDetailsElement.setAttribute('data-acknowledged', 'true');
                    pendingDetailsElement.open = true; 
                    pendingDetailsElement = null;
                }
            });

            <?php if ($is_logged_in): ?>
            
            // --- UNIFIED VIEW TOGGLE LOGIC ---
            const insightToggle = document.getElementById('insight-view-toggle');
            const chatToggle = document.getElementById('chat-view-toggle');
            const chatWindow = document.getElementById('chat-window');

            function setHubViewMode(mode, fromSync = false) {
                // Update dropdowns
                if (insightToggle && insightToggle.value !== mode) insightToggle.value = mode;
                if (chatToggle && chatToggle.value !== mode) chatToggle.value = mode;

                // Render respective areas
                renderInsightView(mode);
                if (chatWindow) {
                    chatWindow.setAttribute('data-view', mode);
                    chatWindow.scrollTop = chatWindow.scrollHeight;
                }

                // If this wasn't triggered by a server sync, tell the server we changed views
                if (!fromSync) {
                    syncCollabStatus(mode);
                }
            }

            if (insightToggle) {
                insightToggle.addEventListener('change', function() { setHubViewMode(this.value); });
            }
            if (chatToggle) {
                chatToggle.addEventListener('change', function() { setHubViewMode(this.value); });
            }

            function syncCollabStatus(forceView = null) {
                const formData = new FormData();
                formData.append('action', 'qrq_collab_sync');
                formData.append('nonce', '<?php echo wp_create_nonce('qrq_collab_action'); ?>');
                formData.append('channel', '<?php echo esc_js($channel); ?>');
                formData.append('filename', '<?php echo esc_js($filename); ?>');
                if (forceView) {
                    formData.append('view_state', forceView);
                }

                fetch('<?php echo admin_url("admin-ajax.php"); ?>', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if(data.success) {
                        const dot = document.getElementById('partner-status-dot');
                        if (data.data.partner_online) {
                            dot.classList.add('online');
                            dot.title = "Online";
                        } else {
                            dot.classList.remove('online');
                            dot.title = "Offline";
                        }

                        // Entanglement Sync
                        if (data.data.shared_view) {
                            if (insightToggle && insightToggle.value !== data.data.shared_view) {
                                setHubViewMode(data.data.shared_view, true);
                                
                                // Flash both toggles to show they were remotely synced
                                if (insightToggle) {
                                    insightToggle.style.transition = 'box-shadow 0.3s ease';
                                    insightToggle.style.boxShadow = '0 0 15px var(--hub-accent)';
                                    setTimeout(() => insightToggle.style.boxShadow = 'none', 1000);
                                }
                                if (chatToggle) {
                                    chatToggle.style.transition = 'box-shadow 0.3s ease';
                                    chatToggle.style.boxShadow = '0 0 15px var(--hub-accent)';
                                    setTimeout(() => chatToggle.style.boxShadow = 'none', 1000);
                                }
                            }
                        }

                        // Entanglement Refresh
                        if (currentDataFingerprint && data.data.fingerprint !== currentDataFingerprint) {
                            fetch('<?php echo admin_url("admin-ajax.php"); ?>?action=qrq_get_meta')
                            .then(r => r.json())
                            .then(resData => {
                                const freshMeta = resData.data.custom_meta['<?php echo esc_js($channel); ?>']['<?php echo esc_js($filename); ?>'];
                                window.collabInsights.global.current = freshMeta.ai_insight || '';
                                if (freshMeta.pair_insights && freshMeta.pair_insights['<?php echo esc_js($pair_id); ?>']) {
                                    window.collabInsights.pair.current = freshMeta.pair_insights['<?php echo esc_js($pair_id); ?>'].current || '';
                                    window.collabInsights.pair.history = freshMeta.pair_insights['<?php echo esc_js($pair_id); ?>'].history || [];
                                }
                                renderInsightView(document.getElementById('insight-view-toggle').value);
                            });
                        }
                        currentDataFingerprint = data.data.fingerprint;

                        // Render New Chat Messages
                        const serverNotes = data.data.notes;
                        if (serverNotes.length > currentChatCount) {
                            const noNotes = document.getElementById('no-notes');
                            if(noNotes) noNotes.remove();

                            for (let i = currentChatCount; i < serverNotes.length; i++) {
                                const newNote = serverNotes[i];
                                const msgDiv = document.createElement('div');
                                msgDiv.className = newNote.is_pair ? 'chat-msg is-pair' : 'chat-msg is-global';
                                msgDiv.innerHTML = "<div class='meta'><strong>" + newNote.user + "</strong> • " + newNote.time_formatted + "</div><div>" + newNote.note_safe + "</div>";
                                chatWindow.appendChild(msgDiv);
                            }
                            chatWindow.scrollTop = chatWindow.scrollHeight;
                            currentChatCount = serverNotes.length;
                        }
                    }
                }).catch(err => { /* fail silently */ });
            }

            setInterval(syncCollabStatus, 5000);

            document.getElementById('frontend-generate-ai').addEventListener('click', function() {
                const btn = this;
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner"></span> Processing...';
                btn.style.opacity = '0.7';

                const formData = new FormData();
                formData.append('action', 'qrq_generate_ai_insight');
                formData.append('nonce', '<?php echo wp_create_nonce('qrq_collab_action'); ?>');
                formData.append('channel', '<?php echo esc_js($channel); ?>');
                formData.append('filename', '<?php echo esc_js($filename); ?>');
                formData.append('ai_engine', 'models/gemini-2.5-flash');

                fetch('<?php echo admin_url("admin-ajax.php"); ?>', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if(data.success) {
                        const saveForm = new FormData();
                        saveForm.append('action', 'qrq_save_frontend_insight');
                        saveForm.append('nonce', '<?php echo wp_create_nonce('qrq_collab_action'); ?>');
                        saveForm.append('channel', '<?php echo esc_js($channel); ?>');
                        saveForm.append('filename', '<?php echo esc_js($filename); ?>');
                        saveForm.append('insight', data.data.text);
                        
                        return fetch('<?php echo admin_url("admin-ajax.php"); ?>', { method: 'POST', body: saveForm })
                            .then(r => r.json())
                            .then(sData => { if(sData.success) window.location.reload(); });
                    }
                }).catch(err => {
                    btn.disabled = false;
                    btn.innerHTML = 'Generate Pair Insight';
                    btn.style.opacity = '1';
                });
            });

            document.getElementById('submit-collab').addEventListener('click', function() {
                const input = document.getElementById('collab-note-input');
                const text = input.value.trim();
                if(!text) return;
                this.disabled = true;
                this.innerText = 'Transmitting...';
                const formData = new FormData();
                formData.append('action', 'qrq_add_collab_note');
                formData.append('nonce', '<?php echo wp_create_nonce('qrq_collab_action'); ?>');
                formData.append('channel', '<?php echo esc_js($channel); ?>');
                formData.append('filename', '<?php echo esc_js($filename); ?>');
                formData.append('note', text);

                fetch('<?php echo admin_url("admin-ajax.php"); ?>', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    this.disabled = false;
                    this.innerText = 'Submit Observation';
                    if(data.success) {
                        input.value = '';
                        syncCollabStatus();
                    }
                });
            });

            const exportBtn = document.getElementById('export-session-pdf');
            if (exportBtn) {
                exportBtn.addEventListener('click', function() {
                    if (!window.jspdf || !window.jspdf.jsPDF) {
                        alert('PDF engine is still loading. Please try again in a moment.');
                        return;
                    }
                    
                    const { jsPDF } = window.jspdf;
                    const doc = new jsPDF();
                    
                    doc.setFont("helvetica", "bold");
                    doc.setFontSize(16);
                    doc.text("Collab Hub Session Logs", 20, 20);
                    
                    doc.setFontSize(12);
                    doc.text("Subject: <?php echo esc_js($display_title); ?>", 20, 30);
                    
                    let y = 40;
                    const insightElement = document.getElementById('ai-insight-display');
                    
                    if (insightElement) {
                        const insightText = insightElement.innerText;
                        doc.setFont("helvetica", "bold");
                        doc.text("Current Active Insight:", 20, y);
                        y += 7;
                        
                        doc.setFont("helvetica", "normal");
                        doc.setFontSize(10);
                        const splitInsight = doc.splitTextToSize(insightText, 170);
                        doc.text(splitInsight, 20, y);
                        y += (splitInsight.length * 5) + 10;
                    }
                    
                    if (y > 260) { doc.addPage(); y = 20; }
                    
                    doc.setFont("helvetica", "bold");
                    doc.setFontSize(12);
                    doc.text("Human Observation Deck Logs:", 20, y);
                    y += 7;
                    
                    const chatMsgs = document.querySelectorAll('.chat-msg');
                    if (chatMsgs.length === 0) {
                        doc.setFont("helvetica", "italic");
                        doc.setFontSize(10);
                        doc.text("No observations recorded in this session.", 20, y);
                    } else {
                        chatMsgs.forEach(msg => {
                            if (y > 270) { doc.addPage(); y = 20; }
                            
                            const meta = msg.querySelector('.meta').innerText;
                            const text = msg.querySelector('div:not(.meta)').innerText;
                            
                            doc.setFont("helvetica", "bold");
                            doc.setFontSize(9);
                            doc.text(meta, 20, y);
                            y += 5;
                            
                            doc.setFont("helvetica", "normal");
                            doc.setFontSize(10);
                            const splitText = doc.splitTextToSize(text, 170);
                            doc.text(splitText, 20, y);
                            y += (splitText.length * 5) + 5;
                        });
                    }
                    
                    const filename = "Collab_Session_<?php echo esc_js($filename_slug); ?>.pdf";
                    doc.save(filename);
                });
            }
            
            document.addEventListener("DOMContentLoaded", function() {
                // Initialize View using the master function
                const initialView = document.getElementById('insight-view-toggle') ? document.getElementById('insight-view-toggle').value : 'global';
                setHubViewMode(initialView);
            });
            <?php else: ?>
            document.addEventListener("DOMContentLoaded", function() {
                renderInsightView('global');
            });
            <?php endif; ?>
        </script>
    </body>
    </html>
    <?php
}

// ==========================================
// 3. USER PARTNER PAIRING SYSTEM
// ==========================================
add_action( 'show_user_profile', 'qrq_partner_profile_field' );
add_action( 'edit_user_profile', 'qrq_partner_profile_field' );
function qrq_partner_profile_field( $user ) {
    $partner_id = get_user_meta( $user->ID, 'qrq_partner_id', true );
    ?>
    <h3>QRQ Collaboration Partner</h3>
    <table class="form-table">
        <tr>
            <th><label for="qrq_partner_id">Partner User ID</label></th>
            <td>
                <input type="number" name="qrq_partner_id" id="qrq_partner_id" value="<?php echo esc_attr( $partner_id ); ?>" class="regular-text" />
                <p class="description">Enter the WordPress User ID of this person's collaboration partner. They will only see each other's track observations.</p>
            </td>
        </tr>
    </table>
    <?php
}

add_action( 'personal_options_update', 'qrq_save_partner_field' );
add_action( 'edit_user_profile_update', 'qrq_save_partner_field' );
function qrq_save_partner_field( $user_id ) {
    if ( !current_user_can( 'edit_user', $user_id ) ) return false;
    update_user_meta( $user_id, 'qrq_partner_id', intval( $_POST['qrq_partner_id'] ) );
}

add_action('wp_ajax_qrq_save_frontend_insight', 'qrq_ajax_save_frontend_insight');
function qrq_ajax_save_frontend_insight() {
    if (!is_user_logged_in() || !check_ajax_referer('qrq_collab_action', 'nonce', false)) {
        wp_send_json_error('Unauthorized');
    }
    
    $channel = sanitize_title($_POST['channel']);
    $filename = sanitize_file_name($_POST['filename']);
    $insight = wp_unslash($_POST['insight']);
    
    $current_user_id = get_current_user_id();
    $partner_id = get_user_meta($current_user_id, 'qrq_partner_id', true);
    $partner_id = $partner_id ? $partner_id : 0;
    $pair_id = $partner_id ? min($current_user_id, $partner_id) . '_' . max($current_user_id, $partner_id) : $current_user_id;

    $meta_file = qrq_get_base_asset_dir() . '/' . $channel . '/metadata.json';
    $meta_data = file_exists($meta_file) ? json_decode(file_get_contents($meta_file), true) : [];
    
    if (!isset($meta_data[$filename]['pair_insights'])) {
        $meta_data[$filename]['pair_insights'] = [];
    }
    if (!isset($meta_data[$filename]['pair_insights'][$pair_id])) {
        $meta_data[$filename]['pair_insights'][$pair_id] = ['current' => '', 'history' => []];
    }
    
    if (!empty($meta_data[$filename]['pair_insights'][$pair_id]['current'])) {
        $meta_data[$filename]['pair_insights'][$pair_id]['history'][] = [
            'time' => current_time('mysql'),
            'text' => $meta_data[$filename]['pair_insights'][$pair_id]['current']
        ];
    }
    
    $meta_data[$filename]['pair_insights'][$pair_id]['current'] = $insight;
    
    if (file_put_contents($meta_file, json_encode($meta_data, JSON_PRETTY_PRINT))) {
        wp_send_json_success();
    } else {
        wp_send_json_error('Failed to save to metadata.json');
    }
}

// ==========================================
// 4. DYNAMIC URL ROUTER FOR COLLAB HUB
// ==========================================
add_action('template_redirect', 'qrq_collab_hub_router');
function qrq_collab_hub_router() {
    $uri = $_SERVER['REQUEST_URI'];
    
    // Intercept URLs matching /radio-player/track/{channel}/{track}
    if (preg_match('#/radio-player/track/([^/?]+)/([^/?]+)#i', $uri, $matches)) {
        $channel = sanitize_title($matches[1]);
        $track   = sanitize_text_field($matches[2]); 
        
        // Render the Collab Hub and halt standard WordPress loading
        if (function_exists('qrq_render_collab_page')) {
            qrq_render_collab_page($channel, $track);
            exit;
        }
    }
}
