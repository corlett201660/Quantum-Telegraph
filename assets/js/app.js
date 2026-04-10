/**
 * Quantum Telegraph - Core Game Engine (Unified & Safari Patched)
 * Handles WebGL rendering, WebXR, Audio Analysis, Gamepad, Touch, Multiplayer, and Input Diagnostics.
 * Interwoven with Matomo Custom Event Tracking.
 */

import * as THREE from 'https://esm.sh/three@0.160.0';
import { VRButton } from 'https://esm.sh/three@0.160.0/examples/jsm/webxr/VRButton.js';
import { XRControllerModelFactory } from 'https://esm.sh/three@0.160.0/examples/jsm/webxr/XRControllerModelFactory.js';

// Inject dynamic CSS for the Voice Recognition Hologram
const styleEl = document.createElement('style');
styleEl.innerHTML = `
    @keyframes voicePulseOut {
        0% { transform: translate(-50%, -50%) scale(0.5); opacity: 0; }
        20% { transform: translate(-50%, -50%) scale(1.2); opacity: 1; }
        100% { transform: translate(-50%, -50%) scale(2.0); opacity: 0; }
    }
`;
document.head.appendChild(styleEl);

// --- CONFIG & GLOBALS ---
const config = window.MelleVRConfig || {
    wpApiUrl: "/wp-json/melle-vr/v1/scores",
    wpValidateUrl: "/wp-json/melle-vr/v1/validate-username",
    beatmapDir: "/wp-content/uploads/beatmaps/",
    assetDir: "/wp-content/uploads/qrq_radio_assets/",
    icecastBaseUrl: "https://qrjournal.org/icecast/",
    pluginUrl: "/wp-content/plugins/melle-vr/",
    directChannel: "",
    directTrack: "",
    directMp3: "",
    vrAllowedMounts: [],
    vrExcludedMounts: ['admin', 'fallback'],
    restrictedMounts: [],
    userHasRoleAccess: false,
    storeLink: "",
    stationReqs: {},
    userPurchased: []
};

let scene, camera, renderer, analyser, dataArray, audioSource, audioContext;
let tunnelRings = [], incomingObjects = [];
let catcherRing, controller1, controller2, activeController;
let runeShield, shieldRuneSprite, catchphraseSprite;
let isShieldActive = false;
let score = { caught: 0, hits: 0 };

// VR UI Canvases
let vrHudCanvas, vrHudCtx, vrHudTexture, vrHudMesh;
let vrRewardCanvas, vrRewardCtx, vrRewardTexture, vrRewardMesh;

// VR Grip & Tracking State
let isGrippingVR = false;
let isRecallingRing = false;
let vrRingZOffset = 0;
let vrDispersePressed = false;
let gamepadDispersePressed = false;

// Stabilization targets
let targetRingPos = new THREE.Vector3();
let targetRingQuat = new THREE.Quaternion();

// Input Flags
let useKeyboard = false; 
let useGamepad = false; 
let hasNotifiedGamepad = false; 
let aimOffset = { x: 0, y: 0, z: -3, rotY: 0 }; 
const keys = {};

let baseBeta = null;
let baseGamma = null;

let isMouseDown = false;
let touchStartX = 0;
let touchStartY = 0;
let touchStartDist = 0;
let lastTapTime = 0;
let tapTimes = [];
let mouseTapTimes = [];

let statusInput = { keyboard: false, tilt: false, gamepad: false, touch: false, gamepadName: "" };

let currentSong = "";
let currentRawTitle = ""; // NEW: Tracks the raw Icecast string independently
let currentTrackSlug = ""; 
let currentChannel = "melle";
let playerName = "";
let players = {}; 
let isMatchResetting = false;
let isPlayerActive = false;
let isSinglePlayer = false;

let playerAvatars = {}; 
let activeRuneChar = "";
let activeCatchphrase = "";

let remoteRuneShield, remoteShieldRuneSprite, remoteCatchphraseSprite;
let isRemoteIncomingActive = false;
let currentRemotePhrase = "";

let globalRunes = 0;
let hasTriggeredEtheric = false;

let currentBeatmap = null;
let trackStartTime = 0;
const BUFFER_TIME = 8000; 
let lastSpawnTime = 0;

let RING_COUNT = 60; 
const TUNNEL_LENGTH = 100, SPEED = 0.15;
let fullChannelMeta = {};

const FUTHARK_RUNES = ['ᚨ','ᛒ','ᚲ','ᛞ','ᛖ','ᚠ','ᚷ','ᚺ','ᛁ','ᛃ','ᛚ','ᛗ','ᚾ','ᛟ','ᛈ','ᚱ','ᛋ','ᛏ','ᚢ','ᚹ','ᛇ','ᛉ'];
window.MelleVRCollectedRunes = []; 
const runeTextureCache = {}; 

// --- VOICE COMMAND ENGINE BRIDGE ---
window.MelleVR = {
    disperseRune: () => disperseRune(),
    moveRingVoice: (direction) => {
        const step = 2.5; 
        switch(direction) {
            case 'up': aimOffset.y += step; break;
            case 'down': aimOffset.y -= step; break;
            case 'left': aimOffset.x -= step; break;
            case 'right': aimOffset.x += step; break;
            case 'center': 
                aimOffset.x = 0; 
                aimOffset.y = 0; 
                aimOffset.z = -3;
                if(camera) camera.rotation.set(0,0,0);
                baseBeta = null; baseGamma = null; 
                break;
        }
    },
    processVocalRune: (runeChar) => {
        const feedback = document.createElement('div');
        feedback.innerText = runeChar;
        feedback.style.cssText = "position:absolute; top:50%; left:50%; transform:translate(-50%, -50%); font-size:15rem; color:#00f2ff; text-shadow:0 0 40px #00f2ff; pointer-events:none; z-index:99999; animation: voicePulseOut 1s ease-out forwards;";
        
        const wrapper = document.getElementById('melle-vr-wrapper');
        if (wrapper) wrapper.appendChild(feedback);
        setTimeout(() => { if (feedback.parentNode) feedback.remove(); }, 1000);

        let targetIndex = -1;
        let closestZ = -9999; 
        for (let i = 0; i < incomingObjects.length; i++) {
            if (incomingObjects[i].userData.rune === runeChar && !incomingObjects[i].userData.isCaught) {
                if (incomingObjects[i].position.z > closestZ) {
                    closestZ = incomingObjects[i].position.z;
                    targetIndex = i;
                }
            }
        }

        if (targetIndex !== -1) {
            const obj = incomingObjects[targetIndex];
            obj.userData.isCaught = true;
            score.caught++;
            
            catcherRing.material.color.copy(obj.userData.color);
            if (renderer && renderer.xr && renderer.xr.isPresenting) {
                renderer.xr.getSession()?.inputSources[0]?.gamepad?.hapticActuators[0]?.pulse(1.0, 200);
            }

            window.MelleVRCollectedRunes.push(obj.userData.rune);
            syncRunesToCore(true);
            updateScoreUI();

            disposeObject(obj);
            incomingObjects.splice(targetIndex, 1);
        } else {
            feedback.style.color = '#ff0055';
            feedback.style.textShadow = '0 0 40px #ff0055';
        }
    }
};

// --- OFFLINE CACHE API LOGIC ---
const CACHE_NAME = 'qrq-offline-tracks-v1';

window.downloadTrackForOffline = async function(mp3Url, beatmapUrl, btnElement) {
    if (!window.caches) return alert("Your browser does not support offline caching.");
    
    btnElement.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Downloading...';
    btnElement.disabled = true;

    try {
        const cache = await caches.open(CACHE_NAME);
        await cache.addAll([mp3Url, beatmapUrl]);
        btnElement.innerHTML = '<i class="fas fa-check text-success me-1"></i> Saved Offline';
        btnElement.classList.replace('btn-outline-secondary', 'btn-outline-success');
    } catch (err) {
        console.error('Offline caching failed', err);
        btnElement.innerHTML = '<i class="fas fa-exclamation-triangle text-danger me-1"></i> Failed';
    }
};

async function getOfflineAudioSrc(trackUrl) {
    if (!window.caches) return trackUrl;
    const cache = await caches.open(CACHE_NAME);
    const response = await cache.match(trackUrl);
    if (response) {
        const blob = await response.blob();
        return URL.createObjectURL(blob);
    }
    return trackUrl;
}

async function getOfflineBeatmap(beatmapUrl) {
    if (!window.caches) return null;
    const cache = await caches.open(CACHE_NAME);
    const response = await cache.match(beatmapUrl);
    if (response) return await response.json();
    return null;
}

// --- PREMIUM STORE UPSALE LOGIC ---
window.updatePremiumStoreButton = function() {
    const btnPremiumStore = document.getElementById('btnPremiumStore');
    if (!btnPremiumStore) return;

    if (config.userHasRoleAccess || !config.restrictedMounts || config.restrictedMounts.length === 0) {
        btnPremiumStore.style.display = 'none';
        return;
    }

    const stationSelect = document.getElementById('stationSelect');
    const trackSelect = document.getElementById('trackSelect');
    
    let mount = stationSelect && stationSelect.value ? stationSelect.value.split('/').pop() : config.directChannel;
    let trackSlug = trackSelect && trackSelect.value ? trackSelect.value : config.directTrack;

    let targetHref = config.storeLink || '#';
    let btnText = '<i class="fas fa-shopping-cart me-2"></i> Unlock Premium Tracks';

    if (mount && trackSlug && fullChannelMeta[mount]) {
        let trackKey = Object.keys(fullChannelMeta[mount]).find(k => k.replace(/\.mp3$/i, '') === trackSlug);
        if (trackKey && fullChannelMeta[mount][trackKey].product_id) {
            targetHref = window.location.origin + '/?p=' + fullChannelMeta[mount][trackKey].product_id;
            btnText = '<i class="fas fa-unlock me-2"></i> Purchase / Unlock Track';
        }
    }

    if (targetHref !== '#') {
        btnPremiumStore.href = targetHref;
        btnPremiumStore.innerHTML = btnText;
        btnPremiumStore.style.display = 'block';
    } else {
        btnPremiumStore.style.display = 'none';
    }
};

// --- LOBBY STATIONS ---
async function loadStations() {
    const stationSelect = document.getElementById('stationSelect');
    const trackSelect = document.getElementById('trackSelect');
    const btnInit = document.getElementById('btnInit');
    
    if (!stationSelect || !btnInit) return setTimeout(loadStations, 500);

    try {
        const ajaxUrl = window.location.origin + '/wp-admin/admin-ajax.php';
        const proxyRes = await fetch(ajaxUrl + '?action=qrq_get_meta');
        if (!proxyRes.ok) throw new Error("WP Proxy HTTP Error");
        const resData = await proxyRes.json();
        
        fullChannelMeta = resData.data.custom_meta || {};

        let activePlayers = {};
        try {
            const sep = config.wpApiUrl.includes('?') ? '&' : '?';
            const apiRes = await fetch(config.wpApiUrl + sep + 't=' + Date.now());
            if (apiRes.ok) {
                const apiData = await apiRes.json();
                activePlayers = apiData.players || {};
            }
        } catch(apiErr) {}
        
        let channelCounts = {};
        for(let p in activePlayers) {
            let ch = activePlayers[p].channel;
            channelCounts[ch] = (channelCounts[ch] || 0) + 1;
        }

        let sources = [];
        if (resData.success && resData.data && resData.data.icestats && resData.data.icestats.source) {
            sources = Array.isArray(resData.data.icestats.source) ? resData.data.icestats.source : [resData.data.icestats.source];
        }
        
        stationSelect.innerHTML = '';
        let stationsFound = 0;
        
        sources.forEach(s => {
            if(s && s.listenurl) {
                let parts = s.listenurl.split('/');
                let mount = parts[parts.length - 1]; 
                
                if (config.vrExcludedMounts && config.vrExcludedMounts.includes(mount)) return;
                if (config.vrAllowedMounts && config.vrAllowedMounts.length > 0 && !config.vrAllowedMounts.includes(mount)) return;
                
                // NEW: Specific WooCommerce Overrides Global Restrictions
                let isLocked = false;
                if (config.restrictedMounts && config.restrictedMounts.includes(mount) && !config.userHasRoleAccess) {
                    isLocked = true;
                }
                
                if (config.stationReqs && config.stationReqs[mount] && config.stationReqs[mount].trim() !== '') {
                    let reqPids = config.stationReqs[mount].split(',').map(id => id.trim());
                    let hasBoughtSpecific = reqPids.some(pid => config.userPurchased && config.userPurchased.map(String).includes(String(pid)));
                    
                    if (hasBoughtSpecific || config.userHasRoleAccess) {
                        isLocked = false; // Specific purchase overrides global lock!
                    } else {
                        isLocked = true; // Required specific product but hasn't bought it
                    }
                }

                if (isLocked) return;
                
                let count = channelCounts[mount] || 0;
                let title = s.server_name || mount;
                
                let opt = document.createElement('option');
                opt.value = config.icecastBaseUrl + mount; 
                opt.text = `${title} - (${count} Particles Active)`;
                stationSelect.appendChild(opt);
                stationsFound++;
            }
        });

        if(stationsFound === 0) {
            stationSelect.innerHTML = '<option value="">No authorized streams available</option>';
            throw new Error("No streams available");
        }
        btnInit.disabled = false;

        function updateTracks() {
            if (!trackSelect) return;
            trackSelect.innerHTML = '<option value="">Live Broadcast (All Tracks)</option>';
            const mount = stationSelect.value.split('/').pop();
            
            if (fullChannelMeta[mount]) {
                Object.keys(fullChannelMeta[mount]).forEach(filename => {
                    if (filename.endsWith('.mp3')) {
                        const meta = fullChannelMeta[mount][filename];
                        const displayTitle = (meta.artist ? meta.artist + ' - ' : '') + (meta.title || filename);
                        const opt = document.createElement('option');
                        opt.value = filename.replace(/\.mp3$/i, '');
                        opt.text = displayTitle;
                        trackSelect.appendChild(opt);
                    }
                });
            }
            
            if (config.directTrack && config.directChannel === mount) {
                trackSelect.value = config.directTrack;
            }

            window.updatePremiumStoreButton();
        }
        
        stationSelect.addEventListener('change', updateTracks);
        trackSelect.addEventListener('change', window.updatePremiumStoreButton);
        
        if (config.directChannel) {
            const targetVal = config.icecastBaseUrl + config.directChannel;
            const optionExists = Array.from(stationSelect.options).some(opt => opt.value === targetVal);
            if (optionExists) {
                stationSelect.value = targetVal;
            }
        }
        
        updateTracks(); 

    } catch (err) {
        if (!stationSelect.options.length) {
            stationSelect.innerHTML = `<option value="">No streams found.</option>`;
        }
    }
}
loadStations();

// --- BOOTSTRAP 5 INPUT DIAGNOSTICS UI ---
document.addEventListener("DOMContentLoaded", () => {
    const btnRevealNetwork = document.getElementById('btnRevealNetwork');
    const networkOptionsContainer = document.getElementById('networkOptionsContainer');
    const spToggle = document.getElementById('singlePlayerToggle');
    
    if (btnRevealNetwork && networkOptionsContainer) {
        btnRevealNetwork.addEventListener('click', (e) => {
            e.preventDefault();
            btnRevealNetwork.style.display = 'none';
            networkOptionsContainer.style.display = 'flex';
            
            window.updatePremiumStoreButton();

            if (spToggle && spToggle.checked) {
                spToggle.checked = false;
                spToggle.dispatchEvent(new Event('change'));
            }
        });
    }

    if (config.directTrack) {
        if (btnRevealNetwork) btnRevealNetwork.style.display = 'none';
        if (networkOptionsContainer) networkOptionsContainer.style.display = 'flex';
        
        window.updatePremiumStoreButton();
        
        const previewBtn = document.getElementById('previewAudioBtn')?.parentElement;
        if(previewBtn) previewBtn.style.display = 'none';
        
        const spToggleLabel = document.getElementById('spToggleLabel');
        const spToggleText = document.getElementById('spToggleText');
        
        if (spToggle && spToggleLabel) {
            spToggle.checked = true;
            spToggle.disabled = false; 
            if(spToggleText) spToggleText.innerText = `Isolated Circuit (Single)`;
            spToggleLabel.classList.replace('btn-outline-secondary', 'btn-outline-warning');
            const icon = spToggleLabel.querySelector('i');
            if(icon) icon.classList.replace('fa-network-wired', 'fa-lock');
            spToggleLabel.style.opacity = '1.0';
            spToggleLabel.style.cursor = 'pointer';
        }
        
        const btnInit = document.getElementById('btnInit');
        if (btnInit) btnInit.disabled = false;
    }

    if (!config.directTrack && spToggle) {
        spToggle.checked = false;
        const spToggleLabel = document.getElementById('spToggleLabel');
        const spToggleText = document.getElementById('spToggleText');
        if (spToggleText) spToggleText.innerText = "Network Circuit (Multi)";
        if (spToggleLabel) {
            spToggleLabel.classList.replace('btn-outline-warning', 'btn-outline-secondary');
            const icon = spToggleLabel.querySelector('i');
            if(icon) icon.classList.replace('fa-lock', 'fa-network-wired');
        }
    }

    const diagnosticsModalBody = document.getElementById('diagnostics-modal-body');
    if (diagnosticsModalBody) {
        const inputMonitor = document.createElement('div');
        inputMonitor.id = "input-monitor";
        inputMonitor.className = "card bg-dark text-light border-info shadow-sm w-100";
        inputMonitor.innerHTML = `
            <div class="card-body p-3">
                <button id="btnTestInputs" class="btn btn-outline-info w-100 fw-bold mb-3 text-uppercase">
                    <i class="fas fa-search me-1"></i> Test Sensors & Inputs
                </button>
                <ul class="list-group list-group-flush bg-transparent mb-0">
                    <li class="list-group-item bg-transparent text-light d-flex justify-content-between align-items-center px-1 border-secondary" id="status-keyboard">
                        <span><i class="fas fa-keyboard"></i> Keyboard</span> <span class="badge bg-secondary">Waiting...</span>
                    </li>
                    <li class="list-group-item bg-transparent text-light d-flex justify-content-between align-items-center px-1 border-secondary" id="status-touch">
                        <span><i class="fas fa-hand-pointer"></i> Touch/Drag</span> <span class="badge bg-secondary">Waiting...</span>
                    </li>
                    <li class="list-group-item bg-transparent text-light d-flex justify-content-between align-items-center px-1 border-secondary" id="status-tilt">
                        <span><i class="fas fa-mobile-alt"></i> Tilt/Gyro</span> <span class="badge bg-secondary">Waiting...</span>
                    </li>
                    <li class="list-group-item bg-transparent text-light d-flex justify-content-between align-items-center px-1 border-0" id="status-gamepad">
                        <span><i class="fas fa-gamepad"></i> Controller</span> <span class="badge bg-secondary">Waiting...</span>
                    </li>
                </ul>
                <div id="diagnostic-log" class="mt-3 text-warning text-center small border-top border-secondary pt-2">
                    Note: Android Tilt requires HTTPS.
                </div>
            </div>
        `;
        diagnosticsModalBody.appendChild(inputMonitor);
        
        document.getElementById('btnTestInputs').addEventListener('click', () => {
            const logBox = document.getElementById('diagnostic-log');
            if (location.protocol !== 'https:') {
                logBox.innerText = "WARNING: Android blocks sensors on HTTP. Please use HTTPS.";
                logBox.className = "mt-3 text-danger text-center small border-top border-secondary pt-2 fw-bold";
            } else {
                logBox.innerText = "Requesting sensor access...";
                logBox.className = "mt-3 text-info text-center small border-top border-secondary pt-2";
            }

            if (typeof DeviceOrientationEvent !== 'undefined' && typeof DeviceOrientationEvent.requestPermission === 'function') {
                DeviceOrientationEvent.requestPermission()
                    .then(state => { 
                        if (state === 'granted') {
                            window.addEventListener('deviceorientation', testTiltListener);
                            logBox.innerText = "Sensors active! Tap screen or press controller.";
                        } else {
                            logBox.innerText = "Sensor access denied by device.";
                            logBox.className = "mt-3 text-danger text-center small border-top border-secondary pt-2 fw-bold";
                        }
                    })
                    .catch(err => console.error(err));
            } else {
                window.addEventListener('deviceorientation', testTiltListener);
                if (location.protocol === 'https:') logBox.innerText = "Sensors listening.";
            }
        });
    }

    let previewAudio = new Audio();
    previewAudio.crossOrigin = "anonymous";
    
    const previewBtn = document.getElementById('previewAudioBtn');
    if (previewBtn) {
        previewBtn.addEventListener('click', () => {
            const stationSelect = document.getElementById('stationSelect');
            if (!previewAudio.paused) {
                previewAudio.pause();
                previewBtn.innerHTML = '<i class="fas fa-play me-2"></i> Preview Frequency';
            } else {
                if (stationSelect && stationSelect.value) {
                    previewAudio.src = stationSelect.value;
                    previewAudio.play().catch(e => alert("Please interact with the page first."));
                    previewBtn.innerHTML = '<i class="fas fa-pause me-2"></i> Pause';
                } else {
                    alert("Please wait for a frequency to load.");
                }
            }
        });
    }

    const btnInit = document.getElementById('btnInit');
    if (btnInit) {
        btnInit.addEventListener('click', async () => {
            btnInit.disabled = true; 
            const btnInitText = document.getElementById('btnInitText');
            const originalText = btnInitText ? btnInitText.innerHTML : '<i class="fas fa-bolt me-2"></i> Inject Particle';
            
            if (btnInitText) btnInitText.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Validating ID...';

            if (!previewAudio.paused) {
                previewAudio.pause();
                if(previewBtn) previewBtn.innerHTML = '<i class="fas fa-play me-2"></i> Preview Frequency';
            }

            const nameInput = document.getElementById('playerName');
            let potentialName = nameInput && nameInput.value.trim() !== "" ? nameInput.value.trim() : "Particle_" + Math.floor(Math.random() * 1000);
            
            try {
                const valRes = await fetch(config.wpValidateUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ username: potentialName })
                });
                const valData = await valRes.json();
                if (!valData.safe) {
                    const feedback = document.getElementById('nameValidationFeedback');
                    if (feedback) {
                        feedback.innerText = "System Alert: Particle ID rejected by AI moderator.";
                        feedback.style.display = 'block';
                    }
                    btnInit.disabled = false;
                    if (btnInitText) btnInitText.innerHTML = originalText;
                    return;
                }
            } catch (e) {}

            playerName = potentialName;
            isPlayerActive = true;

            const stationSelect = document.getElementById('stationSelect');
            let baseChan = "";
            if (stationSelect && stationSelect.value) {
                baseChan = stationSelect.value.split('/').pop();
            } else {
                baseChan = config.directChannel;
            }

            // --- MATOMO TRACKING: Session Start ---
            if (window._paq) {
                window._paq.push(['trackEvent', 'VR Session', 'Join Frequency', baseChan]);
            }
            // --------------------------------------

            const trackSelect = document.getElementById('trackSelect');
            
            const userSelectedTrack = trackSelect ? trackSelect.value : "";
            const isTargetedMission = userSelectedTrack !== "";
            const targetSlug = userSelectedTrack;
            
            const targetMp3Url = config.assetDir + baseChan + '/' + targetSlug + '.mp3';
            const finalAudioSrc = await getOfflineAudioSrc(targetMp3Url);

            if (isTargetedMission) {
                isSinglePlayer = spToggle && spToggle.checked;
                currentChannel = baseChan;
                if (isSinglePlayer) currentChannel += "_iso_" + Date.now();
                
                audioSource = new Audio();
                audioSource.crossOrigin = "anonymous";
                audioSource.src = finalAudioSrc; 
            } else {
                isSinglePlayer = spToggle && spToggle.checked;
                const freshToggle = document.getElementById('freshSessionToggle');
                if (freshToggle && freshToggle.checked) {
                    localStorage.removeItem('iso_lexicon');
                    localStorage.removeItem('blueprint_transfer_data');
                    localStorage.removeItem('blueprint_cumulative_wordcloud');
                    window.MelleVRCollectedRunes = [];
                }
                currentChannel = baseChan;
                if (isSinglePlayer) currentChannel += "_iso_" + Date.now();
                
                audioSource = new Audio();
                audioSource.crossOrigin = "anonymous";
                audioSource.src = await getOfflineAudioSrc(stationSelect.value);
            }

            if (typeof DeviceOrientationEvent !== 'undefined' && typeof DeviceOrientationEvent.requestPermission === 'function') {
                DeviceOrientationEvent.requestPermission()
                    .then(state => { if (state === 'granted') window.addEventListener('deviceorientation', handleTilt); })
                    .catch(console.error);
            } else {
                window.addEventListener('deviceorientation', handleTilt);
            }

            audioContext = new (window.AudioContext || window.webkitAudioContext)();
            const source = audioContext.createMediaElementSource(audioSource);
            analyser = audioContext.createAnalyser();
            
            source.connect(analyser);
            analyser.connect(audioContext.destination);
            dataArray = new Uint8Array(analyser.frequencyBinCount);
            
            try {
                await audioContext.resume();
                await audioSource.play();
            } catch (err) {
                alert("Audio blocked. Please interact and try again.");
                btnInit.disabled = false;
                if (btnInitText) btnInitText.innerHTML = originalText;
                return; 
            }
            
            initScene();
            
            const uiContainer = document.getElementById('ui-container');
            if(uiContainer) {
                uiContainer.classList.remove('d-flex');
                uiContainer.classList.add('d-none');
            } else {
                const uiOverlay = document.getElementById('ui-overlay');
                if(uiOverlay) uiOverlay.style.display = 'none';
            }
            
            document.getElementById('txtStatus').style.display = 'block';
            document.getElementById('songUI').style.display = 'block';
            document.getElementById('livePlayersUI').style.display = 'block';
            document.getElementById('liveLeaderboardUI').style.display = 'block';
            
            const ingameControls = document.getElementById('ingame-overlay-controls');
            if (ingameControls) ingameControls.style.display = 'flex';

            updateScoreUI();
            const collabBtn = document.getElementById('ingame-collab-btn');

            if (isTargetedMission) {
                currentSong = targetSlug.replace(/-/g, ' ');
                currentRawTitle = targetSlug; // Ensure this matches track keys 
                currentTrackSlug = targetSlug;
                loadBeatmap(currentRawTitle);
                const songUI = document.getElementById('songUI');
                if (songUI) songUI.innerHTML = `<i class="fas fa-music me-2"></i> ${currentSong} (LOOPING)`;
                
                if (collabBtn) {
                    collabBtn.style.display = 'block';
                    collabBtn.href = window.location.origin + '/radio-player/track/' + baseChan + '/' + currentTrackSlug;
                }
                
                audioSource.addEventListener('ended', () => {
                    triggerEndOfMatch(currentSong, currentTrackSlug, currentRawTitle);
                    setTimeout(() => {
                        if (isMatchResetting) {
                            resetMatchState(currentSong, currentTrackSlug, currentRawTitle);
                            audioSource.play().catch(e => console.error(e));
                            trackStartTime = performance.now() + BUFFER_TIME;
                        }
                    }, 12000); 
                });
            } else {
                fetchCurrentSong(); 
                setInterval(fetchCurrentSong, 5000);      
            }

            pollGlobalScores();
            updateGlobalScore(); 
            
            setInterval(pollGlobalScores, 1500);
            setInterval(updateGlobalScore, 1500);
            
            const decipherBtn = document.getElementById('sendRunesToGeminiBtn');
            if (decipherBtn) decipherBtn.addEventListener('click', sendRunesToGemini);
        });
    }
});

window.addEventListener("gamepadconnected", (e) => {
    statusInput.gamepadName = e.gamepad.id;
    const statPad = document.getElementById('status-gamepad');
    if(statPad) statPad.innerHTML = `<span><i class="fas fa-gamepad"></i> Controller</span> <span class="badge bg-success">${e.gamepad.id.substring(0,18)}...</span>`;
});

window.addEventListener("gamepaddisconnected", (e) => {
    const statPad = document.getElementById('status-gamepad');
    if(statPad) statPad.innerHTML = `<span><i class="fas fa-gamepad"></i> Controller</span> <span class="badge bg-danger">Disconnected</span>`;
});

function testTiltListener(e) {
    if (!statusInput.tilt && e.gamma !== null) {
        statusInput.tilt = true;
        const statTilt = document.getElementById('status-tilt');
        if(statTilt) statTilt.innerHTML = `<span><i class="fas fa-mobile-alt"></i> Tilt/Gyro</span> <span class="badge bg-success">Active</span>`;
    }
}

function updateDiagnostics() {
    if (useKeyboard && !statusInput.keyboard) {
        statusInput.keyboard = true;
        const statKey = document.getElementById('status-keyboard');
        if(statKey) statKey.innerHTML = `<span><i class="fas fa-keyboard"></i> Keyboard</span> <span class="badge bg-success">Active</span>`;
    }
}
setInterval(updateDiagnostics, 100);

			function runBenchmark() {
    const btn = document.getElementById('btnBenchmark');
    const select = document.getElementById('graphicsQuality');
    if(!btn || !select) return;
    
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    btn.disabled = true;
    
    setTimeout(() => {
        const startTime = performance.now();
        let dummy = 0;
        for(let i = 0; i < 5000000; i++) dummy += Math.sqrt(i) * Math.sin(i);
        const duration = performance.now() - startTime;
        
        if (duration > 65) select.value = 'low';
        else if (duration > 35) select.value = 'med';
        else select.value = 'high';
        
        btn.innerHTML = '<i class="fas fa-check text-success"></i>';
        setTimeout(() => {
            btn.innerHTML = '<i class="fas fa-tachometer-alt"></i>';
            btn.disabled = false;
        }, 1500);
    }, 100);
}

// --- RUNIC HELPERS & SPRITES ---
function disposeObject(obj) {
    if (!obj) return;
    if (obj.geometry) obj.geometry.dispose();
    if (obj.material) {
        if (Array.isArray(obj.material)) obj.material.forEach(m => m.dispose());
        else obj.material.dispose();
    }
    while (obj.children.length > 0) {
        const child = obj.children[0];
        obj.remove(child);
        if (child.geometry) child.geometry.dispose();
        if (child.material) child.material.dispose();
    }
    scene.remove(obj);
}

function createRuneSprite(runeChar) {
    if (!runeTextureCache[runeChar]) {
        const canvas = document.createElement('canvas');
        canvas.width = 128; canvas.height = 128;
        const ctx = canvas.getContext('2d');
        ctx.fillStyle = 'rgba(0,0,0,0)';
        ctx.fillRect(0, 0, 128, 128);
        ctx.font = 'bold 80px sans-serif';
        ctx.fillStyle = '#00f2ff';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.shadowColor = '#00f2ff';
        ctx.shadowBlur = 20;
        ctx.fillText(runeChar, 64, 64);
        runeTextureCache[runeChar] = new THREE.CanvasTexture(canvas);
    }
    const material = new THREE.SpriteMaterial({ map: runeTextureCache[runeChar], transparent: true, depthTest: false });
    const sprite = new THREE.Sprite(material);
    sprite.scale.set(1.5, 1.5, 1); 
    return sprite;
}

function getRuneTexture(runeChar) {
    const cacheKey = 'SHIELD_' + runeChar;
    if (!runeTextureCache[cacheKey]) {
        const canvas = document.createElement('canvas');
        canvas.width = 256; canvas.height = 256;
        const ctx = canvas.getContext('2d');
        ctx.fillStyle = 'rgba(0,0,0,0)';
        ctx.fillRect(0, 0, 256, 256);
        ctx.font = 'bold 120px sans-serif';
        ctx.fillStyle = '#ffaa00'; 
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.shadowColor = '#ffaa00';
        ctx.shadowBlur = 30;
        ctx.fillText(runeChar, 128, 128);
        runeTextureCache[cacheKey] = new THREE.CanvasTexture(canvas);
    }
    return runeTextureCache[cacheKey];
}

function updateCatchphraseSprite(text) {
    if (!catchphraseSprite) return;
    const canvas = document.createElement('canvas');
    canvas.width = 1024; canvas.height = 256;
    const ctx = canvas.getContext('2d');
    ctx.fillStyle = 'rgba(0,0,0,0)';
    ctx.fillRect(0, 0, 1024, 256);
    ctx.font = 'bold 70px sans-serif';
    ctx.fillStyle = '#00f2ff';
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.shadowColor = '#ffaa00';
    ctx.shadowBlur = 20;
    ctx.fillText(text.toUpperCase(), 512, 128);
    if (catchphraseSprite.material.map) catchphraseSprite.material.map.dispose(); 
    catchphraseSprite.material.map = new THREE.CanvasTexture(canvas);
    catchphraseSprite.material.needsUpdate = true;
    catchphraseSprite.material.opacity = 1.0;
    catchphraseSprite.userData.fadeDelay = 180; 
}

function updateRemoteCatchphraseSprite(text) {
    if (!remoteCatchphraseSprite) return;
    const canvas = document.createElement('canvas');
    canvas.width = 1024; canvas.height = 256;
    const ctx = canvas.getContext('2d');
    ctx.fillStyle = 'rgba(0,0,0,0)';
    ctx.fillRect(0, 0, 1024, 256);
    ctx.font = 'bold 60px sans-serif';
    ctx.fillStyle = '#ff00ff'; 
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.shadowColor = '#ff00ff';
    ctx.shadowBlur = 20;
    ctx.fillText(`[SIGNAL] ${text.toUpperCase()}`, 512, 128);
    if (remoteCatchphraseSprite.material.map) remoteCatchphraseSprite.material.map.dispose(); 
    remoteCatchphraseSprite.material.map = new THREE.CanvasTexture(canvas);
    remoteCatchphraseSprite.material.needsUpdate = true;
    remoteCatchphraseSprite.material.opacity = 1.0;
    remoteCatchphraseSprite.userData.fadeDelay = 180; 
}

function updateAvatarCatchphraseSprite(spriteObj, text) {
    const canvas = document.createElement('canvas');
    canvas.width = 1024; canvas.height = 256;
    const ctx = canvas.getContext('2d');
    ctx.fillStyle = 'rgba(0,0,0,0)';
    ctx.fillRect(0, 0, 1024, 256);
    ctx.font = 'bold 70px sans-serif';
    ctx.fillStyle = '#00f2ff';
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.shadowColor = '#ffaa00';
    ctx.shadowBlur = 20;
    ctx.fillText(text.toUpperCase(), 512, 128);
    if (spriteObj.material.map) spriteObj.material.map.dispose(); 
    spriteObj.material.map = new THREE.CanvasTexture(canvas);
    spriteObj.material.needsUpdate = true;
}

async function fetchRuneCatchphrase(rune) {
    updateCatchphraseSprite("CHANNELING...");
    activeCatchphrase = "CHANNELING...";
    updateGlobalScore(); 

    const ajaxUrl = window.location.origin + '/wp-admin/admin-ajax.php';
    const prompt = `You are a cosmic AI. Give me a short, epic, 2-to-4 word battle cry related to the Norse rune ${rune}. Respond ONLY with the battle cry, no quotes or extra text.`;
    
    try {
        const formData = new FormData();
        formData.append('action', 'blueprint_generate_story');
        formData.append('prompt', prompt);

        const response = await fetch(ajaxUrl, { method: 'POST', body: formData });
        const result = await response.json();

        if (result.success && result.data && result.data.story) {
            let phrase = result.data.story.replace(/['"]/g, '').trim();
            updateCatchphraseSprite(phrase);
            activeCatchphrase = phrase;
        } else {
            updateCatchphraseSprite("POWER SURGE!");
            activeCatchphrase = "POWER SURGE!";
        }
    } catch (err) {
        updateCatchphraseSprite("LINK SEVERED!");
        activeCatchphrase = "LINK SEVERED!";
    }
    updateGlobalScore();
}

function syncRunesToCore(voiceCaught = false) {
    localStorage.setItem('melle_vr_collected_runes', JSON.stringify(window.MelleVRCollectedRunes));
    let existingStr = localStorage.getItem('blueprint_transfer_data');
    let data = existingStr ? JSON.parse(existingStr) : {};
    data.collectedRunes = window.MelleVRCollectedRunes;
    localStorage.setItem('blueprint_transfer_data', JSON.stringify(data));
    
    const tickerContent = document.getElementById('rune-ticker-content');
    if (tickerContent) {
        if (voiceCaught && window.MelleVRCollectedRunes.length > 0) {
            let safeRunes = window.MelleVRCollectedRunes.slice(0, -1).join(' ');
            let highlightedRune = window.MelleVRCollectedRunes[window.MelleVRCollectedRunes.length - 1];
            
            tickerContent.innerHTML = safeRunes + (safeRunes.length > 0 ? ' ' : '') + `<span class="rune-highlight" style="color:#ffff00; text-shadow:0 0 15px #ffff00; transition:all 0.3s ease;">${highlightedRune}</span>`;
            
            setTimeout(() => {
                tickerContent.innerText = window.MelleVRCollectedRunes.join(' ');
            }, 3000);
        } else {
            tickerContent.innerText = window.MelleVRCollectedRunes.join(' ');
        }
    }
}

function wpSanitizeFileName(title) {
    let slug = title;
    slug = slug.replace(/\s+/g, '-');
    slug = slug.replace(/[?\[\]/\\=<>:;,'"\&$#*()|~`!{}%+\^]/g, '');
    slug = slug.replace(/-+/g, '-');
    return slug.replace(/^-+|-+$/g, '');
}

// --- ETHERIC INTERCEPTION & TRANSLATION ---
function triggerEthericInterception(words) {
    if (!Array.isArray(words)) return;

    hasTriggeredEtheric = true;

    // --- MATOMO TRACKING: Global Overload Trigger ---
    if (window._paq) {
        window._paq.push(['trackEvent', 'VR Global Event', 'Telegraphic Overload Triggered', currentChannel, words.length]);
    }
    // ------------------------------------------------

    if (renderer && renderer.xr && renderer.xr.isPresenting) {
        let session = renderer.xr.getSession();
        if (session && session.inputSources) {
            session.inputSources.forEach(source => {
                if (source.gamepad && source.gamepad.hapticActuators && source.gamepad.hapticActuators.length > 0) {
                    source.gamepad.hapticActuators[0].pulse(1.0, 1000); 
                }
            });
        }
    }

    updateCatchphraseSprite("TELEGRAPHIC OVERLOAD");
    activeCatchphrase = "TELEGRAPHIC OVERLOAD";
    updateGlobalScore();

    let lexicon = JSON.parse(localStorage.getItem('iso_lexicon') || '{}');
    let newKeywords = words.map(w => w.toLowerCase());
    
    words.forEach(word => {
        let cleanWord = word.toLowerCase();
        if (!lexicon[cleanWord]) {
            lexicon[cleanWord] = { weight: 50, context: `Telegraphic Interception (Global Limit Reached)`, influence: 'essence' };
        } else {
            lexicon[cleanWord].weight += 25; 
        }
    });
    localStorage.setItem('iso_lexicon', JSON.stringify(lexicon));

    let transferData = JSON.parse(localStorage.getItem('blueprint_transfer_data') || '{}');
    transferData.glossary = lexicon;
    localStorage.setItem('blueprint_transfer_data', JSON.stringify(transferData));

    const rewardUI = document.getElementById('rewardUI');
    const rewardDetails = document.getElementById('rewardDetails');
    if (rewardUI && rewardDetails) {
        rewardDetails.innerHTML = `
            <div class="text-center">
                <h3 class="text-warning fw-bold mb-3"><i class="fas fa-bolt me-2"></i> TELEGRAPHIC OVERLOAD</h3>
                <div class="alert alert-dark border-warning text-light mb-4 p-3" style="background: rgba(0,0,0,0.6);">
                    <strong class="d-block text-warning mb-2 border-bottom border-warning pb-1">NETWORK LEXICON BURST</strong>
                    <div style="line-height: 1.5; font-family: monospace;">${words.join(' • ')}</div>
                </div>
                <div class="d-flex gap-2 justify-content-center mb-2">
                    <button id="dismissOverloadBtn" class="btn btn-warning w-100 fw-bold text-dark py-2 text-uppercase shadow-sm">
                        <i class="fas fa-times me-1"></i> Dismiss Alert
                    </button>
                </div>
            </div>
        `;
        rewardUI.style.display = 'block';
        document.getElementById('dismissOverloadBtn').onclick = () => { rewardUI.style.display = 'none'; };
        setTimeout(() => { if(!isMatchResetting) rewardUI.style.display = 'none'; }, 12000);
    }
}

const frequencyMapping = {
    latin:  ['E','T','A','O','I','N','S','H','R','D','L','C','U','M','W','F','G','Y','P','B','V','K','J','X','Q','Z'],
    runes:  ['ᚠ','ᚢ','ᚦ','ᚨ','ᚱ','ᚲ','ᚷ','ᚹ','ᚺ','ᚾ','ᛁ','ᛃ','ᛇ','ᛈ','ᛉ','ᛊ','ᛏ','ᛒ','ᛖ','ᛗ','ᛚ','ᛜ','ᛞ','ᛟ','ᛞ','ᛟ']
};
let currentDisplayMode = 'runes';

function toggleTranslation() {
    currentDisplayMode = currentDisplayMode === 'runes' ? 'latin' : 'runes';
    const elements = document.querySelectorAll('.translatable-word');
    elements.forEach(el => {
        let originalText = el.dataset.originalLatin.toUpperCase();
        let newText = '';
        for(let i = 0; i < originalText.length; i++) {
            let char = originalText[i];
            let freqIndex = frequencyMapping.latin.indexOf(char);
            if (freqIndex !== -1) newText += currentDisplayMode === 'runes' ? frequencyMapping.runes[freqIndex] : frequencyMapping.latin[freqIndex];
            else newText += char;
        }
        el.innerText = newText;
    });
}

async function sendRunesToGemini() {
    let collected = window.MelleVRCollectedRunes || [];
    if (collected.length === 0) {
        let existingStr = localStorage.getItem('blueprint_transfer_data');
        let data = existingStr ? JSON.parse(existingStr) : {};
        if (data.collectedRunes) collected = data.collectedRunes;
    }

    const container = document.getElementById('generated-words-container');
    if (collected.length === 0) {
        if (container) container.innerHTML = '<span style="color:#ff4444;">No runes collected yet! Play a match first.</span>';
        return;
    }

    if (container) container.innerHTML = '<span style="color:#00f2ff;"><i class="fas fa-spinner fa-spin me-2"></i>Consulting AI...</span>';

    const letters = collected.join('');
    const ajaxUrl = window.location.origin + '/wp-admin/admin-ajax.php';

    try {
        const formData = new FormData();
        formData.append('action', 'blueprint_generate_words_from_runes');
        formData.append('letters', letters);
        
        const aiEngineSelect = document.getElementById('aiEngineSelect');
        if (aiEngineSelect) formData.append('ai_engine', aiEngineSelect.value);

        const response = await fetch(ajaxUrl, { method: 'POST', body: formData });
        const result = await response.json();

        if (result.success && result.data && Array.isArray(result.data)) {
            container.innerHTML = '';
            result.data.forEach(word => {
                let span = document.createElement('span');
                span.className = 'translatable-word';
                span.dataset.originalLatin = word;
                span.innerText = word;
                span.style.cursor = 'pointer';
                span.style.marginRight = '10px';
                span.style.color = '#ff8c1a';
                span.style.display = 'inline-block';
                span.style.transition = 'color 0.3s ease';
                span.addEventListener('click', toggleTranslation);
                span.addEventListener('mouseover', () => span.style.color = '#00f2ff');
                span.addEventListener('mouseout', () => span.style.color = '#ff8c1a');
                container.appendChild(span);
            });
            let tempMode = currentDisplayMode;
            currentDisplayMode = 'latin'; 
            toggleTranslation();
            currentDisplayMode = tempMode;
        } else {
            container.innerHTML = `<span style="color:#ff4444;">Error: ${result.data || 'Neural Link Failed.'}</span>`;
        }
    } catch (err) {
        container.innerHTML = '<span style="color:#ff4444;">API Connection Failed.</span>';
    }
}

// --- MULTIPLAYER API SYNC ---
async function removePlayerFromServer() {
    if (isPlayerActive) {
        try {
            await fetch(config.wpApiUrl, {
                method: 'DELETE',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ name: playerName }),
                keepalive: true
            });
        } catch (err) {}
    }
}

async function updateGlobalScore() {
    if (!isPlayerActive || !catcherRing) return;
    const myTotal = score.hits + score.caught;
    
    const payload = { 
        name: playerName, 
        channel: currentChannel, 
        score: myTotal,
        runes_caught: score.caught,
        collected_runes: window.MelleVRCollectedRunes,
        pos_x: catcherRing.position.x,
        pos_y: catcherRing.position.y,
        pos_z: catcherRing.position.z,
        rot_y: catcherRing.rotation.y,
        color: catcherRing.material.color.getHexString(),
        shield_active: isShieldActive ? 1 : 0,
        current_rune: activeRuneChar,
        catchphrase: activeCatchphrase
    };

    try {
        const res = await fetch(config.wpApiUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        if (!res.ok) console.error(`Melle VR Score Sync Error (${res.status})`);
    } catch (err) {}
}

async function pollGlobalScores() {
    try {
        const sep = config.wpApiUrl.includes('?') ? '&' : '?';
        const res = await fetch(config.wpApiUrl + sep + 't=' + Date.now());
        if (!res.ok) return;
        
        const data = await res.json();
        
        players = {};
        if (data.players) {
            for(let p in data.players) {
                if(data.players[p].channel === currentChannel) players[p] = data.players[p];
            }
        }
        
        globalRunes = data.global_runes || 0;
        
        const myTotal = score.hits + score.caught;
        const txtStatus = document.getElementById('txtStatus');
        if(txtStatus) {
            txtStatus.innerHTML = `VOLTAGE: <strong>${myTotal}</strong> (GLOBAL ᚱ: <span style="color:#ff0">${globalRunes}/100</span> | CAPTURED: <span style="color:#0f0">${score.caught}</span>)`;
        }
        updateVRHUD();
        
        if (data.etheric_interception && !hasTriggeredEtheric && Array.isArray(data.etheric_words) && data.etheric_words.length > 0) {
            triggerEthericInterception(data.etheric_words);
        } else if (globalRunes === 0) {
            hasTriggeredEtheric = false; 
        }
        
        updateLiveLeaderboardUI();
        syncAvatars();

    } catch (err) {}
}

function syncAvatars() {
    for (let p in players) {
        if (p === playerName) continue; 
        
        let data = players[p];
        if (data.pos_x === undefined) continue;

        if (!playerAvatars[p]) {
            const geo = new THREE.TorusGeometry(0.6, 0.05, 16, 100);
            const mat = new THREE.MeshBasicMaterial({ color: 0xffffff });
            const mesh = new THREE.Mesh(geo, mat);
            scene.add(mesh);
            
            const avatarShieldGeo = new THREE.CircleGeometry(0.55, 32);
            const avatarShieldMat = new THREE.MeshBasicMaterial({ color: 0xffaa00, transparent: true, opacity: 0, side: THREE.DoubleSide });
            const avatarShield = new THREE.Mesh(avatarShieldGeo, avatarShieldMat);
            avatarShield.position.z = 0.01;
            mesh.add(avatarShield);

            const avatarRuneSprite = new THREE.Sprite(new THREE.SpriteMaterial({ transparent: true, opacity: 0, depthTest: false }));
            avatarRuneSprite.scale.set(0.8, 0.8, 1);
            avatarShield.add(avatarRuneSprite);

            const avatarPhraseSprite = new THREE.Sprite(new THREE.SpriteMaterial({ transparent: true, opacity: 0, depthTest: false }));
            avatarPhraseSprite.position.y = 1.5;
            avatarPhraseSprite.scale.set(4, 1, 1);
            mesh.add(avatarPhraseSprite);
            
            playerAvatars[p] = {
                mesh: mesh, shield: avatarShield, runeSprite: avatarRuneSprite, phraseSprite: avatarPhraseSprite,
                targetPos: new THREE.Vector3(), targetRotY: 0, lastPhrase: "", isRemoteShieldActive: false
            };
        }

        let avatar = playerAvatars[p];
        
        avatar.targetPos.set(-parseFloat(data.pos_x), parseFloat(data.pos_y), -(TUNNEL_LENGTH + parseFloat(data.pos_z)));
        avatar.targetRotY = -parseFloat(data.rot_y); 
        if(data.color) avatar.mesh.material.color.setHex(parseInt(data.color, 16));

        if (data.shield_active == 1 && !avatar.isRemoteShieldActive) {
            avatar.isRemoteShieldActive = true;
            avatar.shield.scale.set(0.1, 0.1, 0.1); 
            avatar.shield.material.opacity = 0.8;
            avatar.runeSprite.material.opacity = 1.0;
            avatar.phraseSprite.material.opacity = 1.0;
        } else if (data.shield_active == 0) {
            avatar.isRemoteShieldActive = false;
        }

        if (data.shield_active == 1 && data.current_rune) avatar.runeSprite.material.map = getRuneTexture(data.current_rune);
        if (data.shield_active == 1 && data.catchphrase && avatar.lastPhrase !== data.catchphrase) {
            updateAvatarCatchphraseSprite(avatar.phraseSprite, data.catchphrase);
            avatar.lastPhrase = data.catchphrase;
        }
        
        if (data.shield_active == 1) {
            if (data.current_rune && data.catchphrase && currentRemotePhrase !== data.catchphrase) {
                isRemoteIncomingActive = true;
                remoteRuneShield.visible = true;
                remoteRuneShield.scale.set(0.1, 0.1, 0.1);
                remoteRuneShield.material.opacity = 0.8;
                remoteShieldRuneSprite.material.opacity = 1.0;
                remoteShieldRuneSprite.material.map = getRuneTexture(data.current_rune);
                updateRemoteCatchphraseSprite(data.catchphrase);
                currentRemotePhrase = data.catchphrase;
                if (renderer && renderer.xr && renderer.xr.isPresenting) renderer.xr.getSession()?.inputSources[0]?.gamepad?.hapticActuators[0]?.pulse(0.5, 100);
            }
        } else {
            if(avatar.lastPhrase === currentRemotePhrase) {
                isRemoteIncomingActive = false;
                currentRemotePhrase = "";
            }
        }
    }

    for (let p in playerAvatars) {
        if (!players[p]) {
            disposeObject(playerAvatars[p].mesh);
            delete playerAvatars[p];
        }
    }
}

// --- MULTIPLAYER & LOCAL GHOST SCORE INTEGRATION ---
function updateLiveLeaderboardUI() {
    const livePlayersUI = document.getElementById('livePlayersUI');
    const liveLeaderboardList = document.getElementById('liveLeaderboardList');
    if(!livePlayersUI || !liveLeaderboardList) return;

    let displayPlayers = Object.entries(players).map(([name, data]) => ({ 
        name, 
        score: data.score, 
        isCurrent: true 
    }));

    if (isSinglePlayer) {
        let spScores = JSON.parse(localStorage.getItem('melle_vr_sp_scores') || '[]');
        spScores.forEach(sp => {
            displayPlayers.push({ name: sp.name, score: sp.score, isCurrent: false });
        });
    }

    const sorted = displayPlayers.sort((a, b) => b.score - a.score);
    
    livePlayersUI.innerText = `Circuit: ${isSinglePlayer ? 'Isolated' : currentChannel} | Particles: ${sorted.length}`;
    liveLeaderboardList.innerHTML = sorted.map(p => {
        let style = p.isCurrent ? '' : 'color:#aaa; font-style:italic;';
        if (p.isCurrent && p.name === playerName) style = 'color:#00d2ff; font-weight:bold;';
        let displayName = (p.isCurrent && p.name === playerName) ? p.name + ' (You)' : p.name;
        
        return `<div class="live-player">
                    <span style="${style}">${displayName}</span>
                    <span style="color:#0f0">${p.score}</span>
                </div>`;
    }).join('');
}

// --- AUDIO & METADATA ---
async function fetchCurrentSong() {
    try {
        const ajaxUrl = window.location.origin + '/wp-admin/admin-ajax.php';
        const response = await fetch(ajaxUrl + '?action=qrq_get_meta');
        const resData = await response.json();

        if (resData.success && resData.data && resData.data.icestats && resData.data.icestats.source) {
            let sources = Array.isArray(resData.data.icestats.source) ? resData.data.icestats.source : [resData.data.icestats.source];
            let checkChannel = isSinglePlayer ? currentChannel.split('_iso_')[0] : currentChannel;
            let activeSource = sources.find(s => s && s.listenurl && s.listenurl.endsWith(checkChannel)) || sources[0];

            if (activeSource && activeSource.title) {
                const rawTitle = activeSource.title;
                const channelMeta = resData.data.custom_meta[checkChannel] || {};
                let trackKey = Object.keys(channelMeta).find(k => k === rawTitle || k === rawTitle + '.mp3' || channelMeta[k].title === rawTitle);
                let actualFilenameSlug = trackKey ? trackKey.replace(/\.mp3$/i, '') : wpSanitizeFileName(rawTitle);

                // NEW: Format the title for UI display
                let displayTitle = rawTitle;
                if (trackKey && channelMeta[trackKey]) {
                    let meta = channelMeta[trackKey];
                    displayTitle = meta.title || rawTitle;
                    if (meta.artist) displayTitle = meta.artist + ' - ' + displayTitle;
                }

                const inGameCollabBtn = document.getElementById('ingame-collab-btn');

                // NEW: Track changes using the raw title, but push displayTitle to the UI
                if (currentRawTitle && currentRawTitle !== rawTitle && !isMatchResetting) {
                    triggerEndOfMatch(displayTitle, actualFilenameSlug, rawTitle);
                } else if (!currentRawTitle) {
                    currentRawTitle = rawTitle;
                    currentSong = displayTitle;
                    currentTrackSlug = actualFilenameSlug;
                    const songUI = document.getElementById('songUI');
                    if(songUI) songUI.innerHTML = `<i class="fas fa-music me-2"></i> ${currentSong}`;
                    loadBeatmap(rawTitle);
                    
                    if (trackKey && inGameCollabBtn) {
                        let filenameSlug = trackKey.replace(/\.mp3$/i, '');
                        inGameCollabBtn.style.display = 'block';
                        let originalChannel = isSinglePlayer ? currentChannel.split('_iso_')[0] : currentChannel;
                        inGameCollabBtn.href = window.location.origin + '/radio-player/track/' + originalChannel + '/' + filenameSlug;
                    } else if (inGameCollabBtn) {
                        inGameCollabBtn.style.display = 'none';
                    }
                }
            }
        }
    } catch (err) {}
}

async function loadBeatmap(songTitle) {
    trackStartTime = performance.now() + BUFFER_TIME; 
    try {
        let filename = wpSanitizeFileName(songTitle).toLowerCase() + '.json';
        let beatmapUrl = config.beatmapDir + filename;
        
        let cachedMap = await getOfflineBeatmap(beatmapUrl);
        if (cachedMap) {
            currentBeatmap = cachedMap;
        } else {
            let res = await fetch(beatmapUrl);
            currentBeatmap = res.ok ? await res.json() : null;
        }
    } catch (e) { currentBeatmap = null; }
}

function triggerEndOfMatch(newSong, newTrackSlug, newRawTitle) {
    isMatchResetting = true;
    updateGlobalScore();
    
    let collectiveTotal = score.hits + score.caught;
    let matchScore = score.hits + score.caught; 
    
    for (let p in players) {
        if (p !== playerName) collectiveTotal += players[p].score;
    }

    // --- MATOMO TRACKING: Match Results ---
    if (window._paq) {
        window._paq.push(['trackEvent', 'VR Match', 'Track Complete', newTrackSlug, matchScore]);
        if (collectiveTotal >= 23) {
            window._paq.push(['trackEvent', 'VR Match', 'Threshold Reached', newTrackSlug, collectiveTotal]);
        }
    }
    // --------------------------------------

    const liveLeaderboardUI = document.getElementById('liveLeaderboardUI');
    const rewardUI = document.getElementById('rewardUI');
    const leaderboardUI = document.getElementById('leaderboardUI');
    const leaderboardList = document.getElementById('leaderboardList');
    const closeRewardBtn = document.getElementById('closeRewardBtn');

    if (collectiveTotal >= 23) {
        if(liveLeaderboardUI) liveLeaderboardUI.style.display = 'none';
        if(rewardUI) rewardUI.style.display = 'block';
        let originalChannel = isSinglePlayer ? currentChannel.split('_iso_')[0] : currentChannel;
        const targetUrl = `https://qrjournal.org/radio-player/track/${originalChannel}/${currentTrackSlug}`;
        attemptCrawl(targetUrl, 5, newSong, newTrackSlug, newRawTitle);
        
        if(closeRewardBtn) {
            closeRewardBtn.onclick = () => {
                if(rewardUI) rewardUI.style.display = 'none';
                if(liveLeaderboardUI) liveLeaderboardUI.style.display = 'block';
                resetMatchState(newSong, newTrackSlug, newRawTitle);
            };
        }
    } else {
        if(liveLeaderboardUI) liveLeaderboardUI.style.display = 'none'; 
        if(leaderboardUI) leaderboardUI.style.display = 'block';
        
        let displayPlayers = Object.entries(players).map(([name, data]) => ({ name, score: data.score, isCurrent: true }));

        if (isSinglePlayer) {
            let spScores = JSON.parse(localStorage.getItem('melle_vr_sp_scores') || '[]');
            spScores.forEach(sp => displayPlayers.push({ name: sp.name, score: sp.score, isCurrent: false }));
        }

        const sorted = displayPlayers.sort((a, b) => b.score - a.score);
        
        if(leaderboardList) {
            leaderboardList.innerHTML = `<div class="player-score" style="color:#00f2ff; border-bottom: 2px solid #00f2ff; margin-bottom: 15px;"><span><strong>CIRCUIT TOTAL</strong></span><span><strong>${collectiveTotal}</strong></span></div>`;
            leaderboardList.innerHTML += sorted.map(p => {
                let style = p.isCurrent ? '' : 'color:#aaa; font-style:italic;';
                if (p.isCurrent && p.name === playerName) style = 'color:#00d2ff; font-weight:bold;';
                let displayName = (p.isCurrent && p.name === playerName) ? `<strong>${p.name} (You)</strong>` : p.name;
                return `<div class="player-score"><span style="${style}">${displayName}</span><span style="color:#0f0">${p.score}</span></div>`;
            }).join('');
        }
        
        setTimeout(() => {
            if(leaderboardUI) leaderboardUI.style.display = 'none';
            if(liveLeaderboardUI) liveLeaderboardUI.style.display = 'block';
            resetMatchState(newSong, newTrackSlug, newRawTitle);
        }, 12000);
    }
    
    if (isSinglePlayer && matchScore > 0) {
        let spScores = JSON.parse(localStorage.getItem('melle_vr_sp_scores') || '[]');
        let dateStr = new Date().toLocaleDateString(undefined, {month: 'short', day: 'numeric'});
        spScores.push({ name: `${playerName} (${dateStr})`, score: matchScore });
        spScores.sort((a, b) => b.score - a.score);
        spScores = spScores.slice(0, 9); 
        localStorage.setItem('melle_vr_sp_scores', JSON.stringify(spScores));
    }
}

function resetMatchState(newSong, newTrackSlug, newRawTitle) {
    score = { caught: 0, hits: 0 };
    updateScoreUI();
    currentSong = newSong;
    currentTrackSlug = newTrackSlug; 
    if (newRawTitle) currentRawTitle = newRawTitle; // Keep raw sync updated
    
    const songUI = document.getElementById('songUI');
    if (!useGamepad && songUI) songUI.innerHTML = `<i class="fas fa-music me-2"></i> ${currentSong}`;
    
    loadBeatmap(newRawTitle || newSong); 
    if (vrRewardMesh) vrRewardMesh.visible = false;
    isMatchResetting = false;
}

function attemptCrawl(targetUrl, retriesLeft, newSong, newTrackSlug, newRawTitle) {
    const rewardDetails = document.getElementById('rewardDetails');
    if(!rewardDetails) return;

    rewardDetails.innerHTML = `<div class="text-center"><div class="spinner-border text-info mb-3" role="status"></div><p class="text-info fw-bold mb-2">Extracting Signals... (Attempt ${6 - retriesLeft}/5)</p></div>`;
    
    fetch(config.wpApiUrl.replace('/scores', '/crawl'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' }, 
        body: JSON.stringify({ url: targetUrl })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.keywords && data.keywords.length > 0) {
            let lexicon = JSON.parse(localStorage.getItem('iso_lexicon') || '{}');
            let newKeywords = data.keywords; 

            newKeywords.forEach(word => {
                if (!lexicon[word]) lexicon[word] = { weight: 20, context: `Intercepted Signal`, influence: 'essence' };
                else lexicon[word].weight += 15; 
            });

            localStorage.setItem('iso_lexicon', JSON.stringify(lexicon));
            let transferData = JSON.parse(localStorage.getItem('blueprint_transfer_data') || '{}');
            transferData.glossary = lexicon; 
            transferData.collectedRunes = window.MelleVRCollectedRunes;
            localStorage.setItem('blueprint_transfer_data', JSON.stringify(transferData));

            const weights = Object.values(lexicon).map(item => item.weight);
            const maxWeight = Math.max(...weights);
            const minWeight = Math.min(...weights);
            
            const cloudHtml = Object.entries(lexicon).map(([word, data]) => {
                const relativeWeight = (maxWeight - minWeight) > 0 ? (data.weight - minWeight) / (maxWeight - minWeight) : 0.5;
                const fontSize = 0.8 + (relativeWeight * 1.5); 
                const isNew = newKeywords.includes(word);
                return `<span style="font-size: ${fontSize}rem; color: ${isNew ? '#ffff00' : '#00f2ff'}; text-shadow: ${isNew ? '0 0 10px rgba(255, 255, 0, 0.8)' : 'none'}; font-weight: ${isNew ? 'bold' : 'normal'}; margin: 4px; display: inline-block; transition: all 0.3s; line-height: 1;">${word.toUpperCase()}</span>`;
            }).join(' ');

            let weightsString = Object.entries(lexicon).map(([w, d]) => `${w}:${d.weight}`).join(',');
            if (weightsString.length > 800) weightsString = weightsString.substring(0, 800) + '...';
            const qrImg = `https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=${encodeURIComponent(weightsString)}&color=00f2ff&bgcolor=000000`;
            
            rewardDetails.innerHTML = `
                <div class="text-center">
                    <img src="${qrImg}" alt="Weights QR" class="img-fluid border border-2 border-info rounded mb-4 pb-3 shadow-lg" style="max-width: 140px; box-shadow: 0 0 15px #00f2ff;">
                    <div class="alert alert-dark border-info text-light mb-4 p-3" style="max-height: 220px; overflow-y: auto; background: rgba(0,0,0,0.6);">
                        <strong class="d-block text-info mb-2 border-bottom border-info pb-1" style="font-size: 0.9rem;">CUMULATIVE NEURAL CLOUD</strong>
                        <div style="line-height: 1.2;">${cloudHtml}</div>
                    </div>
                    <div class="d-flex gap-2 justify-content-center mb-2">
                        <a href="/isochronic-core/" class="btn btn-outline-info w-50 fw-bold py-2 text-uppercase shadow-sm" style="font-size: 0.9rem;"><i class="fas fa-cogs me-1"></i> Core Module</a>
                        <a href="/blueprint/" class="btn btn-info w-50 fw-bold text-dark py-2 text-uppercase shadow-sm" style="font-size: 0.9rem; box-shadow: 0 0 10px #00f2ff !important;"><i class="fas fa-rocket me-1"></i> Blueprint</a>
                    </div>
                </div>
            `;

            if (vrRewardCtx && renderer && renderer.xr.isPresenting) {
                vrRewardCtx.clearRect(0, 0, 1024, 1024);
                vrRewardCtx.fillStyle = 'rgba(5, 5, 5, 0.95)';
                vrRewardCtx.fillRect(0, 0, 1024, 1024);
                vrRewardCtx.strokeStyle = '#00f2ff';
                vrRewardCtx.lineWidth = 10;
                vrRewardCtx.strokeRect(0, 0, 1024, 1024);
                vrRewardCtx.fillStyle = '#00f2ff';
                vrRewardCtx.font = 'bold 70px sans-serif';
                vrRewardCtx.textAlign = 'center';
                vrRewardCtx.fillText("TRANSMISSION DECODED", 512, 100);
                
                let qrImgObj = new Image();
                qrImgObj.crossOrigin = "Anonymous";
                qrImgObj.onload = () => { vrRewardCtx.drawImage(qrImgObj, 512 - 200, 150, 400, 400); vrRewardTexture.needsUpdate = true; };
                qrImgObj.src = qrImg; 

                vrRewardCtx.font = 'bold 35px sans-serif';
                let yOffset = 620;
                vrRewardCtx.fillStyle = '#ffffff';
                vrRewardCtx.fillText("CUMULATIVE NEURAL CLOUD", 512, yOffset);
                yOffset += 60;
                
                vrRewardCtx.font = '28px sans-serif';
                let wordsArr = Object.entries(lexicon).sort((a,b) => b[1].weight - a[1].weight);
                let col1 = wordsArr.slice(0, 10), col2 = wordsArr.slice(10, 20);
                
                vrRewardCtx.textAlign = 'right';
                col1.forEach((w, i) => {
                    vrRewardCtx.fillStyle = newKeywords.includes(w[0]) ? '#ffff00' : '#00f2ff';
                    vrRewardCtx.fillText(w[0].toUpperCase(), 480, yOffset + (i * 35));
                });
                vrRewardCtx.textAlign = 'left';
                col2.forEach((w, i) => {
                    vrRewardCtx.fillStyle = newKeywords.includes(w[0]) ? '#ffff00' : '#00f2ff';
                    vrRewardCtx.fillText(w[0].toUpperCase(), 540, yOffset + (i * 35));
                });
                
                vrRewardMesh.visible = true;
                vrRewardTexture.needsUpdate = true;
                
                let dir = new THREE.Vector3(0, 0, -1).applyQuaternion(camera.quaternion);
                dir.y = 0; dir.normalize();
                vrRewardMesh.position.copy(camera.position).add(dir.multiplyScalar(4));
                vrRewardMesh.position.y = camera.position.y;
                vrRewardMesh.lookAt(camera.position);
            }
        } else {
            handleCrawlRetry(targetUrl, retriesLeft, newSong, newTrackSlug, newRawTitle);
        }
    }).catch(err => { handleCrawlRetry(targetUrl, retriesLeft, newSong, newTrackSlug, newRawTitle); });
}

function handleCrawlRetry(targetUrl, retriesLeft, newSong, newTrackSlug, newRawTitle) {
    const rewardDetails = document.getElementById('rewardDetails');
    if (retriesLeft > 1) {
        setTimeout(() => attemptCrawl(targetUrl, retriesLeft - 1, newSong, newTrackSlug, newRawTitle), 1500);
    } else if(rewardDetails) {
        rewardDetails.innerHTML = `<div class="alert alert-danger text-center"><i class="fas fa-exclamation-triangle fs-3 d-block mb-2"></i><strong>Interference.</strong> Extraction failed.</div><button id="rescanBtn" class="btn btn-warning w-100 fw-bold py-2 shadow-sm"><i class="fas fa-sync me-1"></i> Rescan</button>`;
        document.getElementById('rescanBtn').onclick = () => attemptCrawl(targetUrl, 5, newSong, newTrackSlug, newRawTitle);
    }
}
// --- RENDERING & PHYSICS ---
function updateVRHUD() {
    if (!vrHudCtx) return;
    const myTotal = score.hits + score.caught;
    
    vrHudCtx.clearRect(0, 0, 512, 128);
    vrHudCtx.fillStyle = 'rgba(0, 0, 0, 0.6)';
    vrHudCtx.fillRect(0, 0, 512, 128);
    
    vrHudCtx.font = 'bold 35px sans-serif';
    vrHudCtx.fillStyle = '#00f2ff';
    vrHudCtx.textAlign = 'center';
    vrHudCtx.textBaseline = 'middle';
    vrHudCtx.fillText(`VOLTAGE: ${myTotal}  |  GLOBAL ᚱ: ${globalRunes}/100  |  CAPTURED: ${score.caught}`, 256, 64);
    
    if (vrHudTexture) vrHudTexture.needsUpdate = true;
}

function updateScoreUI() {
    const myTotal = score.hits + score.caught;
    const txtStatus = document.getElementById('txtStatus');
    if(txtStatus) {
        txtStatus.innerHTML = `VOLTAGE: <strong>${myTotal}</strong> (GLOBAL ᚱ: <span style="color:#ff0">${globalRunes}/100</span> | CAPTURED: <span style="color:#0f0">${score.caught}</span>)`;
    }
    if (vrHudCtx) updateVRHUD();
}

function disperseRune() {
    if (isMatchResetting || isShieldActive) return;
    if (window.MelleVRCollectedRunes && window.MelleVRCollectedRunes.length > 0) {
        const dispersedRune = window.MelleVRCollectedRunes.pop();
        syncRunesToCore();
        updateScoreUI(); 

        // --- MATOMO TRACKING: Rune Dispersion ---
        if (window._paq) {
            window._paq.push(['trackEvent', 'VR Interaction', 'Disperse Shield', dispersedRune]);
        }
        // ----------------------------------------
        
        activeRuneChar = dispersedRune;
        activeCatchphrase = "OBSERVING...";

        shieldRuneSprite.material.map = getRuneTexture(dispersedRune);
        
        isShieldActive = true;
        runeShield.visible = true;
        runeShield.material.opacity = 0.8;
        shieldRuneSprite.material.opacity = 1.0;
        runeShield.scale.set(0.1, 0.1, 0.1);
        
        if (renderer && renderer.xr && renderer.xr.isPresenting) {
            renderer.xr.getSession()?.inputSources[0]?.gamepad?.hapticActuators[0]?.pulse(0.8, 200);
        }
        
        fetchRuneCatchphrase(dispersedRune);
        updateGlobalScore(); 
    }
}

function spawnIncomingObject() {
    if (isMatchResetting) return;
    const colorIndex = Math.floor(Math.random() * RING_COUNT);
    const objectColor = new THREE.Color().setHSL(colorIndex / RING_COUNT, 0.8, 0.5);
    const obj = new THREE.Mesh(
        new THREE.IcosahedronGeometry(0.4, 0),
        new THREE.MeshBasicMaterial({ color: objectColor })
    );
    obj.userData.color = objectColor;

    const randomRune = FUTHARK_RUNES[Math.floor(Math.random() * FUTHARK_RUNES.length)];
    obj.userData.isCaught = false;
    obj.userData.rune = randomRune;
    
    const runeSprite = createRuneSprite(randomRune);
    obj.add(runeSprite);

    obj.position.set((Math.random() - 0.5) * 12, (Math.random() - 0.5) * 12, -TUNNEL_LENGTH);
    obj.userData.speed = 0.4 + (Math.random() * 0.4);
    scene.add(obj);
    incomingObjects.push(obj);
}

function initScene() {
    scene = new THREE.Scene();
    camera = new THREE.PerspectiveCamera(75, window.innerWidth/window.innerHeight, 0.1, 1000);
    camera.rotation.order = 'YXZ'; 
    scene.add(camera);
    
    const qualitySelect = document.getElementById('graphicsQuality');
    let quality = qualitySelect ? qualitySelect.value : 'auto';
    if (quality === 'auto') {
        const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
        quality = isMobile ? 'low' : 'high';
    }
    
    let pixelRatio = 1.5; let cubesPerRing = 16; let torusTubularSegments = 40; let torusRadialSegments = 8;
    RING_COUNT = 60; 
    
    if (quality === 'low') {
        pixelRatio = 1.0; RING_COUNT = 20; cubesPerRing = 8; torusTubularSegments = 30; torusRadialSegments = 6;
    } else if (quality === 'med') {
        pixelRatio = 1.5; RING_COUNT = 40; cubesPerRing = 12; torusTubularSegments = 60; torusRadialSegments = 10;
    } else {
        pixelRatio = Math.min(window.devicePixelRatio, 2.0); RING_COUNT = 60; cubesPerRing = 16; torusTubularSegments = 100; torusRadialSegments = 16;
    }
    
    renderer = new THREE.WebGLRenderer({ antialias: true });
    renderer.setPixelRatio(pixelRatio);
    renderer.setSize(window.innerWidth, window.innerHeight);
    renderer.xr.enabled = true;
    
    const threeContainer = document.getElementById('three-container');
    if(threeContainer) threeContainer.appendChild(renderer.domElement);
    
    // --- CONDITIONAL XR NOTIFICATION & MOBILE SPLIT-SCREEN BLOCK ---
    if ('xr' in navigator) {
        navigator.xr.isSessionSupported('immersive-vr').then((supported) => {
            // Check if the device is a standard mobile phone vs a dedicated VR Headset (Quest, Pico, etc.)
            const isMobilePhone = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
            const isDedicatedVR = /Oculus|Quest|VR|Vive|Pico/i.test(navigator.userAgent);

            // ONLY append the XR button if supported AND (it's not a phone OR it is a dedicated VR headset)
            if (supported && (!isMobilePhone || isDedicatedVR)) {
                const vrBtn = VRButton.createButton(renderer);
                
                // Override Three.js default "dock" styling to make it a clean, floating notification badge
                vrBtn.style.position = "absolute";
                vrBtn.style.bottom = "30px";
                vrBtn.style.left = "50%";
                vrBtn.style.transform = "translateX(-50%)";
                vrBtn.style.width = "auto";
                vrBtn.style.minWidth = "200px";
                vrBtn.style.padding = "12px 24px";
                vrBtn.style.borderRadius = "50px";
                vrBtn.style.border = "1px solid #00f2ff";
                vrBtn.style.background = "rgba(5, 5, 5, 0.85)";
                vrBtn.style.color = "#00f2ff";
                vrBtn.style.fontWeight = "bold";
                vrBtn.style.letterSpacing = "1px";
                vrBtn.style.boxShadow = "0 0 20px rgba(0, 242, 255, 0.4)";
                vrBtn.style.zIndex = "999999";
                
                vrBtn.addEventListener('click', () => { 
                    vrBtn.style.pointerEvents = 'none'; 
                    setTimeout(() => { vrBtn.style.pointerEvents = 'auto'; }, 2000); 
                });
                
                const wrapper = document.getElementById('melle-vr-wrapper');
                if (wrapper) wrapper.appendChild(vrBtn);
            }
        });
    }
    // -------------------------------------------------------------

    vrHudCanvas = document.createElement('canvas'); vrHudCanvas.width = 512; vrHudCanvas.height = 128;
    vrHudCtx = vrHudCanvas.getContext('2d'); vrHudTexture = new THREE.CanvasTexture(vrHudCanvas);
    vrHudMesh = new THREE.Mesh(new THREE.PlaneGeometry(0.5, 0.125), new THREE.MeshBasicMaterial({ map: vrHudTexture, transparent: true, depthTest: false }));
    scene.add(vrHudMesh);

    vrRewardCanvas = document.createElement('canvas'); vrRewardCanvas.width = 1024; vrRewardCanvas.height = 1024;
    vrRewardCtx = vrRewardCanvas.getContext('2d'); vrRewardTexture = new THREE.CanvasTexture(vrRewardCanvas);
    vrRewardMesh = new THREE.Mesh(new THREE.PlaneGeometry(3, 3), new THREE.MeshBasicMaterial({ map: vrRewardTexture, transparent: true }));
    vrRewardMesh.visible = false;
    scene.add(vrRewardMesh);

    catcherRing = new THREE.Mesh(new THREE.TorusGeometry(0.6, 0.05, torusRadialSegments, torusTubularSegments), new THREE.MeshBasicMaterial({ color: 0x00ffff }));
    catcherRing.position.z = aimOffset.z;
    scene.add(catcherRing);

    const shieldGeo = new THREE.CircleGeometry(0.55, 32);
    const shieldMat = new THREE.MeshBasicMaterial({ color: 0xffaa00, transparent: true, opacity: 0, side: THREE.DoubleSide });
    runeShield = new THREE.Mesh(shieldGeo, shieldMat);
    runeShield.position.z = 0.01; runeShield.visible = false;
    catcherRing.add(runeShield);

    shieldRuneSprite = new THREE.Sprite(new THREE.SpriteMaterial({ transparent: true, opacity: 0, depthTest: false }));
    shieldRuneSprite.scale.set(0.8, 0.8, 1);
    runeShield.add(shieldRuneSprite);
    
    catchphraseSprite = new THREE.Sprite(new THREE.SpriteMaterial({ transparent: true, opacity: 0, depthTest: false }));
    catchphraseSprite.position.y = 1.5; catchphraseSprite.scale.set(4, 1, 1); catchphraseSprite.userData.fadeDelay = 0;
    catcherRing.add(catchphraseSprite);

    const remoteShieldGeo = new THREE.CircleGeometry(0.45, 32);
    const remoteShieldMat = new THREE.MeshBasicMaterial({ color: 0xff00ff, transparent: true, opacity: 0, side: THREE.DoubleSide });
    remoteRuneShield = new THREE.Mesh(remoteShieldGeo, remoteShieldMat);
    remoteRuneShield.position.z = 0.02; remoteRuneShield.visible = false;
    catcherRing.add(remoteRuneShield);

    remoteShieldRuneSprite = new THREE.Sprite(new THREE.SpriteMaterial({ transparent: true, opacity: 0, depthTest: false }));
    remoteShieldRuneSprite.scale.set(0.7, 0.7, 1);
    remoteRuneShield.add(remoteShieldRuneSprite);

    remoteCatchphraseSprite = new THREE.Sprite(new THREE.SpriteMaterial({ transparent: true, opacity: 0, depthTest: false }));
    remoteCatchphraseSprite.position.y = 2.5; remoteCatchphraseSprite.scale.set(4, 1, 1); remoteCatchphraseSprite.userData.fadeDelay = 0;
    catcherRing.add(remoteCatchphraseSprite);

    const controllerModelFactory = new XRControllerModelFactory();
    
    controller1 = renderer.xr.getController(0); 
    controller1.addEventListener('selectstart', () => { activeController = controller1; disperseRune(); }); 
    controller1.addEventListener('squeezestart', () => { activeController = controller1; isGrippingVR = true; }); 
    controller1.addEventListener('squeezeend', () => { isGrippingVR = false; }); 
    scene.add(controller1);
    const grip1 = renderer.xr.getControllerGrip(0);
    grip1.add(controllerModelFactory.createControllerModel(grip1));
    scene.add(grip1);

    controller2 = renderer.xr.getController(1);
    controller2.addEventListener('selectstart', () => { activeController = controller2; disperseRune(); });
    controller2.addEventListener('squeezestart', () => { activeController = controller2; isGrippingVR = true; });
    controller2.addEventListener('squeezeend', () => { isGrippingVR = false; }); 
    scene.add(controller2);
    const grip2 = renderer.xr.getControllerGrip(1);
    grip2.add(controllerModelFactory.createControllerModel(grip2));
    scene.add(grip2);

    activeController = controller1;

    const cubeGeo = new THREE.BoxGeometry(0.3, 0.3, 0.3);
    for (let i = 0; i < RING_COUNT; i++) {
        const group = new THREE.Group();
        const mat = new THREE.MeshBasicMaterial({color: new THREE.Color().setHSL(i/RING_COUNT, 0.8, 0.5)});
        
        for (let j = 0; j < cubesPerRing; j++) {
            const angle = (j/cubesPerRing) * Math.PI * 2;
            const cube = new THREE.Mesh(cubeGeo, mat);
            cube.position.set(Math.cos(angle)*7, Math.sin(angle)*7, 0);
            group.add(cube);
        }
        group.position.z = -(i * (TUNNEL_LENGTH/RING_COUNT));
        scene.add(group);
        tunnelRings.push(group);
    }
    
    updateVRHUD();
    renderer.setAnimationLoop(render);
}

function render() {
    if (analyser) analyser.getByteFrequencyData(dataArray);
    
    applyContinuousKeyboardMovement();
    applyGamepadMovement(); 
    
    if (useKeyboard && !statusInput.keyboard) {
        statusInput.keyboard = true;
        const statKey = document.getElementById('status-keyboard');
        if(statKey) statKey.innerHTML = `<span><i class="fas fa-keyboard"></i> Keyboard</span> <span class="badge bg-success">Active</span>`;
    }

    if (renderer.xr.isPresenting) {
        let session = renderer.xr.getSession();

        if (session && session.inputSources && session.inputSources.length > 0) {
            session.inputSources.forEach(source => {
                if (source.gamepad) {
                    if (isGrippingVR && activeController && source.handedness === (activeController === controller1 ? 'left' : 'right')) {
                        let zAxis = 0;
                        if (source.gamepad.axes.length >= 4) zAxis = source.gamepad.axes[3];
                        else if (source.gamepad.axes.length >= 2) zAxis = source.gamepad.axes[1];
                        
                        if (zAxis !== undefined && !isNaN(zAxis) && Math.abs(zAxis) > 0.1) {
                            vrRingZOffset += zAxis * 0.3; 
                            if (isNaN(vrRingZOffset)) vrRingZOffset = 0;
                            vrRingZOffset = Math.max(-TUNNEL_LENGTH + 10, Math.min(0, vrRingZOffset)); 
                        }
                    }
                }
            });
        }

        let camEuler = new THREE.Euler().setFromQuaternion(camera.quaternion, 'YXZ');
        let bodyQuat = new THREE.Quaternion().setFromAxisAngle(new THREE.Vector3(0, 1, 0), camEuler.y);
        let hudOffset = new THREE.Vector3(0, -0.6, -0.4).applyQuaternion(bodyQuat);
        vrHudMesh.position.copy(camera.position).add(hudOffset);
        
        let tiltQuat = new THREE.Quaternion().setFromAxisAngle(new THREE.Vector3(1, 0, 0), -Math.PI / 4);
        vrHudMesh.quaternion.copy(bodyQuat).multiply(tiltQuat);

        if (activeController) {
            let dir = new THREE.Vector3(0, 0, -1).applyQuaternion(activeController.quaternion);
            let dist = 1.0; 
            if (!isNaN(vrRingZOffset)) dist += Math.abs(vrRingZOffset); 
            
            targetRingPos.copy(activeController.position).add(dir.multiplyScalar(dist));
            targetRingQuat.copy(activeController.quaternion);
            
            if (!isNaN(targetRingPos.x) && !isNaN(targetRingPos.y) && !isNaN(targetRingPos.z)) {
                catcherRing.position.lerp(targetRingPos, 0.3);
                catcherRing.quaternion.slerp(targetRingQuat, 0.3);
            }
        }
        
    } else {
        if (useKeyboard || useGamepad) {
            if (!isNaN(aimOffset.x) && !isNaN(aimOffset.y) && !isNaN(aimOffset.z) && !isNaN(aimOffset.rotY)) {
                catcherRing.position.x += (aimOffset.x - catcherRing.position.x) * 0.1;
                catcherRing.position.y += (aimOffset.y - catcherRing.position.y) * 0.1;
                catcherRing.position.z += (aimOffset.z - catcherRing.position.z) * 0.1;
                catcherRing.rotation.y += (aimOffset.rotY - catcherRing.rotation.y) * 0.1;
            }
        } else {
            if (!isNaN(aimOffset.x) && !isNaN(aimOffset.y) && !isNaN(aimOffset.z)) {
                catcherRing.position.x += (aimOffset.x * 5 - catcherRing.position.x) * 0.1;
                catcherRing.position.y += (aimOffset.y * 5 - catcherRing.position.y) * 0.1;
                catcherRing.position.z += (aimOffset.z - catcherRing.position.z) * 0.1;
            }
        }
    }

    for (let p in playerAvatars) {
        let avatar = playerAvatars[p];
        avatar.mesh.position.lerp(avatar.targetPos, 0.05); 
        avatar.mesh.rotation.y += (avatar.targetRotY - avatar.mesh.rotation.y) * 0.05;

        if (avatar.isRemoteShieldActive) {
            avatar.shield.scale.lerp(new THREE.Vector3(1, 1, 1), 0.2);
        } else {
            if (avatar.shield.material.opacity > 0) {
                avatar.shield.material.opacity -= 0.02;
                avatar.runeSprite.material.opacity -= 0.02;
                avatar.phraseSprite.material.opacity -= 0.01;
            }
        }
    }

    if (isRemoteIncomingActive) {
        remoteRuneShield.scale.lerp(new THREE.Vector3(1, 1, 1), 0.2);
    } else if (remoteRuneShield && remoteRuneShield.material.opacity > 0) {
        remoteRuneShield.material.opacity -= 0.02;
        remoteShieldRuneSprite.material.opacity -= 0.02;
        if (remoteRuneShield.material.opacity <= 0) remoteRuneShield.visible = false;
    }

    if (remoteCatchphraseSprite && remoteCatchphraseSprite.material.opacity > 0) {
        if (remoteCatchphraseSprite.userData.fadeDelay > 0) {
            remoteCatchphraseSprite.userData.fadeDelay--;
        } else {
            remoteCatchphraseSprite.material.opacity -= 0.01;
        }
    }

    tunnelRings.forEach((ring, i) => {
        ring.position.z += SPEED;
        if (ring.position.z > 5) ring.position.z = -TUNNEL_LENGTH;
        if (dataArray && !isMatchResetting) {
            const scale = 1 + (dataArray[i % 128] / 255) * 5;
            ring.children.forEach(c => c.scale.set(1, 1, scale));
        }
    });

    if (!isMatchResetting) {
        const now = performance.now();
        const elapsedSinceTrackStart = now - trackStartTime;

        if (elapsedSinceTrackStart > 0) { 
            if (currentBeatmap && currentBeatmap.length > 0) {
                let elapsedSeconds = elapsedSinceTrackStart / 1000;
                while(currentBeatmap.length > 0 && currentBeatmap[0].time <= elapsedSeconds) {
                    spawnIncomingObject();
                    currentBeatmap.shift();
                }
            } else if (dataArray) {
                let bassPower = dataArray[2] + dataArray[3] + dataArray[4]; 
                const spawnCooldown = (RING_COUNT < 30) ? 450 : 300;
                if (bassPower > 600 && now - lastSpawnTime > spawnCooldown) { 
                    spawnIncomingObject();
                    lastSpawnTime = now;
                } else if (Math.random() < 0.01) { 
                    spawnIncomingObject();
                }
            }
        }
    }

    if (isShieldActive) {
        runeShield.scale.lerp(new THREE.Vector3(1, 1, 1), 0.2);
        runeShield.material.opacity -= 0.02;
        shieldRuneSprite.material.opacity -= 0.02;

        for (let j = incomingObjects.length - 1; j >= 0; j--) {
            const obj = incomingObjects[j];
            if (Math.abs(obj.position.z - catcherRing.position.z) < 1.0) {
                const dist = obj.position.distanceTo(catcherRing.position);
                if (dist < 2.0) {
                    score.hits++;
                    catcherRing.material.color.copy(obj.userData.color);
                    disposeObject(obj); 
                    incomingObjects.splice(j, 1);
                    updateScoreUI();
                }
            }
        }

        if (runeShield.material.opacity <= 0) {
            isShieldActive = false;
            runeShield.visible = false;
            updateGlobalScore(); 
        }
    }
    
    if (catchphraseSprite && catchphraseSprite.material.opacity > 0) {
        if (catchphraseSprite.userData.fadeDelay > 0) {
            catchphraseSprite.userData.fadeDelay--;
        } else {
            catchphraseSprite.material.opacity -= 0.01;
        }
    }

    for (let i = incomingObjects.length - 1; i >= 0; i--) {
        const obj = incomingObjects[i];
        obj.position.z += obj.userData.speed;

        if (Math.abs(obj.position.z - catcherRing.position.z) < 0.5) {
            const dist = obj.position.distanceTo(catcherRing.position);
            if (dist < 0.7) { 
                score.caught++;
                catcherRing.material.color.copy(obj.userData.color);
                if (renderer.xr.isPresenting) renderer.xr.getSession()?.inputSources[0]?.gamepad?.hapticActuators[0]?.pulse(0.8, 150);

                if (!obj.userData.isCaught) {
                    obj.userData.isCaught = true;
                    window.MelleVRCollectedRunes.push(obj.userData.rune);
                    syncRunesToCore(); 
                    updateScoreUI(); 

                    // --- MATOMO TRACKING: Individual Capture ---
                    if (window._paq) {
                        window._paq.push(['trackEvent', 'VR Interaction', 'Catch Rune', obj.userData.rune]);
                    }
                    // -------------------------------------------
                }

                disposeObject(obj); 
                incomingObjects.splice(i, 1);
                continue;
            }
        }

        if (obj.position.z > 5) {
            disposeObject(obj); 
            incomingObjects.splice(i, 1);
        }
    }

    renderer.render(scene, camera);
}

// --- DOM EVENT BINDINGS & CLEANUP ---
window.addEventListener('keydown', (e) => {
    if ((e.key === 'Escape' || e.key === 'Esc') && isPlayerActive) {
        e.preventDefault();
        const extractBtn = document.getElementById('close-vr-btn');
        if (extractBtn) {
            extractBtn.click();
        } else {
            removePlayerFromServer().then(() => window.location.reload());
        }
        return;
    }

    if (['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight', ' '].includes(e.key)) {
        if (e.target.tagName !== 'INPUT' && e.target.tagName !== 'TEXTAREA') {
            e.preventDefault();
        }
    }

    if (e.key === 'Tab') {
        e.preventDefault(); 
        if (camera && camera.fov !== 30) {
            camera.fov = 30; 
            camera.updateProjectionMatrix();
        }
    }
    
    useKeyboard = true; 
    if (e.key === ' ' && e.target.tagName !== 'INPUT' && e.target.tagName !== 'TEXTAREA') {
        disperseRune();
    }
    keys[e.key] = true;
});

window.addEventListener('keyup', (e) => { 
    if (e.key === 'Tab') {
        e.preventDefault();
        if (camera && camera.fov !== 75) {
            camera.fov = 75; 
            camera.updateProjectionMatrix();
        }
    }
    keys[e.key] = false; 
});

window.addEventListener('mousedown', (e) => {
    if (scene && e.target.tagName === 'CANVAS') {
        isMouseDown = true;
        touchStartX = e.clientX;
        touchStartY = e.clientY;
        
        const currentTime = new Date().getTime();
        mouseTapTimes.push(currentTime);
        if (mouseTapTimes.length > 2) mouseTapTimes.shift();
        
        if (mouseTapTimes.length === 2 && (mouseTapTimes[1] - mouseTapTimes[0] < 300)) {
            aimOffset = { x: 0, y: 0, z: -3, rotY: 0 };
            baseBeta = null; baseGamma = null;
            if(camera) { camera.rotation.set(0,0,0); }
            mouseTapTimes = [];
        }
    }
});

window.addEventListener('mousemove', (e) => {
    if (isMouseDown && scene && e.target.tagName === 'CANVAS') {
        let dx = e.clientX - touchStartX;
        let dy = e.clientY - touchStartY;
        
        if (!isNaN(dx) && !isNaN(dy)) {
            aimOffset.x += dx * 0.01;
            aimOffset.y -= dy * 0.01; 
        }
        
        touchStartX = e.clientX;
        touchStartY = e.clientY;
        useKeyboard = true; 
    }
});

window.addEventListener('mouseup', () => { isMouseDown = false; });

window.addEventListener('touchstart', (e) => {
    if (scene && e.target.tagName === 'CANVAS') {
        e.preventDefault();
    }

    if (e.touches.length === 1) {
        touchStartX = e.touches[0].clientX;
        touchStartY = e.touches[0].clientY;
        
        const currentTime = new Date().getTime();
        
        tapTimes.push(currentTime);
        if (tapTimes.length > 3) tapTimes.shift();
        
        if (tapTimes.length === 3 && (tapTimes[2] - tapTimes[0] < 800)) {
            disperseRune();
            tapTimes = []; 
        } else if (currentTime - lastTapTime < 300 && currentTime - lastTapTime > 0) {
            aimOffset = { x: 0, y: 0, z: -3, rotY: 0 };
            baseBeta = null; baseGamma = null;
            if(camera) { camera.rotation.set(0,0,0); }
        } else if (e.target.tagName !== 'BUTTON' && e.target.tagName !== 'INPUT' && e.target.tagName !== 'SELECT' && scene) {
            useKeyboard = true; 
            if (!statusInput.touch) {
                statusInput.touch = true;
                const statTouch = document.getElementById('status-touch');
                if(statTouch) statTouch.innerHTML = `<span><i class="fas fa-hand-pointer"></i> Touch/Drag</span> <span class="badge bg-success">Active</span>`;
            }
        }
        lastTapTime = currentTime;
    } else if (e.touches.length === 2) {
        let dx = e.touches[0].clientX - e.touches[1].clientX;
        let dy = e.touches[0].clientY - e.touches[1].clientY;
        touchStartDist = Math.sqrt(dx*dx + dy*dy);
    }
}, {passive: false});

window.addEventListener('touchmove', (e) => {
    if (scene && e.target.tagName === 'CANVAS') {
        e.preventDefault();
    }

    if (e.touches.length === 1 && scene) {
        let dx = e.touches[0].clientX - touchStartX;
        let dy = e.touches[0].clientY - touchStartY;
        
        if (!isNaN(dx) && !isNaN(dy)) {
            aimOffset.x += dx * 0.01;
            aimOffset.y -= dy * 0.01; 
        }
        
        touchStartX = e.touches[0].clientX;
        touchStartY = e.touches[0].clientY;
        useKeyboard = true; 
    } else if (e.touches.length === 2 && scene) {
        let dx = e.touches[0].clientX - e.touches[1].clientX;
        let dy = e.touches[0].clientY - e.touches[1].clientY;
        let currentDist = Math.sqrt(dx*dx + dy*dy);
        
        let distDiff = currentDist - touchStartDist;
        if (!isNaN(distDiff)) {
            aimOffset.z += distDiff * 0.02; 
            aimOffset.z = Math.max(-TUNNEL_LENGTH + 5, Math.min(-1, aimOffset.z));
        }
        
        touchStartDist = currentDist;
        useKeyboard = true;
    }
}, {passive: false});

window.addEventListener('resize', () => {
    if (camera) {
        camera.aspect = window.innerWidth / window.innerHeight;
        camera.updateProjectionMatrix();
        renderer.setSize(window.innerWidth, window.innerHeight);
    }
});

function handleTilt(e) {
    if (!statusInput.tilt && e.gamma !== null) {
        statusInput.tilt = true;
        const statTilt = document.getElementById('status-tilt');
        if(statTilt) statTilt.innerHTML = `<span><i class="fas fa-mobile-alt"></i> Tilt/Gyro</span> <span class="badge bg-success">Active</span>`;
    }
    
    if (useKeyboard || useGamepad || (renderer && renderer.xr && renderer.xr.isPresenting)) return; 
    
    if (e.gamma !== null && e.beta !== null) {
        let gamma = e.gamma;
        let beta = e.beta;
        
        if (window.orientation === 90) { gamma = e.beta; beta = -e.gamma; }
        else if (window.orientation === -90) { gamma = -e.beta; beta = e.gamma; }
        
        if (!isNaN(gamma) && !isNaN(beta)) {
            aimOffset.x = gamma / 25; 
            aimOffset.y = (beta - 45) / 25;
        }
    }
}

function applyContinuousKeyboardMovement() {
    if (!useKeyboard) return;
    const moveSpeed = 0.08, fastRotSpeed = 0.03, slowRotSpeed = 0.008; 
    
    if (keys['w'] || keys['W']) aimOffset.y += moveSpeed;
    if (keys['s'] || keys['S']) aimOffset.y -= moveSpeed;
    if (keys['a'] || keys['A']) aimOffset.x -= moveSpeed;
    if (keys['d'] || keys['D']) aimOffset.x += moveSpeed;
    if (keys['ArrowUp']) aimOffset.z -= moveSpeed;
    if (keys['ArrowDown']) aimOffset.z += moveSpeed;
    if (keys['ArrowLeft']) aimOffset.rotY += fastRotSpeed;
    if (keys['ArrowRight']) aimOffset.rotY -= fastRotSpeed;
    if (keys['q'] || keys['Q']) aimOffset.rotY += slowRotSpeed;
    if (keys['e'] || keys['E']) aimOffset.rotY -= slowRotSpeed;
    
    if (isNaN(aimOffset.z)) aimOffset.z = -3;
    aimOffset.z = Math.max(-TUNNEL_LENGTH + 5, Math.min(-1, aimOffset.z));
}

function applyGamepadMovement() {
    const getGamepads = navigator.getGamepads || navigator.webkitGetGamepads;
    if (!getGamepads) return;
    const gamepads = getGamepads.call(navigator);
    
    for (let i = 0; i < gamepads.length; i++) {
        const gp = gamepads[i];
        if (!gp) continue;

        useGamepad = true; 
        
        if (!hasNotifiedGamepad) {
            let controllerName = gp.id ? gp.id.substring(0, 20) : "Gamepad";
            const songUI = document.getElementById('songUI');
            if(songUI) songUI.innerHTML = `<i class="fas fa-gamepad me-2"></i> ${controllerName} Connected!`;
            setTimeout(() => { if (!isMatchResetting && songUI) songUI.innerHTML = `<i class="fas fa-music me-2"></i> ${currentSong}`; }, 3500);
            hasNotifiedGamepad = true;
        }

        const moveSpeed = 0.15, fastRotSpeed = 0.04, zSpeed = 0.1;
        const deadzone = 0.15; 

        if (gp.axes.length > 0 && gp.axes[0] !== undefined && !isNaN(gp.axes[0]) && Math.abs(gp.axes[0]) > deadzone) aimOffset.x += gp.axes[0] * moveSpeed;
        if (gp.axes.length > 1 && gp.axes[1] !== undefined && !isNaN(gp.axes[1]) && Math.abs(gp.axes[1]) > deadzone) aimOffset.y -= gp.axes[1] * moveSpeed;
        if (gp.axes.length > 2 && gp.axes[2] !== undefined && !isNaN(gp.axes[2]) && Math.abs(gp.axes[2]) > deadzone) aimOffset.rotY -= gp.axes[2] * fastRotSpeed;
        if (gp.axes.length > 3 && gp.axes[3] !== undefined && !isNaN(gp.axes[3]) && Math.abs(gp.axes[3]) > deadzone) aimOffset.z += gp.axes[3] * zSpeed;
        
        if (isNaN(aimOffset.x)) aimOffset.x = 0;
        if (isNaN(aimOffset.y)) aimOffset.y = 0;
        if (isNaN(aimOffset.z)) aimOffset.z = -3;
        if (isNaN(aimOffset.rotY)) aimOffset.rotY = 0;

        aimOffset.z = Math.max(-TUNNEL_LENGTH + 5, Math.min(-1, aimOffset.z));

        const isShooting = (
            (gp.buttons[0] && gp.buttons[0].pressed) || 
            (gp.buttons[5] && gp.buttons[5].pressed) || 
            (gp.buttons[6] && gp.buttons[6].pressed) || 
            (gp.buttons[7] && gp.buttons[7].pressed)
        );
        
        if (isShooting && !gamepadDispersePressed) {
            disperseRune();
            gamepadDispersePressed = true;
        } else if (!isShooting) {
            gamepadDispersePressed = false;
        }
        
        break; 
    }
}

window.addEventListener('DOMContentLoaded', () => {
    const closeVrBtn = document.getElementById('close-vr-btn');
    if (closeVrBtn) {
        closeVrBtn.addEventListener('click', async (e) => { 
            e.preventDefault();
            if (closeVrBtn.disabled) return;
            closeVrBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Extracting...';
            closeVrBtn.disabled = true;
            await removePlayerFromServer(); 
            window.location.reload(); 
        });
    }

    // --- HUD TOGGLE LOGIC ---
    const toggleHudBtn = document.getElementById('toggle-hud-btn');
    if (toggleHudBtn) {
        toggleHudBtn.addEventListener('click', () => {
            document.body.classList.toggle('hud-hidden');
            if (document.body.classList.contains('hud-hidden')) {
                toggleHudBtn.innerHTML = '<i class="fas fa-eye me-1"></i> HUD';
            } else {
                toggleHudBtn.innerHTML = '<i class="fas fa-eye-slash me-1"></i> HUD';
            }
        });
    }

    // --- MUTE BUTTON LOGIC ---
    const muteBtn = document.getElementById('mute-audio-btn');
    if (muteBtn) {
        muteBtn.addEventListener('click', () => {
            if (audioSource) {
                audioSource.muted = !audioSource.muted;
                if (audioSource.muted) {
                    muteBtn.innerHTML = '<i class="fas fa-volume-mute me-1"></i> Muted';
                    muteBtn.classList.replace('btn-outline-warning', 'btn-warning');
                    muteBtn.classList.add('text-dark');
                } else {
                    muteBtn.innerHTML = '<i class="fas fa-volume-up me-1"></i> Mute';
                    muteBtn.classList.replace('btn-warning', 'btn-outline-warning');
                    muteBtn.classList.remove('text-dark');
                }
            }
        });
    }
});

window.onbeforeunload = removePlayerFromServer;

		