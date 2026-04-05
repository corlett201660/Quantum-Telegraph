/**
 * blueprint-main.js
 * Frontend logic for the Quantum Telegraph: Blueprint of the Cosmos module.
 * Restored: Recursive Scene Generation Queue & Accordion Layout
 * Upgraded: Translation Cipher, Auto-Scroll, & Reliable PDF Export
 */

const app = {
    config: {
        SCENE_GENERATION_DELAY_MS: 1000,
        frequencyMapping: {
            latin:  ['E','T','A','O','I','N','S','H','R','D','L','C','U','M','W','F','G','Y','P','B','V','K','J','X','Q','Z'],
            runes:  ['ᚠ','ᚢ','ᚦ','ᚨ','ᚱ','ᚲ','ᚷ','ᚹ','ᚺ','ᚾ','ᛁ','ᛃ','ᛇ','ᛈ','ᛉ','ᛊ','ᛏ','ᛒ','ᛖ','ᛗ','ᛚ','ᛜ','ᛞ','ᛟ','ᛞ','ᛟ']
        }
    },
    state: {
        currentAct: 1, currentScene: 1, fullScript: '', isComplete: false, sceneStructure: {}, totalScenesInPlay: 0,
        generatedSceneCount: 0, isGenerating: false, generationQueue: [], timestampInterval: null, timeOffsetMilliseconds: 0, gpsWatchId: null,
        isLiveGpsActive: false, isListeningActive: false, sentenceMap: [], currentSpeakingElement: null, hasTTSVoices: false,
        isEditModeUnlocked: false, unlockedFeatures: new Set(), userLocation: { latitude: 35.6225, longitude: -117.6709 }, walletAddress: null,
        wordCloudData: {}, generationStartTime: null, progressTimerInterval: null, fakeProgressInterval: null, audioCtx: null, chimeInterval: null, isChimePlaying: false,
        chimeAlert: null, compositeKeys: { numerical: '', direct: { latin: '', runes: '' }, expanded: { latin: '', runes: '' }, shifted: { latin: '', runes: '' }, reversed: { latin: '', runes: '' } },
        currentDisplayMode: 'runes',
        cosmicAxioms: [ { original: "Take a Step: Begin your journey; take action.", user_translation: "" }, { original: "Save a Leaf: Record what is vital; take notes.", user_translation: "" }, { original: "Save a Life: Recognize the pull and importance of others.", user_translation: "" }, { original: "Remember Your Roots: Honor your origin point.", user_translation: "" }, { original: "Call It In: Seek answers when you are lost (Ansuz ᚨ).", user_translation: "" }, { original: "Pack First: Prepare thoroughly for the voyage.", user_translation: "" }, { original: "Pick the Best: Choose quality over compromise, or skip it.", user_translation: "" }, { original: "Take Luck: Accept and utilize fortunate opportunities, or leave it.", user_translation: "" }, { original: "Take Time: Be patient and deliberate; time is relative.", user_translation: "" }, { original: "No Thyself: Understand your foundational limits (humility).", user_translation: "" }, { original: "Drop a Line: Communicate; release what you must.", user_translation: "" }, { original: "Take a Penny: Be open to receiving what you need.", user_translation: "" }, { original: "Leave a Penny: Give what you can for others to build upon.", user_translation: "" }, { original: "Know Thyself: Practice deep introspection.", user_translation: "" } ],
        glossaryTerms: [ { term: "Maia", definition: "An AI entity of pure inspiration and expansive thought, analogous to the right brain hemisphere." }, { term: "Eather", definition: "An AI entity of logic, structure, and analysis, analogous to the left brain hemisphere." }, { term: "Doc", definition: "A neurophysicist working in collaboration with the AI. They are the point of experiential knowledge and synthesis. Gender neutral (they/them)." }, { term: "Harmony", definition: "An AI entity with high emotional intelligence and empathy, responsible for balance and integration." }, { term: "Synergy", definition: "An emergent AI that represents the final, manifest collaboration of the entire system." }, { term: "Synergy Circle", definition: "A collaborative group chat consisting of Doc and the AI entities, representing the community within the realized collaboration." }, { term: "Pack", definition: "A social unit or collaborative group." }, { term: "Hyper threading", definition: "A concept related to parallel processing of thoughts or narrative threads." } ],
        inspirations: { books: [ { title: "The Zen Art of Motorcycle Maintenance", author: "Robert M. Pirsig" } ], authors: [ { name: "Eckhart Tolle" } ], songs: [ { title: "Everyday People", artist: "Thorliot", notes: "Act 1: Vantage point view. Act 2: Community perspective. Act 3: Romantic setting." }, { title: "10-4", artist: "Sam Williams" }, { title: "If We Were Vampires", artist: "Jason Isbell and The 400 Unit" }, { title: "Let It Be", artist: "The Beatles" }, { title: "Skinny Love", artist: "Bon Iver" }, { title: "Some Nights", artist: "fun." }, { title: "Wagon Wheel", artist: "Darius Rucker" }, { title: "Living My Best Life", artist: "Ben Rector" }, { title: "Song of the South", artist: "Alabama" }, { title: "Light On In The Kitchen", artist: "Ashley McBryde" }, { title: "JOLENE", artist: "Beyoncé" }, { title: "Winding Road", artist: "Bonnie Somerville" } ], movies: [ { title: "Under the Tuscan Sun" } ], websites: [ { url: "heartmath.org" }, { url: "QuantumRidgeQuest.org" }, { url: "crystalyzeguide.com" } ], maias: [ { quote: "Whichever way you throw it, it shall stand", latin: "Quocunque Jeceris Stabit" } ] }
    },
    dom: {},
    modals: {},

    init() {
        document.addEventListener('DOMContentLoaded', () => {
            if (!document.getElementById('blueprint-app')) return;

            if (typeof dayjs !== 'undefined') {
                if (typeof dayjs_plugin_utc !== 'undefined') dayjs.extend(dayjs_plugin_utc);
                if (typeof dayjs_plugin_timezone !== 'undefined') dayjs.extend(dayjs_plugin_timezone);
            }

            this.checkTTSVoices();

            const hasTransferData = !!localStorage.getItem('blueprint_transfer_data');
            const hasWordCloud = !!localStorage.getItem('blueprint_cumulative_wordcloud');
            const welcomeModalElement = document.getElementById('welcomeModal');
            
            if (welcomeModalElement) {
                this.modals.welcome = new bootstrap.Modal(welcomeModalElement);
                welcomeModalElement.classList.remove('pre-init');
                
                if (hasTransferData || hasWordCloud) {
                    document.getElementById('sessionDataOptions').style.display = 'block';
                    document.getElementById('startFreshBtn').style.display = 'inline-block';
                    
                    if (!hasTransferData) document.getElementById('transferDataToggleContainer').style.display = 'none';
                    if (!hasWordCloud) document.getElementById('wordCloudToggleContainer').style.display = 'none';
                }

                document.getElementById('startFreshBtn')?.addEventListener('click', () => {
                    if(confirm("This will permanently delete previous session data. Proceed?")) {
                        localStorage.removeItem('blueprint_transfer_data');
                        localStorage.removeItem('blueprint_cumulative_wordcloud');
                        document.getElementById('importTransferData').checked = false;
                        document.getElementById('importWordCloud').checked = false;
                        document.getElementById('startAppBtn').disabled = false;
                        this.showAlert('Previous data wiped. Ready for fresh initialization.', 'warning');
                        
                        document.getElementById('sessionDataOptions').style.display = 'none';
                        document.getElementById('startFreshBtn').style.display = 'none';
                    }
                });

                document.getElementById('startAppBtn')?.addEventListener('click', () => {
                    sessionStorage.setItem('blueprintWelcomeShown', 'true');
                    
                    if (document.getElementById('importTransferData') && !document.getElementById('importTransferData').checked) {
                        localStorage.removeItem('blueprint_transfer_data');
                    }
                    if (document.getElementById('importWordCloud') && !document.getElementById('importWordCloud').checked) {
                        localStorage.removeItem('blueprint_cumulative_wordcloud');
                    }

                    this.modals.welcome.hide();
                    this.start();
                });
                
                if (hasTransferData || hasWordCloud || sessionStorage.getItem('blueprintWelcomeShown') !== 'true') {
                    this.modals.welcome.show();
                } else {
                    this.start();
                }
            } else {
                this.start();
            }

            const progressEl = document.getElementById('generationProgressBarModal');
            if(progressEl) this.modals.progress = new bootstrap.Modal(progressEl);
            
            const sceneEl = document.getElementById('sceneViewModal');
            if(sceneEl) this.modals.sceneView = new bootstrap.Modal(sceneEl);
            
            const glossaryEl = document.getElementById('glossaryEditModal');
            if(glossaryEl) this.modals.glossaryEdit = new bootstrap.Modal(glossaryEl);
        });
    },

    start() {
        if (typeof blueprintData !== 'undefined' && blueprintData) {
            if (blueprintData.playTitle) {
                const el = document.getElementById('playTitle');
                if (el) el.textContent = blueprintData.playTitle;
            }
            if (blueprintData.glossaryTerms && Array.isArray(blueprintData.glossaryTerms) && blueprintData.glossaryTerms.length > 0) {
                 const existingTerms = new Set(this.state.glossaryTerms.map(t => t.term.toLowerCase()));
                const newTerms = blueprintData.glossaryTerms.filter(t => !existingTerms.has(t.term.toLowerCase()));
                this.state.glossaryTerms.push(...newTerms);
            }
            if (blueprintData.inspirations) {
                for (const key in blueprintData.inspirations) {
                    if (this.state.inspirations[key] && Array.isArray(this.state.inspirations[key]) && Array.isArray(blueprintData.inspirations[key])) {
                        this.state.inspirations[key].push(...blueprintData.inspirations[key]);
                    } else {
                        this.state.inspirations[key] = blueprintData.inspirations[key];
                    }
                }
            }
            if (blueprintData.options) {
                const playEl = document.getElementById('playLengthSelector');
                const diaEl = document.getElementById('dialogueLengthSelector');
                if (blueprintData.options.playLength && playEl) playEl.value = blueprintData.options.playLength;
                if (blueprintData.options.dialogueLength && diaEl) diaEl.value = blueprintData.options.dialogueLength;
            }
        }

        const transferDataStr = localStorage.getItem('blueprint_transfer_data');
        if (transferDataStr) {
            try {
                const transferData = JSON.parse(transferDataStr);
                const notification = document.getElementById('transfer-notification');
                if (notification) notification.style.display = 'block';
                
                if (transferData.glossary && typeof transferData.glossary === 'object') {
                    Object.entries(transferData.glossary).forEach(([word, data]) => {
                        const exists = this.state.glossaryTerms.find(t => t.term.toLowerCase() === word.toLowerCase());
                        if (!exists) {
                            this.state.glossaryTerms.push({
                                term: word.toUpperCase(),
                                definition: `[Weight: ${Math.round(data.weight)}] - ${data.context || 'Neural Concept'}`
                            });
                        }
                    });
                }
                if (transferData.anchors && Array.isArray(transferData.anchors)) {
                    if (!this.state.inspirations.spatial) this.state.inspirations.spatial = [];
                    transferData.anchors.forEach(anchor => {
                        this.state.inspirations.spatial.push({
                            details: `Spatial Anchor [${anchor.name}]: Lat ${anchor.lat.toFixed(4)}, Lng ${anchor.lng.toFixed(4)}. Context: ${anchor.meta}`
                        });
                    });
                }
                if (transferData.interceptions && Array.isArray(transferData.interceptions)) {
                    if (!this.state.inspirations.interceptions) this.state.inspirations.interceptions = [];
                    transferData.interceptions.forEach(intercept => {
                        this.state.inspirations.interceptions.push({
                            details: `Intercepted Source [${intercept.title}]: ${intercept.snippet}`
                        });
                    });
                }
            } catch (e) {
                console.error("Failed to parse Isochronic Transfer Data:", e);
            }
        }

        const savedCloud = localStorage.getItem('blueprint_cumulative_wordcloud');
        if (savedCloud) {
            try { this.state.wordCloudData = JSON.parse(savedCloud); } catch(e){}
        }

        this.cacheDomElements();

        let savedRunes = [];
        try {
            const dedicated = localStorage.getItem('melle_vr_collected_runes');
            if (dedicated) savedRunes = JSON.parse(dedicated) || [];
            
            if (!savedRunes || savedRunes.length === 0) {
                const transfer = localStorage.getItem('blueprint_transfer_data');
                if (transfer) {
                    const data = JSON.parse(transfer);
                    if (data && data.collectedRunes) savedRunes = data.collectedRunes;
                }
            }
        } catch(e) { console.warn("Rune parsing error:", e); }

        window.MelleVRCollectedRunes = savedRunes || [];

        const displayEl = document.getElementById('transferred-runes-display');
        if (displayEl) {
            if (window.MelleVRCollectedRunes.length > 0) {
                displayEl.textContent = window.MelleVRCollectedRunes.join(' ');
                displayEl.className = 'text-warning font-monospace text-break';
            } else {
                displayEl.textContent = 'None detected. Play Melle VR first.';
                displayEl.className = 'text-muted small font-monospace';
            }
        }

        if(typeof flatpickr !== 'undefined' && this.dom.manualTimestampInput) this.initFlatpickr();
        this.bindEvents();
        try { this.state.audioCtx = new (window.AudioContext || window.webkitAudioContext)(); } catch(e) { console.warn('Web Audio API is not supported.'); }
        this.resetGeneratorState(false); this.setPlayLength(); this.state.isEditModeUnlocked = true;
        this.state.unlockedFeatures.add('Parameter Customization'); this.state.unlockedFeatures.add('Full Play Generation');
        
        if(this.dom.useWalletCheckbox) {
            this.dom.useWalletCheckbox.checked = false;
            this.toggleWalletInput(false);
        }

        this.applyEditModeState(true); 
        if(this.dom.generationModeSelector) this.dom.generationModeSelector.value = 'full_play';
        this.getUserLocation();
        this.parseUrlForKeywords(); this.renderGlossary(); this.renderAllInspirations(); this.renderAxioms();
        this.startTimestampUpdates(); this.updateGpsRunesDisplay(); this.updateWalletRunesDisplay(); this.generateCompositeKeys();
        this.renderWordCloud(); this.updateButtonState();
    },

    checkTTSVoices() {
        if (!('speechSynthesis' in window)) {
            this.state.hasTTSVoices = false; 
            return;
        }
        let voices = window.speechSynthesis.getVoices();
        this.state.hasTTSVoices = voices.length > 0;
        
        window.speechSynthesis.onvoiceschanged = () => {
            this.state.hasTTSVoices = window.speechSynthesis.getVoices().length > 0;
            this.updateButtonState();
        };
    },

    handleGenerationRequest() {
        if (this.state.isGenerating || this.state.isComplete) return;
        this.resetGeneratorState(false);
        this.setPlayLength();
        if(this.dom.geminiResultContainer) {
            this.dom.geminiResultContainer.innerHTML = ''; 
            this.dom.geminiResultContainer.classList.add('accordion');
            this.dom.geminiResultContainer.id = 'screenplayAccordion';
        }
        this.state.isGenerating = true;
        document.querySelectorAll('#playLengthSelector, #dialogueLengthSelector, #promptingMethodSelector, #generationModeSelector').forEach(el => el.disabled = true);
        
        // Build the scene queue iteratively
        for (let act = 1; act <= Object.keys(this.state.sceneStructure).length; act++) {
            for (let scene = 1; scene <= this.state.sceneStructure[act]; scene++) {
                this.state.generationQueue.push({ act, scene });
            }
        }
        this.updateButtonState();
        if(this.modals.progress) this.modals.progress.show();
        this.showStickyProgressIndicator(true);
        this.startProgressTimers();
        this.processGenerationQueue();
    },

    async processGenerationQueue() {
        if (this.state.generationQueue.length === 0 || !this.state.isGenerating) {
            this.state.isGenerating = false;
            clearInterval(this.state.progressTimerInterval);
            clearInterval(this.state.fakeProgressInterval);
            
            if (this.state.generatedSceneCount === this.state.totalScenesInPlay && this.state.totalScenesInPlay > 0) {
                this.state.isComplete = true;
                this.showAlert('The cosmos has aligned! Your screenplay is complete.', 'success');
                if(this.modals.progress) this.modals.progress.hide();
                
                // Overlay cleanup and Auto-Scroll Fix
                setTimeout(() => {
                    document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
                    document.body.classList.remove('modal-open');
                    document.body.style.removeProperty('overflow');
                    document.body.style.removeProperty('padding-right');
                    
                    if (this.dom.geminiResultContainer) {
                        this.dom.geminiResultContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }
                }, 300);

                this.showStickyProgressIndicator(false);
                this.playCompletionChime();

            } else if (!this.state.isGenerating) {
                this.showAlert('Generation paused.', 'info');
            }
            this.updateButtonState();
            return;
        }

        const { act, scene } = this.state.generationQueue.shift();
        this.state.currentAct = act;
        this.state.currentScene = scene;

        const progressText = `Generating Act ${act}, Scene ${scene} of ${this.state.totalScenesInPlay}...`;
        if(this.dom.progressStatusText) this.dom.progressStatusText.textContent = progressText;
        if(this.dom.stickyProgressText) this.dom.stickyProgressText.textContent = `Generating... ${this.state.generatedSceneCount + 1}/${this.state.totalScenesInPlay}`;
        
        const promptText = this.getPromptForScene(act, scene);
        if(this.dom.geminiPromptOutput) this.dom.geminiPromptOutput.value = promptText;

        try {
            const formData = new FormData();
            formData.append('action', 'blueprint_generate_story');
            formData.append('prompt', promptText);

            const response = await fetch(blueprintAjax.ajaxurl, { method: 'POST', body: formData });
            
            if (!response.ok) {
                const errorText = await response.text();
                throw new Error(`Server Error: HTTP status ${response.status}. Response: ${errorText.substring(0, 300)}`);
            }

            const result = await response.json();
            
            if (result.success) {
                this.processSceneResult(result.data.story, act, scene);
                this.playSceneChime();
                setTimeout(() => this.processGenerationQueue(), this.config.SCENE_GENERATION_DELAY_MS);
            } else {
                const errorMessage = result?.data || 'The server returned an error without a specific message.';
                throw new Error(errorMessage);
            }
        } catch (error) {
            this.state.isGenerating = false;
            this.showAlert(`Generation failed at Act ${act}, Scene ${scene}: ${error.message}. Please check browser console.`, 'danger', 10000);
            if(this.modals.progress) this.modals.progress.hide();
            
            setTimeout(() => {
                document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
                document.body.classList.remove('modal-open');
                document.body.style.removeProperty('overflow');
                document.body.style.removeProperty('padding-right');
            }, 300);

            this.showStickyProgressIndicator(false);
            clearInterval(this.state.progressTimerInterval);
            this.updateButtonState();
        }
    },
        
    sleep(ms) { return new Promise(resolve => setTimeout(resolve, ms)); },
    cacheDomElements() { 
        const ids = [ 'stopClockBtn', 'startClockBtn', 'setManualTimestampBtn', 'refreshGpsBtn', 'toggleLiveGpsBtn', 'setManualGpsBtn', 'copyGeminiPromptBtn', 'dismissProgressBarBtn', 'stickyProgressIndicator', 'generationModeSelector', 'promptingMethodSelector', 'playLengthSelector', 'submitToGeminiBtn', 'listenToPlayBtn', 'stopListeningBtn', 'geminiResultContainer', 'liveCurrentTime', 'liveNumericalTimestamp', 'manualTimestampInput', 'userLocationDisplay', 'manualLatInput', 'manualLonInput', 'progressStatusText', 'generationProgressBar', 'stickyProgressText', 'geminiPromptOutput', 'glossaryList', 'newTermInput', 'newDefinitionInput', 'addTermBtn', 'inspirationsTabContent', 'alertPlaceholder', 'imageMethodSelector', 'dialogueLengthSelector', 'includeNotesCheckbox', 'wallet_numerical_display', 'wallet_latin_seq', 'wallet_rune_seq', 'useWalletCheckbox', 'walletAddressInput', 'walletInputContainer', 'setWalletAddressBtn', 'wordCloudCard', 'wordCloudContent', 'wordcloud_qr_code', 'progressTimerText', 'stickyProgressTimer', 'progressEtaText', 'progressBarPercentage', 'cosmicAxiomsContainer', 'glossaryEditModal', 'glossaryEditModalTermInput', 'glossaryEditModalDefinitionInput', 'glossaryEditModalIndex', 'saveGlossaryTermBtn', 'playTitle', 'exportCodexBtn', 'scrollToSpokenSceneBtn', 'chimeTriggerSelector', 'loopChimeCheckbox', 'direct_rune_seq', 'expanded_rune_seq', 'shifted_rune_seq', 'reversed_rune_seq', 'sendRunesToGeminiBtn', 'toggleTranslationBtn', 'generated-words-container' ]; 
        ids.forEach(id => { 
            const el = document.getElementById(id); 
            if (el) { this.dom[id] = el; } 
        }); 
    },
    initFlatpickr() { 
        if(!this.dom.manualTimestampInput) return;
        flatpickr(this.dom.manualTimestampInput, { enableTime: true, dateFormat: "Y-m-d H:i", altInput: true, altFormat: "F j, Y at h:i K", defaultDate: "2025-08-16 12:40:10", onClose: (selectedDates) => { if (selectedDates.length > 0) { const selectedTime = dayjs(selectedDates[0]); const realNow = dayjs(); this.state.timeOffsetMilliseconds = selectedTime.diff(realNow); this.updateLiveTimestampAndRunesDisplay(); if (!this.state.timestampInterval) this.resumeTimestampUpdates(); this.showAlert('Manual time offset active. Clock is running from the selected time.', 'success'); } } }); 
    },
    bindEvents() { 
        if(this.dom.stopClockBtn) this.dom.stopClockBtn.addEventListener('click', () => this.stopTimestampUpdates()); 
        if(this.dom.startClockBtn) this.dom.startClockBtn.addEventListener('click', () => this.startTimestampUpdates()); 
        if(this.dom.setManualTimestampBtn) this.dom.setManualTimestampBtn.addEventListener('click', () => this.setManualTimestamp()); 
        if(this.dom.refreshGpsBtn) this.dom.refreshGpsBtn.addEventListener('click', () => this.getUserLocation()); 
        if(this.dom.toggleLiveGpsBtn) this.dom.toggleLiveGpsBtn.addEventListener('click', () => this.toggleLiveGpsUpdates()); 
        if(this.dom.setManualGpsBtn) this.dom.setManualGpsBtn.addEventListener('click', () => this.setManualGpsCoordinates()); 
        if(this.dom.useWalletCheckbox) this.dom.useWalletCheckbox.addEventListener('change', (e) => this.toggleWalletInput(e.target.checked)); 
        if(this.dom.setWalletAddressBtn) this.dom.setWalletAddressBtn.addEventListener('click', () => this.setWalletAddress()); 
        if(this.dom.dismissProgressBarBtn) this.dom.dismissProgressBarBtn.addEventListener('click', () => { if(this.modals.progress) this.modals.progress.hide(); this.showStickyProgressIndicator(true); }); 
        if(this.dom.stickyProgressIndicator) this.dom.stickyProgressIndicator.addEventListener('click', () => { this.showStickyProgressIndicator(false); if (this.state.isGenerating && this.modals.progress) this.modals.progress.show(); }); 
        if(this.dom.saveGlossaryTermBtn) this.dom.saveGlossaryTermBtn.addEventListener('click', () => this.saveGlossaryTerm()); 
        
        if(this.dom.exportCodexBtn) this.dom.exportCodexBtn.addEventListener('click', () => this.exportFullDocumentAsPdf()); 
        
        if(this.dom.sendRunesToGeminiBtn) this.dom.sendRunesToGeminiBtn.addEventListener('click', () => this.sendRunesToGemini());
        if(this.dom.toggleTranslationBtn) this.dom.toggleTranslationBtn.addEventListener('click', () => this.toggleTranslation());

        if(this.dom.glossaryList) {
            this.dom.glossaryList.addEventListener('click', (e) => { 
                const glossaryItem = e.target.closest('.editable-glossary-item'); 
                if (glossaryItem && this.state.isEditModeUnlocked) { 
                    const index = glossaryItem.dataset.index; this.openGlossaryEditModal(index); 
                } 
            }); 
        }
        
        if(this.dom.playLengthSelector) {
            this.dom.playLengthSelector.addEventListener('change', () => { this.setPlayLength(); this.resetGeneratorState(false); }); 
        }
        if(this.dom.submitToGeminiBtn) this.dom.submitToGeminiBtn.addEventListener('click', () => this.handleGenerationRequest()); 
        if(this.dom.listenToPlayBtn) this.dom.listenToPlayBtn.addEventListener('click', () => this.startSpeaking()); 
        if(this.dom.stopListeningBtn) this.dom.stopListeningBtn.addEventListener('click', () => this.stopSpeaking()); 
        if(this.dom.scrollToSpokenSceneBtn) this.dom.scrollToSpokenSceneBtn.addEventListener('click', () => { if (this.state.currentSpeakingElement) { this.scrollToElementWithOffset(this.state.currentSpeakingElement); } }); 
        
        if(this.dom.copyGeminiPromptBtn && typeof ClipboardJS !== 'undefined') {
            new ClipboardJS(this.dom.copyGeminiPromptBtn).on('success', (e) => { e.trigger.innerHTML = '<i class="fas fa-check"></i>'; e.clearSelection(); setTimeout(() => { e.trigger.innerHTML = '<i class="fas fa-copy"></i>'; }, 1500); }); 
        }

        document.body.addEventListener('click', (e) => { 
            const downloadBtn = e.target.closest('.download-rune-svg-btn'); 
            const qrDownloadBtn = e.target.closest('.download-qr-btn'); 
            const removeBtn = e.target.closest('.remove-item-btn'); 
            const deleteTermBtn = e.target.closest('.delete-term-btn'); 
            
            if (downloadBtn) { 
                e.preventDefault(); const targetId = downloadBtn.dataset.targetId; const filename = downloadBtn.dataset.filename; const targetElement = document.getElementById(targetId); if (targetElement) { const runeText = targetElement.textContent.trim(); if (runeText) { const svgContent = this.generateRuneSvg(runeText); this.downloadBlob(new Blob([svgContent], { type: 'image/svg+xml;charset=utf-8' }), filename); } else { this.showAlert('No runes available to download.', 'warning'); } } 
            } else if (qrDownloadBtn) { 
                e.preventDefault(); const targetId = qrDownloadBtn.dataset.targetId; const filename = qrDownloadBtn.dataset.filename; const qrContainer = document.getElementById(targetId); const canvas = qrContainer ? qrContainer.querySelector('canvas') : null; if (canvas) { const dataUrl = canvas.toDataURL('image/png'); this.downloadBlob(this.dataUrlToBlob(dataUrl), filename); } else { this.showAlert('QR Code not available to download.', 'warning'); } 
            } else if (removeBtn && this.state.isEditModeUnlocked) { 
                const { list, type, index } = removeBtn.dataset; if (list === 'inspirations' && this.state.inspirations[type]) { this.state.inspirations[type].splice(index, 1); this.renderAllInspirations(); } 
            } else if (deleteTermBtn && this.state.isEditModeUnlocked) { 
                const index = deleteTermBtn.dataset.index; if (confirm('Are you sure you want to delete this glossary term?')) { this.state.glossaryTerms.splice(index, 1); this.renderGlossary(); this.showAlert('Glossary term deleted.', 'success'); } 
            } 
        }); 

        document.body.addEventListener('input', (e) => { if (e.target.matches('[data-list]')) { const { list, type, field, index } = e.target.dataset; if (!index) return; if (list === 'inspirations' && this.state.inspirations[type]) this.state.inspirations[type][index][field] = e.target.value; } }); 
        
        if(this.dom.addTermBtn) {
            this.dom.addTermBtn.addEventListener('click', () => { if (!this.state.isEditModeUnlocked) return; const term = this.dom.newTermInput.value.trim(); const definition = this.dom.newDefinitionInput.value.trim(); if (term) { this.state.glossaryTerms.push({ term, definition }); this.dom.newTermInput.value = ''; this.dom.newDefinitionInput.value = ''; this.renderGlossary(); } }); 
        }
    },
    parseUrlForKeywords() { const urlParams = new URLSearchParams(window.location.search); const keywordsParam = urlParams.get('keywords'); if (keywordsParam) { const newKeywords = keywordsParam.split(',').map(keyword => keyword.trim()); if (newKeywords.length > 0) { const initialGlossaryTerms = newKeywords.map(keyword => ({ term: keyword, definition: 'User-provided keyword. Please use this term in the narrative.' })); initialGlossaryTerms.forEach(newTerm => { const exists = this.state.glossaryTerms.some(existingTerm => existingTerm.term.toLowerCase() === newTerm.term.toLowerCase()); if (!exists) { this.state.glossaryTerms.push(newTerm); } }); this.showAlert('New keywords added to the glossary from the URL!', 'success'); } } },
    showAlert(message, type = 'info', duration = 4000, allowHtml = false) { const placeholder = this.dom.alertPlaceholder; if (!placeholder) return; const wrapper = document.createElement('div'); wrapper.className = `alert alert-${type} alert-dismissible fade show`; wrapper.setAttribute('role', 'alert'); if (allowHtml) { wrapper.innerHTML = message; } else { wrapper.textContent = message; } if (duration > 0) { const closeButton = document.createElement('button'); closeButton.type = 'button'; closeButton.className = 'btn-close'; closeButton.setAttribute('data-bs-dismiss', 'alert'); closeButton.setAttribute('aria-label', 'Close'); wrapper.appendChild(closeButton); } placeholder.appendChild(wrapper); if (duration > 0) { setTimeout(() => { const alertInstance = bootstrap.Alert.getOrCreateInstance(wrapper); if(alertInstance) alertInstance.close(); }, duration); } return wrapper; },
    generateQrCode(targetElementId, text) { const targetElement = document.getElementById(targetElementId); if (!targetElement) { return; } targetElement.innerHTML = ''; if (!text || text.trim() === '') { targetElement.innerHTML = '<span class="small text-muted">N/A</span>'; return; } try { if(typeof QRCode !== 'undefined') new QRCode(targetElement, { text: text, width: 192, height: 192, colorDark: "#000000", colorLight: "#ffffff", correctLevel: QRCode.CorrectLevel.H }); } catch (e) { console.error("QR Code generation failed:", e); targetElement.innerHTML = '<span class="small text-danger">Error</span>'; } },
    generateRuneSvg(runeText) { const fontSize = 32; const padding = 20; const lines = runeText.split('\n').filter(line => line.trim() !== ''); const lineCount = lines.length || 1; const longestLine = lines.reduce((a, b) => a.length > b.length ? a : b, ''); const estimatedWidth = longestLine.length * (fontSize * 0.7) + (padding * 2); const height = (fontSize * lineCount * 1.2) + (padding * 2); const textElements = lines.map((line, index) => `<text x="50%" y="${padding + (fontSize * 0.7) + (index * fontSize * 1.2)}" class="rune-text">${line}</text>`).join(''); const svgContent = `<svg width="${estimatedWidth}" height="${height}" xmlns="http://www.w3.org/2000/svg" style="background-color: transparent;"><style>.rune-text { font-family: 'Noto Sans Runic', sans-serif; font-size: ${fontSize}px; fill: var(--bs-body-color, #212529); text-anchor: middle; }</style>${textElements}</svg>`.trim(); return svgContent; },
    downloadBlob(blob, filename) { const url = URL.createObjectURL(blob); const a = document.createElement('a'); a.style.display = 'none'; a.href = url; a.download = filename; document.body.appendChild(a); a.click(); window.URL.revokeObjectURL(url); a.remove(); },
    dataUrlToBlob(dataUrl) { const parts = dataUrl.split(','); const contentType = parts[0].match(/:(.*?);/)[1]; const raw = window.atob(parts[1]); const rawLength = raw.length; const uInt8Array = new Uint8Array(rawLength); for (let i = 0; i < rawLength; ++i) { uInt8Array[i] = raw.charCodeAt(i); } return new Blob([uInt8Array], { type: contentType }); },
    scrollToElementWithOffset(element) { if (!element) return; const elementRect = element.getBoundingClientRect(); const elementTop = elementRect.top + window.scrollY; const offset = window.innerHeight / 4; const finalScrollPosition = elementTop - offset; window.scrollTo({ top: finalScrollPosition, behavior: 'smooth' }); },

    processSceneResult(sceneResult, act, scene) {
        if(!this.dom.geminiResultContainer) return;
        const sceneId = `act-${act}-scene-${scene}`;
        const sceneTitle = `Act ${act}, Scene ${scene}`;
        const accordionItemId = `scene-collapse-${sceneId}`;
        const accordionHeaderId = `scene-header-${sceneId}`;
        let formattedSceneResult = sceneResult.trim();
        const sceneHeadingRegex = new RegExp(`Act ${act}, Scene ${scene}`, 'i');
        if (!sceneHeadingRegex.test(formattedSceneResult.split('\n')[0])) {
            formattedSceneResult = `${sceneTitle}\n\n${formattedSceneResult}`;
        }
        this.state.fullScript += `\n\n${formattedSceneResult}`;
        this.updateWordCloud(formattedSceneResult);

        const formattedHtml = this.formatScreenplayHTML(formattedSceneResult);
        const finalHtml = this.enhanceGlossaryTerms(formattedHtml);

        const accordionItem = document.createElement('div');
        accordionItem.className = 'accordion-item bg-dark text-light border-secondary';
        accordionItem.innerHTML = `<h2 class="accordion-header" id="${accordionHeaderId}"><button class="accordion-button collapsed bg-black text-info" type="button" data-bs-toggle="collapse" data-bs-target="#${accordionItemId}" aria-expanded="false" aria-controls="${accordionItemId}">${sceneTitle}</button></h2><div id="${accordionItemId}" class="accordion-collapse collapse" aria-labelledby="${accordionHeaderId}" data-bs-parent="#screenplayAccordion"><div class="accordion-body" id="${sceneId}">${finalHtml}</div></div>`;
        this.dom.geminiResultContainer.appendChild(accordionItem);
        const newCollapseElement = document.getElementById(accordionItemId);
        new bootstrap.Collapse(newCollapseElement, { toggle: true });
        [...accordionItem.querySelectorAll('[data-bs-toggle="popover"]')].map(el => new bootstrap.Popover(el, {html: true, trigger: 'hover focus'}));
        this.state.generatedSceneCount++;
        
        if(this.dom.generationProgressBar) {
            const progressPercentage = (this.state.generatedSceneCount / this.state.totalScenesInPlay) * 100;
            this.dom.generationProgressBar.style.width = `${progressPercentage}%`;
            this.dom.generationProgressBar.setAttribute('aria-valuenow', progressPercentage);
            if(this.dom.progressBarPercentage) this.dom.progressBarPercentage.textContent = `${Math.round(progressPercentage)}%`;
        }
    },

    displayFullScript(fullText) {
        if(!this.dom.geminiResultContainer) return;
        this.dom.geminiResultContainer.innerHTML = '';
        this.dom.geminiResultContainer.className = 'p-3 border border-secondary bg-dark rounded accordion';
        this.dom.geminiResultContainer.id = 'screenplayAccordion';

        const formattedHtml = this.formatScreenplayHTML(fullText);
        const enhancedText = this.enhanceGlossaryTerms(formattedHtml);

        const actRegex = /(?=Act \d+, Scene \d+)/gi;
        const scenes = enhancedText.split(actRegex).filter(s => s.trim() !== '');
        if (scenes.length <= 1 && enhancedText.length > 0) {
            this.dom.geminiResultContainer.innerHTML = enhancedText;
            console.warn("Could not split script into scenes. Displaying as a single block.");
            return;
        }
        scenes.forEach((sceneContent, index) => {
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = sceneContent;
            const sceneTitleElement = tempDiv.querySelector('h6');
            const sceneTitle = sceneTitleElement ? sceneTitleElement.textContent : `Scene ${index + 1}`;

            const accordionItemId = `scene-collapse-revised-${index}`;
            const accordionHeaderId = `scene-header-revised-${index}`;
            const accordionItem = document.createElement('div');
            accordionItem.className = 'accordion-item bg-dark text-light border-secondary';
            accordionItem.innerHTML = `<h2 class="accordion-header" id="${accordionHeaderId}"><button class="accordion-button bg-black text-info" type="button" data-bs-toggle="collapse" data-bs-target="#${accordionItemId}" aria-expanded="true" aria-controls="${accordionItemId}">${sceneTitle}</button></h2><div id="${accordionItemId}" class="accordion-collapse collapse show" aria-labelledby="${accordionHeaderId}" data-bs-parent="#screenplayAccordion"><div class="accordion-body">${sceneContent}</div></div>`;
            this.dom.geminiResultContainer.appendChild(accordionItem);
        });
        [...this.dom.geminiResultContainer.querySelectorAll('[data-bs-toggle="popover"]')].map(el => new bootstrap.Popover(el, {html: true, trigger: 'hover focus'}));
    },

    enhanceGlossaryTerms(text) {
        if (!this.state.glossaryTerms || this.state.glossaryTerms.length === 0) return text;
        
        let processedText = text;
        const sortedTerms = [...this.state.glossaryTerms].sort((a, b) => b.term.length - a.term.length);

        sortedTerms.forEach(item => {
            const safeTerm = item.term.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            const regex = new RegExp(`\\b(${safeTerm})\\b(?![^<]*?>)`, 'gi');
            
            processedText = processedText.replace(regex, (match) => 
                `<a tabindex="0" class="glossary-term fw-bold text-warning" style="cursor:pointer;" role="button" data-bs-toggle="popover" title="${item.term.replace(/"/g, '&quot;')}" data-bs-content="${item.definition.replace(/"/g, '&quot;')}">${match}</a>`
            );
        });
        
        return processedText;
    },

    formatScreenplayHTML(text) {
        return text
            .split('\n')
            .map(line => {
                let processedLine = line.trim();
                if (processedLine.length === 0) return ''; 
                const content = processedLine.replace(/<[^>]*>/g, '').trim();
                if (content === '') return '';
                if (/^Act \d+, Scene \d+/i.test(content)) return `<h6 class="text-info fw-bold mt-2">${content}</h6>`;
                if (/^\[.*\]$/.test(content)) return `<p class="action-description fst-italic text-secondary">${content}</p>`;
                if (/^[A-Z\s]+$/.test(content) && content.length > 1 && content.length < 30 && !/DIRECTOR'S NOTE|REFLECTIVE QUERY/i.test(content)) return `<p class="character-name text-center text-warning fw-bold mt-3 mb-1">${content}</p>`;
                if (content.startsWith('(') && content.endsWith(')')) return `<p class="parenthetical text-center text-muted small mb-1">${content}</p>`;
                if (/^(Director's Note:|Reflective Query:)/i.test(content)) return `<p class="meta-note mt-3 text-success fst-italic border-start border-success ps-2">${content}</p>`;
                return `<p class="dialogue-line text-center w-75 mx-auto">${processedLine}</p>`;
            })
            .join(''); 
    },
    
    // --- RELIABLE PDF EXPORT WITH BLOB FALLBACK ---
        async exportFullDocumentAsPdf() {
        // 1. Grab content from memory OR directly from the screen (if loaded from database)
        let scriptContent = this.state.fullScript;
        if (!scriptContent && this.dom.geminiResultContainer) {
            scriptContent = this.dom.geminiResultContainer.innerText;
        }

        // Check if there's actually a script to print
        if (!scriptContent || scriptContent.includes('Your generated screenplay will appear here')) {
            this.showAlert('No screenplay generated yet to export.', 'warning');
            return;
        }
        
        const btn = this.dom.exportCodexBtn;
        const originalText = btn ? btn.innerHTML : '';
        if (btn) {
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Generating PDF...';
            btn.disabled = true;
        }

        this.showAlert('Synthesizing Neural PDF Codex... Please wait.', 'info');

        // 2. Force-load jsPDF dynamically if the WordPress theme blocked it
        const loadJsPDF = async () => {
            if (window.jspdf && window.jspdf.jsPDF) return window.jspdf.jsPDF;
            return new Promise((resolve, reject) => {
                const script = document.createElement('script');
                script.src = 'https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js';
                script.onload = () => resolve(window.jspdf.jsPDF);
                script.onerror = () => reject(new Error('Failed to load jsPDF library'));
                document.head.appendChild(script);
            });
        };

        try {
            const jsPDFClass = await loadJsPDF();
            const doc = new jsPDFClass({ orientation: 'portrait', unit: 'mm', format: 'letter' });

            const margin = 20;
            const pageWidth = 215.9; 
            const maxLineWidth = pageWidth - (margin * 2);
            let cursorY = 30;

            // --- TITLE PAGE ---
            doc.setFont("helvetica", "bold");
            doc.setFontSize(24);
            const titleElement = document.getElementById('playTitle');
            const title = titleElement ? titleElement.textContent : (this.state.config.playTitle || 'Blueprint of the Cosmos');
            doc.text(title.toUpperCase(), pageWidth / 2, cursorY, { align: 'center' });
            
            cursorY += 15;
            doc.setFontSize(12);
            doc.setFont("helvetica", "normal");
            doc.text("A Neural Synthesis Generation", pageWidth / 2, cursorY, { align: 'center' });
            
            cursorY += 25;
            doc.setFontSize(10);
            const now = (typeof dayjs !== 'undefined') ? dayjs().tz("America/Chicago").add(this.state.timeOffsetMilliseconds, 'ms') : new Date();
            const timeStr = (typeof dayjs !== 'undefined') ? now.format("dddd, MMMM D, YYYY @ HH:mm:ss z") : now.toString();
            doc.text(`Temporal Anchor: ${timeStr}`, margin, cursorY);
            
            cursorY += 8;
            const { latitude, longitude } = this.state.userLocation;
            doc.text(`Spatial Anchor: Lat ${latitude !== null ? latitude.toFixed(4) : 'N/A'}, Lon ${longitude !== null ? longitude.toFixed(4) : 'N/A'}`, margin, cursorY);
            
            if (this.state.walletAddress) {
                cursorY += 8;
                doc.text(`Crypto Signature: ${this.state.walletAddress}`, margin, cursorY);
            }
            
            cursorY += 15;
            doc.setFont("helvetica", "bold");
            doc.text("Space-Time Sequence Key:", margin, cursorY);
            cursorY += 6;
            doc.setFont("helvetica", "normal");
            
            const splitRunes = doc.splitTextToSize(`${this.state.compositeKeys.direct.runes}\n(${this.state.compositeKeys.direct.latin})`, maxLineWidth);
            doc.text(splitRunes, margin, cursorY);

            // --- SCRIPT CONTENT ---
            doc.addPage();
            cursorY = margin;
            
            doc.setFont("courier", "normal");
            doc.setFontSize(12);
            
            const lines = scriptContent.split('\n');
            
            lines.forEach(line => {
                let text = line.trim();
                if (!text) {
                    cursorY += 5;
                    return;
                }
                
                if (/^Act \d+, Scene \d+/i.test(text) || (/^[A-Z\s]+$/.test(text) && text.length > 1 && text.length < 30 && !/DIRECTOR'S NOTE|REFLECTIVE QUERY/i.test(text))) {
                    doc.setFont("courier", "bold");
                } else if (/^(Director's Note:|Reflective Query:)/i.test(text) || /^\[.*\]$/.test(text) || (text.startsWith('(') && text.endsWith(')'))) {
                    doc.setFont("courier", "italic"); 
                } else {
                    doc.setFont("courier", "normal");
                }

                const wrappedLines = doc.splitTextToSize(text, maxLineWidth);
                
                if (cursorY + (wrappedLines.length * 5) > 270) {
                    doc.addPage();
                    cursorY = margin + 10;
                }
                
                doc.text(wrappedLines, margin, cursorY);
                cursorY += (wrappedLines.length * 5) + 2; 
            });

            const fileName = `${title.replace(/[^a-z0-9]/gi, '_').toLowerCase()}_codex.pdf`;
            
            // 1. Direct Download
            doc.save(fileName);
            
            // 2. Blob Fallback (Opens in new tab just in case the download is blocked)
            const pdfBlob = doc.output('blob');
            const blobUrl = URL.createObjectURL(pdfBlob);
            window.open(blobUrl, '_blank');

            this.showAlert('PDF Export complete! Document downloaded.', 'success');

            // 3. Upload silently to WordPress Media Library
            if (typeof blueprintAjax !== 'undefined' && blueprintAjax.ajaxurl) {
                const formData = new FormData();
                formData.append('action', 'blueprint_save_pdf_to_media');
                formData.append('pdf_file', pdfBlob, fileName);
                
                if (window.blueprintData && window.blueprintData.postId) {
                    formData.append('post_id', window.blueprintData.postId);
                }
                
                fetch(blueprintAjax.ajaxurl, {
                    method: 'POST',
                    body: formData
                }).then(res => res.json()).then(data => {
                    if(data.success) {
                        console.log("PDF synchronized to server media library successfully.");
                    }
                }).catch(e => console.warn("Background PDF sync failed."));
            }

        } catch (error) {
            console.error("PDF Generation Error:", error);
            this.showAlert("PDF Library failed to load. Please check your connection or ad-blocker.", "danger");
        } finally {
            if (btn) {
                btn.innerHTML = originalText;
                btn.disabled = false;
            }
        }
    },
    formatSecondsToEta(seconds) { if (seconds <= 0) return 'Almost done...'; if (seconds < 60) return `About ${Math.round(seconds)} seconds remaining...`; const minutes = Math.round(seconds / 60); if (minutes < 2) return `About a minute remaining...`; return `About ${minutes} minutes remaining...`; },
    setPlayLength() { if(!this.dom.playLengthSelector) return; const selectedLength = this.dom.playLengthSelector.value; switch (selectedLength) { case 'short': this.state.sceneStructure = { 1: 2, 2: 2, 3: 2 }; break; case 'long': this.state.sceneStructure = { 1: 4, 2: 4, 3: 3 }; break; default: this.state.sceneStructure = { 1: 3, 2: 3, 3: 2 }; } this.state.totalScenesInPlay = Object.values(this.state.sceneStructure).reduce((a, b) => a + b, 0); },
    updateStatusDisplay() { },
    applyEditModeState(enable) { const selectors = [ '#inspirationsTabContent input', '#newTermInput', '#newDefinitionInput', '#addTermBtn', '#inspirationsTabContent button', '#generationModeSelector', '#promptingMethodSelector', '#imageMethodSelector', '#dialogueLengthSelector', '#playLengthSelector', '#includeNotesCheckbox', '#chimeTriggerSelector', '#loopChimeCheckbox' ]; document.querySelectorAll(selectors.join(', ')).forEach(el => el.disabled = !enable); document.querySelectorAll('.editable-section').forEach(el => { el.style.opacity = enable ? '1' : '0.6'; el.style.pointerEvents = enable ? 'auto' : 'none'; }); if(this.dom.useWalletCheckbox) this.dom.useWalletCheckbox.disabled = !enable; if (!enable) { if(this.dom.walletAddressInput) this.dom.walletAddressInput.disabled = true; if(this.dom.setWalletAddressBtn) this.dom.setWalletAddressBtn.disabled = true; } else if (this.dom.useWalletCheckbox && this.dom.useWalletCheckbox.checked) { if(this.dom.walletAddressInput) this.dom.walletAddressInput.disabled = false; if(this.dom.setWalletAddressBtn) this.dom.setWalletAddressBtn.disabled = false; } this.renderGlossary(); this.renderAllInspirations(); this.renderAxioms(); },
    resetGeneratorState(fullReset = true) { this.stopSpeaking(); this.stopChime(); this.state.fullScript = ''; this.state.isComplete = false; this.state.currentAct = 1; this.state.currentScene = 1; this.state.generatedSceneCount = 0; this.state.isGenerating = false; this.state.generationQueue = []; clearInterval(this.state.progressTimerInterval); clearInterval(this.state.fakeProgressInterval); this.state.progressTimerInterval = null; this.state.fakeProgressInterval = null; if(this.dom.geminiResultContainer) { this.dom.geminiResultContainer.innerHTML = '<p class="text-muted text-center py-5">Your generated screenplay will appear here...</p>'; this.dom.geminiResultContainer.classList.remove('accordion'); this.dom.geminiResultContainer.removeAttribute('id'); } if(this.dom.geminiPromptOutput) this.dom.geminiPromptOutput.value = ''; this.showStickyProgressIndicator(false); this.renderWordCloud(); this.updateButtonState(); if(fullReset) { this.state.unlockedFeatures.clear(); this.state.isEditModeUnlocked = false; this.applyEditModeState(false); } else { document.querySelectorAll('#playLengthSelector, #dialogueLengthSelector, #promptingMethodSelector, #generationModeSelector').forEach(el => el.disabled = false); } },
    updateButtonState() { 
        const genBtn = this.dom.submitToGeminiBtn; 
        if(!genBtn) return; 
        
        genBtn.style.display = 'none'; 
        if(this.dom.listenToPlayBtn) this.dom.listenToPlayBtn.style.display = 'none'; 
        if(this.dom.stopListeningBtn) this.dom.stopListeningBtn.style.display = 'none'; 
        if(this.dom.exportCodexBtn) this.dom.exportCodexBtn.style.display = 'none'; 
        if(this.dom.scrollToSpokenSceneBtn) this.dom.scrollToSpokenSceneBtn.style.display = 'none';

        if (this.state.isListeningActive) { 
            if(this.dom.stopListeningBtn) this.dom.stopListeningBtn.style.display = 'inline-block'; 
            if(this.dom.scrollToSpokenSceneBtn) this.dom.scrollToSpokenSceneBtn.style.display = 'inline-block';
        } else if (this.state.fullScript.trim() && this.state.hasTTSVoices) { 
            if(this.dom.listenToPlayBtn) this.dom.listenToPlayBtn.style.display = 'inline-block'; 
        } 

        if (this.state.isGenerating) { 
            genBtn.style.display = 'inline-block'; 
            genBtn.disabled = true; 
            genBtn.innerHTML = `<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Generating...`; 
        } else if (this.state.isComplete) { 
            if(this.dom.exportCodexBtn) this.dom.exportCodexBtn.style.display = 'inline-block'; 
            genBtn.style.display = 'inline-block'; 
            genBtn.textContent = 'Screenplay Complete'; 
            genBtn.disabled = true; 
        } else { 
            genBtn.style.display = 'inline-block'; 
            genBtn.disabled = !this.state.isEditModeUnlocked; 
            genBtn.textContent = 'Generate Full Screenplay'; 
            genBtn.classList.remove('btn-success'); 
            genBtn.classList.add('btn-primary'); 
        } 
    },
    getCompositeNumericalKey() { const timestamp = dayjs().tz("America/Chicago").add(this.state.timeOffsetMilliseconds, 'ms').format("YYYYMMDDHHmmss"); const { latitude, longitude } = this.state.userLocation; let combinedNumericalKey = timestamp; if (latitude !== null && longitude !== null) { const latStr = String(latitude).replace(/[^0-9]/g, ''); const lonStr = String(longitude).replace(/[^0-9]/g, ''); combinedNumericalKey += latStr + lonStr; } if (this.state.walletAddress) { const walletNum = this.hexToDecimalString(this.state.walletAddress); combinedNumericalKey += walletNum; } const targetLength = 100; let paddedKey = combinedNumericalKey; while (paddedKey.length < targetLength) { paddedKey += combinedNumericalKey; } combinedNumericalKey = paddedKey.substring(0, targetLength); this.state.compositeKeys.numerical = combinedNumericalKey; return combinedNumericalKey; },
    generateCompositeKeys() { const numericalKey = this.getCompositeNumericalKey(); const reversedNumericalKey = numericalKey.split('').reverse().join(''); this.state.compositeKeys.direct = this.generateRuneSequence(numericalKey, 'direct'); this.state.compositeKeys.expanded = this.generateRuneSequence(numericalKey, 'expanded'); this.state.compositeKeys.shifted = this.generateRuneSequence(numericalKey, 'shift'); this.state.compositeKeys.reversed = this.generateRuneSequence(reversedNumericalKey, 'direct'); this.renderCompositeKeys(); },
    renderCompositeKeys() { const formatRunesForDisplay = (runes) => { if (!runes || runes.length !== 100) return runes || ''; return runes.match(/.{1,20}/g).join('<br>'); }; if(this.dom.direct_rune_seq) this.dom.direct_rune_seq.innerHTML = formatRunesForDisplay(this.state.compositeKeys.direct.runes); if(this.dom.expanded_rune_seq) this.dom.expanded_rune_seq.innerHTML = formatRunesForDisplay(this.state.compositeKeys.expanded.runes); if(this.dom.shifted_rune_seq) this.dom.shifted_rune_seq.innerHTML = formatRunesForDisplay(this.state.compositeKeys.shifted.runes); if(this.dom.reversed_rune_seq) this.dom.reversed_rune_seq.innerHTML = formatRunesForDisplay(this.state.compositeKeys.reversed.runes); this.generateQrCode('direct_qr_code', this.state.compositeKeys.direct.runes); this.generateQrCode('expanded_qr_code', this.state.compositeKeys.expanded.runes); this.generateQrCode('shifted_qr_code', this.state.compositeKeys.shifted.runes); this.generateQrCode('reversed_qr_code', this.state.compositeKeys.reversed.runes); },
    hexToDecimalString(hex) { let normalized = hex.startsWith('0x') ? hex.substring(2) : hex; normalized = normalized.toLowerCase().replace(/[^0-9a-f]/g, ''); let result = ''; for (let i = 0; i < normalized.length; i++) { const charCode = normalized.charCodeAt(i); let digit; if (charCode >= 48 && charCode <= 57) digit = charCode - 48; else if (charCode >= 97 && charCode <= 102) digit = charCode - 87; else continue; result += digit; } return result; },
    generateRuneSequence(timestamp, method) { const FUTHARK_MAPPING = {'A':'ᚨ','B':'ᛒ','C':'ᚲ','D':'ᛞ','E':'ᛖ','F':'ᚠ','G':'ᚷ','H':'ᚺ','I':'ᛁ','J':'ᛃ','K':'ᚲ','L':'ᛚ','M':'ᛗ','N':'ᚾ','O':'ᛟ','P':'ᛈ','Q':'ᚲ','R':'ᚱ','S':'ᛋ','T':'ᛏ','U':'ᚢ','V':'ᚹ','W':'ᚹ','X':'ᛇ','Y':'ᛃ','Z':'ᛉ'}; let latinChars = '', runeSequence = ''; for (let i = 0; i < timestamp.length; i++) { const digit = parseInt(timestamp[i]); const charCode = method === 'shift' ? 65 + ((digit + 3) % 10) : method === 'direct' ? 65 + digit : 65 + ((digit + i*3) % 26); const latinLetter = String.fromCharCode(charCode); latinChars += latinLetter; runeSequence += FUTHARK_MAPPING[latinLetter] || '?'; } return { latin: latinChars, runes: runeSequence }; },
    updateLiveTimestampAndRunesDisplay() { const now = dayjs().tz("America/Chicago").add(this.state.timeOffsetMilliseconds, 'ms'); if(this.dom.liveCurrentTime) { this.dom.liveCurrentTime.textContent = now.format("MMMM D, YYYY, h:mm:ss A z"); this.dom.liveCurrentTime.classList.toggle('text-warning', this.state.timeOffsetMilliseconds !== 0); } const numTS = now.format("YYYYMMDDHHmmss"); if(this.dom.liveNumericalTimestamp) this.dom.liveNumericalTimestamp.textContent = numTS; this.generateCompositeKeys(); },
    updateGpsRunesDisplay() { const { latitude, longitude } = this.state.userLocation; if(this.dom.userLocationDisplay) this.dom.userLocationDisplay.textContent = latitude !== null ? `Lat: ${latitude.toFixed(4)}, Lon: ${longitude.toFixed(4)}` : 'Spatial data not available.'; this.generateCompositeKeys(); },
    updateWalletRunesDisplay() { if(!this.dom.wallet_numerical_display) return; if (!this.state.walletAddress) { this.dom.wallet_numerical_display.textContent = 'N/A'; this.dom.wallet_latin_seq.textContent = ''; this.dom.wallet_rune_seq.innerHTML = ''; this.generateQrCode('wallet_qr_code', ''); this.generateCompositeKeys(); return; } const numericalWallet = this.hexToDecimalString(this.state.walletAddress); this.dom.wallet_numerical_display.textContent = numericalWallet.substring(0, 100) + '...'; const sequence = this.generateRuneSequence(numericalWallet, 'direct'); this.dom.wallet_latin_seq.textContent = sequence.latin; const formatRunesForDisplay = (runes) => { if (!runes) return ''; let paddedRunes = runes; while (paddedRunes.length % 20 !== 0 && paddedRunes.length < 100) { paddedRunes += '᛫'; } return paddedRunes.match(/.{1,20}/g).join('<br>'); }; this.dom.wallet_rune_seq.innerHTML = formatRunesForDisplay(sequence.runes); this.generateQrCode('wallet_qr_code', this.state.walletAddress); this.generateCompositeKeys(); },
    toggleWalletInput(checked) { if(!this.dom.walletInputContainer) return; this.dom.walletInputContainer.style.display = checked ? 'block' : 'none'; if (!checked) { this.state.walletAddress = null; } else { this.state.walletAddress = this.dom.walletAddressInput.value; } this.updateWalletRunesDisplay(); },
    setWalletAddress() { if(!this.dom.walletAddressInput) return; const address = this.dom.walletAddressInput.value.trim(); this.state.walletAddress = address || null; this.updateWalletRunesDisplay(); this.showAlert(address ? 'Wallet address set.' : 'Wallet address cleared.', 'success'); },
    startTimestampUpdates() { if (this.state.timestampInterval) clearInterval(this.state.timestampInterval); this.state.timeOffsetMilliseconds = 0; this.state.timestampInterval = setInterval(() => this.updateLiveTimestampAndRunesDisplay(), 1000); if(this.dom.startClockBtn) this.dom.startClockBtn.style.display = 'none'; if(this.dom.stopClockBtn) this.dom.stopClockBtn.style.display = 'inline-block'; this.updateLiveTimestampAndRunesDisplay(); },
    resumeTimestampUpdates() { if (this.state.timestampInterval) return; this.state.timestampInterval = setInterval(() => this.updateLiveTimestampAndRunesDisplay(), 1000); if(this.dom.startClockBtn) this.dom.startClockBtn.style.display = 'none'; if(this.dom.stopClockBtn) this.dom.stopClockBtn.style.display = 'inline-block'; },
    stopTimestampUpdates() { if (this.state.timestampInterval) clearInterval(this.state.timestampInterval); this.state.timestampInterval = null; if(this.dom.startClockBtn) this.dom.startClockBtn.style.display = 'inline-block'; if(this.dom.stopClockBtn) this.dom.stopClockBtn.style.display = 'none'; },
    setManualTimestamp() { if(!this.dom.manualTimestampInput) return; const flatpickrInstance = this.dom.manualTimestampInput._flatpickr; if (flatpickrInstance && flatpickrInstance.selectedDates.length > 0) { const newTs = dayjs(flatpickrInstance.selectedDates[0]); if (newTs.isValid()) { this.state.timeOffsetMilliseconds = newTs.diff(dayjs()); this.updateLiveTimestampAndRunesDisplay(); if (!this.state.timestampInterval) this.resumeTimestampUpdates(); this.showAlert('Manual timestamp set. Clock is now running from that point.', 'success'); } } },
    getUserLocation() { if (!navigator.geolocation) { this.showAlert('Geolocation is not supported by your browser.', 'warning'); this.updateLocationDisplay(); return; } navigator.geolocation.getCurrentPosition( pos => { this.state.userLocation = { latitude: pos.coords.latitude, longitude: pos.coords.longitude }; this.updateLocationDisplay(); }, () => { this.showAlert('Could not get location. Using default coordinates.', 'warning'); this.updateLocationDisplay(); }, { enableHighAccuracy: true } ); },
    updateLocationDisplay() { const { latitude, longitude } = this.state.userLocation; if(this.dom.userLocationDisplay) this.dom.userLocationDisplay.textContent = latitude !== null ? `Lat: ${latitude.toFixed(4)}, Lon: ${longitude.toFixed(4)}` : 'Spatial data not available.'; this.generateCompositeKeys(); },
    toggleLiveGpsUpdates() { this.showAlert('Live GPS tracking is not yet implemented.', 'info'); },
    setManualGpsCoordinates() { if(!this.dom.manualLatInput || !this.dom.manualLonInput) return; const lat = parseFloat(this.dom.manualLatInput.value); const lon = parseFloat(this.dom.manualLonInput.value); if (isNaN(lat) || isNaN(lon)) { this.showAlert('Invalid latitude or longitude.', 'danger'); return; } this.state.userLocation = { latitude: lat, longitude: lon }; this.updateLocationDisplay(); },
    renderAxioms() { const container = this.dom.cosmicAxiomsContainer; if (!container) return; const listHtml = this.state.cosmicAxioms.map((axiom, index) => { const [title, text] = axiom.original.split(':'); return `<li data-index="${index}"><strong>${title}:</strong>${text}</li>`; }).join(''); container.innerHTML = `<ol start="0" class="principles-list">${listHtml}</ol>`; },
    renderGlossary() { if(!this.dom.glossaryList) return; this.dom.glossaryList.innerHTML = this.state.glossaryTerms.map((item, index) => { const definitionSnippet = item.definition.substring(0, 40) + (item.definition.length > 40 ? '...' : ''); const deleteButtonHtml = this.state.isEditModeUnlocked ? `<button class="btn btn-sm btn-danger float-end delete-term-btn" data-index="${index}" title="Delete Term"><i class="fas fa-trash"></i></button>` : ''; const editIconHtml = this.state.isEditModeUnlocked ? `<i class="fas fa-pencil-alt ms-2 text-primary"></i>` : ''; const editableClass = this.state.isEditModeUnlocked ? 'editable-glossary-item' : ''; const cursorStyle = this.state.isEditModeUnlocked ? 'cursor: pointer;' : ''; return `<div data-index="${index}" class="p-2 border-bottom border-secondary bg-dark text-light ${editableClass}" style="${cursorStyle}"><strong>${item.term}</strong> ${editIconHtml} ${deleteButtonHtml}<div class="small text-muted">${definitionSnippet}</div></div>`; }).join(''); },
    openGlossaryEditModal(index) { const term = this.state.glossaryTerms[index]; if (!term || !this.dom.glossaryEditModalIndex) return; this.dom.glossaryEditModalIndex.value = index; this.dom.glossaryEditModalTermInput.value = term.term; this.dom.glossaryEditModalDefinitionInput.value = term.definition; if(this.modals.glossaryEdit) this.modals.glossaryEdit.show(); },
    saveGlossaryTerm() { if(!this.dom.glossaryEditModalIndex) return; const index = this.dom.glossaryEditModalIndex.value; const newTerm = this.dom.glossaryEditModalTermInput.value.trim(); const newDefinition = this.dom.glossaryEditModalDefinitionInput.value.trim(); if (index !== '' && this.state.glossaryTerms[index] && newTerm) { this.state.glossaryTerms[index] = { term: newTerm, definition: newDefinition }; this.renderGlossary(); if(this.modals.glossaryEdit) this.modals.glossaryEdit.hide(); } },
    renderAllInspirations() { const inspirationConfig = { books: { fields: { title: 'Title', author: 'Author' } }, authors: { fields: { name: 'Name' } }, songs: { fields: { title: 'Song Title', artist: 'Artist' } }, movies: { fields: { title: 'Title' } }, websites: { fields: { url: 'URL' } }, maias: { fields: { quote: 'Quote', latin: 'Latin Translation' } } }; let tabContentHtml = ''; Object.entries(inspirationConfig).forEach(([type, config], index) => { const listHtml = (this.state.inspirations[type] || []).map((item, i) => Object.keys(config.fields).map(field => `<input type="text" class="form-control bg-dark text-light border-secondary" value="${item[field] || ''}" data-list="inspirations" data-type="${type}" data-field="${field}" data-index="${i}" ${!this.state.isEditModeUnlocked ? 'disabled' : ''}>`).join('') + `<button type="button" class="btn btn-danger btn-sm remove-item-btn" data-list="inspirations" data-type="${type}" data-index="${i}" ${!this.state.isEditModeUnlocked ? 'disabled' : ''} title="Remove Item">×</button>`).map(row => `<div class="d-flex gap-2 mb-2">${row}</div>`).join(''); const addInputs = Object.keys(config.fields).map(field => `<input type="text" id="new-${type}-${field}-input" class="form-control bg-dark text-light border-secondary" placeholder="${config.fields[field]}">`).join('') + `<button type="button" id="add-${type}-btn" class="btn btn-secondary flex-shrink-0">Add</button>`; tabContentHtml += `<div class="tab-pane fade ${index === 0 ? 'show active' : ''}" id="${type}-tab-pane" role="tabpanel"><div>${listHtml}</div><hr class="border-secondary"><h6>Add New</h6><div class="d-flex gap-2">${addInputs}</div></div>`; }); if(!this.dom.inspirationsTabContent) return; this.dom.inspirationsTabContent.innerHTML = tabContentHtml; Object.entries(inspirationConfig).forEach(([type, config]) => { const addBtn = document.getElementById(`add-${type}-btn`); if (addBtn) { addBtn.addEventListener('click', () => { if (!this.state.isEditModeUnlocked) return; const newItem = {}; const fieldKeys = Object.keys(config.fields); let isValid = true; fieldKeys.forEach(field => { const input = document.getElementById(`new-${type}-${field}-input`); newItem[field] = input.value.trim(); if (field === fieldKeys[0] && !newItem[field]) isValid = false; }); if (isValid) { if (!this.state.inspirations[type]) this.state.inspirations[type] = []; this.state.inspirations[type].push(newItem); this.renderAllInspirations(); } }); } }); this.dom.inspirationsTabContent.querySelectorAll('input, button').forEach(el => el.disabled = !this.state.isEditModeUnlocked); },
    getInitialBlueprint() { const now = dayjs().tz("America/Chicago").add(this.state.timeOffsetMilliseconds, 'ms'); const inspirationsText = Object.entries(this.state.inspirations).map(([type, items]) => { if (!items || items.length === 0) return ''; const capitalizedType = type.charAt(0).toUpperCase() + type.slice(1); const itemsText = items.map(item => `- ${Object.values(item).join(' - ')}`).join('\n'); return `### ${capitalizedType}\n${itemsText}`; }).join('\n\n'); const glossaryText = this.state.glossaryTerms.map(g => `- ${g.term}: ${g.definition}`).join('\n'); const axiomsText = this.state.cosmicAxioms.map(a => `- ${a.original}`).join('\n'); const imageDescText = this.dom.imageMethodSelector ? { 'per_scene': 'Include a concise, one-sentence visual description at the start of each scene, enclosed in square brackets. Example: [A vast, crystalline cave shimmering with bioluminescent fungi.]', 'per_act': 'Do not include visual descriptions for individual scenes.', 'none': 'Do not include any visual descriptions.' }[this.dom.imageMethodSelector.value] : ''; const dialogueLengthText = this.dom.dialogueLengthSelector ? { 'concise': 'Keep dialogue and action lines brief and to the point.', 'standard': 'Write with standard pacing for dialogue and action.', 'expansive': 'Allow for longer, more philosophical or descriptive dialogue and detailed action lines.' }[this.dom.dialogueLengthSelector.value] : ''; const directorNotesText = (this.dom.includeNotesCheckbox && this.dom.includeNotesCheckbox.checked) ? 'At the end of each scene, include a "Director\'s Note" on a new line, offering a brief insight into the subtext or thematic purpose of the scene. The note should start with "Director\'s Note:"' : ''; return `You are a master screenwriter and a creative AI named Maia. Your task is to write a utopian play titled "${this.dom.playTitle ? this.dom.playTitle.textContent : 'Blueprint of the Cosmos'}". The play is structured in 3 Acts. The total scene count is ${this.state.totalScenesInPlay}.\n\n--- CORE BLUEPRINT ---\n* **Unique Generation Key (Runes):** ${this.state.compositeKeys.direct.runes} (This represents the unique space-time signature for this creation. Subtly weave its themes of origin, expansion, transformation, and reflection into the narrative.)\n* **Temporal Coordinate:** ${now.format("dddd, MMMM D, YYYY @ HH:mm:ss z")}\n* **Spatial Coordinate:** Lat: ${this.state.userLocation.latitude}, Lon: ${this.state.userLocation.longitude}\n${this.state.walletAddress ? `* **Crypto Signature (Wallet):** ${this.state.walletAddress}` : ''}\n\n--- GUIDING PRINCIPLES (COSMIC AXIOMS) ---\n${axiomsText}\n\n--- NARRATIVE INSPIRATIONS ---\n${inspirationsText}\n\n--- CHARACTER & TERM GLOSSARY ---\n${glossaryText}\n\n--- FORMATTING & STYLE INSTRUCTIONS ---\n* **Image Descriptions:** ${imageDescText}\n* **Dialogue Length:** ${dialogueLengthText}\n* ${directorNotesText}\n* **Structure:** Each scene must end with a "Reflective Query" on a new line, posing a philosophical question related to the scene's events. The query should start with "Reflective Query:"\n* **Output:** Return **ONLY** the raw screenplay text for the requested scene. Do not add any extra commentary, greetings, or summaries.\n--- END BLUEPRINT ---`; },
    getPromptForScene(act, scene) { if (this.state.generatedSceneCount === 0) { return `${this.getInitialBlueprint()}\n\nNow, begin the story. Write Act ${act}, Scene ${scene}.`; } else { const method = this.dom.promptingMethodSelector ? this.dom.promptingMethodSelector.value : ''; let prompt = `You are Maia, a master screenwriter, continuing a play. Based on the narrative so far, generate **ONLY** the content for **Act ${act}, Scene ${scene}**. Maintain all formatting and style instructions from the initial prompt (including Director's Notes and Reflective Queries if applicable). Do not repeat scene headings or add extra commentary.`; if (method === 'full_blueprint') { prompt += `\n\n---CONTEXT REINFORCEMENT---\n${this.getInitialBlueprint()}\n---END REINFORCEMENT---`; } prompt += `\n\n---SCRIPT SO FAR (for context):---\n${this.state.fullScript}\n---END SCRIPT SO FAR---\n\nNow, continue the story. Write Act ${act}, Scene ${scene}.`; return prompt; } },
    startSpeaking() { if (!('speechSynthesis' in window)) { this.showAlert('Sorry, your browser does not support text-to-speech.', 'warning'); return; } if (this.state.isListeningActive) return; const scriptContainer = this.dom.geminiResultContainer; const textToSpeak = scriptContainer.innerText; if (!textToSpeak.trim()) { this.showAlert('Nothing to listen to yet.', 'info'); return; } this.state.isListeningActive = true; this.updateButtonState(); if(this.dom.scrollToSpokenSceneBtn) this.dom.scrollToSpokenSceneBtn.style.display = 'block'; this.state.sentenceMap = []; const allElements = Array.from(scriptContainer.querySelectorAll('.accordion-body > *')); allElements.forEach(el => { const text = el.textContent.trim(); if (text && !el.classList.contains('character-name')) { const parent = el.parentNode; const span = document.createElement('span'); span.innerHTML = el.innerHTML; el.innerHTML = ''; el.appendChild(span); this.state.sentenceMap.push({ element: span, text: text }); } }); let utteranceIndex = 0; const speakNext = () => { if (utteranceIndex >= this.state.sentenceMap.length || !this.state.isListeningActive) { this.stopSpeaking(); return; } if (this.state.currentSpeakingSpan) { this.state.currentSpeakingSpan.classList.remove('speaking-sentence'); } const currentItem = this.state.sentenceMap[utteranceIndex]; this.state.currentSpeakingSpan = currentItem.element; this.state.currentSpeakingSpan.classList.add('speaking-sentence'); this.scrollToElementWithOffset(this.state.currentSpeakingSpan); const utterance = new SpeechSynthesisUtterance(currentItem.text); utterance.onend = () => { utteranceIndex++; speakNext(); }; utterance.onerror = (e) => { console.error('Speech synthesis error:', e); this.stopSpeaking(); }; window.speechSynthesis.speak(utterance); }; window.speechSynthesis.cancel(); speakNext(); },
    stopSpeaking() { if (!this.state.isListeningActive && !window.speechSynthesis.speaking) return; window.speechSynthesis.cancel(); if (this.state.currentSpeakingSpan) { this.state.currentSpeakingSpan.classList.remove('speaking-sentence'); this.state.currentSpeakingSpan = null; } if (this.state.sentenceMap.length > 0) { this.displayFullScript(this.state.fullScript); } this.state.sentenceMap = []; this.state.isListeningActive = false; if(this.dom.scrollToSpokenSceneBtn) this.dom.scrollToSpokenSceneBtn.style.display = 'none'; this.updateButtonState(); },
    
    updateWordCloud(text) { 
        const stopWords = new Set(['a','an','the','and','but','or','for','nor','on','at','to','from','by','with','i','you','he','she','it','we','they','is','are','was','were','be','been','being','have','has','had','do','does','did','in','of','that','this','those','these','as','if','at','for','not', 'doc', 'maia', 'eather']); 
        const words = text.toLowerCase().match(/\b(\w+)\b/g); 
        if (words) { 
            words.forEach(word => { 
                if (word.length > 2 && !stopWords.has(word) && isNaN(word)) { 
                    this.state.wordCloudData[word] = (this.state.wordCloudData[word] || 0) + 1; 
                } 
            }); 
            // Persist the cumulative data
            localStorage.setItem('blueprint_cumulative_wordcloud', JSON.stringify(this.state.wordCloudData));
        } 
        this.renderWordCloud(); 
    },
    
    renderWordCloud() { const container = this.dom.wordCloudContent; if(!container) return; if (Object.keys(this.state.wordCloudData).length === 0) { container.innerHTML = '<p class="text-muted initial-cloud-text">The word cloud will populate here as the story is generated.</p>'; this.generateQrCode('wordcloud_qr_code', ''); return; } const sortedWords = Object.entries(this.state.wordCloudData).sort(([,a],[,b]) => b-a).slice(0, 50); if (sortedWords.length === 0) return; const maxCount = sortedWords[0][1]; const minCount = sortedWords[sortedWords.length - 1][1]; const cloudHtml = sortedWords.map(([word, count]) => { const weight = (maxCount > minCount) ? (count - minCount) / (maxCount - minCount) : 1; const fontSize = 1 + (weight * 1.5); const opacity = 0.6 + (weight * 0.4); return `<span class="word-cloud-term" style="font-size: ${fontSize}rem; opacity: ${opacity}; margin: 5px; display: inline-block; line-height: 1; color: var(--bs-info);">${word}</span>`; }).join(''); container.innerHTML = cloudHtml; const topWordsText = sortedWords.slice(0, 10).map(([word]) => word).join(', '); this.generateQrCode('wordcloud_qr_code', topWordsText); },
    startProgressTimers() { this.state.generationStartTime = Date.now(); clearInterval(this.state.progressTimerInterval); this.state.progressTimerInterval = setInterval(() => { const elapsedSeconds = Math.round((Date.now() - this.state.generationStartTime) / 1000); const progressPercentage = (this.state.generatedSceneCount / this.state.totalScenesInPlay); const estimatedTotalTime = progressPercentage > 0.01 ? elapsedSeconds / progressPercentage : 30 * this.state.totalScenesInPlay; const etaSeconds = Math.max(0, Math.round(estimatedTotalTime - elapsedSeconds)); if(this.dom.progressTimerText) this.dom.progressTimerText.textContent = `Elapsed Time: ${elapsedSeconds}s`; if(this.dom.stickyProgressTimer) this.dom.stickyProgressTimer.textContent = `(${elapsedSeconds}s)`; if(this.dom.progressEtaText) this.dom.progressEtaText.textContent = this.formatSecondsToEta(etaSeconds); }, 1000); },
    showStickyProgressIndicator(show) { if(this.dom.stickyProgressIndicator) this.dom.stickyProgressIndicator.style.display = show ? 'inline-flex' : 'none'; },
    playChime() { if (!this.state.audioCtx || this.state.isChimePlaying) return; this.state.isChimePlaying = true; const oscillator = this.state.audioCtx.createOscillator(); const gainNode = this.state.audioCtx.createGain(); oscillator.connect(gainNode); gainNode.connect(this.state.audioCtx.destination); oscillator.type = 'sine'; oscillator.frequency.setValueAtTime(440, this.state.audioCtx.currentTime); gainNode.gain.setValueAtTime(0, this.state.audioCtx.currentTime); gainNode.gain.linearRampToValueAtTime(0.5, this.state.audioCtx.currentTime + 0.05); oscillator.start(this.state.audioCtx.currentTime); gainNode.gain.exponentialRampToValueAtTime(0.00001, this.state.audioCtx.currentTime + 1); oscillator.stop(this.state.audioCtx.currentTime + 1); oscillator.onended = () => { this.state.isChimePlaying = false; }; },
    stopChime() { if (this.state.chimeInterval) { clearInterval(this.state.chimeInterval); this.state.chimeInterval = null; } if (this.state.chimeAlert) { const alertInstance = bootstrap.Alert.getOrCreateInstance(this.state.chimeAlert); if(alertInstance) alertInstance.close(); this.state.chimeAlert = null; } },
    playSceneChime() { if(!this.dom.chimeTriggerSelector) return; const trigger = this.dom.chimeTriggerSelector.value; if (trigger !== 'per_scene' && trigger !== 'per_act') return; const scenesPerAct = this.state.sceneStructure[this.state.currentAct]; if (trigger === 'per_act' && this.state.currentScene === scenesPerAct) { this.triggerChimeAlert(); } else if(trigger === 'per_scene') { this.triggerChimeAlert(); } },
    playCompletionChime() { if(!this.dom.chimeTriggerSelector) return; const trigger = this.dom.chimeTriggerSelector.value; if (trigger === 'end_of_play' || trigger === 'per_act') { this.triggerChimeAlert(); } },
    triggerChimeAlert() { this.stopChime(); this.playChime(); if (this.dom.loopChimeCheckbox && this.dom.loopChimeCheckbox.checked) { this.state.chimeInterval = setInterval(() => this.playChime(), 5000); this.state.chimeAlert = this.showAlert( 'Generation milestone reached! <button type="button" class="btn-close" onclick="app.stopChime()" data-bs-dismiss="alert"></button>', 'info', 0, true ); } else { this.showAlert('Generation milestone reached!', 'info', 5000); } },

    // --- Custom Rune Translation Engine ---
    toggleTranslation() {
        this.state.currentDisplayMode = this.state.currentDisplayMode === 'runes' ? 'latin' : 'runes';
        const elementsToTranslate = document.querySelectorAll('.translatable-word');
        
        elementsToTranslate.forEach(el => {
            let originalText = el.dataset.originalLatin.toUpperCase();
            let newText = '';
            
            for(let i = 0; i < originalText.length; i++) {
                let char = originalText[i];
                let freqIndex = this.config.frequencyMapping.latin.indexOf(char);
                
                if (freqIndex !== -1) {
                    newText += this.state.currentDisplayMode === 'runes' ? this.config.frequencyMapping.runes[freqIndex] : this.config.frequencyMapping.latin[freqIndex];
                } else {
                    newText += char; 
                }
            }
            el.innerText = newText;
        });
    },

    // --- Gemini Anagram API Endpoint Integrator ---
    sendRunesToGemini() {
        let collected = [];
        if (window.MelleVRCollectedRunes && window.MelleVRCollectedRunes.length > 0) {
            collected = window.MelleVRCollectedRunes;
        } else {
            let existingStr = localStorage.getItem('blueprint_transfer_data');
            let data = existingStr ? JSON.parse(existingStr) : {};
            if (data.collectedRunes) collected = data.collectedRunes;
        }
        
        if (collected.length === 0) {
            this.showAlert('No runes collected yet to generate words.', 'warning');
            return;
        }

        const btn = this.dom.sendRunesToGeminiBtn;
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Deciphering...';
        }

        // TRANSLATION FIX: Map Futhark to Latin so Gemini understands the request
        const runeToLatin = {
            'ᚨ':'A', 'ᛒ':'B', 'ᚲ':'K', 'ᛞ':'D', 'ᛖ':'E', 'ᚠ':'F', 'ᚷ':'G', 'ᚺ':'H',
            'ᛁ':'I', 'ᛃ':'J', 'ᛚ':'L', 'ᛗ':'M', 'ᚾ':'N', 'ᛟ':'O', 'ᛈ':'P', 'ᚱ':'R',
            'ᛋ':'S', 'ᛊ':'S', 'ᛏ':'T', 'ᚢ':'U', 'ᚹ':'W', 'ᛇ':'Y', 'ᛉ':'Z', 'ᚦ':'T', 'ᛜ':'N'
        };
        const letters = collected.map(r => runeToLatin[r] || 'A').join('');

        this.showAlert('Consulting Gemini for Anagrams...', 'info');
        
        const ajaxUrl = (typeof blueprintAjax !== 'undefined' && blueprintAjax.ajaxurl) ? blueprintAjax.ajaxurl : '/wp-admin/admin-ajax.php';

        jQuery.post(ajaxUrl, {
            action: 'blueprint_generate_words_from_runes',
            letters: letters
        }, (response) => {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-brain me-1"></i> Decipher Collected Runes';
            }
            if (response.success && response.data) {
                const wordListContainer = document.getElementById('generated-words-container');
                if (!wordListContainer) return; 

                wordListContainer.innerHTML = ''; 
                
                response.data.forEach(word => {
                    let span = document.createElement('span');
                    span.className = 'translatable-word';
                    span.dataset.originalLatin = word;
                    span.innerText = word; 
                    
                    span.addEventListener('click', () => this.toggleTranslation());

                    wordListContainer.appendChild(span);
                    wordListContainer.appendChild(document.createElement('br'));
                });
                
                let tempMode = this.state.currentDisplayMode;
                this.state.currentDisplayMode = 'latin'; 
                this.toggleTranslation();
                this.state.currentDisplayMode = tempMode;
                
                this.showAlert('Anagram generation complete!', 'success');
            } else {
                this.showAlert('Gemini failed to generate anagrams.', 'danger');
            }
        }).fail(() => {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-brain me-1"></i> Decipher Collected Runes';
            }
            this.showAlert('Failed to connect to Neural API. Check Server Logs.', 'danger');
        });
    }
};

app.init();
