/**
 * ui.js
 * Handles DOM manipulation, HUD rendering, and HTML overlay updates 
 * for the Quantum Telegraph interface.
 */

import { state, config } from './config.js';
import { masterGainNode } from './audio.js';

// Global references for the VR 2D canvases (Set by main app.js rendering loop)
export let vrHudCtx = null;
export let vrHudTexture = null;
export let vrRewardCtx = null;
export let vrRewardTexture = null;

export function setVrContexts(hudCtx, hudTex, rewardCtx, rewardTex) {
    vrHudCtx = hudCtx;
    vrHudTexture = hudTex;
    vrRewardCtx = rewardCtx;
    vrRewardTexture = rewardTex;
}

/**
 * Updates the floating VR Canvas HUD inside the WebXR headset.
 */
export function updateVRHUD() {
    if (!vrHudCtx) return;
    
    const myTotal = state.score.hits + state.score.caught;
    
    vrHudCtx.clearRect(0, 0, 512, 128);
    vrHudCtx.fillStyle = 'rgba(0, 0, 0, 0.6)';
    vrHudCtx.fillRect(0, 0, 512, 128);
    
    vrHudCtx.font = 'bold 35px sans-serif';
    vrHudCtx.fillStyle = '#00f2ff';
    vrHudCtx.textAlign = 'center';
    vrHudCtx.textBaseline = 'middle';
    vrHudCtx.fillText(`VOLTAGE: ${myTotal}  |  GLOBAL ᚱ: ${state.globalRunes}/100  |  CAPTURED: ${state.score.caught}`, 256, 64);
    
    if (vrHudTexture) vrHudTexture.needsUpdate = true;
}

/**
 * Updates the 2D HTML overlays for desktop/mobile users.
 */
export function updateScoreUI() {
    const myTotal = state.score.hits + state.score.caught;
    const txtStatus = document.getElementById('txtStatus');
    
    if (txtStatus) {
        txtStatus.innerHTML = `VOLTAGE: <strong>${myTotal}</strong> (GLOBAL ᚱ: <span style="color:#ff0">${state.globalRunes}/100</span> | CAPTURED: <span style="color:#0f0">${state.score.caught}</span>)`;
    }
    
    if (vrHudCtx) updateVRHUD();
}

/**
 * Updates the right-side live leaderboard list.
 */
export function updateLiveLeaderboardUI() {
    const livePlayersUI = document.getElementById('livePlayersUI');
    const liveLeaderboardList = document.getElementById('liveLeaderboardList');
    
    if (!livePlayersUI || !liveLeaderboardList) return;

    let displayPlayers = Object.entries(state.players).map(([name, data]) => ({ 
        name, 
        score: data.score, 
        isCurrent: true 
    }));

    if (state.isSinglePlayer) {
        let spScores = JSON.parse(localStorage.getItem('melle_vr_sp_scores') || '[]');
        spScores.forEach(sp => {
            displayPlayers.push({ name: sp.name, score: sp.score, isCurrent: false });
        });
    }

    const sorted = displayPlayers.sort((a, b) => b.score - a.score);
    
    livePlayersUI.innerText = `Circuit: ${state.isSinglePlayer ? 'Isolated' : state.currentChannel} | Particles: ${sorted.length}`;
    
    liveLeaderboardList.innerHTML = sorted.map(p => {
        let style = p.isCurrent ? '' : 'color:#aaa; font-style:italic;';
        if (p.isCurrent && p.name === state.playerName) style = 'color:#00d2ff; font-weight:bold;';
        
        let displayName = (p.isCurrent && p.name === state.playerName) ? p.name + ' (You)' : p.name;
        
        return `<div class="live-player">
                    <span style="${style}">${displayName}</span>
                    <span style="color:#0f0">${p.score}</span>
                </div>`;
    }).join('');
}

/**
 * Updates the left-side vertically stacked metadata UI.
 */
export function updateMetadataUI(rawTitle, customData) {
    const songUI = document.getElementById('songUI');
    if (!songUI || state.isMatchResetting) return;

    let displayTitle = customData && customData.title ? customData.title : rawTitle;
    let metaHtml = `<div class="track-title"><i class="fas fa-music me-2"></i> ${displayTitle}</div>`;
    
    if (customData) {
        if (customData.artist) metaHtml += `<div class="track-artist"><i class="fas fa-user text-muted me-2"></i> ${customData.artist}</div>`;
        if (customData.album) metaHtml += `<div class="track-album"><i class="fas fa-compact-disc text-muted me-2"></i> ${customData.album}</div>`;
        if (customData.publish_date) metaHtml += `<div class="track-date"><i class="fas fa-calendar-alt text-muted me-2"></i> ${customData.publish_date}</div>`;
    }

    songUI.innerHTML = metaHtml;
}

/**
 * Syncs the collected runes array to localStorage and updates the scrolling ticker.
 */
export function syncRunesToCore(voiceCaught = false) {
    if (!window.MelleVRCollectedRunes) return;
    
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

/**
 * Initializes the mute button logic for the in-game overlay.
 */
export function initMuteToggle() {
    const muteBtn = document.getElementById('mute-audio-btn');
    if (muteBtn) {
        muteBtn.addEventListener('click', (e) => {
            e.preventDefault();
            // Fallback to window.masterGainNode if module import is stale
            const gainNode = masterGainNode || window.masterGainNode;
            
            if (gainNode) {
                const isMuted = gainNode.gain.value === 0;
                gainNode.gain.value = isMuted ? 1 : 0;
                
                const icon = document.getElementById('mute-icon');
                const text = document.getElementById('mute-text');
                
                if (isMuted) {
                    icon.className = 'fas fa-volume-up me-1';
                    text.innerText = 'Mute';
                    muteBtn.classList.replace('btn-outline-secondary', 'btn-outline-warning');
                } else {
                    icon.className = 'fas fa-volume-mute me-1 text-danger';
                    text.innerText = 'Unmute';
                    muteBtn.classList.replace('btn-outline-warning', 'btn-outline-secondary');
                }
            }
        });
    }
}
