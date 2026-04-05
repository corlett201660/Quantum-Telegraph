<?php
/**
 * Template Name: Isochronic Core Fullscreen
 * Description: A pure, raw HTML canvas that completely bypasses the WordPress theme.
 */

// We do NOT call get_header() here. This completely eliminates the theme's layout grids.
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php wp_title('|', true, 'right'); ?></title>
    
    <?php 
    // Load essential WordPress scripts (like jQuery) but bypass theme layout wrappers
    wp_head(); 
    ?>
    
    <style>
        /* Nuke the WP Admin bar bump to keep the app flush to the top of the screen */
        html { margin-top: 0 !important; }
        body { 
            margin: 0 !important; 
            padding: 0 !important; 
            width: 100vw !important; 
            min-height: 100vh !important; 
            background: #050505; 
            overflow-x: hidden;
        }
        #wpadminbar { display: none !important; }
        
        /* Aggressively hide any stray theme elements that might inject via wp_footer */
        footer, #footer, .site-footer, #colophon, .qrq-toast-link { display: none !important; }
    </style>
</head>
<body <?php body_class('isochronic-app-active'); ?>>

    <div id="isochronic-master-container" style="width: 100vw; min-height: 100vh; padding: 0; margin: 0;">
        <?php 
        // Render the shortcode directly inside this clean wrapper
        echo do_shortcode('[isochronic_core]'); 
        ?>
    </div>

    <?php 
    // Load essential footer scripts (required for your JS to run)
    wp_footer(); 
    ?>
</body>
</html>
