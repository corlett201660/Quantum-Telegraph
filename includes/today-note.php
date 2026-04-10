<?php
/**
 * Quantum Telegraph - Daily Frequency Assignment
 * Locks a single unworked track for 24 hours (UTC) to focus community collaboration.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * 1. ENGINE: Find an unworked track and lock it for 24 hours (UTC)
 */
if ( ! function_exists( 'qrq_get_daily_assigned_track' ) ) {
    function qrq_get_daily_assigned_track() {
        // Use strict UTC to align with the frontend JavaScript timer
        $today = gmdate('Ymd');
        
        // Check if we've already locked in a track for today
        $saved_daily = get_option('qrq_daily_track_lock');
        if (is_array($saved_daily) && isset($saved_daily['date']) && $saved_daily['date'] === $today) {
            return $saved_daily['track'];
        }

        // If not, we need to generate today's track
        $base_dir = qrq_get_base_asset_dir();
        $channels = array_filter(glob($base_dir . '/*'), 'is_dir');
        $untouched = [];
        $worked = [];

        foreach ($channels as $channel_path) {
            $channel_name = basename($channel_path);
            $meta_file = $channel_path . '/metadata.json';
            $meta_data = file_exists($meta_file) ? json_decode(file_get_contents($meta_file), true) : [];
            $mp3s = glob($channel_path . '/*.mp3');

            foreach ($mp3s as $file) {
                $basename = basename($file);
                $track = ['channel' => $channel_name, 'file' => $basename];
                if (!isset($meta_data[$basename]['collab_notes']) || empty($meta_data[$basename]['collab_notes'])) {
                    $untouched[] = $track;
                } else {
                    $worked[] = $track;
                }
            }
        }

        $pool = !empty($untouched) ? $untouched : $worked;
        if (empty($pool)) return null;

        // --- SEEDING LOGIC ---
        $seed = (int)$today;
        srand($seed); 
        $index = rand(0, count($pool) - 1);
        $selection = $pool[$index];
        srand(); // Reset RNG

        // Save the selection to the database so it survives comments/pool shifts
        update_option('qrq_daily_track_lock', [
            'date' => $today,
            'track' => $selection
        ]);

        return $selection;
    }
}

/**
 * 2. ENGINE: Get Recent History
 */
if ( ! function_exists( 'qrq_get_recent_worked_tracks' ) ) {
    function qrq_get_recent_worked_tracks($limit = 4) {
        $base_dir = qrq_get_base_asset_dir();
        $channels = array_filter(glob($base_dir . '/*'), 'is_dir');
        $worked = [];
        foreach ($channels as $path) {
            $meta_file = $path . '/metadata.json';
            if (!file_exists($meta_file)) continue;
            $data = json_decode(file_get_contents($meta_file), true);
            foreach ($data as $file => $meta) {
                if (!empty($meta['collab_notes'])) {
                    $worked[] = ['channel' => basename($path), 'file' => $file, 'time' => filemtime($path . '/' . $file)];
                }
            }
        }
        usort($worked, function($a, $b) { return $b['time'] - $a['time']; });
        return array_slice($worked, 0, $limit);
    }
}

/**
 * 3. THE SHORTCODE HANDLER
 */
function qrq_daily_assignment_render($atts) {
    $args = shortcode_atts( array('public' => 'false'), $atts);
    $is_public = filter_var($args['public'], FILTER_VALIDATE_BOOLEAN);

    if (!is_user_logged_in() && !$is_public) {
        $login_url = class_exists('UM') ? um_get_core_page('login') : wp_login_url(get_permalink());
        return '
        <div style="min-height: 65vh; display: flex; align-items: center; justify-content: center; padding: 40px;">
            <div style="background: #111; padding: 50px; border-radius: 12px; border: 1px solid #333; text-align:center; max-width: 450px;">
                <div style="font-size: 1.5rem; font-weight: 900; letter-spacing: 5px; color: #fff; margin-bottom: 10px;">QUANTUM TELEGRAPH</div>
                <h2 style="color: #00f2ff; margin-bottom: 25px; font-size: 0.9rem; text-transform: uppercase;">Observation Deck</h2>
                <a href="'.esc_url($login_url).'" style="display: block; background: #00f2ff; color: #000; padding: 15px; border-radius: 4px; font-weight: 900; text-decoration: none; text-transform: uppercase; font-size: 0.8rem;">Identify & Authenticate</a>
            </div>
        </div>';
    }

    $assigned = qrq_get_daily_assigned_track();
    if (!$assigned) return '<div style="min-height: 65vh; display:flex; align-items:center; justify-content:center;"><h3>Archives empty.</h3></div>';

    $file_slug = preg_replace('/\.mp3$/i', '', $assigned['file']);
    
    ob_start();
    ?>
    <div class="qrq-assignment-wrapper" style="min-height: 65vh; display: flex; flex-direction: column; max-width: 1200px; margin: 0 auto; padding: 20px;">
        
        <div style="background: rgba(0, 242, 255, 0.03); border-left: 4px solid #00f2ff; padding: 25px; margin-bottom: 30px;">
             <h2 style="margin:0; font-size: 2rem; font-weight: 900; color: #fff;">TODAY'S FREQUENCY: <?php echo esc_html($file_slug); ?></h2>
             <p style="margin: 5px 0 0 0; opacity: 0.5; font-size: 0.8rem; font-family: monospace;">CHANNEL: /<?php echo esc_html($assigned['channel']); ?>/</p>
        </div>

        <div class="hub-content" style="flex-grow: 1;">
            <?php 
            if (function_exists('qrq_render_collab_page')) {
                qrq_render_collab_page($assigned['channel'], $file_slug);
            }
            ?>
        </div>

        <div style="margin-top: 50px; border-top: 1px solid #222; padding-top: 20px;">
             <button onclick="window.location.reload();" style="background:#222; color:#fff; border:none; padding:10px 20px; border-radius:4px; cursor:pointer; font-size:0.7rem;">REFRESH FREQUENCY</button>
        </div>
    </div>
    <style>.qrq-assignment-wrapper header, .qrq-assignment-wrapper footer { display: none !important; }</style>
    <?php
    return ob_get_clean();
}

// 4. REGISTER
add_action('init', function() {
    add_shortcode('qrq_daily_assignment', 'qrq_daily_assignment_render');
});
