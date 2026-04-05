<?php
/**
 * Quantum Telegraph - Tutorial System
 * Guides users through the spatial mechanics, runic catching, and multiplayer syncing.
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class MelleVR_Tutorial_System {

    public function __construct() {
        // Inject the tutorial UI into the footer of the page where the VR shortcode lives
        add_action( 'wp_footer', [ $this, 'render_tutorial_ui' ] );
    }

    public function render_tutorial_ui() {
        // Only load if the Quantum Telegraph shortcode is present on the page
        global $post;
        if ( ! is_a( $post, 'WP_Post' ) || ! has_shortcode( $post->post_content, 'melle_vr' ) ) {
            return;
        }
        ?>
        
        <div id="melle-tutorial-overlay" class="glass-panel" style="display: none; position: fixed; bottom: 5%; left: 50%; transform: translateX(-50%); z-index: 9999; width: 90%; max-width: 600px; text-align: center; border: 2px solid #00f2ff; box-shadow: 0 0 30px rgba(0, 242, 255, 0.3);">
            <div class="d-flex justify-content-between align-items-center border-bottom border-info pb-2 mb-3">
                <h4 id="tutorial-title" class="text-info fw-bold mb-0 text-uppercase" style="letter-spacing: 2px;"><i class="fas fa-graduation-cap me-2"></i> System Orientation</h4>
                <button id="tutorial-close-btn" class="btn btn-sm btn-outline-danger" title="Skip Tutorial"><i class="fas fa-times"></i></button>
            </div>
            
            <div id="tutorial-content" class="mb-4 text-light" style="font-size: 1.1rem; line-height: 1.5;">
            </div>
            
            <div class="progress mb-3" style="height: 5px; background-color: #222;">
                <div id="tutorial-progress-bar" class="progress-bar bg-info" role="progressbar" style="width: 0%;"></div>
            </div>
            
            <div class="d-flex justify-content-between">
                <button id="tutorial-prev-btn" class="btn btn-outline-secondary fw-bold px-4" disabled>BACK</button>
                <span id="tutorial-step-counter" class="text-muted small align-self-center font-monospace">1 / 5</span>
                <button id="tutorial-next-btn" class="btn btn-info text-dark fw-bold px-4 shadow-sm" style="box-shadow: 0 0 10px #00f2ff !important;">NEXT <i class="fas fa-arrow-right ms-1"></i></button>
            </div>
        </div>

        <script>
            document.addEventListener("DOMContentLoaded", () => {
                const TutorialSystem = {
                    currentStep: 0,
                    isActive: false,
                    
                    // The script for the tutorial steps
                    steps: [
                        {
                            title: "Welcome to the Void",
                            content: "You are about to enter a synchronized spatial audio tunnel.<br><br><strong>Look around:</strong> Move your mouse, swipe your screen, or look around in your VR headset to aim your crosshair."
                        },
                        {
                            title: "Catching Runes",
                            content: "As the music plays, <strong>Futhark Runes</strong> will fly toward you. <br><br>Keep your blue ring perfectly aligned with the incoming objects to catch them. Each catch adds a rune to your Neural Lexicon."
                        },
                        {
                            title: "Dispersing the Shield",
                            content: "Press the <strong>Spacebar</strong>, double-tap your screen, or press a <strong>Face Button</strong> on your VR controller to disperse a caught rune.<br><br>This triggers a protective shield and broadcasts a translated battle cry to all other players in the tunnel!"
                        },
                        {
                            title: "Cooperative Interception",
                            content: "You are not alone. This is a cooperative system.<br><br>If your team collectively catches <strong>23 runes</strong> by the end of the track, you will successfully intercept the signal and generate a neural word cloud."
                        },
                        {
                            title: "Etheric Overload",
                            content: "Watch the <strong>GLOBAL ᚱ</strong> counter. If the entire lobby reaches 100 total runes, an <strong>Etheric Interception</strong> will trigger, injecting raw Gemini anagrams permanently into your Blueprint codex!<br><br><span class='text-warning fw-bold'>Good luck. The cosmos awaits.</span>"
                        }
                    ],

                    init() {
                        this.cacheDOM();
                        this.bindEvents();
                        
                        // Check if user has already completed the tutorial
                        if (!localStorage.getItem('melle_vr_tutorial_complete')) {
                            // Delay slightly to let the main UI load
                            setTimeout(() => this.start(), 1500);
                        }
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
                        this.manualBtn = document.getElementById('manualTutorialBtn'); // The new button from shortcode.php
                    },

                    bindEvents() {
                        this.nextBtn.addEventListener('click', () => this.nextStep());
                        this.prevBtn.addEventListener('click', () => this.prevStep());
                        this.closeBtn.addEventListener('click', () => this.end());
                        
                        // Bind the manual trigger button if it exists
                        if (this.manualBtn) {
                            this.manualBtn.addEventListener('click', (e) => {
                                e.preventDefault();
                                this.start(true); // Force start, bypassing localStorage check
                            });
                        }
                    },

                    start(force = false) {
                        this.currentStep = 0;
                        this.isActive = true;
                        this.overlay.style.display = 'block';
                        
                        // Optional: Fade out background elements slightly to focus on tutorial
                        const mainUI = document.getElementById('ui-overlay');
                        if (mainUI) mainUI.style.opacity = '0.3';
                        
                        this.renderStep();
                    },

                    renderStep() {
                        const stepData = this.steps[this.currentStep];
                        
                        // Animate text transition
                        this.contentEl.style.opacity = 0;
                        setTimeout(() => {
                            this.titleEl.innerHTML = `<i class="fas fa-graduation-cap me-2"></i> ${stepData.title}`;
                            this.contentEl.innerHTML = stepData.content;
                            this.contentEl.style.opacity = 1;
                        }, 200);

                        this.counterEl.innerText = `${this.currentStep + 1} / ${this.steps.length}`;
                        
                        const progressPct = ((this.currentStep + 1) / this.steps.length) * 100;
                        this.progressBar.style.width = `${progressPct}%`;

                        // Button states
                        this.prevBtn.disabled = this.currentStep === 0;
                        
                        if (this.currentStep === this.steps.length - 1) {
                            this.nextBtn.innerHTML = 'START ENGINE <i class="fas fa-power-off ms-1"></i>';
                            this.nextBtn.classList.replace('btn-info', 'btn-success');
                        } else {
                            this.nextBtn.innerHTML = 'NEXT <i class="fas fa-arrow-right ms-1"></i>';
                            this.nextBtn.classList.replace('btn-success', 'btn-info');
                        }
                    },

                    nextStep() {
                        if (this.currentStep < this.steps.length - 1) {
                            this.currentStep++;
                            this.renderStep();
                        } else {
                            this.end();
                        }
                    },

                    prevStep() {
                        if (this.currentStep > 0) {
                            this.currentStep--;
                            this.renderStep();
                        }
                    },

                    end() {
                        this.isActive = false;
                        this.overlay.style.display = 'none';
                        localStorage.setItem('melle_vr_tutorial_complete', 'true');
                        
                        // Restore background UI
                        const mainUI = document.getElementById('ui-overlay');
                        if (mainUI) mainUI.style.opacity = '1.0';
                    }
                };

                TutorialSystem.init();
                
                // Allow triggering from browser console or other scripts
                window.MelleVRTutorial = TutorialSystem;
            });
        </script>
        <?php
    }
}

new MelleVR_Tutorial_System();
