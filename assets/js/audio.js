/**
 * audio.js
 * Handles the Web Audio API, AnalyserNode, GainNode (Muting), and Offline PWA Caching
 * for Quantum Telegraph.
 */

import { config, state, BUFFER_TIME } from './config.js';

export let audioContext = null;
export let audioSource = null;
export let analyser = null;
export let dataArray = null;
export let masterGainNode = null;

const CACHE_NAME = 'qrq-offline-tracks-v1';

/**
 * Initializes the Audio Context, Analyser, and the GainNode for muting.
 * @param {HTMLAudioElement} audioElement - The <audio> element to source from.
 */
export async function initAudio(audioElement) {
    if (!audioContext) {
        audioContext = new (window.AudioContext || window.webkitAudioContext)();
    }

    if (audioContext.state === 'suspended') {
        await audioContext.resume();
    }

    audioSource = audioContext.createMediaElementSource(audioElement);
    analyser = audioContext.createAnalyser();
    
    // [FIX] GainNode inserted to allow UI muting without starving the visualizer
    masterGainNode = audioContext.createGain();
    window.masterGainNode = masterGainNode; // Export globally for UI toggle
    
    audioSource.connect(analyser);
    analyser.connect(masterGainNode);
    masterGainNode.connect(audioContext.destination);
    
    analyser.fftSize = 256;
    dataArray = new Uint8Array(analyser.frequencyBinCount);
    
    return { audioContext, analyser, dataArray };
}

/**
 * Updates the frequency data array for the current frame.
 * @returns {Uint8Array} The current frequency data.
 */
export function getAudioData() {
    if (analyser && dataArray) {
        analyser.getByteFrequencyData(dataArray);
    }
    return dataArray;
}

/**
 * PWA Offline Cache: Downloads a track and its beatmap for offline mode.
 */
export async function downloadTrackForOffline(mp3Url, beatmapUrl, btnElement) {
    if (!window.caches) {
        alert("Your browser does not support offline caching.");
        return;
    }
    
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
}

/**
 * PWA Offline Cache: Retrieves a track from the cache if available.
 */
export async function getOfflineAudioSrc(trackUrl) {
    if (!window.caches) return trackUrl;
    
    try {
        const cache = await caches.open(CACHE_NAME);
        const response = await cache.match(trackUrl);
        if (response) {
            const blob = await response.blob();
            return URL.createObjectURL(blob);
        }
    } catch (err) {
        console.error("Cache read error:", err);
    }
    
    return trackUrl;
}

/**
 * PWA Offline Cache: Retrieves a JSON beatmap from the cache if available.
 */
export async function getOfflineBeatmap(beatmapUrl) {
    if (!window.caches) return null;
    
    try {
        const cache = await caches.open(CACHE_NAME);
        const response = await cache.match(beatmapUrl);
        if (response) {
            return await response.json();
        }
    } catch (err) {
        console.error("Cache read error:", err);
    }
    
    return null;
}

/**
 * Sanitizes a filename to match WordPress's sanitize_title format for beatmaps.
 */
function sanitizeBeatmapName(title) {
    let slug = title;
    slug = slug.replace(/\s+/g, '-');
    slug = slug.replace(/[?\[\]/\\=<>:;,'"\&$#*()|~`!{}%+\^]/g, '');
    slug = slug.replace(/-+/g, '-');
    return slug.replace(/^-+|-+$/g, '').toLowerCase();
}

/**
 * Loads the neural JSON map (beatmap) for the current track.
 */
export async function loadBeatmap(songTitle) {
    state.trackStartTime = performance.now() + BUFFER_TIME; 
    
    try {
        const filename = sanitizeBeatmapName(songTitle) + '.json';
        const beatmapUrl = config.beatmapDir + filename;
        
        const cachedMap = await getOfflineBeatmap(beatmapUrl);
        if (cachedMap) {
            state.currentBeatmap = cachedMap;
            return;
        } 
        
        const res = await fetch(beatmapUrl);
        state.currentBeatmap = res.ok ? await res.json() : null;
    } catch (e) { 
        state.currentBeatmap = null; 
        console.warn("No beatmap found for:", songTitle);
    }
}
