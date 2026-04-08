<?php
/**
 * Shortcode logic for the Isochronic Spatial-Neural Core.
 * Registers the [isochronic_core] shortcode.
 * Part of the Quantum Telegraph suite.
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

add_shortcode( 'isochronic_core', 'melle_vr_isochronic_shortcode' );

function melle_vr_isochronic_shortcode() {
    ob_start();
    ?>
    
    <link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css'>
    <link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css'>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,700;1,700&family=Inter:wght@400;900&family=JetBrains+Mono&display=swap" rel="stylesheet">
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/d3/7.8.5/d3.min.js"></script>
    <script src="https://unpkg.com/html5-qrcode"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/annyang/2.6.1/annyang.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

    <style>
        #full-map-overlay svg { width: 100%; height: 100%; background: #050505; cursor: grab; }
        #full-map-overlay svg:active { cursor: grabbing; }
        #geo-map svg { cursor: grab; border: 1px solid #222; background: #000; }
        #geo-map svg:active { cursor: grabbing; }
      
        :root { --bg: #000; --cyan: #00f2ff; --gold: #ffff00; --border: #222; --danger: #ff0055; }
        body.isochronic-active { background: var(--bg); color: #fff; font-family: 'Inter', sans-serif; min-height: 100vh; margin: 0; overflow-x: hidden; }
        
        #init-overlay { position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: #000; z-index: 20000; display: flex; flex-direction: column; align-items: center; justify-content: center; }

        #gen-overlay { position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(0,0,0,0.92); z-index: 11000; display: none; flex-direction: column; align-items: center; justify-content: center; }
        .forge-dna { font-size: 5rem; color: var(--cyan); animation: dnaSpin 2.5s infinite linear; filter: drop-shadow(0 0 10px var(--cyan)); }
        @keyframes dnaSpin { 0% { transform: rotateY(0deg); color: var(--cyan); } 50% { color: var(--gold); } 100% { transform: rotateY(360deg); color: var(--cyan); } }

        #crawl-overlay { position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; pointer-events: none; z-index: 10000; display: none; }
        .spawn-icon { position: absolute; color: var(--cyan); opacity: 0; animation: spawnFade 1.5s cubic-bezier(0.19, 1, 0.22, 1) forwards; }
        @keyframes spawnFade { 0% { transform: scale(0.5) rotate(-90deg); opacity: 0; } 50% { opacity: 1; color: var(--gold); } 100% { transform: scale(3) rotate(90deg); opacity: 0; } }

        #checkpointOverlay { position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; z-index: 15000; display: none; background: rgba(0,0,0,0.98); backdrop-filter: blur(25px); -webkit-backdrop-filter: blur(25px); align-items: flex-start; justify-content: center; padding-top: 20px; overflow-y: auto; pointer-events: auto; }
        #checkpointOverlay input, #checkpointOverlay select, #nodeEditorModal select, #lexiconEditorModal select { border: 2px solid var(--cyan) !important; color: #ffffff !important; background-color: #111 !important; }
        #checkpointOverlay label { color: var(--gold) !important; }

        .dash-panel { background: #050505; border: 1px solid var(--border); border-radius: 12px; min-height: calc(100vh - 2rem); display: flex; flex-direction: column; padding: 1.2rem; }
        .lex-entry { display: flex; justify-content: space-between; align-items: center; font-size: 0.75rem; padding: 6px; border-bottom: 1px solid #111; font-family: 'JetBrains Mono'; }
        .weight-high { color: var(--gold); border-left: 3px solid var(--gold); padding-left: 5px; }

        .diag-terminal { background: rgba(10,10,10,0.5); border: 1px solid #333; border-radius: 4px; font-family: 'JetBrains Mono'; font-size: 0.65rem; padding: 10px; color: #0f0; height: 200px; overflow-y: auto; text-transform: uppercase; }
        .diag-entry { margin-bottom: 4px; border-bottom: 1px solid #111; padding-bottom: 2px; }
        .diag-alt { color: #555; display: block; font-size: 0.6rem; }

        #geo-hud-container { margin-bottom: 10px; border: 1px solid #333; background: #080808; border-radius: 4px; padding: 8px; }
        #geo-map svg { background: #000; width: 100%; height: 150px; border: 1px solid #222; }
        .node-point { fill: var(--cyan); stroke: none; transition: all 0.5s; cursor: pointer; }
        .node-point:hover { fill: var(--gold); r: 8; }
        .user-point { fill: var(--danger); animate: pulse 2s infinite; }
        .node-label { fill: #fff; font-size: 8px; font-family: 'JetBrains Mono'; pointer-events: none; opacity: 0.7; }
        .geo-data { font-family: 'JetBrains Mono'; font-size: 0.7rem; color: var(--gold); display: flex; justify-content: space-between; margin-bottom: 5px; }

        .spectrum-line { font-family: 'Playfair Display', serif; text-align: center; opacity: 0.1; transition: 1s; font-size: 2.2rem; margin-bottom: 180px; padding: 0 10%; }
        .active-focus { opacity: 1 !important; transform: scale(1.05); text-shadow: 0 0 15px rgba(0, 242, 255, 0.3); }

        .btn-sync.active { background: var(--gold) !important; color: #000; border-color: var(--gold); }
        .spin-icon { display: none; }
        .active .spin-icon { display: inline-block; animation: fa-spin 1s infinite linear; margin-right: 5px; }
        
        ::-webkit-scrollbar { width: 5px; }
        ::-webkit-scrollbar-track { background: #000; }
        ::-webkit-scrollbar-thumb { background: #333; }
    </style>

    <div class="isochronic-wrapper">
        <div id="init-overlay">
            <h1 class="display-4 fw-900 text-info mb-3 text-center">ISOCHRONIC 4.0</h1>
            <p class="text-secondary small mb-4 px-4 text-center">System requires explicit user authorization to initialize Spatial-Neural links.</p>
            
            <div id="isoSessionOptions" style="display: none; width: 100%; max-width: 400px; background: rgba(10,10,10,0.9); padding: 15px; border-radius: 8px; border: 1px solid #00f2ff; margin-bottom: 25px; box-shadow: 0 0 20px rgba(0,242,255,0.1);">
                <div class="alert alert-dark border-info text-info py-2 small mb-3 text-center fw-bold">
                    <i class="fas fa-database me-2"></i>Previous Neural State Detected
                </div>
                
                <div class="form-check form-switch mb-2" id="toggleWaypointsContainer" style="display:none;">
                    <input class="form-check-input" type="checkbox" id="importWaypoints" checked>
                    <label class="form-check-label text-light small" for="importWaypoints">Load Spatial Anchors (Waypoints)</label>
                </div>
                
                <div class="form-check form-switch mb-2" id="toggleLexiconContainer" style="display:none;">
                    <input class="form-check-input" type="checkbox" id="importLexiconData" checked>
                    <label class="form-check-label text-light small" for="importLexiconData">Load Neural Lexicon (Nodes)</label>
                </div>
                
                <div class="form-check form-switch mb-3" id="toggleInterceptionsContainer" style="display:none;">
                    <input class="form-check-input" type="checkbox" id="importInterceptions" checked>
                    <label class="form-check-label text-light small" for="importInterceptions">Load Intercepted Works Archive</label>
                </div>
                
                <hr class="border-secondary my-3">
                <button class="btn btn-outline-danger btn-sm w-100 fw-bold" id="isoStartFreshBtn"><i class="fas fa-trash me-2"></i>WIPE ALL DATA (Start Fresh)</button>
            </div>
            
            <button class="btn btn-outline-info btn-lg px-5 fw-bold" id="initCoreBtn">INITIALIZE CORE</button>
        </div>

        <div id="gen-overlay">
            <i class="fas fa-dna forge-dna mb-4"></i>
            <h4 class="text-info fw-bold letter-spacing-2">NEURAL FORGING</h4>
            <p class="text-secondary small">Synthesizing Lexical Weights...</p>
        </div>
        <div id="crawl-overlay"></div>

        <div class="modal fade" id="interceptionsModal" tabindex="-1">
            <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content bg-black border border-info">
                    <div class="modal-header border-secondary">
                        <h5 class="modal-title text-info fw-bold"><i class="fas fa-folder-open me-2"></i>INTERCEPTED WORKS</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body" id="interceptions-list"></div>
                </div>
            </div>
        </div>

        <div id="checkpointOverlay">
            <div class="container" style="max-width: 1000px;">
                <div class="row g-4">
                    <div class="col-md-5 border-end border-secondary">
                        <h2 class="text-warning fw-900 mb-4">NEURAL CHECKPOINT</h2>
                        
                        <div class="bg-dark p-3 rounded mb-3 border border-secondary">
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" id="reflexMode" checked>
                                <label class="form-check-label small text-info fw-bold" for="reflexMode">REFLEX SYNC (AUTO)</label>
                            </div>

                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" id="autoPilotMode">
                                <label class="form-check-label small text-warning fw-bold" for="autoPilotMode">AUTO PILOT ENABLED</label>
                            </div>

                            <label class="small text-secondary mb-1">GOAL / INTENT</label>
                            <input type="text" id="auto-goal" class="form-control form-control-sm bg-black text-white border-secondary mb-2" placeholder="e.g., Transcendence">
                            
                            <label class="small text-secondary mb-1">TIMEOUT: <span id="timer-val">5</span>s</label>
                            <input type="range" id="auto-timer" class="form-range" min="0.5" max="30" step="0.5" value="5" oninput="document.getElementById('timer-val').innerText = this.value">
                            
                            <div id="auto-countdown" class="text-center text-danger fw-bold mt-2" style="display:none; font-family: 'JetBrains Mono';">
                                AUTO-ADJUST IN: <span id="countdown-sec">5</span>s
                            </div>

                            <hr class="border-secondary">

                            <label class="small text-secondary mb-1">STRATEGY</label>
                            <select id="reflexStrategy" class="form-select form-select-sm bg-black text-white border-secondary mb-3">
                                <option value="first">First Word Detected</option>
                                <option value="last">Last Word Detected</option>
                                <option value="most">Most Frequent</option>
                                <option value="least">Least Frequent (Outlier)</option>
                            </select>
                            <label class="small text-secondary mb-1">INJECTION WEIGHT</label>
                            <input type="range" id="chk-weight" class="form-range" min="1" max="100" value="50">
                        </div>

                        <input type="text" id="chk-word" class="form-control bg-black text-white border-info mb-2" placeholder="Manual Override...">
                        <button class="btn btn-info w-100 fw-bold mb-3" onclick="app.commitCheckpoint()">FORCE SYNC</button>
                        <button class="btn btn-danger w-100 fw-bold btn-sm mb-2" onclick="app.exportPDF()"><i class="fas fa-file-pdf me-2"></i>EXPORT SESSION DATA</button>
                        
                        <button class="btn btn-warning w-100 fw-bold btn-sm shadow" style="box-shadow: 0 0 10px rgba(255, 255, 0, 0.4) !important;" onclick="app.transmitToBlueprint()">
                            <i class="fas fa-rocket me-2"></i>TRANSMIT TO BLUEPRINT
                        </button>

                    </div>

                    <div class="col-md-7">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="small text-info fw-bold"><i class="fas fa-satellite-dish me-2"></i>DIVA DIAGNOSTICS (LIVE NLP)</span>
                            <span class="badge bg-danger pulse">LISTENING</span>
                        </div>
                        <div id="diag-terminal" class="diag-terminal"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="nodeEditorModal" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered"><div class="modal-content bg-black border border-info">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title text-info fw-bold">NEURAL ANCHOR EDITOR</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="edit-node-id">
                    <label class="small text-secondary">ANCHOR NAME</label>
                    <input type="text" id="edit-node-name" class="form-control bg-dark text-white border-secondary mb-3">
                    <label class="small text-secondary">ACTIVE RADIUS (METERS)</label>
                    <input type="number" id="edit-node-radius" class="form-control bg-dark text-white border-secondary mb-3">
                    <label class="small text-secondary">INFLUENCE TYPE</label>
                    <select id="edit-node-influence" class="form-select bg-dark text-white border-secondary mb-3">
                        <option value="essence">Essence (Thematic & Implicit - Primary)</option>
                        <option value="literal">Literal (Explicit Name Insertion)</option>
                    </select>
                    <label class="small text-secondary">META / CONTEXT</label>
                    <textarea id="edit-node-meta" class="form-control bg-dark text-white border-secondary mb-3" rows="3" placeholder="Describe the feeling..."></textarea>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-danger btn-sm me-auto" onclick="app.deleteNode()">DELETE</button>
                    <button type="button" class="btn btn-info fw-bold" onclick="app.saveNodeEdit()">UPDATE ANCHOR</button>
                </div>
            </div></div>
        </div>

        <div class="modal fade" id="lexiconEditorModal" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered"><div class="modal-content bg-black border border-warning">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title text-warning fw-bold">LEXICON NODE EDITOR</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <label class="small text-secondary">NODE WORD</label>
                    <input type="text" id="edit-lex-word" class="form-control bg-dark text-white border-secondary mb-3" readonly>
                    <label class="small text-secondary">SYNAPTIC WEIGHT</label>
                    <input type="number" id="edit-lex-weight" class="form-control bg-dark text-white border-secondary mb-3">
                    <label class="small text-secondary">INFLUENCE TYPE</label>
                    <select id="edit-lex-influence" class="form-select bg-dark text-white border-secondary mb-3">
                        <option value="essence">Essence (Thematic Integration - Primary)</option>
                        <option value="literal">Literal (Direct Word Insertion)</option>
                    </select>
                    <label class="small text-secondary">ADDITIONAL CONTEXT</label>
                    <textarea id="edit-lex-context" class="form-control bg-dark text-white border-secondary mb-3" rows="3" placeholder="Provide deeper meaning..."></textarea>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-danger btn-sm me-auto" onclick="app.deleteLexiconWord()">REMOVE NODE</button>
                    <button type="button" class="btn btn-warning fw-bold text-dark" onclick="app.saveLexiconEdit()">UPDATE NODE</button>
                </div>
            </div></div>
        </div>

        <div class="container-fluid py-3 h-100">
            <div class="row g-3 h-100">
                <div class="col-lg-4">
                    <div class="dash-panel">
                        <div id="geo-hud-container">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="small text-info fw-bold"><i class="fas fa-map-marked-alt me-2"></i>SPATIAL ENGINE</span>
                                <span id="gps-status" class="badge bg-secondary">OFFLINE</span>
                            </div>
                            <div class="geo-data">
                                <span id="geo-coords">LAT: -- | LNG: -- | ALT: --</span>
                                <span id="geo-anchor" class="text-white">NO ANCHOR</span>
                            </div>
                            <div id="geo-map"></div>
                            <div class="btn-group w-100 mt-2" role="group">
                                <button class="btn btn-outline-info btn-sm" onclick="app.tagLocation()"><i class="fas fa-thumbtack"></i> TAG</button>
                                <button class="btn btn-outline-info btn-sm" onclick="app.toggleFullMap()"><i class="fas fa-expand"></i> MAP</button>
                                <button class="btn btn-outline-secondary btn-sm" onclick="app.exportAnchors()"><i class="fas fa-download"></i></button>
                                <button class="btn btn-outline-secondary btn-sm" onclick="document.getElementById('import-anchors').click()"><i class="fas fa-upload"></i></button>
                            </div>
                            <input type="file" id="import-anchors" style="display:none;" accept=".json" onchange="app.importAnchors(event)">
                        </div>

                        <h6 class="text-secondary small fw-bold mb-2 mt-2">SOURCE SYNC</h6>
                        
                        <div class="input-group mb-2">
                            <button class="btn btn-outline-secondary btn-sm" onclick="document.getElementById('manual-url').value='https://www.telegraph.co.uk/'" title="Target: The Telegraph"><i class="fas fa-newspaper"></i></button>
                            <input type="text" id="manual-url" class="form-control form-control-sm bg-black text-white border-secondary" value="https://qrjournal.org/todays-note/">
                            <button id="sync-btn" class="btn btn-secondary btn-sm btn-sync" onclick="app.processInput(document.getElementById('manual-url').value)">
                                <i class="fas fa-sync spin-icon"></i>SYNC
                            </button>
                        </div>
                        
                        <button class="btn btn-outline-info btn-sm w-100 mb-2 fw-bold" onclick="app.showInterceptions()">
                            <i class="fas fa-folder-open me-2"></i>SCANNED WORKS ARCHIVE
                        </button>

                        <button class="btn btn-outline-warning w-100 mb-2 btn-sm" onclick="app.toggleScanner()">
                            <i class="fas fa-qrcode me-2"></i>QR CAMERA
                        </button>
                        <div id="qr-reader" class="mb-3 border border-secondary" style="display:none; height: 200px;"></div>

                        <div class="d-flex justify-content-between align-items-center border-bottom border-secondary pb-1 mb-2">
                            <span class="small text-secondary fw-bold">ACTIVE LEXICON</span>
                            <div class="btn-group" role="group">
                                <button class="btn btn-outline-secondary btn-sm py-0" onclick="app.exportLexicon()" title="Export Lexicon"><i class="fas fa-download"></i></button>
                                <button class="btn btn-outline-secondary btn-sm py-0" onclick="document.getElementById('import-lexicon').click()" title="Import Lexicon"><i class="fas fa-upload"></i></button>
                            </div>
                            <input type="file" id="import-lexicon" style="display:none;" accept=".json" onchange="app.importLexicon(event)">
                        </div>
                        
                        <div id="lex-list" style="flex-grow:1; overflow-y:auto; max-height: 250px;"></div>

                        <div class="mt-auto pt-3 border-top border-secondary">
                            <div class="d-flex justify-content-between small mb-2">
                                <span class="text-secondary" id="drift-status">Passive Drift: 0/10</span>
                                <span class="text-warning" id="voice-status">Voice: STANDBY</span>
                            </div>
                            <button class="btn btn-info w-100 fw-bold" onclick="app.generate()">GENERATE STREAM</button>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-8">
                    <div class="dash-panel align-items-center justify-content-center text-center">
                        <h1 class="display-4 fw-900 mb-0">ISOCHRONIC</h1>
                        <p class="text-secondary small mb-5">v4.0 | SPATIAL-NEURAL CORE</p>
                    </div>
                </div>
            </div>
        </div>

        <div id="launch-btn-container" class="position-fixed bottom-0 start-50 translate-middle-x mb-4 d-none" style="z-index: 1050;">
            <button id="launch-btn" class="btn btn-info btn-lg px-5 fw-bold shadow-lg" style="box-shadow: 0 0 20px var(--cyan) !important;" onclick="app.openStream()">LAUNCH STREAM</button>
        </div>

        <div class="modal fade" id="streamModal" tabindex="-1" data-bs-backdrop="static">
            <div class="modal-dialog modal-fullscreen"><div class="modal-content bg-black border-0">
                <div class="position-absolute top-0 end-0 p-3" style="z-index: 2000;">
                    <button class="btn btn-outline-info btn-sm fw-bold me-2" onclick="app.manualAdvance()">NEXT MESSAGE <i class="fas fa-forward"></i></button>
                    <button class="btn btn-outline-light btn-sm fw-bold" onclick="app.closeStream()">EXIT</button>
                </div>
                <div class="modal-body overflow-auto" id="modalScroll" style="padding-top: 45vh; padding-bottom: 60vh;">
                    <div id="waterfall-area"></div>
                </div>
            </div></div>
        </div>

        <div id="full-map-overlay" style="display:none; position:fixed; top:0; left:0; width:100vw; height:100vh; background:#000; z-index:12000;">
            <div class="position-absolute top-0 start-0 p-3 w-100 d-flex justify-content-between align-items-center" style="z-index: 12001; background: linear-gradient(180deg, rgba(0,0,0,0.9) 0%, rgba(0,0,0,0) 100%);">
                <h5 class="text-info fw-bold mb-0"><i class="fas fa-satellite me-2"></i>TOPOGRAPHY LINK</h5>
                <button class="btn btn-outline-light btn-sm fw-bold" onclick="app.toggleFullMap()">CLOSE</button>
            </div>
            <div id="full-geo-map" style="width:100%; height:100%;"></div>
        </div>
    </div> <script>
    document.body.classList.add('isochronic-active');

    const CONFIG = { API_KEY: "AIzaSyCGxdg_TGMaUAYQ_kr-HejCpeZ5zTY8O64", MODEL: "gemini-2.5-flash" };
    let streamModal, nodeEditorModal, lexiconEditorModal, html5QrCode;

    setTimeout(() => {
        streamModal = new bootstrap.Modal(document.getElementById('streamModal'));
        nodeEditorModal = new bootstrap.Modal(document.getElementById('nodeEditorModal'));
        lexiconEditorModal = new bootstrap.Modal(document.getElementById('lexiconEditorModal'));
    }, 100);

    const app = {
        state: { 
            lexicon: {}, 
            diagLogs: [], 
            sentences: [], 
            waypoints: [], 
            interceptions: [], 
            currentPos: { lat: 0, lng: 0, alt: 0 },
            activeAnchor: null,
            lastIdx: 0, 
            loading: false, 
            passiveCount: 0, 
            isLocked: false, 
            reflex: true, 
            playing: false,
            autoPilotActive: false,
            countdownInterval: null,
            narrativeHistory: [],
            maxHistory: 10,
            isFullMap: false,
            fallbackTimer: null
        },

        init() {
            // --- SESSION DATA MANAGER ---
            const hasWaypoints = !!localStorage.getItem('iso_waypoints');
            const hasLexicon = !!localStorage.getItem('iso_lexicon');
            const hasInterceptions = !!localStorage.getItem('iso_interceptions');

            const sessionBox = document.getElementById('isoSessionOptions');
            const btnInit = document.getElementById('initCoreBtn');
            const btnFresh = document.getElementById('isoStartFreshBtn');

            if (hasWaypoints || hasLexicon || hasInterceptions) {
                sessionBox.style.display = 'block';
                if (hasWaypoints) document.getElementById('toggleWaypointsContainer').style.display = 'block';
                if (hasLexicon) document.getElementById('toggleLexiconContainer').style.display = 'block';
                if (hasInterceptions) document.getElementById('toggleInterceptionsContainer').style.display = 'block';
            }

            btnFresh.addEventListener('click', () => {
                if(confirm("This will permanently sever previous neural links. Proceed?")) {
                    localStorage.removeItem('iso_waypoints');
                    localStorage.removeItem('iso_lexicon');
                    localStorage.removeItem('iso_interceptions');
                    sessionBox.style.display = 'none';
                    // Uncheck so they don't get saved back when start happens
                    if(document.getElementById('importWaypoints')) document.getElementById('importWaypoints').checked = false;
                    if(document.getElementById('importLexiconData')) document.getElementById('importLexiconData').checked = false;
                    if(document.getElementById('importInterceptions')) document.getElementById('importInterceptions').checked = false;
                }
            });

            btnInit.addEventListener('click', () => {
                // Clear any unselected toggle data
                if (hasWaypoints && !document.getElementById('importWaypoints').checked) localStorage.removeItem('iso_waypoints');
                if (hasLexicon && !document.getElementById('importLexiconData').checked) localStorage.removeItem('iso_lexicon');
                if (hasInterceptions && !document.getElementById('importInterceptions').checked) localStorage.removeItem('iso_interceptions');

                // Load remaining approved state
                this.loadState(); 
                this.loadLexiconState(); 
                this.loadInterceptionsState(); 
                
                // Quantum Telegraph Game Target URL link check
                const interceptedUrl = localStorage.getItem('iso_target_url');
                if (interceptedUrl) {
                    document.getElementById('manual-url').value = interceptedUrl;
                    this.logDiag("System Transfer", "Intercepted Target URL loaded into sync queue.");
                }
                
                document.getElementById('reflexMode').onchange = (e) => this.state.reflex = e.target.checked;
                document.getElementById('autoPilotMode').onchange = (e) => this.state.autoPilotActive = e.target.checked;
                
                // Provide a default fallback lexicon if completely empty
                if(Object.keys(this.state.lexicon).length === 0) {
                    this.state.lexicon = { 
                        "coherence": { weight: 20, context: "State of synchrony", influence: "essence" }, 
                        "quantum": { weight: 15, context: "Subatomic potentials", influence: "essence" }, 
                        "resonance": { weight: 15, context: "Vibrational matching", influence: "literal" } 
                    };
                    this.saveLexiconState();
                }
                
                this.renderLex();
                this.renderMap();
                setInterval(() => this.bufferManager(), 3000);
                
                this.startSystem();
            });
        },

        transmitToBlueprint() {
            this.logDiag("System Transfer", "Packaging Neural State for Blueprint...");
            const transferData = {
                glossary: this.state.lexicon,
                anchors: this.state.waypoints,
                interceptions: this.state.interceptions
            };
            
            localStorage.setItem('blueprint_transfer_data', JSON.stringify(transferData));
            window.location.href = "<?php echo esc_url( home_url( '/blueprint/' ) ); ?>"; 
        },

        startSystem() {
            const btn = document.getElementById('initCoreBtn');
            btn.innerText = "REQUESTING UPLINK...";
            btn.classList.replace('btn-outline-info', 'btn-warning');

            if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
                navigator.mediaDevices.getUserMedia({ audio: true })
                .then(stream => {
                    this.initVoice();
                    this.initGeo();
                    document.getElementById('init-overlay').style.display = 'none';
                })
                .catch(err => {
                    alert("Microphone denied. Voice reflexes will be offline.");
                    this.initVoice(); 
                    this.initGeo();   
                    document.getElementById('init-overlay').style.display = 'none';
                });
            } else {
                this.initVoice();
                this.initGeo();
                document.getElementById('init-overlay').style.display = 'none';
            }
        },

        initGeo() {
            if ("geolocation" in navigator) {
                navigator.geolocation.watchPosition((pos) => {
                    const altRaw = pos.coords.altitude;
                    const altDisplay = altRaw ? altRaw.toFixed(1) + 'm' : 'N/A';

                    this.state.currentPos = { 
                        lat: pos.coords.latitude, 
                        lng: pos.coords.longitude,
                        alt: altRaw || 0
                    };
                    
                    document.getElementById('geo-coords').innerText = `LAT: ${this.state.currentPos.lat.toFixed(4)} | LNG: ${this.state.currentPos.lng.toFixed(4)} | ALT: ${altDisplay}`;
                    document.getElementById('gps-status').className = "badge bg-success pulse";
                    document.getElementById('gps-status').innerText = "LIVE";

                    this.checkSpatialAnchors();
                    this.renderMap();
                }, (err) => {
                    console.error(err);
                    document.getElementById('gps-status').className = "badge bg-danger";
                    document.getElementById('gps-status').innerText = "ERR";
                }, { enableHighAccuracy: true });
            }
        },

        tagLocation() {
            if(this.state.currentPos.lat === 0) return alert("Waiting for GPS Lock...");
            const name = prompt("Name this Neural Anchor:", "New Waypoint");
            if(!name) return;

            const altStr = this.state.currentPos.alt ? `${this.state.currentPos.alt.toFixed(1)}m` : 'Unknown';

            const newNode = {
                id: Date.now(),
                name: name,
                lat: this.state.currentPos.lat,
                lng: this.state.currentPos.lng,
                radius: 50, 
                meta: `User tagged location. Altitude: ${altStr}`,
                influence: "essence" 
            };

            this.state.waypoints.push(newNode);
            this.saveState();
            this.logDiag("Spatial Engine", `Anchor Created: ${name} (Alt: ${altStr})`);
            this.renderMap();
        },

        exportAnchors() {
            if(this.state.waypoints.length === 0) return alert("No anchors to export.");
            const dataStr = "data:text/json;charset=utf-8," + encodeURIComponent(JSON.stringify(this.state.waypoints));
            const downloadAnchorNode = document.createElement('a');
            downloadAnchorNode.setAttribute("href", dataStr);
            downloadAnchorNode.setAttribute("download", `isochronic_anchors_${Date.now()}.json`);
            document.body.appendChild(downloadAnchorNode);
            downloadAnchorNode.click();
            downloadAnchorNode.remove();
            this.logDiag("Spatial Engine", "Exported Spatial Anchors.");
        },

        importAnchors(event) {
            const file = event.target.files[0];
            if (!file) return;
            const reader = new FileReader();
            reader.onload = (e) => {
                try {
                    const imported = JSON.parse(e.target.result);
                    if (Array.isArray(imported)) {
                        const currentIds = new Set(this.state.waypoints.map(w => w.id));
                        let count = 0;
                        imported.forEach(wp => {
                            if (!currentIds.has(wp.id)) {
                                if(!wp.influence) wp.influence = "essence"; 
                                this.state.waypoints.push(wp);
                                currentIds.add(wp.id);
                                count++;
                            }
                        });
                        this.saveState();
                        this.renderMap();
                        this.checkSpatialAnchors();
                        this.logDiag("Spatial Engine", `Imported ${count} new Anchors.`);
                    }
                } catch (err) {
                    alert("Invalid JSON file. Please upload a valid exported anchors file.");
                    this.logDiag("Spatial Engine", "Failed to import anchors - Invalid JSON.");
                }
                event.target.value = ''; 
            };
            reader.readAsText(file);
        },

        checkSpatialAnchors() {
            const R = 6371e3; 
            let nearest = null;
            let minDist = Infinity;

            this.state.waypoints.forEach(wp => {
                const φ1 = this.state.currentPos.lat * Math.PI/180;
                const φ2 = wp.lat * Math.PI/180;
                const Δφ = (wp.lat - this.state.currentPos.lat) * Math.PI/180;
                const Δλ = (wp.lng - this.state.currentPos.lng) * Math.PI/180;

                const a = Math.sin(Δφ/2) * Math.sin(Δφ/2) + Math.cos(φ1) * Math.cos(φ2) * Math.sin(Δλ/2) * Math.sin(Δλ/2);
                const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
                const d = R * c;

                if(d < wp.radius && d < minDist) { minDist = d; nearest = wp; }
            });

            this.state.activeAnchor = nearest;
            const anchorLabel = document.getElementById('geo-anchor');
            if(nearest) {
                anchorLabel.innerText = nearest.name.toUpperCase();
                anchorLabel.className = "text-info fw-bold blink";
            } else {
                anchorLabel.innerText = "NO ANCHOR";
                anchorLabel.className = "text-secondary";
            }
        },

        toggleFullMap() {
            this.state.isFullMap = !this.state.isFullMap;
            const overlay = document.getElementById('full-map-overlay');
            if(this.state.isFullMap) {
                overlay.style.display = 'block';
                this.renderMap();
            } else {
                overlay.style.display = 'none';
            }
        },

        renderMap() {
            const hudContainer = document.getElementById('geo-map');
            this.drawD3Map('#geo-map', hudContainer.clientWidth, 150);
            
            if (this.state.isFullMap) {
                this.drawD3Map('#full-geo-map', window.innerWidth, window.innerHeight);
            }
        },

        drawD3Map(containerSelector, w, h) {
            const container = document.querySelector(containerSelector);
            if(!container) return;
            container.innerHTML = ''; 
            
            const svg = d3.select(containerSelector).append("svg")
                .attr("width", w).attr("height", h);

            const g = svg.append("g");

            const zoom = d3.zoom()
                .scaleExtent([0.5, 10]) 
                .on("zoom", (event) => {
                    g.attr("transform", event.transform);
                });
            
            svg.call(zoom);

            const points = [...this.state.waypoints, { lat: this.state.currentPos.lat, lng: this.state.currentPos.lng }];
            
            if(points.length < 2) {
                 g.append("circle").attr("cx", w/2).attr("cy", h/2).attr("r", 5).attr("class", "user-point");
                 return;
            }

            const latExtent = d3.extent(points, d => d.lat);
            const lngExtent = d3.extent(points, d => d.lng);
            const pad = 0.001; 

            const xScale = d3.scaleLinear().domain([lngExtent[0]-pad, lngExtent[1]+pad]).range([20, w-20]);
            const yScale = d3.scaleLinear().domain([latExtent[0]-pad, latExtent[1]+pad]).range([h-20, 20]); 

            g.selectAll(".node-point").data(this.state.waypoints).enter().append("circle")
                .attr("class", "node-point").attr("cx", d => xScale(d.lng)).attr("cy", d => yScale(d.lat)).attr("r", 5)
                .on("click", (e, d) => this.openNodeEditor(d));

            g.append("circle").attr("cx", xScale(this.state.currentPos.lng)).attr("cy", yScale(this.state.currentPos.lat))
                .attr("r", 4).attr("class", "user-point");

            g.selectAll(".node-label").data(this.state.waypoints).enter().append("text")
                .attr("class", "node-label").attr("x", d => xScale(d.lng) + 8).attr("y", d => yScale(d.lat) + 3).text(d => d.name);
        },

        openNodeEditor(node) {
            document.getElementById('edit-node-id').value = node.id;
            document.getElementById('edit-node-name').value = node.name;
            document.getElementById('edit-node-radius').value = node.radius;
            document.getElementById('edit-node-meta').value = node.meta;
            document.getElementById('edit-node-influence').value = node.influence || 'essence';
            nodeEditorModal.show();
        },

        saveNodeEdit() {
            const id = parseInt(document.getElementById('edit-node-id').value);
            const idx = this.state.waypoints.findIndex(w => w.id === id);
            if(idx > -1) {
                this.state.waypoints[idx].name = document.getElementById('edit-node-name').value;
                this.state.waypoints[idx].radius = parseFloat(document.getElementById('edit-node-radius').value);
                this.state.waypoints[idx].meta = document.getElementById('edit-node-meta').value;
                this.state.waypoints[idx].influence = document.getElementById('edit-node-influence').value;
                this.saveState();
                this.renderMap();
                nodeEditorModal.hide();
            }
        },

        deleteNode() {
            if(!confirm("Destroy this Neural Anchor?")) return;
            const id = parseInt(document.getElementById('edit-node-id').value);
            this.state.waypoints = this.state.waypoints.filter(w => w.id !== id);
            this.saveState();
            this.renderMap();
            nodeEditorModal.hide();
        },

        saveState() { localStorage.setItem('iso_waypoints', JSON.stringify(this.state.waypoints)); },
        loadState() {
            const saved = localStorage.getItem('iso_waypoints');
            if(saved) this.state.waypoints = JSON.parse(saved);
        },

        saveInterceptionsState() { localStorage.setItem('iso_interceptions', JSON.stringify(this.state.interceptions)); },
        loadInterceptionsState() {
            const saved = localStorage.getItem('iso_interceptions');
            if(saved) this.state.interceptions = JSON.parse(saved);
        },

        showInterceptions() {
            const list = document.getElementById('interceptions-list');
            if(this.state.interceptions.length === 0) {
                list.innerHTML = '<p class="text-secondary text-center p-4">No intercepted works found.</p>';
            } else {
                list.innerHTML = this.state.interceptions.map((item, idx) => `
                    <div class="border border-secondary p-3 mb-3 rounded bg-dark">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="text-info fw-bold mb-0">${item.title}</h6>
                            <span class="small text-secondary">${item.time}</span>
                        </div>
                        <p class="small text-light mb-2" style="font-family: 'JetBrains Mono'; font-size: 0.8rem; line-height: 1.4;">${item.snippet}</p>
                        <div class="d-flex gap-2 border-top border-secondary pt-2 mt-2">
                            <a href="${item.url}" target="_blank" class="btn btn-sm btn-outline-warning fw-bold"><i class="fas fa-external-link-alt me-2"></i>VISIT SOURCE</a>
                            <button class="btn btn-sm btn-outline-danger" onclick="app.removeInterception(${idx})"><i class="fas fa-trash"></i></button>
                        </div>
                    </div>
                `).join('');
            }
            new bootstrap.Modal(document.getElementById('interceptionsModal')).show();
        },

        removeInterception(idx) {
            if(!confirm("Remove this log from archives?")) return;
            this.state.interceptions.splice(idx, 1);
            this.saveInterceptionsState();
            this.showInterceptions(); 
        },

        saveLexiconState() { localStorage.setItem('iso_lexicon', JSON.stringify(this.state.lexicon)); },
        loadLexiconState() {
            const saved = localStorage.getItem('iso_lexicon');
            if(saved) this.state.lexicon = JSON.parse(saved);
        },

        exportLexicon() {
            if(Object.keys(this.state.lexicon).length === 0) return alert("Lexicon is empty.");
            const dataStr = "data:text/json;charset=utf-8," + encodeURIComponent(JSON.stringify(this.state.lexicon));
            const downloadNode = document.createElement('a');
            downloadNode.setAttribute("href", dataStr);
            downloadNode.setAttribute("download", `isochronic_lexicon_${Date.now()}.json`);
            document.body.appendChild(downloadNode);
            downloadNode.click();
            downloadNode.remove();
            this.logDiag("Lexicon Engine", "Exported Lexicon State.");
        },

        importLexicon(event) {
            const file = event.target.files[0];
            if (!file) return;
            const reader = new FileReader();
            reader.onload = (e) => {
                try {
                    const imported = JSON.parse(e.target.result);
                    if (typeof imported === 'object' && !Array.isArray(imported)) {
                        let count = 0;
                        Object.entries(imported).forEach(([word, data]) => {
                            if(!this.state.lexicon[word]) {
                                this.state.lexicon[word] = { 
                                    weight: data.weight || 10, 
                                    context: data.context || "", 
                                    influence: data.influence || "essence" 
                                };
                                count++;
                            } else {
                                this.state.lexicon[word].weight += (data.weight || 0);
                            }
                        });
                        this.saveLexiconState();
                        this.renderLex();
                        this.logDiag("Lexicon Engine", `Merged ${count} new Lexicon Nodes.`);
                    }
                } catch (err) {
                    alert("Invalid JSON file.");
                    this.logDiag("Lexicon Engine", "Failed to import Lexicon - Invalid JSON.");
                }
                event.target.value = ''; 
            };
            reader.readAsText(file);
        },

        openLexiconEditor(word) {
            const data = this.state.lexicon[word];
            document.getElementById('edit-lex-word').value = word;
            document.getElementById('edit-lex-weight').value = Math.round(data.weight);
            document.getElementById('edit-lex-context').value = data.context || '';
            document.getElementById('edit-lex-influence').value = data.influence || 'essence';
            lexiconEditorModal.show();
        },

        saveLexiconEdit() {
            const word = document.getElementById('edit-lex-word').value;
            if(this.state.lexicon[word]) {
                this.state.lexicon[word].weight = parseFloat(document.getElementById('edit-lex-weight').value);
                this.state.lexicon[word].context = document.getElementById('edit-lex-context').value;
                this.state.lexicon[word].influence = document.getElementById('edit-lex-influence').value;
                this.saveLexiconState();
                this.renderLex();
                lexiconEditorModal.hide();
            }
        },

        deleteLexiconWord() {
            if(!confirm("Sever this Lexicon Node?")) return;
            const word = document.getElementById('edit-lex-word').value;
            delete this.state.lexicon[word];
            this.saveLexiconState();
            this.renderLex();
            lexiconEditorModal.hide();
        },

        initVoice() {
            if (annyang) {
                document.getElementById('voice-status').innerText = "Voice: ACTIVE";
                annyang.addCallback('result', (phrases) => {
                    const primary = phrases[0];
                    const alternates = phrases.slice(1);
                    this.logDiag("Voice Capture", primary, alternates);
                    
                    if (this.state.isLocked && this.state.countdownInterval) {
                        clearInterval(this.state.countdownInterval);
                        document.getElementById('auto-countdown').style.display = 'none';
                        this.logDiag("Auto Pilot", "Manual voice input detected; auto-timer aborted.");
                    }

                    if (!this.state.isLocked) {
                        this.processReflex(primary, false); 
                    } else if (this.state.reflex) {
                        this.processReflex(primary, true);
                    }
                });

                annyang.addCommands({
                    'add :word': (w) => { this.commitWord(w, 50, "Voice Manual"); },
                    'continue': () => { this.commitCheckpoint(); },
                    'tag location': () => { this.tagLocation(); }
                });
                
                annyang.start();
            } else {
                document.getElementById('voice-status').innerText = "Voice: UNAVAILABLE";
                document.getElementById('voice-status').className = "text-danger";
            }
        },

        logDiag(label, msg, alts = []) {
            const time = new Date().toLocaleTimeString();
            const bufferSnap = this.state.sentences.slice(this.state.lastIdx, this.state.lastIdx + 3).join(" | ");
            
            let spatialTag = "";
            if(this.state.activeAnchor) {
                spatialTag = `[${this.state.activeAnchor.name}]`;
                label = `${spatialTag} ${label}`;
            }

            this.state.diagLogs.push({ time, label, msg, alts, bufferSnap, location: this.state.activeAnchor ? this.state.activeAnchor.name : "Drift" });
            
            const term = document.getElementById('diag-terminal');
            const entry = document.createElement('div');
            entry.className = 'diag-entry';
            let altHtml = alts.length > 0 ? `<span class="diag-alt">Alts: ${alts.join(', ')}</span>` : '';
            entry.innerHTML = `<span class="text-info">[${time}]</span> <strong>${label}:</strong> ${msg} ${altHtml}`;
            term.prepend(entry);
        },

        processReflex(sentence, isCheckpoint) {
            const words = sentence.toLowerCase().match(/\b([a-z]{4,})\b/g) || [];
            if (words.length === 0) return;

            const strategy = document.getElementById('reflexStrategy').value;
            let selected = words[0];

            if (strategy === 'last') selected = words[words.length - 1];
            else if (strategy === 'most' || strategy === 'least') {
                const counts = {};
                words.forEach(w => counts[w] = (counts[w] || 0) + 1);
                const sorted = Object.entries(counts).sort((a,b) => strategy === 'most' ? b[1]-a[1] : a[1]-b[1]);
                selected = sorted[0][0];
            }

            this.logDiag("Reflex Action", `Synched word: "${selected}"`);
            
            const weightValue = isCheckpoint ? document.getElementById('chk-weight').value : 25;
            this.commitWord(selected, weightValue, "Reflex Sync");

            if (isCheckpoint) {
                document.getElementById('chk-word').value = selected;
                setTimeout(() => this.commitCheckpoint(), 800);
            }
        },

        toggleScanner() {
            const reader = document.getElementById('qr-reader');
            if (reader.style.display === 'none') {
                reader.style.display = 'block';
                html5QrCode = new Html5Qrcode("qr-reader");
                html5QrCode.start({ facingMode: "environment" }, { fps: 10, qrbox: 200 }, (text) => {
                    this.processInput(text);
                    this.toggleScanner(); 
                });
            } else {
                html5QrCode.stop();
                reader.style.display = 'none';
            }
        },

        processInput(rawUrl) {
            let url = rawUrl.trim();
            if (!url.startsWith('http')) url = 'https://' + url;
            this.crawl(url);
        },

        async crawl(url) {
            const btn = document.getElementById('sync-btn');
            btn.classList.add('active');
            document.getElementById('crawl-overlay').style.display = 'block';
            const spawner = setInterval(() => this.spawnIcon(), 150);
            
            this.logDiag("Crawler", `Targeting: ${url}`);
            this.renderLex(); 

            try {
                const res = await fetch(`https://api.allorigins.win/get?url=${encodeURIComponent(url)}`);
                const data = await res.json();
                const doc = new DOMParser().parseFromString(data.contents, 'text/html');

                const titleMatch = doc.querySelector('title');
                const pageTitle = titleMatch ? titleMatch.innerText : new URL(url).hostname;
                let snippetText = "";
                const firstP = doc.querySelector('p');
                if (firstP) {
                    snippetText = firstP.innerText.substring(0, 200) + '...';
                } else {
                    snippetText = doc.body.innerText.substring(0, 200).replace(/\s+/g, ' ') + '...';
                }

                this.state.interceptions.unshift({
                    url: url,
                    title: pageTitle,
                    time: new Date().toLocaleTimeString(),
                    snippet: snippetText
                });
                this.saveInterceptionsState();

                const targets = doc.querySelectorAll('p, h1, h2, h3, h4, li, span, blockquote');
                targets.forEach(tag => {
                    const text = tag.innerText.toLowerCase();
                    const words = text.match(/\b([a-z]{6,})\b/g) || [];
                    const isEmph = tag.closest('strong, b, em, i, u') !== null;
                    const weight = isEmph ? 50 : 10;

                    words.forEach(w => {
                        if (!this.state.lexicon[w]) {
                            this.state.lexicon[w] = { weight: 0, context: `Sync: ${new URL(url).hostname}`, influence: 'essence' };
                        }
                        this.state.lexicon[w].weight += weight;
                    });
                });
                
                this.saveLexiconState();
                requestAnimationFrame(() => this.renderLex());
                this.logDiag("Crawler", "Sync Complete");
            } catch(e) { 
                this.logDiag("API Sync Failure", "Crawl Failed (CORS/Net)");
            }
            finally {
                clearInterval(spawner);
                btn.classList.remove('active');
                document.getElementById('crawl-overlay').style.display = 'none';
                this.renderLex();
            }
        },

        spawnIcon() {
            const icons = ['fa-bolt', 'fa-dna', 'fa-network-wired', 'fa-satellite-dish', 'fa-microchip'];
            const i = document.createElement('i');
            i.className = `fas ${icons[Math.floor(Math.random() * icons.length)]} spawn-icon`;
            i.style.left = Math.random() * 95 + 'vw';
            i.style.top = Math.random() * 95 + 'vh';
            document.getElementById('crawl-overlay').appendChild(i);
            setTimeout(() => i.remove(), 1500);
        },

        commitWord(word, weight, context = "Manual") {
            const clean = word.toLowerCase().replace(/[^\w]/g, '');
            if (clean) {
                if (this.state.lexicon[clean]) {
                    this.state.lexicon[clean].weight += parseFloat(weight);
                } else {
                    this.state.lexicon[clean] = { weight: parseFloat(weight), context, influence: 'essence' };
                }
                this.saveLexiconState();
                this.renderLex();
                if (!this.state.loading && this.state.playing) this.generate();
            }
        },

        renderLex() {
            const list = document.getElementById('lex-list');
            const sorted = Object.entries(this.state.lexicon).sort((a,b) => b[1].weight - a[1].weight).slice(0, 40);
            list.innerHTML = sorted.map(([w, d]) => `
                <div class="lex-entry ${d.weight > 40 ? 'weight-high' : ''}">
                    <div style="flex-grow:1; cursor:pointer;" onclick="app.commitWord('${w}', 5, 'Reinforce')">
                        <span class="fw-bold">${w.toUpperCase()}</span> 
                        <span class="badge ${d.influence === 'literal' ? 'bg-danger' : 'bg-secondary'} border border-dark ms-1" style="font-size: 0.55rem;">
                            ${d.influence === 'literal' ? 'LITERAL' : 'ESSENCE'}
                        </span>
                        <div class="text-secondary" style="font-size: 0.65rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 180px;">
                            ${d.context || 'No specific context.'}
                        </div>
                    </div>
                    <div class="d-flex align-items-center">
                        <span class="me-3 text-warning fw-bold">${Math.round(d.weight)}</span>
                        <button class="btn btn-sm btn-outline-info py-0 px-2" onclick="app.openLexiconEditor('${w}')"><i class="fas fa-edit"></i></button>
                    </div>
                </div>
            `).join('');
        },

        async exportPDF() {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF();
            let y = 20;
            const margin = 20;
            const pageWidth = 170; 

            doc.setFontSize(18); doc.text("ISOCHRONIC 4.0 | SPATIAL SESSION REPORT", margin, y);
            y += 10; doc.setFontSize(10); doc.text(`Timestamp: ${new Date().toLocaleString()}`, margin, y);

            y += 15; doc.setFontSize(14); doc.text("SPATIAL NODES (NEURAL ANCHORS)", margin, y);
            y += 10; doc.setFontSize(8);
            if(this.state.waypoints.length === 0) {
                doc.text("No spatial anchors recorded.", margin + 5, y);
                y += 5;
            } else {
                this.state.waypoints.forEach(wp => {
                    const txt = `[${wp.name.toUpperCase()}] Lat: ${wp.lat.toFixed(5)}, Lng: ${wp.lng.toFixed(5)} | Rad: ${wp.radius}m | Inf: ${wp.influence} | Meta: ${wp.meta}`;
                    const lines = doc.splitTextToSize(txt, pageWidth);
                    doc.text(lines, margin + 5, y);
                    y += (lines.length * 5);
                });
            }

            y += 10; doc.setFontSize(14); doc.text("WEIGHTED LEXICON", margin, y);
            y += 10; doc.setFontSize(8);
            Object.entries(this.state.lexicon).sort((a,b)=>b[1].weight-a[1].weight).slice(0,50).forEach(([w,d]) => {
                const entryText = `${w.toUpperCase()} (Wt: ${Math.round(d.weight)} | ${d.influence.toUpperCase()}) - ${d.context || 'N/A'}`;
                const entryLines = doc.splitTextToSize(entryText, pageWidth);
                doc.text(entryLines, margin + 5, y);
                y += (entryLines.length * 5);
                if (y > 270) { doc.addPage(); y = 20; }
            });

            doc.addPage(); y = 20;
            doc.setFontSize(14); doc.text("SYSTEM DIAGNOSTICS & SPATIAL LOGS", margin, y);
            y += 10; doc.setFontSize(8);
            this.state.diagLogs.forEach(l => {
                const mainMsg = `[${l.time}] ${l.label.toUpperCase()}: ${l.msg}`;
                const mainLines = doc.splitTextToSize(mainMsg, pageWidth);
                doc.text(mainLines, margin, y);
                y += (mainLines.length * 5);

                if (l.bufferSnap) {
                    doc.setTextColor(150);
                    const snapLines = doc.splitTextToSize(`   Buffer: ${l.bufferSnap}`, pageWidth - 10);
                    doc.text(snapLines, margin + 5, y);
                    y += (snapLines.length * 4);
                    doc.setTextColor(0);
                }
                y += 2;
                if (y > 270) { doc.addPage(); y = 20; }
            });

            doc.save(`isochronic_spatial_export_${Date.now()}.pdf`);
        },

        async generate() {
            if (this.state.loading) return;
            this.state.loading = true;
            document.getElementById('gen-overlay').style.display = 'flex';
            
            const sortedLex = Object.entries(this.state.lexicon).sort((a,b) => b[1].weight - a[1].weight).slice(0, 15);
            const literalWords = sortedLex.filter(e => e[1].influence === 'literal').map(e => e[0]);
            const essenceWords = sortedLex.filter(e => e[1].influence !== 'literal').map(e => `${e[0]} (Context: ${e[1].context || 'None'})`);

            const recentCache = this.state.narrativeHistory.slice(-5).join(" ");
            
            let spatialContext = "No specific spatial anchor active.";
            if (this.state.activeAnchor) {
                const inf = this.state.activeAnchor.influence || 'essence';
                if (inf === 'literal') {
                    spatialContext = `Active Location: "${this.state.activeAnchor.name}". You MUST explicitly mention this location and heavily utilize its context: ${this.state.activeAnchor.meta}.`;
                } else {
                    spatialContext = `Active Location Concept: "${this.state.activeAnchor.name}". Context: ${this.state.activeAnchor.meta}. Implicitly weave the thematic essence, emotion, and atmosphere of this location into the narrative. Do NOT explicitly name it.`;
                }
            }

            const prompt = `
                Task: Synthesize prose.
                
                Literal Constraints (MUST incorporate these words directly): ${literalWords.length ? literalWords.join(', ') : 'None'}.
                
                Thematic Essence (Let these concepts and their contexts heavily guide the tone and meaning): ${essenceWords.length ? essenceWords.join(' | ') : 'None'}.
                
                Spatial Context: ${spatialContext}
                
                Narrative Cache (DO NOT REPEAT PHRASING FROM THIS): ${recentCache}.
                
                Protocol: Ensure narrative progression. The "Essence" elements and Spatial Concept should be the primary drivers of the narrative's soul and direction, outweighing direct word insertion. Do not recycle previous sentence structures or vocabulary.
            `;

            try {
                const res = await fetch(`https://generativelanguage.googleapis.com/v1beta/models/${CONFIG.MODEL}:generateContent?key=${CONFIG.API_KEY}`, {
                    method: "POST", headers: {"Content-Type":"application/json"},
                    body: JSON.stringify({ contents: [{ parts: [{ text: prompt }] }] })
                });
                const data = await res.json();
                const text = data.candidates[0].content.parts[0].text;
                
                this.state.narrativeHistory.push(text);
                if(this.state.narrativeHistory.length > this.state.maxHistory) this.state.narrativeHistory.shift();

                this.processText(text);
                this.logDiag("API Buffer Refreshed", "Content synthesized via Influence Engine (Essence vs Literal).");
                
                document.getElementById('launch-btn-container').classList.remove('d-none');
            } catch(e) { 
                this.logDiag("API Sync Failure", "Generation failed"); 
            }
            finally { 
                this.state.loading = false; 
                document.getElementById('gen-overlay').style.display = 'none'; 
            }
        },

        processText(text) {
            const lines = text.match(/[^\.!\?]+[\.!\?]+/g) || [text];
            lines.forEach((s) => {
                const idx = this.state.sentences.length;
                this.state.sentences.push(s.trim());
                const div = document.createElement('div');
                div.className = 'spectrum-line';
                div.id = `wf-${idx}`;
                div.innerHTML = s.trim().split(' ').map(w => {
                    const clean = w.toLowerCase().replace(/[^\w]/g,'');
                    return `<span class="token ${this.state.lexicon[clean] ? 'text-warning fw-bold' : ''}" onclick="app.commitWord('${clean}', 10)">${w}</span>`;
                }).join(' ');
                document.getElementById('waterfall-area').appendChild(div);
            });
        },

        openStream() { 
            this.state.playing = true; 
            document.getElementById('launch-btn-container').classList.add('d-none'); 
            streamModal.show(); 
            setTimeout(() => this.streamLoop(), 800); 
        },

        manualAdvance() {
            if (!this.state.playing || this.state.isLocked) return;
            if (window.speechSynthesis) window.speechSynthesis.cancel();
            if (this.state.fallbackTimer) clearTimeout(this.state.fallbackTimer);
            
            if (!document.getElementById(`wf-${this.state.lastIdx}`)?.classList.contains('active-focus')) {
                 this.focusLine(this.state.lastIdx);
                 this.state.passiveCount++;
            }

            this.state.lastIdx++;
            this.updateUI();
            setTimeout(() => this.streamLoop(), 100);
        },
        
        streamLoop() {
            if (!this.state.playing || this.state.isLocked || this.state.lastIdx >= this.state.sentences.length) return;
            
            if (this.state.passiveCount >= 10) {
                this.state.isLocked = true;
                if (window.speechSynthesis) window.speechSynthesis.cancel();
                document.getElementById('checkpointOverlay').style.display = 'flex';
                this.logDiag("System", "Neural Checkpoint Triggered.");
                
                if (this.state.autoPilotActive) {
                    this.startAutoPilotCountdown();
                }
                return;
            }

            const currentText = this.state.sentences[this.state.lastIdx];
            
            const availableVoices = window.speechSynthesis ? window.speechSynthesis.getVoices() : [];
            
            if (!window.speechSynthesis || availableVoices.length === 0) {
                this.focusLine(this.state.lastIdx);
                this.state.passiveCount++;
                this.updateUI();
                
                const wordCount = currentText.split(' ').length;
                const readDelayMs = Math.max(2000, wordCount * 300); 
                
                this.state.fallbackTimer = setTimeout(() => {
                    if (this.state.playing && !this.state.isLocked) {
                        this.state.lastIdx++;
                        this.streamLoop();
                    }
                }, readDelayMs);
                return;
            }

            const utter = new SpeechSynthesisUtterance(currentText);
            utter.onstart = () => { 
                this.focusLine(this.state.lastIdx); 
                this.state.passiveCount++; 
                this.updateUI(); 
            };
            utter.onend = () => { this.state.lastIdx++; setTimeout(() => this.streamLoop(), 200); };
            speechSynthesis.speak(utter);
        },

        startAutoPilotCountdown() {
            const duration = parseFloat(document.getElementById('auto-timer').value);
            let remaining = duration;
            const display = document.getElementById('auto-countdown');
            const secSpan = document.getElementById('countdown-sec');
            
            display.style.display = 'block';
            secSpan.innerText = remaining.toFixed(1);

            this.state.countdownInterval = setInterval(async () => {
                remaining -= 0.5;
                secSpan.innerText = remaining.toFixed(1);
                
                if (remaining <= 0) {
                    clearInterval(this.state.countdownInterval);
                    this.logDiag("Auto Pilot", "Timer expired. Synchronizing goal alignment...");
                    await this.runAutoPilotAdjustment();
                }
            }, 500);
        },

        async runAutoPilotAdjustment() {
            const goal = document.getElementById('auto-goal').value || "System Autonomy";
            const currentLex = Object.keys(this.state.lexicon).join(", ");
            
            const prompt = `Goal: ${goal}. Current Lexicon: ${currentLex}. 
            Provide a JSON response containing:
            1. "weights": A dictionary updating 3 existing lexicon words with higher weights (50-100) to meet the goal.
            2. "newWords": 3 additional lexicon words that provide guidance toward this goal.
            Return ONLY valid JSON.`;

            try {
                const res = await fetch(`https://generativelanguage.googleapis.com/v1beta/models/${CONFIG.MODEL}:generateContent?key=${CONFIG.API_KEY}`, {
                    method: "POST", headers: {"Content-Type":"application/json"},
                    body: JSON.stringify({ contents: [{ parts: [{ text: prompt }] }] })
                });
                const data = await res.json();
                const cleanJson = data.candidates[0].content.parts[0].text.replace(/```json|```/g, "").trim();
                const adjustment = JSON.parse(cleanJson);

                Object.entries(adjustment.weights).forEach(([word, wt]) => this.commitWord(word, wt, "AutoPilot Weight Shift"));
                adjustment.newWords.forEach(word => this.commitWord(word, 75, "AutoPilot Goal Injection"));

                this.logDiag("Auto Pilot", `Goal aligned: ${goal}`);
                this.commitCheckpoint();
            } catch (e) {
                this.logDiag("Auto Pilot Error", "Gemini synchronization failed.");
                this.commitCheckpoint();
            }
        },

        focusLine(idx) {
            document.querySelectorAll('.spectrum-line').forEach(l => l.classList.remove('active-focus'));
            const el = document.getElementById(`wf-${idx}`);
            if (el) { 
                el.classList.add('active-focus'); 
                document.getElementById('modalScroll').scrollTo({ top: el.offsetTop - 350, behavior: 'smooth' }); 
            }
        },

        commitCheckpoint() {
            if (this.state.countdownInterval) clearInterval(this.state.countdownInterval);
            document.getElementById('auto-countdown').style.display = 'none';

            const w = document.getElementById('chk-word').value;
            const wt = document.getElementById('chk-weight').value;
            if (w) this.commitWord(w, wt, "Checkpoint Manual");
            document.getElementById('checkpointOverlay').style.display = 'none';
            this.state.isLocked = false;
            this.state.passiveCount = 0;
            if (window.annyang) window.annyang.resume(); 
            this.streamLoop();
        },

        updateUI() { document.getElementById('drift-status').innerText = `Passive Drift: ${this.state.passiveCount}/10`; },
        bufferManager() { 
            if (this.state.sentences.length - this.state.lastIdx <= 3 && this.state.playing && !this.state.loading) {
                this.logDiag("v2.6 Core Latency", "50% Threshold Active.");
                this.generate(); 
            }
        },
        closeStream() { 
            this.state.playing = false; 
            if (this.state.countdownInterval) clearInterval(this.state.countdownInterval);
            if (window.speechSynthesis) window.speechSynthesis.cancel(); 
            if (this.state.fallbackTimer) clearTimeout(this.state.fallbackTimer);
            streamModal.hide(); 
        }
    };

    // Initialize when the DOM inside the shortcode is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => app.init());
    } else {
        app.init();
    }
    </script>
    
    <?php
    return ob_get_clean();
}
