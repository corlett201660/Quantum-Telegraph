<?php
/**
 * Template Name: Blueprint of the Cosmos App
 * Merged into Melle Well VR v4.0
 */

// 1. FORCE THE THEME TO DROP THE SIDEBAR VIA PHP
add_filter( 'body_class', function( $classes ) {
    $classes[] = 'blueprint-app-active';
    $classes[] = 'no-sidebar';                 
    $classes[] = 'full-width-content';         
    $classes[] = 'ast-no-sidebar';             
    $classes[] = 'oceanwp-no-sidebar';         
    $classes[] = 'generatepress-no-sidebar';   
    $classes[] = 'page-template-template-fullwidth-php'; 
    return $classes;
} );

add_filter( 'theme_mod_sidebar_layout', '__return_false' );
add_filter( 'is_active_sidebar', '__return_false' );

$config = get_post_meta( get_the_ID(), '_blueprint_config', true );

$server_config = [
    'playTitle'     => isset($config['play_title']) ? esc_html($config['play_title']) : 'Blueprint of the Cosmos',
    'glossaryTerms' => !empty($config['glossary']) ? json_decode($config['glossary'], true) : [],
    'inspirations'  => !empty($config['inspirations']) ? json_decode($config['inspirations'], true) : ['books'=>[], 'authors'=>[], 'songs'=>[], 'axioms'=>[]],
    'options'       => isset($config['options']) ? $config['options'] : ['dialogueLength' => 'standard'],
];

get_header(); 
?>

<style>
    /* PREVENT BOOTSTRAP MODALS FROM PERMANENTLY LOCKING THE SCROLLBAR */
    html, body.blueprint-app-active {
        overflow-y: auto !important;
    }

    /* AGGRESSIVE CSS OVERRIDES TO NUKE REMAINING SIDEBAR HTML 
    #secondary, aside, #sidebar, .sidebar, .widget-area, .sidebar-container {
        display: none !important;
        width: 0 !important;
        height: 0 !important;
        margin: 0 !important;
        padding: 0 !important;
        visibility: hidden !important;
    }
*/
    /* FORCE ALL THEME WRAPPERS TO BREAK OUT OF COLUMNS AND FILL THE SCREEN 
    #page, #content, #primary, .content-area, .site-main, .site-content, main, .ast-container, .elementor-container {
        width: 100% !important;
        max-width: 100% !important;
        padding: 0 !important;
        margin: 0 !important;
        flex: none !important;
        display: block !important;
        border: none !important;
    }
*/
    /* Scoped layout fixes for our Blueprint App container */
    #blueprint-app { 
        width: 100%; 
        max-width: 1320px; 
        margin: 120px auto 60px auto; 
        padding: 20px 30px; 
        min-height: 80vh; 
        background: var(--bp-bg);
    }
    
    .transfer-alert { background: rgba(0, 242, 255, 0.1); border: 1px solid #00f2ff; color: #00f2ff; padding: 15px; border-radius: 8px; margin-bottom: 20px; display: none; }
    #alertPlaceholder { position: fixed; top: 20px; right: 20px; z-index: 9999; width: 350px; max-width: 90vw; }
    
    @media (max-width: 768px) {
        #blueprint-app { margin-top: 80px; padding: 15px; } 
    }
</style>

<div id="blueprint-app">
    
    <div class="modal fade pre-init" id="welcomeModal" tabindex="-1" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content bg-dark border-info shadow-lg">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title text-info fw-bold"><i class="fas fa-microchip me-2"></i>System Initialization</h5>
                </div>
                <div class="modal-body">
                    <p class="text-secondary small mb-4" id="welcomeMessageText">Initialize the cosmic engine.</p>
                    
                    <div id="sessionDataOptions" style="display: none;">
                        <div class="alert alert-warning py-2 small mb-3 border-warning">
                            <i class="fas fa-database me-2"></i>Previous neural session data detected. Select which elements to carry over.
                        </div>
                        
                        <div class="form-check form-switch mb-3" id="transferDataToggleContainer">
                            <input class="form-check-input" type="checkbox" id="importTransferData" checked>
                            <label class="form-check-label text-light" for="importTransferData">
                                <strong>Isochronic Link Data</strong><br>
                                <span class="text-muted small">Imports intercepted lexicon terms, spatial anchors, and source URLs.</span>
                            </label>
                        </div>

                        <div class="form-check form-switch mb-4" id="wordCloudToggleContainer">
                            <input class="form-check-input" type="checkbox" id="importWordCloud" checked>
                            <label class="form-check-label text-light" for="importWordCloud">
                                <strong>Cumulative Word Cloud</strong><br>
                                <span class="text-muted small">Retains historical word frequencies from past generations.</span>
                            </label>
                        </div>
                        <hr class="border-secondary">
                    </div>
                </div>
                <div class="modal-footer border-secondary d-flex justify-content-between">
                    <button type="button" class="btn btn-outline-danger btn-sm fw-bold" id="startFreshBtn" style="display: none;">Start Fresh (Clear Data)</button>
                    <button type="button" class="btn btn-info fw-bold ms-auto" id="startAppBtn">Initialize Core</button>
                </div>
            </div>
        </div>
    </div>

    <div id="alertPlaceholder"></div>
    <div id="transfer-notification" class="transfer-alert text-center fw-bold shadow-sm">
        <i class="fas fa-satellite-dish me-2"></i> NEURAL TRANSFER COMPLETE: Isochronic Data Integrated.
    </div>

    <div class="blueprint-header d-flex justify-content-between align-items-center mt-4">
        <div>
            <h1 id="playTitle"><?php echo esc_html( $server_config['playTitle'] ); ?></h1>
            <p class="text-secondary">Cosmic Story Engine & Neural Synthesizer</p>
        </div>
    </div>

    <div class="row g-3 mb-4">
        
        <div class="col-lg-4">
            <div class="panel h-100 mb-0">
                <h5 class="text-info border-bottom border-secondary pb-2">Spatiotemporal Coordinates</h5>
                <div class="mb-3">
                    <small class="text-muted d-block">Live Clock</small>
                    <strong id="liveCurrentTime" class="d-block text-white fs-5">Loading...</strong>
                    <span id="liveNumericalTimestamp" class="text-secondary small d-block mb-2"></span>
                    <div class="btn-group w-100">
                        <button id="stopClockBtn" class="btn btn-sm btn-outline-warning">Pause</button>
                        <button id="startClockBtn" class="btn btn-sm btn-outline-success" style="display:none;">Resume</button>
                        <input type="text" id="manualTimestampInput" class="form-control form-control-sm bg-dark text-light border-secondary" placeholder="Set Manual Time">
                        <button id="setManualTimestampBtn" class="btn btn-sm btn-outline-info">Set</button>
                    </div>
                </div>
                <div class="mb-0">
                    <small class="text-muted d-block">Spatial Anchor (GPS)</small>
                    <strong id="userLocationDisplay" class="d-block text-white">Acquiring...</strong>
                    <div class="d-flex gap-2 mt-2">
                        <input type="number" id="manualLatInput" class="form-control form-control-sm bg-dark text-light border-secondary" placeholder="Lat">
                        <input type="number" id="manualLonInput" class="form-control form-control-sm bg-dark text-light border-secondary" placeholder="Lon">
                        <button id="setManualGpsBtn" class="btn btn-sm btn-outline-info">Set</button>
                    </div>
                    <div class="btn-group w-100 mt-2">
                        <button id="refreshGpsBtn" class="btn btn-sm btn-outline-secondary">Refresh</button>
                        <button id="toggleLiveGpsBtn" class="btn btn-sm btn-outline-secondary">Live Track</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="panel h-100 mb-0">
                <h5 class="text-info border-bottom border-secondary pb-2">Crypto Signature</h5>
                <div class="form-check form-switch mb-2">
                    <input class="form-check-input" type="checkbox" id="useWalletCheckbox">
                    <label class="form-check-label" for="useWalletCheckbox">Bind Wallet Address</label>
                </div>
                <div id="walletInputContainer" style="display:none;">
                    <div class="input-group input-group-sm mb-2">
                        <input type="text" id="walletAddressInput" class="form-control bg-dark text-light border-secondary" placeholder="0x...">
                        <button class="btn btn-outline-info" id="setWalletAddressBtn">Bind</button>
                    </div>
                    <small class="text-muted d-block">Numerical: <span id="wallet_numerical_display">N/A</span></small>
                    <small class="text-muted d-block">Latin: <span id="wallet_latin_seq">N/A</span></small>
                    <div id="wallet_rune_seq" class="mt-2 text-warning font-monospace small text-break"></div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="panel editable-section h-100 mb-0">
                <h5 class="text-info border-bottom border-secondary pb-2">Generation Parameters</h5>
                <div class="mb-2">
                    <label class="form-label small">Play Length</label>
                    <select id="playLengthSelector" class="form-select form-select-sm bg-dark text-light border-secondary">
                        <option value="short">Short (3 Acts, 6 Scenes)</option>
                        <option value="standard" selected>Standard (3 Acts, 8 Scenes)</option>
                        <option value="long">Long (3 Acts, 11 Scenes)</option>
                    </select>
                </div>
                <div class="mb-2">
                    <label class="form-label small">Dialogue Pacing</label>
                    <select id="dialogueLengthSelector" class="form-select form-select-sm bg-dark text-light border-secondary">
                        <option value="concise">Concise</option>
                        <option value="standard" selected>Standard</option>
                        <option value="expansive">Expansive</option>
                    </select>
                </div>
                <div class="mb-2">
                    <label class="form-label small">Prompting Method</label>
                    <select id="promptingMethodSelector" class="form-select form-select-sm bg-dark text-light border-secondary">
                        <option value="standard">Standard Continuation</option>
                        <option value="full_blueprint">Full Blueprint Reinforcement</option>
                    </select>
                </div>
                <div class="mb-2">
                    <label class="form-label small">Scene Image Descriptions</label>
                    <select id="imageMethodSelector" class="form-select form-select-sm bg-dark text-light border-secondary">
                        <option value="per_scene">Start of every scene</option>
                        <option value="none" selected>None</option>
                    </select>
                </div>
                <div class="form-check mt-3">
                    <input class="form-check-input" type="checkbox" id="includeNotesCheckbox" checked>
                    <label class="form-check-label small" for="includeNotesCheckbox">Include Director's Notes</label>
                </div>
                <div class="mt-3">
                    <label class="form-label small">Chime Alerts</label>
                    <select id="chimeTriggerSelector" class="form-select form-select-sm bg-dark text-light border-secondary mb-1">
                        <option value="per_scene">Every Scene</option>
                        <option value="per_act">Every Act</option>
                        <option value="end_of_play">End of Play Only</option>
                    </select>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="loopChimeCheckbox">
                        <label class="form-check-label small" for="loopChimeCheckbox">Loop alert until dismissed</label>
                    </div>
                </div>
            </div>
        </div>

    </div> 

    <div class="row g-3 mb-4">
        <div class="col-12">
            <div class="panel mb-0" id="anagramEngineCard">
                <div class="d-flex justify-content-between align-items-center border-bottom border-secondary pb-2 mb-3">
                    <h5 class="mb-0 text-info"><i class="fas fa-language me-2"></i> Neural Anagrams</h5>
                    <button class="btn btn-sm btn-outline-info" id="toggleTranslationBtn" title="Toggle Runes / Latin" style="display: none;">
                        <i class="fas fa-exchange-alt"></i> Translate
                    </button>
                </div>
                
                <div class="mb-3">
                    <span class="small text-muted">Transferred Runes:</span>
                    <div id="transferred-runes-display" class="text-warning font-monospace text-break mt-2" style="font-size: 1.5rem; letter-spacing: 5px;">Checking neural link...</div>
                </div>
                
                <button id="sendRunesToGeminiBtn" class="btn btn-warning fw-bold mb-3 text-dark px-4">
                    <i class="fas fa-brain me-1"></i> Decipher Collected Runes
                </button>
                
                <div id="generated-words-container" class="bg-black p-3 rounded border border-secondary d-flex flex-wrap gap-2 justify-content-center" style="min-height: 80px; font-size: 1.2rem;">
                    <span class="text-muted small align-self-center">Deciphered sequences will appear here...</span>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            
            <div class="panel mb-4">
                <ul class="nav nav-tabs border-secondary mb-3" id="outputTabs" role="tablist">
                    <li class="nav-item"><button class="nav-link active text-info" data-bs-toggle="tab" data-bs-target="#lexicon-tab">Lexicon & Inspirations</button></li>
                    <li class="nav-item"><button class="nav-link text-info" data-bs-toggle="tab" data-bs-target="#runes-tab">Cosmic Runes</button></li>
                    <li class="nav-item"><button class="nav-link text-info" data-bs-toggle="tab" data-bs-target="#cloud-tab">Word Cloud (Cumulative)</button></li>
                    <li class="nav-item"><button class="nav-link text-info" data-bs-toggle="tab" data-bs-target="#debug-tab">Prompt Log</button></li>
                </ul>
                <div class="tab-content" id="outputTabsContent">
                    
                    <div class="tab-pane fade show active" id="lexicon-tab">
                        <div class="row">
                            <div class="col-md-6 editable-section">
                                <h6 class="text-warning">Glossary Terms</h6>
                                <div id="glossaryList" class="mb-3" style="max-height: 300px; overflow-y: auto;"></div>
                                <div class="input-group input-group-sm">
                                    <input type="text" id="newTermInput" class="form-control bg-dark text-white border-secondary" placeholder="New Term">
                                    <input type="text" id="newDefinitionInput" class="form-control bg-dark text-white border-secondary w-50" placeholder="Definition">
                                    <button class="btn btn-secondary" id="addTermBtn">Add</button>
                                </div>
                            </div>
                            <div class="col-md-6 editable-section">
                                <h6 class="text-warning">Inspirations & Axioms</h6>
                                <ul class="nav nav-pills nav-sm mb-2" id="inspirationPills" role="tablist">
                                    <li class="nav-item"><button class="nav-link active py-1 px-2" data-bs-toggle="pill" data-bs-target="#books-tab-pane">Books</button></li>
                                    <li class="nav-item"><button class="nav-link py-1 px-2" data-bs-toggle="pill" data-bs-target="#authors-tab-pane">Authors</button></li>
                                    <li class="nav-item"><button class="nav-link py-1 px-2" data-bs-toggle="pill" data-bs-target="#songs-tab-pane">Songs</button></li>
                                </ul>
                                
                                <div class="tab-content" id="inspirationsTabContent" style="max-height: 200px; overflow-y: auto;">
                                    
                                    <div class="tab-pane fade show active" id="books-tab-pane">
                                        <div id="booksList" class="mb-2"></div>
                                        <div class="input-group input-group-sm">
                                            <input type="text" id="newBookInput" class="form-control bg-dark text-white border-secondary" placeholder="Add Book Inspiration">
                                            <button class="btn btn-secondary" id="addBookBtn">Add</button>
                                        </div>
                                    </div>
                                    
                                    <div class="tab-pane fade" id="authors-tab-pane">
                                        <div id="authorsList" class="mb-2"></div>
                                        <div class="input-group input-group-sm">
                                            <input type="text" id="newAuthorInput" class="form-control bg-dark text-white border-secondary" placeholder="Add Author Style">
                                            <button class="btn btn-secondary" id="addAuthorBtn">Add</button>
                                        </div>
                                    </div>
                                    
                                    <div class="tab-pane fade" id="songs-tab-pane">
                                        <div id="songsList" class="mb-2"></div>
                                        <div class="input-group input-group-sm">
                                            <input type="text" id="newSongInput" class="form-control bg-dark text-white border-secondary" placeholder="Add Song Mood">
                                            <button class="btn btn-secondary" id="addSongBtn">Add</button>
                                        </div>
                                    </div>

                                </div>

                                <hr class="border-secondary mt-3">
                                <h6 class="text-warning mt-3">Cosmic Axioms</h6>
                                <div id="cosmicAxiomsContainer" class="mb-2 small text-muted" style="max-height: 150px; overflow-y: auto;"></div>
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="runes-tab">
                        <div class="row text-center font-monospace">
                            <div class="col-3">
                                <h6 class="text-muted small">Direct Sequence</h6>
                                <div id="direct_rune_seq" class="text-info fs-4"></div>
                            </div>
                            <div class="col-3">
                                <h6 class="text-muted small">Expanded</h6>
                                <div id="expanded_rune_seq" class="text-warning fs-4"></div>
                            </div>
                            <div class="col-3">
                                <h6 class="text-muted small">Shifted</h6>
                                <div id="shifted_rune_seq" class="text-success fs-4"></div>
                            </div>
                            <div class="col-3">
                                <h6 class="text-muted small">Reversed</h6>
                                <div id="reversed_rune_seq" class="text-danger fs-4"></div>
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="cloud-tab">
                        <div class="d-flex justify-content-between">
                            <div id="wordCloudContent" class="w-75 text-center p-3"></div>
                            <div id="wordcloud_qr_code" class="w-25 d-flex justify-content-center align-items-center"></div>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="debug-tab">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="m-0 text-secondary">Latest API Payload</h6>
                            <button id="copyGeminiPromptBtn" class="btn btn-sm btn-outline-secondary" title="Copy to clipboard"><i class="fas fa-copy"></i></button>
                        </div>
                        <textarea id="geminiPromptOutput" class="form-control bg-black text-success font-monospace small" rows="10" readonly></textarea>
                    </div>

                </div>
            </div>

            <div class="panel d-flex flex-wrap gap-2 align-items-center justify-content-between mb-4">
                <div>
                    <button id="submitToGeminiBtn" class="btn btn-primary fw-bold px-4 py-2" style="font-size: 1.1rem;">Generate Full Screenplay</button>
                </div>
                <div class="d-flex gap-2">
                    <button id="listenToPlayBtn" class="btn btn-outline-info" style="display:none;"><i class="fas fa-play"></i> Listen</button>
                    <button id="stopListeningBtn" class="btn btn-outline-danger" style="display:none;"><i class="fas fa-stop"></i> Stop</button>
                    <button id="scrollToSpokenSceneBtn" class="btn btn-outline-secondary" style="display:none;"><i class="fas fa-crosshairs"></i> Track</button>
                    <button id="exportCodexBtn" class="btn btn-outline-primary fw-bold" style="display:none;"><i class="fas fa-file-pdf"></i> Export PDF Codex</button>
                </div>
            </div>

            <div id="geminiResultContainer" class="panel min-vh-50" style="min-height: 500px;">
                <p class="text-muted text-center py-5">Your generated screenplay will appear here...</p>
            </div>

            <div id="stickyProgressIndicator" class="btn btn-info position-fixed bottom-0 end-0 m-4 rounded-pill shadow" style="display:none; z-index: 1050; cursor:pointer;">
                <span class="spinner-border spinner-border-sm me-2" role="status"></span>
                <span id="stickyProgressText">Generating...</span>
                <span id="stickyProgressTimer" class="ms-1 small"></span>
            </div>

            <div id="generationProgressBarModal" class="modal fade" data-bs-backdrop="static" tabindex="-1">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content bg-dark border-info">
                        <div class="modal-body text-center p-4">
                            <h4 class="text-info mb-3">Synthesizing Narrative</h4>
                            <div class="progress mb-3" style="height: 25px; background-color: #222;">
                                <div id="generationProgressBar" class="progress-bar progress-bar-striped progress-bar-animated bg-info" role="progressbar" style="width: 0%;"></div>
                            </div>
                            <div class="d-flex justify-content-between text-muted small mb-3">
                                <span id="progressBarPercentage">0%</span>
                                <span id="progressStatusText">Initializing...</span>
                            </div>
                            <div class="d-flex justify-content-between text-secondary small">
                                <span id="progressTimerText">Elapsed: 0s</span>
                                <span id="progressEtaText">Calculating...</span>
                            </div>
                            <button id="dismissProgressBarBtn" class="btn btn-outline-secondary btn-sm mt-4">Run in Background</button>
                        </div>
                    </div>
                </div>
            </div>

        </div> 
    </div> 

    <div class="modal fade" id="glossaryEditModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content bg-dark border-warning">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title text-warning fw-bold">Edit Lexicon Node</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="glossaryEditModalIndex">
                    <div class="mb-3">
                        <label for="glossaryEditModalTermInput" class="form-label text-secondary small">Term</label>
                        <input type="text" class="form-control bg-black text-white border-secondary" id="glossaryEditModalTermInput">
                    </div>
                    <div class="mb-3">
                        <label for="glossaryEditModalDefinitionInput" class="form-label text-secondary small">Context / Definition</label>
                        <textarea class="form-control bg-black text-white border-secondary" id="glossaryEditModalDefinitionInput" rows="4"></textarea>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-warning text-dark fw-bold" id="saveGlossaryTermBtn">Save Changes</button>
                </div>
            </div>
        </div>
    </div>

</div>

<script>
  let serverConfig = <?php echo wp_json_encode( $server_config ); ?>;
  window.blueprintData = serverConfig;
</script>

<?php 
get_footer(); 
?>
