<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// ==========================================
// 1. GLOBAL CALL-TO-ACTION BANNER
// ==========================================
add_action( 'wp_footer', function() {
    if ( get_option( 'qrq_is_live' ) != 1 ) return; 
    ?>
    <style>
        :root { --toast-bg: #000; --toast-border: #00f2ff; --toast-text: #fff; --toast-accent: #00f2ff; }
        html[data-theme="light"] { --toast-bg: #fff; --toast-border: #0088cc; --toast-text: #111; --toast-accent: #0088cc; }
        #qrq-toast-link { position: fixed; bottom: 20px; right: 20px; z-index: 999999; background: var(--toast-bg); border: 2px solid var(--toast-border); border-radius: 12px; padding: 15px 20px; color: var(--toast-text); text-decoration: none; display: flex; align-items: center; box-shadow: 0 0 20px rgba(0,242,255,0.4); font-family: 'Inter', sans-serif; transition: all 0.3s; }
        #qrq-toast-link:hover { transform: scale(1.05); color: var(--toast-text); }
        .pulse-icon { width: 12px; height: 12px; background: #ff0055; border-radius: 50%; margin-right: 12px; animation: pulse-red 1.5s infinite; }
        .toast-iso { font-size: 10px; color: var(--toast-accent); font-weight: bold; letter-spacing: 1px; }
        @keyframes pulse-red { 0% { box-shadow: 0 0 0 0 rgba(255,0,85,0.7); } 70% { box-shadow: 0 0 0 10px rgba(255,0,85,0); } 100% { box-shadow: 0 0 0 0 rgba(255,0,85,0); } }
        @media (max-width: 576px) { #qrq-toast-link { bottom: 15px; right: 15px; padding: 10px 15px; } .toast-iso { font-size: 9px; } }
    </style>
    <a href="<?php echo home_url('/radio-player/'); ?>" target="QRQPlayer" id="qrq-toast-link" onclick="window.open(this.href, 'QRQPlayer', 'width=400,height=800'); return false;">
        <div class="pulse-icon"></div>
        <div>
            <div class="toast-iso">ISOCHRONIC SYNC</div>
            <div style="font-weight: 900;">LISTEN LIVE</div>
        </div>
    </a>
    <?php
});

// ==========================================
// 2. THE QUANTUM TELEGRAPH PLAYER UI
// ==========================================
function qrq_render_player() {
    $default_stream = str_replace('.m3u', '', get_option('qrq_stream_url', 'https://qrjournal.org/stream'));
    $json_url = get_option('qrq_icecast_json_url', 'https://qrjournal.org/status-json.xsl');
    $available_streams = [];
    $fetch_error = '';

    if (!empty($json_url)) {
        $response = wp_remote_get($json_url, ['timeout' => 5, 'sslverify' => false]);
        if (!is_wp_error($response)) {
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            if (isset($data['icestats']['source'])) {
                $sources = $data['icestats']['source'];
                $raw_streams = isset($sources['listenurl']) ? [$sources] : $sources;
                
                // Fetch Restriction Rules
                $restricted_mounts = array_filter(array_map('trim', explode(',', get_option('melle_vr_restricted_mounts', ''))));
                $station_reqs      = get_option('melle_vr_station_reqs', []);
                $radio_excluded    = array_filter(array_map('trim', explode(',', get_option('melle_vr_radio_excluded_mounts', 'admin, fallback'))));
                $radio_allowed     = array_filter(array_map('trim', explode(',', get_option('melle_vr_radio_allowed_mounts', ''))));
                
                $req_roles         = array_filter(array_map('trim', explode(',', strtolower(get_option('melle_vr_required_roles', '')))));
                $is_super          = is_super_admin();
                $user_has_role     = false;
                $user_email        = '';
                $user_id           = 0;
                
                if (is_user_logged_in()) {
                    $user = wp_get_current_user();
                    $user_email = $user->user_email;
                    $user_id = $user->ID;
                    foreach ($user->roles as $role) {
                        if (in_array(strtolower($role), $req_roles)) {
                            $user_has_role = true; break;
                        }
                    }
                }

                // Filter the streams based on access rights
                foreach ($raw_streams as $stream) {
                    if (empty($stream['listenurl'])) continue;
                    
                    $parsed_listen = parse_url($stream['listenurl']);
                    $mountpoint = isset($parsed_listen['path']) ? ltrim($parsed_listen['path'], '/') : '';
                    
                    // Exclude hidden or specifically unallowed radio mounts
                    if (in_array($mountpoint, $radio_excluded)) continue;
                    if (!empty($radio_allowed) && !in_array($mountpoint, $radio_allowed)) continue;
                    
                    $is_locked = false;
                    
                    // Check Global Restrictions
                    if (in_array($mountpoint, $restricted_mounts) && !$is_super && !$user_has_role) {
                        $is_locked = true;
                    }
                    
                    // Check Specific WooCommerce Requirements
                    if (!empty($station_reqs[$mountpoint])) {
                        $req_pids = array_filter(array_map('trim', explode(',', $station_reqs[$mountpoint])));
                        $has_bought = false;
                        
                        if (is_user_logged_in() && function_exists('wc_customer_bought_product')) {
                            foreach ($req_pids as $pid) {
                                if (wc_customer_bought_product($user_email, $user_id, $pid)) {
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
                    
                    if (!$is_locked) {
                        $available_streams[] = $stream;
                    }
                }
            }
        } else {
            $fetch_error = $response->get_error_message();
        }
    }
    ?>
<?php get_header('qrq'); ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
        <title>Quantum Telegraph | Spatial-Neural Player</title>
        <script>
            const savedTheme = localStorage.getItem('appTheme') || 'light';
            document.documentElement.setAttribute('data-theme', savedTheme);
        </script>
        <link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css'>
        <link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css'>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;900&family=JetBrains+Mono&display=swap" rel="stylesheet">
        
        <style>
            :root { 
                --bg: #050505; --text-main: #ffffff; --text-muted: #888888;
                --cyan: #00f2ff; --gold: #ffff00; --danger: #ff0055; 
                --glass-bg: rgba(20, 20, 20, 0.8); --glass-border: rgba(255,255,255,0.1);
                --pill-bg: rgba(255,255,255,0.05); --term-bg: rgba(0,0,0,0.5); --term-border: #222222;
                --input-bg: #111; --input-border: #333;
            }
            :root[data-theme="light"] {
                --bg: transparent; 
                --text-main: #111111; --text-muted: #555555;
                --cyan: #0088cc; --gold: #cc9900; --danger: #cc0000;
                --glass-bg: rgba(255, 255, 255, 0.8); --glass-border: rgba(0, 0, 0, 0.1);
                --pill-bg: rgba(0, 0, 0, 0.05); --term-bg: rgba(255, 255, 255, 0.6); --term-border: #cccccc;
                --input-bg: #fff; --input-border: #ccc;
            }
            body { background: var(--bg); color: var(--text-main); font-family: 'Inter', sans-serif; min-height: 100vh; display: flex; flex-direction: column; overflow-x: hidden; transition: background-color 0.4s ease, color 0.4s ease; }
            .glass-panel { background: var(--glass-bg); backdrop-filter: blur(10px); border: 1px solid var(--glass-border); border-radius: 24px; padding: 2rem; }
            .status-pill { font-family: 'JetBrains Mono', monospace; font-size: 0.7rem; letter-spacing: 1px; padding: 4px 12px; border-radius: 50px; background: var(--pill-bg); display: inline-block; }
            .visualizer-area { height: 100px; display: flex; align-items: center; justify-content: center; gap: 4px; }
            .bar { width: 4px; height: 20px; background: var(--cyan); border-radius: 2px; }
            .bar.active { animation: bar-dance 1s infinite alternate ease-in-out; }
            @keyframes bar-dance { 0% { height: 10px; } 100% { height: 70px; } }
            audio { width: 100%; height: 45px; margin-top: 15px; filter: invert(1) hue-rotate(180deg); }
            :root[data-theme="light"] audio { filter: none; }
            .stream-select { background-color: var(--input-bg); color: var(--text-main); border: 1px solid var(--input-border); font-family: 'JetBrains Mono', monospace; font-size: 0.8rem; }
            .stream-select:focus { box-shadow: none; border-color: var(--cyan); color: var(--text-main); background-color: var(--input-bg); }
            @media (max-width: 576px) { .glass-panel { padding: 1.25rem; border-radius: 16px; } .display-6 { font-size: 1.5rem; } .visualizer-area { height: 70px; } .action-buttons { flex-wrap: wrap; gap: 8px; justify-content: center !important; } .action-buttons button { flex: 1; min-width: 30%; font-size: 0.8rem; padding: 8px 4px; } }
            .now-playing-box { background: var(--pill-bg); border: 1px solid var(--glass-border); border-radius: 8px; padding: 10px; margin-top: 15px; text-align: left; }
            .title-container { width: 100%; overflow: hidden; white-space: nowrap; position: relative; margin-top: 4px; }
            .title-text { display: inline-block; color: var(--cyan); font-size: 1rem; font-weight: bold; }
            .needs-scroll { padding-left: 100%; animation: marquee 12s linear infinite; }
            @keyframes marquee { 0% { transform: translateX(0); } 100% { transform: translateX(-100%); } }
            .custom-close-btn { background-color: rgba(255, 255, 255, 0.9); border-radius: 50%; opacity: 0.8; padding: 0.6rem; margin-top: 0; }
            .custom-close-btn:hover { opacity: 1; }
        </style>
    </head>
    <body>

    <div class="container my-auto py-3">
        <div class="row justify-content-center">
            <div class="col-12 col-md-8 col-lg-5">
                <div class="text-center mb-4">
                    <div class="status-pill text-info mb-3" id="sys-status" style="color: var(--cyan) !important;">INITIALIZING...</div>
                    <h1 class="display-6 fw-900 mb-0 text-uppercase">QUANTUM TELEGRAPH</h1>
                    <p class="small letter-spacing-2" style="color: var(--text-muted);">SPATIAL-NEURAL BROADCAST</p>
                </div>

                <div class="glass-panel text-center shadow-lg">
                    <div class="visualizer-area" id="viz"></div>
                    <div id="stat-text" class="fw-bold mt-2" style="color: var(--gold); font-size: 0.9rem;">STATION STANDBY</div>
                    
                    <?php 
                    $active_audio_src = esc_url($default_stream);
                    if (!empty($available_streams)): 
                    ?>
                        <select id="stream-selector" class="form-select form-select-sm mt-3 stream-select">
                            <?php foreach($available_streams as $index => $stream): ?>
                                <?php 
                                    $raw_listen_url = esc_url($stream['listenurl']);
                                    $parsed_listen = parse_url($raw_listen_url);
                                    $mountpoint = isset($parsed_listen['path']) ? $parsed_listen['path'] : '';
                                    $secure_listen_url = "https://qrjournal.org/icecast" . $mountpoint;
                                    
                                    if ($index === 0) { $active_audio_src = esc_url($secure_listen_url); }
                                    $title = !empty($stream['server_name']) ? esc_html($stream['server_name']) : basename($secure_listen_url); 
                                ?>
                                <option value="<?php echo esc_url($secure_listen_url); ?>">
                                    [CHANNEL] <?php echo $title; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    <?php else: ?>
                        <div class="alert alert-dark mt-3" style="border-color: #333; color: #888; font-size: 0.85rem;">
                            No authorized streams available.
                        </div>
                    <?php endif; ?>

                    <div class="now-playing-box">
                        <div class="d-flex justify-content-between align-items-center">
                            <div style="font-family: 'JetBrains Mono', monospace; font-size: 0.65rem; color: var(--text-muted); letter-spacing: 1px;">NOW PLAYING</div>
                            <div class="d-flex gap-3 align-items-center">
                                <button class="btn btn-sm p-0 border-0 d-none" id="notes-btn" data-bs-toggle="modal" data-bs-target="#notesModal" title="View Notes" style="color: var(--gold); font-size: 1.1rem;"><i class="fas fa-sticky-note"></i></button>
                                <button class="btn btn-sm p-0 border-0 d-none" id="video-btn" title="Watch Video" style="color: var(--danger); font-size: 1.1rem;"><i class="fas fa-play-circle"></i></button>
                                <button class="btn btn-sm p-0 border-0 d-none" id="collab-hub-btn" title="Enter Collab Hub" style="color: var(--cyan); font-size: 1.1rem;"><i class="fas fa-user-astronaut"></i></button>
                                <button class="btn btn-sm p-0 border-0 d-none" id="vr-game-btn" title="Launch VR Mission" style="color: #00f2ff; font-size: 1.1rem;"><i class="fas fa-vr-cardboard"></i></button>
                            </div>
                        </div>
                        <div class="title-container" id="titleContainer">
                            <div id="current-song" class="title-text">Awaiting Metadata...</div>
                        </div>
                    </div>

                    <audio id="aud" controls preload="none">
                        <source src="<?php echo $active_audio_src; ?>" type="audio/mpeg">
                    </audio>

                    <div class="d-flex justify-content-between action-buttons mt-4 mb-3 border-bottom pb-3" style="border-color: var(--glass-border) !important;">
                        <button class="btn btn-sm btn-outline-secondary border-0" onclick="location.reload()"><i class="fas fa-sync"></i> RE-SYNC</button>
                        <button class="btn btn-sm border-0" id="theme-btn" style="color: var(--cyan);"><i class="fas fa-adjust"></i> THEME</button>
                        <button class="btn btn-sm btn-outline-danger border-0" onclick="window.close()"><i class="fas fa-power-off"></i> EXIT</button>
                    </div>
                    
                    <?php 
                    // Dynamic Authentication URLs
                    $login_url = class_exists('UM') ? um_get_core_page('login') : wp_login_url( get_permalink() );
                    $register_url = class_exists('UM') ? um_get_core_page('register') : wp_registration_url();
                    $profile_url = class_exists('UM') ? um_get_core_page('user') : get_edit_profile_url();
                    $logout_url = class_exists('UM') ? um_get_core_page('logout') : wp_logout_url( get_permalink() );
                    $store_link = get_option('melle_vr_store_link', '');
                    ?>

                    <div class="row g-2 mb-2">
                        <?php if ( ! is_user_logged_in() ) : ?>
                            <div class="col-6">
                                <a href="<?php echo esc_url($login_url); ?>" class="btn btn-sm btn-outline-warning w-100 fw-bold"><i class="fas fa-sign-in-alt me-1"></i> Login</a>
                            </div>
                            <div class="col-6">
                                <a href="<?php echo esc_url($register_url); ?>" class="btn btn-sm btn-outline-info w-100 fw-bold"><i class="fas fa-user-plus me-1"></i> Register</a>
                            </div>
                        <?php else : ?>
                            <div class="col-6">
                                <a href="<?php echo esc_url($profile_url); ?>" class="btn btn-sm btn-outline-warning w-100 fw-bold"><i class="fas fa-user me-1"></i> Profile</a>
                            </div>
                            <div class="col-6">
                                <a href="<?php echo esc_url($logout_url); ?>" class="btn btn-sm btn-outline-danger w-100 fw-bold"><i class="fas fa-sign-out-alt me-1"></i> Logout</a>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if ( !empty($store_link) ) : ?>
                    <div class="mt-2">
                        <a href="<?php echo esc_url($store_link); ?>" target="_blank" class="btn btn-sm btn-warning w-100 fw-bold text-dark text-uppercase" style="box-shadow: 0 0 10px rgba(255, 193, 7, 0.4); letter-spacing: 1px;">
                            <i class="fas fa-shopping-cart me-1"></i> Unlock Premium Tracks
                        </a>
                    </div>
                    <?php endif; ?>

                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="notesModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="background: var(--glass-bg); color: var(--text-main); backdrop-filter: blur(10px); border: 1px solid var(--glass-border);">
          <div class="modal-header border-0 pb-0">
            <h5 class="modal-title" style="color: var(--cyan);"><i class="fas fa-info-circle"></i> Track Details</h5>
            <button type="button" class="btn-close custom-close-btn" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body" id="notes-content" style="font-family: 'JetBrains Mono', monospace; font-size: 0.85rem; line-height: 1.6;">
          </div>
        </div>
      </div>
    </div>

    <div class="modal fade" id="observerEffectModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-lg" style="background: var(--glass-bg); color: var(--text-main); backdrop-filter: blur(10px); border: 1px solid var(--danger);">
          <div class="modal-header border-0 pb-0">
            <h5 class="modal-title" style="color: var(--danger);"><i class="fas fa-eye"></i> The Observer Effect</h5>
          </div>
          <div class="modal-body" style="font-family: 'JetBrains Mono', monospace; font-size: 0.85rem; line-height: 1.6;">
            <p><strong>WARNING:</strong> Visuals fundamentally alter auditory perception.</p>
            <p>By watching this video, your interpretation of the music will be anchored to the director's visual narrative, potentially collapsing your own unique creative perspective.</p>
            <p style="color: var(--gold);">Do you wish to proceed and collapse the waveform?</p>
          </div>
          <div class="modal-footer border-0 d-flex justify-content-between">
            <button type="button" class="btn btn-sm btn-outline-secondary border-0" data-bs-dismiss="modal">Maintain Pure Audio</button>
            <button type="button" class="btn btn-sm" id="acknowledge-observer-btn" style="background-color: var(--danger); color: #fff;">I Understand - Show Video</button>
          </div>
        </div>
      </div>
    </div>

    <div class="modal fade" id="videoModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
      <div class="modal-dialog modal-fullscreen">
        <div class="modal-content" style="background: var(--glass-bg); color: var(--text-main); backdrop-filter: blur(10px); border: none;">
          <div class="modal-header border-0 pb-0 position-absolute top-0 end-0 m-3 z-3">
            <button type="button" class="btn-close custom-close-btn shadow" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body p-0 d-flex align-items-center justify-content-center">
            <div class="w-100 h-100 d-flex align-items-center justify-content-center">
                <div class="w-100 ratio ratio-16x9" style="max-height: 100vh;">
                    <iframe id="yt-iframe" src="" allowfullscreen allow="autoplay; encrypted-media"></iframe>
                </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        const a = document.getElementById('aud');
        const s = document.getElementById('sys-status');
        const st = document.getElementById('stat-text');
        const viz = document.getElementById('viz');
        const themeBtn = document.getElementById('theme-btn');
        const streamSelector = document.getElementById('stream-selector');
        
        const currentSongEl = document.getElementById('current-song');
        const titleContainer = document.getElementById('titleContainer');
        const notesBtn = document.getElementById('notes-btn');
        const notesContent = document.getElementById('notes-content');
        const videoBtn = document.getElementById('video-btn');
        const collabBtn = document.getElementById('collab-hub-btn');
        const vrBtn = document.getElementById('vr-game-btn'); 
        
        const ajaxUrl = '<?php echo admin_url("admin-ajax.php"); ?>';
        let currentStreamUrl = a.querySelector('source').src;
        let songPollInterval;

        document.getElementById('videoModal').addEventListener('hidden.bs.modal', function () {
            document.getElementById('yt-iframe').src = '';
        });

        themeBtn.addEventListener('click', () => {
            const currentTheme = document.documentElement.getAttribute('data-theme');
            const newTheme = currentTheme === 'light' ? 'dark' : 'light';
            document.documentElement.setAttribute('data-theme', newTheme);
            localStorage.setItem('appTheme', newTheme);
            log(`Theme adjusted to ${newTheme.toUpperCase()} mode.`, 'var(--cyan)');
        });

        if (streamSelector) {
            const savedStream = localStorage.getItem('qrq_saved_station');
            let optionExists = Array.from(streamSelector.options).some(opt => opt.value === savedStream);
            
            if (savedStream && optionExists) {
                streamSelector.value = savedStream;
                currentStreamUrl = savedStream;
                a.src = currentStreamUrl;
                a.load();
            } else {
                streamSelector.value = currentStreamUrl;
            }

            streamSelector.addEventListener('change', function() {
                const newUrl = this.value;
                localStorage.setItem('qrq_saved_station', newUrl);
                
                const streamName = this.options[this.selectedIndex].text;
                
                log(`Rerouting to ${streamName}...`, 'var(--cyan)');
                st.innerText = "SWITCHING CHANNELS...";
                currentSongEl.innerText = "Refreshing Data...";
                currentSongEl.classList.remove('needs-scroll');
                notesBtn.classList.add('d-none');
                videoBtn.classList.add('d-none');
                collabBtn.classList.add('d-none');
                if (vrBtn) vrBtn.classList.add('d-none');
                setViz(false);
                
                currentStreamUrl = newUrl;
                a.src = currentStreamUrl;
                a.load();
                a.play().catch(() => log("User action required to play new stream.", "var(--gold)"));
                
                fetchMetadata(); 
            });
        }

        for(let i=0; i<15; i++) {
            let b = document.createElement('div');
            b.className = 'bar';
            b.style.animationDelay = (i * 0.1) + 's';
            viz.appendChild(b);
        }

        function log(msg, color='var(--text-muted)') {
            console.log(`> ${new Date().toLocaleTimeString()}: ${msg}`);
        }

        function setViz(active) { document.querySelectorAll('.bar').forEach(b => { active ? b.classList.add('active') : b.classList.remove('active'); }); }

        function handleRetry() {
            s.innerText = "OFFLINE"; s.style.color = "var(--danger)"; st.innerText = "SIGNAL LOST - RECONNECTING..."; setViz(false);
            log("Stream interrupt detected. Auto-reconnect triggered.", "var(--danger)");
            setTimeout(() => {
                log("Attempting handshake..."); a.src = currentStreamUrl + "?cb=" + Date.now(); a.load();
                a.play().catch(e => log("User interaction required for play."));
            }, 5000);
        }
        
        function fetchMetadata() {
            fetch(ajaxUrl + '?action=qrq_get_meta')
                .then(res => res.json())
                .then(response => {
                    if(response.success && response.data && response.data.icestats && response.data.icestats.source) {
                        let sources = response.data.icestats.source;
                        if (!Array.isArray(sources)) sources = [sources];
                        
                        const mountpoint = currentStreamUrl.split('/').pop();
                        const channelMeta = response.data.custom_meta[mountpoint] || {};

                        let activeSource = sources.find(s => s.listenurl && s.listenurl.endsWith(mountpoint));
                        if (!activeSource && sources.length > 0) activeSource = sources[0];

                        // ALWAYS ENABLE VR BUTTON FOR THE CHANNEL
                        if (vrBtn && mountpoint) {
                            vrBtn.classList.remove('d-none');
                            vrBtn.onclick = () => {
                                const audEl = document.getElementById('aud');
                                if(audEl) audEl.pause();
                                window.open('<?php echo home_url("/vr-suite/?channel="); ?>' + mountpoint, '_blank');
                            };
                        }

                        if (activeSource && activeSource.title) {
                            let rawIcecastTitle = activeSource.title;
                            let trackKey = Object.keys(channelMeta).find(k => k === rawIcecastTitle || k === rawIcecastTitle + '.mp3' || channelMeta[k].title === rawIcecastTitle);
                            let customData = trackKey ? channelMeta[trackKey] : null;

                            let displayTitle = rawIcecastTitle;
                            
                            if (customData) {
                                let t = customData.title || rawIcecastTitle;
                                displayTitle = t;
                                
                                if (customData.artist) displayTitle += ` - ${customData.artist}`;
                                if (customData.album) displayTitle += ` from the ${customData.album} album`;

                                if (customData.show_notes && customData.notes) {
                                    notesBtn.classList.remove('d-none');
                                    notesContent.innerHTML = customData.notes.replace(/\n/g, '<br>');
                                } else {
                                    notesBtn.classList.add('d-none');
                                }

                                if (customData.show_video && customData.video) {
                                    videoBtn.classList.remove('d-none');
                                    
                                    let ytMatch = customData.video.match(/(?:youtu\.be\/|youtube\.com\/(?:embed\/|v\/|watch\?v=|watch\?.+&v=))((\w|-){11})/);
                                    let pendingVideoAction = null;
                                    
                                    if (ytMatch && ytMatch[1]) {
                                        pendingVideoAction = () => {
                                            document.getElementById('yt-iframe').src = `https://www.youtube.com/embed/${ytMatch[1]}?rel=0&autoplay=1&controls=1`;
                                            new bootstrap.Modal(document.getElementById('videoModal')).show();
                                        };
                                    } else {
                                        pendingVideoAction = () => window.open(customData.video, '_blank');
                                    }

                                    videoBtn.onclick = () => {
                                        new bootstrap.Modal(document.getElementById('observerEffectModal')).show();
                                        document.getElementById('acknowledge-observer-btn').onclick = () => {
                                            let obsModal = bootstrap.Modal.getInstance(document.getElementById('observerEffectModal'));
                                            if(obsModal) obsModal.hide();
                                            if (pendingVideoAction) pendingVideoAction();
                                        };
                                    };
                                } else {
                                    videoBtn.classList.add('d-none');
                                }
                            } else {
                                notesBtn.classList.add('d-none');
                                videoBtn.classList.add('d-none');
                            }

                            if (trackKey) {
                                collabBtn.classList.remove('d-none');
                                let filenameSlug = trackKey.replace(/\.mp3$/i, '');
                                collabBtn.onclick = () => {
                                    window.open('<?php echo home_url("/radio-player/track/"); ?>' + mountpoint + '/' + filenameSlug, '_blank');
                                };
                                
                                // UPDATE VR BUTTON TO INCLUDE TRACK SLUG
                                if (vrBtn) {
                                    vrBtn.onclick = () => {
                                        const audEl = document.getElementById('aud');
                                        if(audEl) audEl.pause();
                                        window.open('<?php echo home_url("/vr-suite/?channel="); ?>' + mountpoint + '&track=' + filenameSlug, '_blank');
                                    };
                                }
                            } else {
                                collabBtn.classList.add('d-none');
                            }

                            const formattedTitle = displayTitle.replace(/-/g, ' ');

                            if (currentSongEl.innerText !== formattedTitle) {
                                currentSongEl.innerText = formattedTitle;
                                log(`Track Sync: ${formattedTitle}`, 'var(--gold)');
                                currentSongEl.classList.remove('needs-scroll');

                                setTimeout(() => {
                                    if (currentSongEl.scrollWidth > titleContainer.clientWidth) {
                                        currentSongEl.classList.add('needs-scroll');
                                    }
                                }, 50);
                            }
                        } else {
                            currentSongEl.innerText = "Live Broadcast (No Meta)";
                            currentSongEl.classList.remove('needs-scroll');
                            notesBtn.classList.add('d-none');
                            videoBtn.classList.add('d-none');
                            collabBtn.classList.add('d-none');
                        }
                    }
                })
                .catch(err => { /* fail silently */ });
        }

        a.onplaying = () => { 
            s.innerText = "LIVE STREAM"; s.style.color = "var(--cyan)"; 
            st.innerText = "BROADCAST ACTIVE"; st.style.color = "var(--cyan)"; 
            setViz(true); 
            log("Buffer synchronized. Audio active.", "var(--cyan)"); 
            fetchMetadata(); 
            if(songPollInterval) clearInterval(songPollInterval);
            songPollInterval = setInterval(fetchMetadata, 10000); 
        };
        
        a.onpause = () => { if(songPollInterval) clearInterval(songPollInterval); };
        a.onwaiting = () => { st.innerText = "BUFFERING..."; log("Network jitter detected. Re-buffering..."); };
        a.onerror = handleRetry;
        a.onended = handleRetry;

        window.addEventListener('DOMContentLoaded', () => {
            log("Spatial core initialization complete.");
            fetchMetadata();
            a.play().catch(() => {
                log("Autoplay blocked. Awaiting user 'Play' trigger.", "var(--gold)");
                st.innerText = "READY TO START";
            });
        });
    </script>
    </body>
    </html>
    <?php
}

// ==========================================
// 3. ROUTE INTERCEPTOR
// ==========================================
add_action('template_redirect', 'qrq_radio_player_router');
function qrq_radio_player_router() {
    $uri = $_SERVER['REQUEST_URI'];
    // Intercept URLs matching /radio-player/ but NOT /radio-player/track/...
    if (preg_match('#^/radio-player/?$#i', $uri)) {
        qrq_render_player();
        exit;
    }
}

// ==========================================
// 4. IFRAME EMBED SHORTCODE FOR MAIN PLAYER
// ==========================================
add_shortcode('qrq_player', 'qrq_player_shortcode_render');
function qrq_player_shortcode_render($atts) {
    $a = shortcode_atts([
        'height' => '800px', // Adjusted slightly to accommodate new buttons
        'width'  => '100%',
    ], $atts);

    // Point the iframe to our custom WebXR routing endpoint
    $player_url = home_url('/radio-player/');

    ob_start();
    ?>
    <div style="width: <?php echo esc_attr($a['width']); ?>; max-width: 500px; margin: 0 auto; border-radius: 24px; overflow: hidden; box-shadow: 0 10px 30px rgba(0, 242, 255, 0.2); border: 1px solid rgba(0, 242, 255, 0.3);">
        <iframe src="<?php echo esc_url($player_url); ?>" style="width: 100%; height: <?php echo esc_attr($a['height']); ?>; border: none; display: block;" allow="autoplay; encrypted-media; xr-spatial-tracking"></iframe>
    </div>
    <?php
    return ob_get_clean();
}
