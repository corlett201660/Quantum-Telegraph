<?php
/**
 * Shortcode logic for Melle Well VR.
 * Features WebXR Engine, Multiplayer Lobby, and Built-in Tutorial System.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_shortcode( 'melle_vr', 'melle_vr_shortcode' );

function melle_vr_shortcode($atts) {
    $a = shortcode_atts(['direct_channel' => '', 'direct_track' => ''], $atts);
    
    $api_url      = esc_url_raw( rest_url( 'melle-vr/v1/scores' ) );
    $validate_url = esc_url_raw( rest_url( 'melle-vr/v1/validate-username' ) );
    $beatmap_dir  = esc_url_raw( wp_upload_dir()['baseurl'] . '/beatmaps/' ); 
    $asset_dir    = esc_url_raw( wp_upload_dir()['baseurl'] . '/qrq_radio_assets/' ); 
    $plugin_url   = trailingslashit( MELLE_VR_URL ); 

    // Intercept URL parameters if they exist, otherwise fallback to shortcode attributes
    $direct_channel = isset($_GET['channel']) ? sanitize_title($_GET['channel']) : $a['direct_channel'];
    $direct_track   = isset($_GET['track']) ? sanitize_text_field($_GET['track']) : $a['direct_track'];

    $direct_mp3 = '';
    if (!empty($direct_channel) && !empty($direct_track)) {
        $direct_mp3 = $asset_dir . $direct_channel . '/' . $direct_track . '.mp3';
    }

    // Role & WooCommerce Filtering Hooks
    $vr_allowed  = array_filter(array_map('trim', explode(',', get_option('melle_vr_vr_allowed_mounts', ''))));
    $vr_excluded = array_filter(array_map('trim', explode(',', get_option('melle_vr_vr_excluded_mounts', 'admin, fallback'))));
    $restricted  = array_filter(array_map('trim', explode(',', get_option('melle_vr_restricted_mounts', ''))));
    
    // Convert roles to lowercase array
    $req_roles    = array_filter(array_map('trim', explode(',', strtolower(get_option('melle_vr_required_roles', '')))));
    // Convert global products to clean array of IDs
    $req_products = array_filter(array_map('trim', explode(',', get_option('melle_vr_required_products', ''))));
    
    // Fetch the new per-station requirements
    $station_reqs = get_option('melle_vr_station_reqs', []);
    $user_purchased = [];
    
    $user_has_access = false;
    
    if (empty($req_roles) && empty($req_products) && empty($station_reqs)) {
        $user_has_access = true; 
    } elseif (is_user_logged_in()) {
        $user = wp_get_current_user();
        if (is_super_admin()) {
            $user_has_access = true;
        } else {
            // 1. Check if user possesses any of the global required roles
            foreach ($user->roles as $role) {
                if (in_array(strtolower($role), $req_roles)) {
                    $user_has_access = true;
                    break;
                }
            }
            
            // 2. Check WooCommerce purchases
            if (function_exists('wc_customer_bought_product')) {
                // Global product check
                if (!$user_has_access && !empty($req_products)) {
                    foreach ($req_products as $product_id) {
                        if (wc_customer_bought_product($user->user_email, $user->ID, $product_id)) {
                            $user_has_access = true;
                            $user_purchased[] = $product_id;
                        }
                    }
                }
                
                // Build an array of every specific product the user has bought from the station reqs
                foreach ($station_reqs as $station => $ids_string) {
                    $ids = array_filter(array_map('trim', explode(',', $ids_string)));
                    foreach ($ids as $pid) {
                        if (wc_customer_bought_product($user->user_email, $user->ID, $pid)) {
                            $user_purchased[] = $pid;
                        }
                    }
                }
            }
        }
    }
    
    $user_purchased = array_values(array_unique($user_purchased));

    ob_start();
    ?>
    
    <link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css'>
    <link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css'>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/annyang/2.6.1/annyang.min.js"></script>

    <style>
        #rune-ticker-container { position: fixed !important; bottom: 0 !important; left: 0 !important; width: 100% !important; background: rgba(0, 0, 0, 0.7) !important; color: #00f2ff !important; overflow: hidden !important; white-space: nowrap !important; z-index: 99998 !important; padding: 8px 0 !important; font-size: 1.5rem !important; font-weight: bold !important; letter-spacing: 8px !important; pointer-events: none !important; }
        #rune-ticker-content { display: inline-block !important; padding-left: 100% !important; animation: marqueeRTL 25s linear infinite !important; }
        @keyframes marqueeRTL { 0%   { transform: translateX(0); } 100% { transform: translateX(-100%); } }
        /* Highlight spoken runes in the ticker */
        .rune-highlight { color: #ffff00; text-shadow: 0 0 15px rgba(255, 255, 0, 1); transition: all 0.3s ease; }
    </style>

    <script>
        document.documentElement.setAttribute('data-bs-theme', 'light');

        window.MelleVRConfig = {
            wpApiUrl: "<?php echo $api_url; ?>",
            wpValidateUrl: "<?php echo $validate_url; ?>",
            beatmapDir: "<?php echo $beatmap_dir; ?>",
            assetDir: "<?php echo $asset_dir; ?>",
            icecastBaseUrl: "<?php echo esc_js(get_option('melle_vr_icecast_base_url', 'https://qrjournal.org/icecast/')); ?>",
            pluginUrl: "<?php echo $plugin_url; ?>",
            directChannel: "<?php echo esc_js($direct_channel); ?>",
            directTrack: "<?php echo esc_js($direct_track); ?>",
            directMp3: "<?php echo esc_url($direct_mp3); ?>",
            vrAllowedMounts: <?php echo json_encode($vr_allowed); ?>,
            vrExcludedMounts: <?php echo json_encode($vr_excluded); ?>,
            restrictedMounts: <?php echo json_encode($restricted); ?>,
            userHasRoleAccess: <?php echo $user_has_access ? 'true' : 'false'; ?>,
            storeLink: "<?php echo esc_js(get_option('melle_vr_store_link', '')); ?>",
            homeUrl: "<?php echo esc_js(get_option('melle_vr_home_url', '/community')); ?>",
            stationReqs: <?php echo json_encode($station_reqs); ?>,
            userPurchased: <?php echo json_encode($user_purchased); ?>
        };

        document.addEventListener("DOMContentLoaded", () => {
            const offcanvases = document.querySelectorAll('.offcanvas, .modal');
            offcanvases.forEach(oc => document.body.appendChild(oc));

            const themeBtn = document.getElementById('themeToggleBtn');
            const themeIcon = themeBtn ? themeBtn.querySelector('i') : null;
            if (themeBtn) {
                themeBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    const root = document.documentElement;
                    const isDark = root.getAttribute('data-bs-theme') === 'dark';
                    const tutorialOverlay = document.getElementById('melle-tutorial-overlay');
                    
                    if (isDark) {
                        root.setAttribute('data-bs-theme', 'light');
                        if(themeIcon) themeIcon.className = 'fas fa-moon';
                        if(tutorialOverlay) tutorialOverlay.style.background = 'rgba(255, 255, 255, 0.95)';
                    } else {
                        root.setAttribute('data-bs-theme', 'dark');
                        if(themeIcon) themeIcon.className = 'fas fa-sun';
                        if(tutorialOverlay) tutorialOverlay.style.background = 'rgba(15, 20, 30, 0.95)';
                    }
                });
            }

            // --- VOICE NAVIGATION UI HOOKS ---
            const voiceToggle = document.getElementById('voiceNavToggle');
            if (voiceToggle) {
                voiceToggle.addEventListener('change', (e) => {
                    if (window.MelleVoiceNav) {
                        window.MelleVoiceNav.toggle(e.target.checked);
                    }
                });
            }

            const voiceDebugToggle = document.getElementById('voiceDebugToggle');
            if (voiceDebugToggle) {
                voiceDebugToggle.addEventListener('change', (e) => {
                    if (window.MelleVoiceNav) {
                        window.MelleVoiceNav.setDebug(e.target.checked);
                    }
                    const debugOverlay = document.getElementById('voice-debug-overlay');
                    if (debugOverlay) {
                        debugOverlay.style.display = e.target.checked ? 'block' : 'none';
                    }
                });
            }

            const aiEngineSelect = document.getElementById('aiEngineSelect');
            if (aiEngineSelect) {
                aiEngineSelect.innerHTML = '<option value="models/gemini-2.5-flash">Loading Models...</option>';
                fetch(window.location.origin + '/wp-admin/admin-ajax.php?action=qrq_get_ai_models')
                    .then(res => res.json())
                    .then(resData => {
                        if (resData.success && resData.data && resData.data.length > 0) {
                            aiEngineSelect.innerHTML = '<option value="gpt">OpenAI (GPT-4o)</option>';
                            let hasFlash25 = false;
                            resData.data.forEach(model => {
                                let opt = document.createElement('option');
                                opt.value = model.id; opt.text = "Google " + model.name;
                                aiEngineSelect.appendChild(opt);
                                if (model.id === 'models/gemini-2.5-flash') hasFlash25 = true;
                            });
                            const savedEngine = localStorage.getItem('melle_vr_ai_engine');
                            if (savedEngine && Array.from(aiEngineSelect.options).some(o => o.value === savedEngine)) {
                                aiEngineSelect.value = savedEngine;
                            } else {
                                aiEngineSelect.value = hasFlash25 ? 'models/gemini-2.5-flash' : resData.data[0].id;
                                localStorage.setItem('melle_vr_ai_engine', aiEngineSelect.value);
                            }
                        } else {
                            aiEngineSelect.innerHTML = '<option value="models/gemini-2.5-flash">Google Gemini (2.5 Flash)</option><option value="gpt">OpenAI (GPT-4o)</option>';
                        }
                    }).catch(err => {
                        aiEngineSelect.innerHTML = '<option value="models/gemini-2.5-flash">Google Gemini (2.5 Flash)</option><option value="gpt">OpenAI (GPT-4o)</option>';
                    });

                aiEngineSelect.addEventListener('change', (e) => { localStorage.setItem('melle_vr_ai_engine', e.target.value); });
            }

            const spToggle = document.getElementById('singlePlayerToggle');
            const spToggleText = document.getElementById('spToggleText');
            const spToggleLabel = document.getElementById('spToggleLabel');
            
            if(spToggle && spToggleLabel) {
                spToggle.addEventListener('change', (e) => {
                    if(e.target.checked) {
                        spToggleText.innerText = "Isolated Circuit (Single)";
                        spToggleLabel.classList.replace('btn-outline-secondary', 'btn-outline-warning');
                        spToggleLabel.querySelector('i').classList.replace('fa-network-wired', 'fa-lock');
                    } else {
                        spToggleText.innerText = "Network Circuit (Multi)";
                        spToggleLabel.classList.replace('btn-outline-warning', 'btn-outline-secondary');
                        spToggleLabel.querySelector('i').classList.replace('fa-lock', 'fa-network-wired');
                    }
                });
            }

            const TutorialSystem = {
                currentStep: 0,
                isActive: false,
                steps: [
                    { title: "The Subatomic Interstate", content: "You have been digitized and injected into a transatlantic telegraph cable. You are now a stationary <strong>Observer Particle</strong>.<br><br>Move your mouse, swipe your screen, or look around in your VR headset to aim your magnetic receptor." },
                    { title: "The Observer Effect", content: "Electrons carrying anomalous data (Runes) travel down the wire in a state of quantum superposition.<br><br>Keep your blue ring perfectly aligned to <strong>observe</strong> them. The act of observation collapses their waveform, allowing you to harvest the data." },
                    { title: "Magnetic Dispersion", content: "Press the <strong>Spacebar</strong>, double-tap your screen, or press a <strong>Face Button</strong> on your VR controller to release a captured electron.<br><br>This triggers a localized magnetic shield, defending you from collisions, and broadcasts a telemetry ping to other Observer Particles on the line!" },
                    { title: "Cooperative Current", content: "Telegraphs rely on a continuous, closed circuit. You are not alone in the wire.<br><br>If your local team collectively captures <strong>23 electrons</strong> by the end of the transmission, you will successfully intercept the signal and extract its keywords." },
                    { title: "Instant System Overload", content: "Watch the <strong>GLOBAL VOLTAGE</strong> counter. If the entire network captures 100 electrons, a massive <strong>Telegraphic Interception</strong> occurs <em>instantly</em>.<br><br>This forces the central AI to decrypt the anomalies into raw English anagrams immediately! <br><br><span class='text-warning fw-bold'>Prepare for injection.</span>" }
                ],
                init() {
                    this.cacheDOM();
                    this.bindEvents();
                    if (this.overlay) this.overlay.style.background = 'rgba(255, 255, 255, 0.95)'; 
                    if (!localStorage.getItem('melle_vr_tutorial_complete')) setTimeout(() => this.start(), 1500);
                },
                cacheDOM() {
                    this.overlay = document.getElementById('melle-tutorial-overlay');
                    this.titleEl = document.getElementById('tutorial-title');
                    this.contentEl = document.getElementById('tutorial-content');
                    this.progressBar = document.getElementById('tutorial-progress-bar');
                    this.prevBtn = document.getElementById('tutorial-prev-btn');
                    this.nextBtn = document.getElementById('tutorial-next-btn');
                    this.closeBtn = document.getElementById('tutorial-close-btn');
                    this.counterEl = document.getElementById('tutorial-step-counter');
                    this.manualBtn = document.getElementById('manualTutorialBtn');
                },
                bindEvents() {
                    if(this.nextBtn) this.nextBtn.addEventListener('click', () => this.nextStep());
                    if(this.prevBtn) this.prevBtn.addEventListener('click', () => this.prevStep());
                    if(this.closeBtn) this.closeBtn.addEventListener('click', () => this.end());
                    if(this.manualBtn) { this.manualBtn.addEventListener('click', (e) => { e.preventDefault(); this.start(true); }); }
                },
                start(force = false) {
                    this.currentStep = 0; this.isActive = true;
                    if(this.overlay) this.overlay.style.display = 'block';
                    const mainUI = document.getElementById('ui-container');
                    if (mainUI) mainUI.style.opacity = '0.3';
                    this.renderStep();
                },
                renderStep() {
                    if(!this.contentEl) return;
                    const stepData = this.steps[this.currentStep];
                    this.contentEl.style.opacity = 0;
                    setTimeout(() => {
                        if(this.titleEl) this.titleEl.innerHTML = `<i class="fas fa-microchip me-2"></i> ${stepData.title}`;
                        this.contentEl.innerHTML = stepData.content;
                        this.contentEl.style.opacity = 1;
                    }, 200);

                    if(this.counterEl) this.counterEl.innerText = `${this.currentStep + 1} / ${this.steps.length}`;
                    if(this.progressBar) {
                        const progressPct = ((this.currentStep + 1) / this.steps.length) * 100;
                        this.progressBar.style.width = `${progressPct}%`;
                    }
                    if(this.prevBtn) this.prevBtn.disabled = this.currentStep === 0;
                    if(this.nextBtn) {
                        if (this.currentStep === this.steps.length - 1) {
                            this.nextBtn.innerHTML = 'INJECT PARTICLE <i class="fas fa-power-off ms-1"></i>';
                            this.nextBtn.classList.replace('btn-info', 'btn-success');
                        } else {
                            this.nextBtn.innerHTML = 'NEXT <i class="fas fa-arrow-right ms-1"></i>';
                            this.nextBtn.classList.replace('btn-success', 'btn-info');
                        }
                    }
                },
                nextStep() {
                    if (this.currentStep < this.steps.length - 1) { this.currentStep++; this.renderStep(); } else { this.end(); }
                },
                prevStep() {
                    if (this.currentStep > 0) { this.currentStep--; this.renderStep(); }
                },
                end() {
                    this.isActive = false;
                    if(this.overlay) this.overlay.style.display = 'none';
                    localStorage.setItem('melle_vr_tutorial_complete', 'true');
                    const mainUI = document.getElementById('ui-container');
                    if (mainUI) mainUI.style.opacity = '1.0';
                }
            };
            TutorialSystem.init();
        });
    </script>

    <div id="melle-vr-wrapper">
        
        <div id="ingame-overlay-controls" style="display:none; position: fixed !important; top: 15px !important; left: 15px !important; right: 15px !important; z-index: 999999 !important; gap: 8px; flex-direction: row; flex-wrap: wrap;">
            
            <button id="home-vr-btn" class="btn btn-sm btn-outline-primary fw-bold" style="border-radius: 6px !important; background: rgba(0,0,0,0.5) !important; box-shadow: 0 0 10px rgba(13,110,253,0.3);">
                <i class="fas fa-home me-1"></i> Home
            </button>

            <button id="close-vr-btn" class="btn btn-sm btn-outline-danger fw-bold" style="border-radius: 6px !important; background: rgba(0,0,0,0.5) !important; box-shadow: 0 0 10px rgba(255,0,0,0.3);">
                <i class="fas fa-sign-out-alt me-1"></i> Extract
            </button>
            <button id="mute-audio-btn" class="btn btn-sm btn-outline-warning fw-bold" style="border-radius: 6px !important; background: rgba(0,0,0,0.5) !important; box-shadow: 0 0 10px rgba(255,255,0,0.3);">
                <i class="fas fa-volume-up me-1"></i> Mute
            </button>
            <button id="toggle-hud-btn" class="btn btn-sm btn-outline-light fw-bold" style="border-radius: 6px !important; background: rgba(0,0,0,0.5) !important;">
                <i class="fas fa-eye-slash me-1"></i> HUD
            </button>
            <a id="ingame-collab-btn" href="#" target="_blank" class="btn btn-sm btn-outline-info fw-bold" style="display:none; border-radius: 6px !important; background: rgba(0,0,0,0.5) !important; box-shadow: 0 0 10px rgba(0,242,255,0.3);">
                <i class="fas fa-user-astronaut me-1"></i> Collab Hub
            </a>
        </div>

        <div id="txtStatus">Initializing...</div>
        <div id="livePlayersUI">Active Particles: 0</div>
        <div id="songUI">Waiting for Transmission...</div>
        
        <div id="liveLeaderboardUI">
            <h3>Local Circuit</h3>
            <div id="liveLeaderboardList"></div>
        </div>

        <div id="voice-debug-overlay" style="display: none; position: absolute; top: 120px; left: 15px; width: 300px; max-height: 300px; overflow-y: auto; background: rgba(0,0,0,0.8); border: 1px solid #00f2ff; border-radius: 8px; z-index: 99999; padding: 10px; font-family: monospace; font-size: 0.85rem; color: #0f0; pointer-events: none;">
            <div class="fw-bold text-info mb-2 border-bottom border-info pb-1">VOICE DEBUG LOG</div>
            <div id="voice-debug-log-content"></div>
        </div>

        <div id="ui-container" class="position-absolute top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center p-3 p-md-4" style="z-index: 100;">
            <div id="ui-overlay" class="card p-4 rounded-4 shadow-lg border-top border-2 border-primary w-100" style="max-width: 450px;">
                
                <div class="d-flex justify-content-between align-items-center pb-3 mb-3 border-bottom border-secondary">
                    <h2 class="m-0 text-primary fw-bold" style="letter-spacing: 1px;">M.E.L.L.E.</h2>
                    <div class="btn-group shadow-sm">
                        <button id="themeToggleBtn" class="btn btn-sm btn-outline-secondary" title="Toggle Light/Dark Theme">
                            <i class="fas fa-moon"></i>
                        </button>
                        <button id="manualTutorialBtn" class="btn btn-sm btn-outline-info" title="System Orientation">
                            <i class="fas fa-graduation-cap"></i>
                        </button>
                        
                        <?php 
                        $store_link = get_option( 'melle_vr_store_link', '' );
                        if ( ! empty( $user_purchased ) && ! empty( $store_link ) ) : 
                        ?>
                            <a href="<?php echo esc_url( $store_link ); ?>" target="_blank" class="btn btn-sm btn-outline-warning" title="Premium Store">
                                <i class="fas fa-shopping-cart"></i>
                            </a>
                        <?php endif; ?>

                        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="offcanvas" data-bs-target="#settingsOffcanvas" title="System Settings">
                            <i class="fas fa-cog"></i>
                        </button>
                    </div>
                </div>
                
                <div id="controls" class="d-flex flex-column gap-3">
                    <div>
                        <div class="input-group input-group-lg shadow-sm">
                            <span class="input-group-text border-secondary text-primary"><i class="fas fa-microchip"></i></span>
                            <input type="text" id="playerName" class="form-control border-secondary" placeholder="Enter Particle ID..." maxlength="15">
                        </div>
                        <div id="nameValidationFeedback" class="text-danger small mt-1 fw-bold" style="display:none;"></div>
                    </div>

                    <button id="btnRevealNetwork" class="btn btn-outline-info fw-bold shadow-sm py-2" type="button">
                        <i class="fas fa-globe me-2"></i> Access Network
                    </button>

                    <div id="networkOptionsContainer" style="display:none; flex-direction:column; gap:1rem;">
                        <div class="input-group shadow-sm">
                            <span class="input-group-text border-secondary"><i class="fas fa-satellite-dish text-primary"></i></span>
                            <select id="stationSelect" class="form-select border-secondary">
                                <option value="">Locating Frequencies...</option>
                            </select>
                        </div>

                        <div class="input-group shadow-sm">
                            <span class="input-group-text border-secondary"><i class="fas fa-crosshairs text-warning"></i></span>
                            <select id="trackSelect" class="form-select border-secondary">
                                <option value="">Live Broadcast (All Tracks)</option>
                            </select>
                        </div>

                        <div class="mt-1">
                            <input type="checkbox" class="btn-check" id="singlePlayerToggle" autocomplete="off" checked>
                            <label id="spToggleLabel" class="btn btn-outline-warning w-100 fw-bold shadow-sm" for="singlePlayerToggle" style="padding: 12px;">
                                <i class="fas fa-lock me-2"></i> <span id="spToggleText">Isolated Circuit (Single)</span>
                            </label>
                        </div>
                    </div>

                    <button id="btnInit" class="btn btn-primary btn-lg fw-bold text-uppercase shadow-lg mt-2 py-3" disabled>
                        <span id="btnInitText"><i class="fas fa-bolt me-2"></i> Inject Particle</span>
                    </button>

                    <?php if ( empty( $user_purchased ) ) : ?>
                    <a id="btnPremiumStore" href="#" target="_blank" class="btn btn-warning btn-lg fw-bold text-dark text-uppercase shadow-lg mt-2 py-3" style="display:none; box-shadow: 0 0 15px rgba(255, 193, 7, 0.5) !important;">
                        <i class="fas fa-shopping-cart me-2"></i> Unlock Premium Tracks
                    </a>
                    <?php endif; ?>
                </div>
                
                <div class="row mt-3 g-2">
                    <?php 
                    $login_url = class_exists('UM') ? um_get_core_page('login') : wp_login_url( get_permalink() );
                    $register_url = class_exists('UM') ? um_get_core_page('register') : wp_registration_url();
                    $profile_url = class_exists('UM') ? um_get_core_page('user') : get_edit_profile_url();
                    $logout_url = class_exists('UM') ? um_get_core_page('logout') : wp_logout_url( get_permalink() );

                    if ( ! is_user_logged_in() ) : 
                    ?>
                        <div class="col-6">
                            <a href="<?php echo esc_url( $login_url ); ?>" class="btn btn-outline-warning w-100 shadow-sm fw-bold py-2">
                                <i class="fas fa-sign-in-alt me-1"></i> Login
                            </a>
                        </div>
                        <div class="col-6">
                            <a href="<?php echo esc_url( $register_url ); ?>" class="btn btn-outline-info w-100 shadow-sm fw-bold py-2">
                                <i class="fas fa-user-plus me-1"></i> Register
                            </a>
                        </div>
                    <?php else : ?>
                        <div class="col-6">
                            <a href="<?php echo esc_url( $profile_url ); ?>" class="btn btn-outline-warning w-100 shadow-sm fw-bold py-2">
                                <i class="fas fa-user me-1"></i> Profile
                            </a>
                        </div>
                        <div class="col-6">
                            <a href="<?php echo esc_url( $logout_url ); ?>" class="btn btn-outline-danger w-100 shadow-sm fw-bold py-2">
                                <i class="fas fa-sign-out-alt me-1"></i> Logout
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="offcanvas offcanvas-bottom h-auto" tabindex="-1" id="settingsOffcanvas" style="border-top: 2px solid #0d6efd;">
            <div class="offcanvas-header border-bottom border-secondary">
                <h5 class="offcanvas-title text-primary fw-bold"><i class="fas fa-cog me-2"></i> System Settings</h5>
                <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
            </div>
            <div class="offcanvas-body">
                
                <div class="form-check form-switch mb-2 p-3 border border-info rounded bg-light" data-bs-theme="light">
                    <input class="form-check-input ms-0 me-2" type="checkbox" id="voiceNavToggle" style="cursor:pointer; width: 40px; height: 20px;">
                    <label class="form-check-label text-info fw-bold" for="voiceNavToggle" style="cursor:pointer; font-size: 1.1rem; margin-top: 2px;">
                        <i class="fas fa-microphone" id="voiceNavIcon"></i> Enable Voice Control
                    </label>
                    <p class="small text-muted mt-2 mb-0 ms-4 ps-2 border-start border-info">Use your mic to cast spells. Say <strong>"Left", "Right", "Up", "Down"</strong>, or the phonetic name of a Rune.</p>
                </div>

                <div class="form-check form-switch mb-4 p-3 border border-warning rounded bg-light" data-bs-theme="light">
                    <input class="form-check-input ms-0 me-2" type="checkbox" id="voiceDebugToggle" style="cursor:pointer; width: 40px; height: 20px;">
                    <label class="form-check-label text-warning fw-bold" for="voiceDebugToggle" style="cursor:pointer; font-size: 1.1rem; margin-top: 2px;">
                        <i class="fas fa-bug"></i> Show Voice Log (iPad/Mobile)
                    </label>
                    <p class="small text-muted mt-2 mb-0 ms-4 ps-2 border-start border-warning">Displays an in-game HUD showing exactly what words the microphone thinks you are saying.</p>
                </div>

                <div class="mb-4">
                    <label class="form-label text-primary fw-bold"><i class="fas fa-brain me-2"></i> AI Engine</label>
                    <div class="input-group">
                        <select id="aiEngineSelect" class="form-select border-secondary"></select>
                    </div>
                </div>
                <div class="mb-4">
                    <label class="form-label text-primary fw-bold"><i class="fas fa-vr-cardboard me-2"></i> Tunnel Fidelity</label>
                    <div class="input-group">
                        <select id="graphicsQuality" class="form-select border-secondary">
                            <option value="auto">Auto-Detect</option>
                            <option value="low">Low (Performance)</option>
                            <option value="med">Medium (Balanced)</option>
                            <option value="high">High (Fidelity)</option>
                        </select>
                        <button id="btnBenchmark" class="btn btn-outline-info"><i class="fas fa-tachometer-alt"></i></button>
                    </div>
                </div>
                <div class="mb-4">
                    <label class="form-label text-primary fw-bold"><i class="fas fa-volume-up me-2"></i> Audio Test</label>
                    <button id="previewAudioBtn" class="btn btn-outline-secondary w-100 fw-bold"><i class="fas fa-play me-2"></i> Preview Frequency</button>
                </div>
            </div>
        </div>

        <div class="offcanvas offcanvas-bottom h-auto" tabindex="-1" id="anagramOffcanvas" style="border-top: 2px solid #ffc107;">
            <div class="offcanvas-header border-bottom border-secondary">
                <h5 class="offcanvas-title text-warning fw-bold"><i class="fas fa-barcode me-2"></i> Signal Decryption</h5>
                <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
            </div>
            <div class="offcanvas-body text-center">
                <div class="d-flex gap-2 mb-3">
                    <button id="sendRunesToGeminiBtn" class="btn btn-warning text-dark fw-bold flex-grow-1"><i class="fas fa-satellite-dish me-2"></i> Decipher</button>
                    <button id="toggleTranslationBtn" class="btn btn-outline-info"><i class="fas fa-language"></i></button>
                </div>
                <div id="generated-words-container" class="p-3 rounded border border-secondary font-monospace text-start" style="min-height: 100px; max-height: 250px; overflow-y: auto;">
                    <span class="text-muted">Decrypted sequences will appear here...</span>
                </div>
            </div>
        </div>

        <div class="offcanvas offcanvas-bottom h-auto" tabindex="-1" id="diagnosticsOffcanvas" style="border-top: 2px solid #0dcaf0;">
            <div class="offcanvas-header border-bottom border-secondary">
                <h5 class="offcanvas-title text-info fw-bold"><i class="fas fa-microchip me-2"></i> Input Diagnostics</h5>
                <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
            </div>
            <div class="offcanvas-body" id="diagnostics-modal-body">
                </div>
        </div>

        <div id="leaderboardUI">
            <h2 id="match-results-title">Circuit Analytics</h2>
            <div id="leaderboardList"></div>
            <p id="buffer-text">Buffer Time: Aligning to next transmission...</p>
        </div>

        <div id="rewardUI" style="display: none; position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: rgba(0, 0, 0, 0.95); padding: 2rem; border-radius: 12px; border: 2px solid #00f2ff; text-align: center; z-index: 25; box-shadow: 0 10px 50px rgba(0, 242, 255, 0.3); width: 80%; max-width: 500px;">
            <h2 style="color: #00f2ff; margin-top: 0; text-transform: uppercase;">Transmission Decoded</h2>
            <div id="rewardDetails">Decoding neural link...</div>
            <button id="closeRewardBtn" class="btn btn-secondary mt-3">Return to Interface</button>
        </div>

        <div id="rune-ticker-container"><div id="rune-ticker-content"></div></div>
        <div id="three-container"></div>
    </div>

    <link rel="stylesheet" href="<?php echo $plugin_url; ?>assets/css/style.css?v=<?php echo time(); ?>">
    <script src="<?php echo $plugin_url; ?>assets/js/voice-nav.js?v=<?php echo time(); ?>"></script>
    <script type="module" src="<?php echo $plugin_url; ?>assets/js/app.js?v=<?php echo time(); ?>"></script>
    <?php
    return ob_get_clean();
}
