/**
 * config.js
 * Central configuration, constants, and shared mutable state for Quantum Telegraph.
 * This file is an ES6 module and must be loaded with `type="module"`.
 */

// 1. CORE SYSTEM CONFIGURATION
// Pulls from WordPress localized script data (window.MelleVRConfig) with safe fallbacks.
const localizedConfig = window.MelleVRConfig || {};

export const config = {
    wpApiUrl: localizedConfig.wpApiUrl || "/wp-json/melle-vr/v1/scores",
    wpValidateUrl: localizedConfig.wpValidateUrl || "/wp-json/melle-vr/v1/validate-username",
    beatmapDir: localizedConfig.beatmapDir || "/wp-content/uploads/beatmaps/",
    assetDir: localizedConfig.assetDir || "/wp-content/uploads/qrq_radio_assets/",
    icecastBaseUrl: localizedConfig.icecastBaseUrl || "https://qrjournal.org/icecast/",
    pluginUrl: localizedConfig.pluginUrl || "/wp-content/plugins/melle-vr/",
    directChannel: localizedConfig.directChannel || "",
    directTrack: localizedConfig.directTrack || "",
    directMp3: localizedConfig.directMp3 || "",
    homeUrl: localizedConfig.homeUrl || "/community" // [NEW] Added Home URL
};

// 2. SHARED MUTABLE STATE
// This object is imported by other modules. Changes to its properties are instantly
// visible across the entire application, as it's a shared reference.
export const state = {
    // Player & Network
    playerName: "",
    isPlayerActive: false,
    isSinglePlayer: false,
    currentChannel: "melle",
    players: {}, 
    globalRunes: 0,
    hasTriggeredEtheric: false,
    
    // Audio & Match
    currentSong: "",
    currentTrackSlug: "",
    isMatchResetting: false,
    currentBeatmap: null,
    trackStartTime: 0,
    lastSpawnTime: 0,
    
    // Gameplay & Scoring
    score: { caught: 0, hits: 0 },
    isShieldActive: false,
    activeRuneChar: "",
    activeCatchphrase: "",
    
    // Engine Parameters
    RING_COUNT: 60,
    TUNNEL_LENGTH: 100,
    SPEED: 0.15,
    
    // Controls & Diagnostics
    aimOffset: { x: 0, y: 0, z: -3, rotY: 0 },
    statusInput: { keyboard: false, tilt: false, gamepad: false, touch: false, gamepadName: "" }
};

// 3. CONSTANTS
export const FUTHARK_RUNES = ['ᚨ','ᛒ','ᚲ','ᛞ','ᛖ','ᚠ','ᚷ','ᚺ','ᛁ','ᛃ','ᛚ','ᛗ','ᚾ','ᛟ','ᛈ','ᚱ','ᛋ','ᛏ','ᚢ','ᚹ','ᛇ','ᛉ'];
export const BUFFER_TIME = 8000; // Time in milliseconds for audio buffering and pre-loading

// 4. GLOBAL BINDINGS (Legacy Support)
// For compatibility with any external or non-module scripts that might rely on this global variable.
// In a pure module-based system, this would ideally be removed or handled via events.
if (typeof window.MelleVRCollectedRunes === 'undefined') {
    window.MelleVRCollectedRunes = [];
}
