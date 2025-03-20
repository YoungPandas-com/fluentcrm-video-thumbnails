<?php
/*
Plugin Name: FluentCRM Video Thumbnail Generator
Plugin URI: https://fluentcrm.com
Description: Generate high-quality video thumbnails from YouTube, Vimeo, or Media Library videos for use in FluentCRM emails
Version: 1.0.0
Author: FluentCRM
Author URI: https://fluentcrm.com
License: GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: fluentcrm-video-thumbnails
Domain Path: /languages
Requires at least: 5.6
Requires PHP: 7.2
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

define('FLUENTCRM_VIDEO_THUMBNAILS_VERSION', '1.0.0');
define('FLUENTCRM_VIDEO_THUMBNAILS_PATH', plugin_dir_path(__FILE__));
define('FLUENTCRM_VIDEO_THUMBNAILS_URL', plugin_dir_url(__FILE__));
define('FLUENTCRM_VIDEO_THUMBNAILS_MIN_WP_VERSION', '5.6');
define('FLUENTCRM_VIDEO_THUMBNAILS_MIN_PHP_VERSION', '7.2');
define('FLUENTCRM_VIDEO_THUMBNAILS_MIN_FLUENTCRM_VERSION', '2.5.0');

/**
 * Check if system requirements are met
 *
 * @return bool True if system requirements are met, false if not
 */
function fluentcrm_video_thumbnails_requirements_met() {
    global $wp_version;
    
    if (version_compare(PHP_VERSION, FLUENTCRM_VIDEO_THUMBNAILS_MIN_PHP_VERSION, '<')) {
        add_action('admin_notices', 'fluentcrm_video_thumbnails_php_version_notice');
        return false;
    }
    
    if (version_compare($wp_version, FLUENTCRM_VIDEO_THUMBNAILS_MIN_WP_VERSION, '<')) {
        add_action('admin_notices', 'fluentcrm_video_thumbnails_wp_version_notice');
        return false;
    }
    
    if (!defined('FLUENTCRM')) {
        add_action('admin_notices', 'fluentcrm_video_thumbnails_dependency_notice');
        return false;
    }
    
    if (defined('FLUENTCRM_PLUGIN_VERSION') && version_compare(FLUENTCRM_PLUGIN_VERSION, FLUENTCRM_VIDEO_THUMBNAILS_MIN_FLUENTCRM_VERSION, '<')) {
        add_action('admin_notices', 'fluentcrm_video_thumbnails_fluentcrm_version_notice');
        return false;
    }
    
    return true;
}

/**
 * Admin notice for minimum PHP version
 */
function fluentcrm_video_thumbnails_php_version_notice() {
    echo '<div class="notice notice-error"><p>' . 
         sprintf(
             __('FluentCRM Video Thumbnail Generator requires PHP version %s or higher. You are running version %s.', 'fluentcrm-video-thumbnails'),
             FLUENTCRM_VIDEO_THUMBNAILS_MIN_PHP_VERSION,
             PHP_VERSION
         ) . 
         '</p></div>';
}

/**
 * Admin notice for minimum WordPress version
 */
function fluentcrm_video_thumbnails_wp_version_notice() {
    global $wp_version;
    echo '<div class="notice notice-error"><p>' . 
         sprintf(
             __('FluentCRM Video Thumbnail Generator requires WordPress version %s or higher. You are running version %s.', 'fluentcrm-video-thumbnails'),
             FLUENTCRM_VIDEO_THUMBNAILS_MIN_WP_VERSION,
             $wp_version
         ) . 
         '</p></div>';
}

/**
 * Admin notice for FluentCRM dependency
 */
function fluentcrm_video_thumbnails_dependency_notice() {
    echo '<div class="notice notice-error"><p>' . 
         __('FluentCRM Video Thumbnail Generator requires FluentCRM to be installed and activated.', 'fluentcrm-video-thumbnails') . 
         ' <a href="' . admin_url('plugin-install.php?s=fluentcrm&tab=search&type=term') . '">' . 
         __('Install FluentCRM', 'fluentcrm-video-thumbnails') . 
         '</a>' . 
         '</p></div>';
}

/**
 * Admin notice for minimum FluentCRM version
 */
function fluentcrm_video_thumbnails_fluentcrm_version_notice() {
    echo '<div class="notice notice-error"><p>' . 
         sprintf(
             __('FluentCRM Video Thumbnail Generator requires FluentCRM version %s or higher. Please update FluentCRM to the latest version.', 'fluentcrm-video-thumbnails'),
             FLUENTCRM_VIDEO_THUMBNAILS_MIN_FLUENTCRM_VERSION
         ) . 
         '</p></div>';
}

// Check requirements before loading the plugin
if (fluentcrm_video_thumbnails_requirements_met()) {
    // Include required files
    require_once FLUENTCRM_VIDEO_THUMBNAILS_PATH . 'includes/Plugin.php';
    require_once FLUENTCRM_VIDEO_THUMBNAILS_PATH . 'includes/AjaxHandler.php';
    require_once FLUENTCRM_VIDEO_THUMBNAILS_PATH . 'includes/Utils.php';
    
    // Initialize the plugin
    add_action('plugins_loaded', function() {
        // Register the admin UI
        FluentCrmVideoThumbnails\Plugin::init();
        
        // Register AJAX handlers
        FluentCrmVideoThumbnails\AjaxHandler::init();
    });
    
    // Add links to plugin listing
    add_filter('plugin_action_links_' . plugin_basename(__FILE__), function ($links) {
        $action_links = array(
            'generate' => '<a href="' . esc_url(admin_url('admin.php?page=fluentcrm-video-thumbnails')) . '">' . 
                          esc_html__('Generate Thumbnails', 'fluentcrm-video-thumbnails') . '</a>',
        );
        return array_merge($action_links, $links);
    });
    
    // Add plugin row meta
    add_filter('plugin_row_meta', function($links, $file) {
        if (plugin_basename(__FILE__) === $file) {
            $row_meta = array(
                'docs' => '<a href="' . esc_url('https://fluentcrm.com/docs/video-thumbnail-generator/') . '" target="_blank">' . 
                          esc_html__('Documentation', 'fluentcrm-video-thumbnails') . '</a>',
                'support' => '<a href="' . esc_url('https://wpmanageninja.com/support-tickets/') . '" target="_blank">' . 
                             esc_html__('Support', 'fluentcrm-video-thumbnails') . '</a>',
            );
            return array_merge($links, $row_meta);
        }
        return $links;
    }, 10, 2);
    
    // Activation hook
    register_activation_hook(__FILE__, function() {
        try {
            // Create necessary directories with proper permissions
            $upload_dir = wp_upload_dir();
            if (isset($upload_dir['error']) && !empty($upload_dir['error'])) {
                // Handle upload directory error
                error_log('FluentCRM Video Thumbnails: Upload directory error - ' . $upload_dir['error']);
                return;
            }
            
            $thumbnail_dir = $upload_dir['basedir'] . '/fluentcrm-video-thumbnails';
            
            if (!file_exists($thumbnail_dir)) {
                $dir_created = wp_mkdir_p($thumbnail_dir);
                
                if (!$dir_created) {
                    error_log('FluentCRM Video Thumbnails: Failed to create directory - ' . $thumbnail_dir);
                    return;
                }
                
                // Create index.php for security
                @file_put_contents($thumbnail_dir . '/index.php', '<?php // Silence is golden');
                
                // Create .htaccess with less restrictive rules that are more compatible
                $htaccess_content = "# Disable directory browsing\n";
                $htaccess_content .= "Options -Indexes\n";
                $htaccess_content .= "# Protect .htaccess file\n";
                $htaccess_content .= "<Files .htaccess>\n";
                $htaccess_content .= "Order Allow,Deny\n";
                $htaccess_content .= "Deny from all\n";
                $htaccess_content .= "</Files>\n";
                
                @file_put_contents($thumbnail_dir . '/.htaccess', $htaccess_content);
            }
            
            // Add capabilities - only if user has manage_options capability
            if (current_user_can('manage_options')) {
                $admin = get_role('administrator');
                if ($admin) {
                    $admin->add_cap('manage_fluentcrm_video_thumbnails');
                }
            }
            
            // Set activation flag for admin notice
            update_option('fluentcrm_video_thumbnails_activated', time());
            
        } catch (Exception $e) {
            // Log activation errors
            error_log('FluentCRM Video Thumbnails activation error: ' . $e->getMessage());
        }
    });
    
    // Deactivation hook
    register_deactivation_hook(__FILE__, function() {
        // Remove scheduled events if any
        wp_clear_scheduled_hook('fluentcrm_video_thumbnails_cleanup');
        
        // Optionally remove capabilities
        $admin = get_role('administrator');
        if ($admin) {
            $admin->remove_cap('manage_fluentcrm_video_thumbnails');
        }
    });
    
    // Uninstall hook (for complete removal)
    register_uninstall_hook(__FILE__, 'fluentcrm_video_thumbnails_uninstall');
}

/**
 * Cleanup on uninstall
 */
function fluentcrm_video_thumbnails_uninstall() {
    // Remove options
    delete_option('fluentcrm_video_thumbnails_activated');
    delete_option('fluentcrm_video_thumbnails_settings');
    
    // Optionally remove uploaded thumbnails
    // Note: Be careful with this as it might delete user content
    // $upload_dir = wp_upload_dir();
    // $thumbnail_dir = $upload_dir['basedir'] . '/fluentcrm-video-thumbnails';
    // if (file_exists($thumbnail_dir)) {
    //     fluentcrm_video_thumbnails_remove_directory($thumbnail_dir);
    // }
}

/**
 * Helper function to recursively remove a directory
 */
function fluentcrm_video_thumbnails_remove_directory($dir) {
    if (!file_exists($dir)) {
        return true;
    }
    
    if (!is_dir($dir)) {
        return unlink($dir);
    }
    
    foreach (scandir($dir) as $item) {
        if ($item == '.' || $item == '..') {
            continue;
        }
        
        if (!fluentcrm_video_thumbnails_remove_directory($dir . DIRECTORY_SEPARATOR . $item)) {
            return false;
        }
    }
    
    return rmdir($dir);
}