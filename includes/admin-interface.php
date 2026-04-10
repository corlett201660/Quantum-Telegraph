<?php
/**
 * Quantum Telegraph - Alternate Asset Interface
 * Provides a grid-based channel manager and metadata editor for local audio assets.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// 1. ASSET DATA HELPER
function qrq_get_channel_meta($channel) {
    $meta_file = qrq_get_base_asset_dir() . '/' . $channel . '/meta.json';
    if (file_exists($meta_file)) {
        return json_decode(file_get_contents($meta_file), true);
    }
    return [];
}

// 2. RENDER THE ASSET MANAGER
function qrq_render_channel_manager() {
    $base_dir = qrq_get_base_asset_dir();
    $channels = array_filter(glob($base_dir . '/*'), 'is_dir');
    $current_chan = isset($_GET['manage']) ? sanitize_title($_GET['manage']) : '';

    if ($current_chan) {
        qrq_render_asset_editor($current_chan);
        return;
    }

    ?>
    <div class="qrq-dashboard-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px;">
        <?php foreach ($channels as $path): 
            $name = basename($path);
            $files = glob($path . '/*.mp3');
        ?>
            <div class="card">
                <h2 class="title"><?php echo esc_html($name); ?></h2>
                <p><strong>Total Assets:</strong> <?php echo count($files); ?></p>
                <a href="?page=qrq-radio-settings&tab=channels&manage=<?php echo $name; ?>" class="button button-primary">Edit Assets & Meta</a>
            </div>
        <?php endforeach; ?>
    </div>
    <?php
}

// 3. DETAILED FILE & META EDITOR
function qrq_render_asset_editor($channel) {
    $dir = qrq_get_base_asset_dir() . '/' . $channel;
    $files = glob($dir . '/*.mp3');
    $meta_data = qrq_get_channel_meta($channel);

    // Save Logic
    if (isset($_POST['save_meta'])) {
        check_admin_referer('qrq_save_assets');
        $new_meta = $_POST['meta'];
        file_put_contents($dir . '/meta.json', json_encode($new_meta, JSON_PRETTY_PRINT));
        echo "<div class='updated'><p>Asset Metadata Updated.</p></div>";
        $meta_data = $new_meta;
    }

    ?>
    <div class="wrap">
        <a href="?page=qrq-radio-settings&tab=channels" class="button">&larr; Back to Frequencies</a>
        <h2>Quantum Telegraph Asset Manager: /<?php echo $channel; ?></h2>

        <form method="post">
            <?php wp_nonce_field('qrq_save_assets'); ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 200px;">File Name</th>
                        <th>Display Title (Overrides)</th>
                        <th>AI Insight Prompt / Artist Info</th>
                        <th style="width: 100px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($files as $file): 
                        $id = md5(basename($file));
                        $val = isset($meta_data[$id]) ? $meta_data[$id] : ['title' => '', 'artist' => '', 'prompt' => ''];
                    ?>
                    <tr>
                        <td><code><?php echo basename($file); ?></code></td>
                        <td>
                            <input type="text" name="meta[<?php echo $id; ?>][title]" value="<?php echo esc_attr($val['title']); ?>" class="widefat" placeholder="Song Title">
                        </td>
                        <td>
                            <textarea name="meta[<?php echo $id; ?>][prompt]" class="widefat" rows="1" placeholder="Add specific context for the AI DJ..."><?php echo esc_textarea($val['prompt']); ?></textarea>
                        </td>
                        <td>
                            <button type="button" class="button-link-delete" onclick="alert('Use FTP to delete source file')">Delete</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p class="submit">
                <input type="submit" name="save_meta" class="button button-primary" value="Save All Changes">
            </p>
        </form>
    </div>
    <?php
}
