/**
 * Quantum Telegraph - Voice Command Interface
 * Uses Annyang to allow hands-free spatial navigation and rune casting.
 */

window.MelleVoiceNav = (function() {
    let isActive = false;
    let isDebug = false;

    // Helper to log what the microphone hears to the on-screen debug HUD
    const logDebug = (msg) => {
        if (!isDebug) return;
        const logContent = document.getElementById('voice-debug-log-content');
        if (logContent) {
            const entry = document.createElement('div');
            entry.style.borderBottom = "1px solid #333";
            entry.style.paddingBottom = "4px";
            entry.style.marginBottom = "4px";
            entry.innerText = `[${new Date().toLocaleTimeString()}] ${msg}`;
            logContent.prepend(entry);
        }
    };

    const init = () => {
        if (!window.annyang) {
            console.warn("Quantum Telegraph: Annyang voice library not loaded or blocked by browser.");
            return;
        }

        // Standard spatial movement and action commands
        const commands = {
            'up': () => { if (window.MelleVR) window.MelleVR.moveRingVoice('up'); logDebug('Command: UP'); },
            'down': () => { if (window.MelleVR) window.MelleVR.moveRingVoice('down'); logDebug('Command: DOWN'); },
            'left': () => { if (window.MelleVR) window.MelleVR.moveRingVoice('left'); logDebug('Command: LEFT'); },
            'right': () => { if (window.MelleVR) window.MelleVR.moveRingVoice('right'); logDebug('Command: RIGHT'); },
            'center': () => { if (window.MelleVR) window.MelleVR.moveRingVoice('center'); logDebug('Command: CENTER'); },
            'disperse': () => { if (window.MelleVR) window.MelleVR.disperseRune(); logDebug('Command: DISPERSE'); },
            'fire': () => { if (window.MelleVR) window.MelleVR.disperseRune(); logDebug('Command: FIRE'); },
            'release': () => { if (window.MelleVR) window.MelleVR.disperseRune(); logDebug('Command: RELEASE'); }
        };

        // Futhark phonetic mapping for voice-activated catching
        const runePhonetics = {
            'fehu': 'ᚠ', 'uruz': 'ᚢ', 'thurisaz': 'ᚦ', 'ansuz': 'ᚨ',
            'raido': 'ᚱ', 'kaunan': 'ᚲ', 'gebo': 'ᚷ', 'wunjo': 'ᚹ',
            'hagalaz': 'ᚺ', 'naudiz': 'ᚾ', 'isaz': 'ᛁ', 'jeran': 'ᛃ',
            'ihwaz': 'ᛇ', 'pertho': 'ᛈ', 'algiz': 'ᛉ', 'sowilo': 'ᛊ',
            'tiwaz': 'ᛏ', 'berkanan': 'ᛒ', 'ehwaz': 'ᛖ', 'mannaz': 'ᛗ',
            'laguz': 'ᛚ', 'ingwaz': 'ᛜ', 'dagaz': 'ᛞ', 'othalan': 'ᛟ'
        };

        // Dynamically bind phonetics to the MelleVR engine catcher
        for (const [phonetic, char] of Object.entries(runePhonetics)) {
            commands[phonetic] = () => {
                logDebug(`Rune Spoken: ${phonetic} (${char})`);
                if (window.MelleVR) window.MelleVR.processVocalRune(char);
            };
        }

        window.annyang.addCommands(commands);

        // Catch-all to display exactly what words the microphone is parsing
        window.annyang.addCallback('result', (phrases) => {
            logDebug(`Heard: "${phrases[0]}"`);
        });
    };

    // Called from shortcode.php when the user flips the "Enable Voice" toggle
    const toggle = (enable) => {
        if (!window.annyang) return;
        isActive = enable;
        const icon = document.getElementById('voiceNavIcon');
        
        if (isActive) {
            // autoRestart ensures it keeps listening after a command is spoken
            window.annyang.start({ autoRestart: true, continuous: false });
            console.log("Quantum Telegraph: Voice Interface Active.");
            if (icon) {
                icon.classList.remove('text-info');
                icon.classList.add('text-danger', 'fa-beat'); // Pulse to indicate recording
            }
        } else {
            window.annyang.abort();
            console.log("Quantum Telegraph: Voice Interface Offline.");
            if (icon) {
                icon.classList.remove('text-danger', 'fa-beat');
                icon.classList.add('text-info');
            }
        }
    };

    // Called from shortcode.php when the user flips the "Debug Mode" toggle
    const setDebug = (enable) => {
        isDebug = enable;
        if (isDebug) {
            logDebug("Voice debug initialized. Speak into your microphone.");
        }
    };

    // Boot up the command list the moment the script is loaded
    init();

    // Expose the public methods
    return {
        toggle,
        setDebug
    };
})();
