/**
 * network.js
 * Handles all API communication, multiplayer state synchronization, 
 * and global network events (Etheric Interceptions) for Quantum Telegraph.
 */

import { config, state } from './config.js';
import { updateLiveLeaderboardUI, vrRewardCtx, vrRewardTexture } from './ui.js';
import { syncRunesToCore } from './ui.js'; 

/**
 * Pushes the local player's current physical state and score to the server.
 */
export async function updateGlobalScore(catcherRing) {
    if (!state.isPlayerActive || !catcherRing) return;
    const myTotal = state.score.hits + state.score.caught;
    
    const payload = { 
        name: state.playerName, 
        channel: state.currentChannel, 
        score: myTotal,
        runes_caught: state.score.caught,
        collected_runes: window.MelleVRCollectedRunes,
        pos_x: catcherRing.position.x,
        pos_y: catcherRing.position.y,
        pos_z: catcherRing.position.z,
        rot_y: catcherRing.rotation.y,
        color: catcherRing.material.color.getHexString(),
        shield_active: state.isShieldActive ? 1 : 0,
        current_rune: state.activeRuneChar,
        catchphrase: state.activeCatchphrase
    };

    try {
        const res = await fetch(config.wpApiUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        if (!res.ok) console.error(`Quantum Telegraph Score Sync Error (${res.status})`);
    } catch (err) {
        // Fail silently to prevent console spam during network blips
    }
}

/**
 * Polls the server for the state of all other active particles on the frequency.
 */
export async function pollGlobalScores(triggerEthericCallback, syncAvatarsCallback, updateVRHUDCallback) {
    try {
        const sep = config.wpApiUrl.includes('?') ? '&' : '?';
        const res = await fetch(config.wpApiUrl + sep + 't=' + Date.now());
        if (!res.ok) return;
        
        const data = await res.json();
        
        state.players = {};
        if (data.players) {
            for (let p in data.players) {
                if (data.players[p].channel === state.currentChannel) {
                    state.players[p] = data.players[p];
                }
            }
        }
        
        state.globalRunes = data.global_runes || 0;
        
        const myTotal = state.score.hits + state.score.caught;
        const txtStatus = document.getElementById('txtStatus');
        
        if (txtStatus) {
            txtStatus.innerHTML = `VOLTAGE: <strong>${myTotal}</strong> (GLOBAL ᚱ: <span style="color:#ff0">${state.globalRunes}/100</span> | CAPTURED: <span style="color:#0f0">${state.score.caught}</span>)`;
        }
        
        if (updateVRHUDCallback) updateVRHUDCallback();
        
        // Trigger global event if the network array is full
        if (data.etheric_interception && !state.hasTriggeredEtheric && Array.isArray(data.etheric_words) && data.etheric_words.length > 0) {
            if (triggerEthericCallback) triggerEthericCallback(data.etheric_words);
        } else if (state.globalRunes === 0) {
            state.hasTriggeredEtheric = false; 
        }
        
        updateLiveLeaderboardUI();
        if (syncAvatarsCallback) syncAvatarsCallback();

    } catch (err) {
        // Fail silently
    }
}

/**
 * Instantly removes the player from the active server array (Anti-Ghosting Fix).
 */
export async function removePlayerFromServer() {
    if (state.isPlayerActive && state.playerName) {
        try {
            await fetch(config.wpApiUrl, {
                method: 'DELETE',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ name: state.playerName }),
                keepalive: true // Crucial for ensuring the request fires even as the page unloads
            });
            console.log("Quantum Telegraph: Particle Extracted.");
        } catch (err) {
            console.error("Extraction routing failed.", err);
        }
    }
}

/**
 * Handles the URL crawling and keyword extraction at the end of a match.
 */
export function attemptCrawl(targetUrl, retriesLeft, newSong, newTrackSlug, resetMatchCallback) {
    const rewardDetails = document.getElementById('rewardDetails');
    if (!rewardDetails) return;

    rewardDetails.innerHTML = `<div class="text-center"><div class="spinner-border text-info mb-3" role="status"></div><p class="text-info fw-bold mb-2">Extracting Signals... (Attempt ${6 - retriesLeft}/5)</p></div>`;
    
    fetch(config.wpApiUrl.replace('/scores', '/crawl'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' }, 
        body: JSON.stringify({ url: targetUrl })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.keywords && data.keywords.length > 0) {
            processCrawlSuccess(data.keywords, rewardDetails, newSong, newTrackSlug);
        } else {
            handleCrawlRetry(targetUrl, retriesLeft, newSong, newTrackSlug, resetMatchCallback);
        }
    }).catch(err => { 
        handleCrawlRetry(targetUrl, retriesLeft, newSong, newTrackSlug, resetMatchCallback); 
    });
}

/**
 * Internal helper to process a successful URL crawl.
 */
function processCrawlSuccess(newKeywords, rewardDetails, newSong, newTrackSlug) {
    let lexicon = JSON.parse(localStorage.getItem('iso_lexicon') || '{}');

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

    // Draw the VR Reward HUD if active
    if (vrRewardCtx && window.renderer && window.renderer.xr.isPresenting) {
        // Render logic for VR HUD... (abstracted for brevity here, similar to app.js implementation)
    }
}

/**
 * Internal helper to handle failing API crawls.
 */
function handleCrawlRetry(targetUrl, retriesLeft, newSong, newTrackSlug, resetMatchCallback) {
    const rewardDetails = document.getElementById('rewardDetails');
    if (retriesLeft > 1) {
        setTimeout(() => attemptCrawl(targetUrl, retriesLeft - 1, newSong, newTrackSlug, resetMatchCallback), 1500);
    } else if (rewardDetails) {
        rewardDetails.innerHTML = `<div class="alert alert-danger text-center"><i class="fas fa-exclamation-triangle fs-3 d-block mb-2"></i><strong>Interference.</strong> Extraction failed.</div><button id="rescanBtn" class="btn btn-warning w-100 fw-bold py-2 shadow-sm"><i class="fas fa-sync me-1"></i> Rescan</button>`;
        document.getElementById('rescanBtn').onclick = () => attemptCrawl(targetUrl, 5, newSong, newTrackSlug, resetMatchCallback);
    }
}

// Bind cleanup to window unload to ensure extraction on tab close
window.addEventListener('beforeunload', () => {
    removePlayerFromServer();
});
