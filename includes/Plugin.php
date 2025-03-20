<?php
namespace FluentCrmVideoThumbnails;

/**
 * Main plugin class for FluentCRM Video Thumbnail Generator
 */
class Plugin
{
    /**
     * Initialize the plugin
     */
    public static function init()
    {
        // Register admin menu
        add_action('admin_menu', [__CLASS__, 'registerAdminMenu'], 99);
        
        // Register assets
        add_action('admin_enqueue_scripts', [__CLASS__, 'registerAssets']);
        
        // Add translation support
        add_action('init', [__CLASS__, 'loadTextDomain']);
        
        // Register cleanup task
        add_action('fluentcrm_video_thumbnails_cleanup', [__CLASS__, 'scheduledCleanup']);
        
        // Schedule daily cleanup if not already scheduled
        if (!wp_next_scheduled('fluentcrm_video_thumbnails_cleanup')) {
            wp_schedule_event(time(), 'daily', 'fluentcrm_video_thumbnails_cleanup');
        }
        
        // Add activation notice
        add_action('admin_notices', [__CLASS__, 'activationNotice']);
        
        // Load compatibility modules
        self::loadCompatibilityModules();
        
        // Webhook listener for external events
        add_action('rest_api_init', [__CLASS__, 'registerRestRoutes']);
    }
    
    /**
     * Load text domain for translations
     */
    public static function loadTextDomain()
    {
        load_plugin_textdomain('fluentcrm-video-thumbnails', false, dirname(dirname(plugin_basename(__FILE__))) . '/languages');
    }
    
    /**
     * Register admin menu
     */
    public static function registerAdminMenu()
    {
        // Add submenu under FluentCRM
        $capability = apply_filters('fluentcrm_video_thumbnails_capability', 'manage_options');
        
        add_submenu_page(
            'fluent_crm',
            __('Video Thumbnail Generator', 'fluentcrm-video-thumbnails'),
            __('Video Thumbnail Generator', 'fluentcrm-video-thumbnails'),
            $capability,
            'fluentcrm-video-thumbnails',
            [__CLASS__, 'renderAdminPage']
        );
    }
    
    /**
     * Register and enqueue admin assets
     * 
     * @param string $hook Current admin page
     */
    public static function registerAssets($hook)
    {
        // Only load on our admin page
        if ($hook != 'fluentcrm_page_fluentcrm-video-thumbnails') {
            return;
        }
        
        // Enqueue WordPress media library
        wp_enqueue_media();
        
        // Enqueue styles - using minified version for production
        $min = (defined('SCRIPT_DEBUG') && SCRIPT_DEBUG) ? '' : '.min';
        
        wp_enqueue_style(
            'fluentcrm-video-thumbnails-style',
            FLUENTCRM_VIDEO_THUMBNAILS_URL . "assets/css/admin{$min}.css",
            [],
            FLUENTCRM_VIDEO_THUMBNAILS_VERSION
        );
        
        // Enqueue scripts
        wp_enqueue_script(
            'fluentcrm-video-thumbnails-script',
            FLUENTCRM_VIDEO_THUMBNAILS_URL . "assets/js/admin{$min}.js",
            ['jquery', 'wp-element', 'wp-components', 'wp-api-fetch'],
            FLUENTCRM_VIDEO_THUMBNAILS_VERSION,
            true
        );
        
        // Enhanced translations
        $i18n = self::getTranslations();
        
        // Add localized data
        wp_localize_script(
            'fluentcrm-video-thumbnails-script',
            'fluentCrmVideoThumbnails',
            [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'apiUrl' => rest_url('wp/v2/media'),
                'restApiUrl' => rest_url('fluentcrm-video-thumbnails/v1'),
                'nonce' => wp_create_nonce('fluentcrm-video-thumbnails'),
                'restNonce' => wp_create_nonce('wp_rest'),
                'siteUrl' => site_url(),
                'isDebug' => defined('WP_DEBUG') && WP_DEBUG,
                'supportsWebP' => Utils::browserSupportsWebP(),
                'maxUploadSize' => wp_max_upload_size(),
                'isMobile' => wp_is_mobile(),
                'isPro' => defined('FLUENTCAMPAIGN'),
                'supportedVideoFormats' => self::getSupportedVideoFormats(),
                'maxDuration' => apply_filters('fluentcrm_video_thumbnails_max_duration', 3600), // 1 hour default
                'i18n' => $i18n
            ]
        );
        
        // Add RTL support
        if (is_rtl()) {
            wp_enqueue_style(
                'fluentcrm-video-thumbnails-rtl',
                FLUENTCRM_VIDEO_THUMBNAILS_URL . "assets/css/admin-rtl{$min}.css",
                ['fluentcrm-video-thumbnails-style'],
                FLUENTCRM_VIDEO_THUMBNAILS_VERSION
            );
        }
        
        do_action('fluentcrm_video_thumbnails_assets_loaded');
    }
    
    /**
     * Get enhanced translations
     * 
     * @return array Translations
     */
    private static function getTranslations()
    {
        return [
            // Basic UI translations
            'title' => __('Video Thumbnail Generator', 'fluentcrm-video-thumbnails'),
            'selectVideo' => __('Select Video', 'fluentcrm-video-thumbnails'),
            'pasteUrl' => __('Paste YouTube or Vimeo URL here', 'fluentcrm-video-thumbnails'),
            'selectFromLibrary' => __('Select Video from Media Library', 'fluentcrm-video-thumbnails'),
            'captureFrame' => __('Capture Current Frame', 'fluentcrm-video-thumbnails'),
            'thumbnail' => __('Thumbnail', 'fluentcrm-video-thumbnails'),
            'thumbnailEditor' => __('Thumbnail Editor', 'fluentcrm-video-thumbnails'),
            'preview' => __('Preview', 'fluentcrm-video-thumbnails'),
            'adjustments' => __('Adjustments', 'fluentcrm-video-thumbnails'),
            
            // Editor controls
            'brightness' => __('Brightness', 'fluentcrm-video-thumbnails'),
            'contrast' => __('Contrast', 'fluentcrm-video-thumbnails'),
            'saturation' => __('Saturation', 'fluentcrm-video-thumbnails'),
            'aspectRatio' => __('Aspect Ratio', 'fluentcrm-video-thumbnails'),
            'playButton' => __('Add Play Button Overlay', 'fluentcrm-video-thumbnails'),
            'playButtonStyle' => __('Play Button Style', 'fluentcrm-video-thumbnails'),
            'playButtonSize' => __('Play Button Size', 'fluentcrm-video-thumbnails'),
            'cropImage' => __('Crop Image', 'fluentcrm-video-thumbnails'),
            'applyChanges' => __('Apply Changes', 'fluentcrm-video-thumbnails'),
            'reset' => __('Reset Adjustments', 'fluentcrm-video-thumbnails'),
            'undo' => __('Undo', 'fluentcrm-video-thumbnails'),
            'redo' => __('Redo', 'fluentcrm-video-thumbnails'),
            'saveToGallery' => __('Save to Gallery', 'fluentcrm-video-thumbnails'),
            
            // Status messages
            'processing' => __('Processing...', 'fluentcrm-video-thumbnails'),
            'capturing' => __('Capturing frame...', 'fluentcrm-video-thumbnails'),
            'uploading' => __('Uploading to media library...', 'fluentcrm-video-thumbnails'),
            'success' => __('Thumbnail saved to Media Gallery!', 'fluentcrm-video-thumbnails'),
            'error' => __('Error saving thumbnail', 'fluentcrm-video-thumbnails'),
            'invalidUrl' => __('Invalid video URL', 'fluentcrm-video-thumbnails'),
            'noThumbnail' => __('No thumbnail to save', 'fluentcrm-video-thumbnails'),
            'unsupportedSource' => __('Unsupported video source', 'fluentcrm-video-thumbnails'),
            'networkError' => __('Network error. Please check your connection and try again.', 'fluentcrm-video-thumbnails'),
            'serverError' => __('Server error. Please try again or contact support.', 'fluentcrm-video-thumbnails'),
            'videoLoading' => __('Video loading...', 'fluentcrm-video-thumbnails'),
            'mediaLibraryError' => __('Error accessing WordPress Media Library', 'fluentcrm-video-thumbnails'),
            
            // Help text and tooltips
            'brightnessTip' => __('Adjust the brightness of the thumbnail', 'fluentcrm-video-thumbnails'),
            'contrastTip' => __('Adjust the contrast of the thumbnail', 'fluentcrm-video-thumbnails'),
            'saturationTip' => __('Adjust the color intensity of the thumbnail', 'fluentcrm-video-thumbnails'),
            'aspectRatioTip' => __('Select the desired aspect ratio for your thumbnail', 'fluentcrm-video-thumbnails'),
            'playButtonTip' => __('Add a play button overlay to indicate this is a video', 'fluentcrm-video-thumbnails'),
            'cropTip' => __('Crop the thumbnail to focus on a specific area', 'fluentcrm-video-thumbnails'),
            'timeframeTip' => __('Move through the video to find the perfect frame', 'fluentcrm-video-thumbnails'),
            
            // Aspect ratio options
            'widescreen' => __('16:9 (Widescreen)', 'fluentcrm-video-thumbnails'),
            'standard' => __('4:3 (Standard)', 'fluentcrm-video-thumbnails'),
            'square' => __('1:1 (Square)', 'fluentcrm-video-thumbnails'),
            
            // Source selection
            'videoSource' => __('Video Source', 'fluentcrm-video-thumbnails'),
            'externalSource' => __('External URL (YouTube, Vimeo, etc.)', 'fluentcrm-video-thumbnails'),
            'mediaLibrary' => __('WordPress Media Library', 'fluentcrm-video-thumbnails'),
            'selectedVideo' => __('Selected video:', 'fluentcrm-video-thumbnails'),
            'unsupportedVideoFormat' => __('This video format is not supported', 'fluentcrm-video-thumbnails'),
            'qualityNote' => __('Note: Captured frame quality depends on the original video resolution', 'fluentcrm-video-thumbnails'),
            'urlInputPlaceholder' => __('Enter YouTube, Vimeo or other video URL', 'fluentcrm-video-thumbnails'),
            
            // Quality indicators
            'hdQuality' => __('HD Quality', 'fluentcrm-video-thumbnails'),
            'sdQuality' => __('SD Quality', 'fluentcrm-video-thumbnails'),
            'lowQuality' => __('Low Quality', 'fluentcrm-video-thumbnails'),
            
            // Accessibility text
            'videoPlayer' => __('Video Player', 'fluentcrm-video-thumbnails'),
            'timeSlider' => __('Time Slider', 'fluentcrm-video-thumbnails'),
            'brightnessSlider' => __('Brightness Slider', 'fluentcrm-video-thumbnails'),
            'contrastSlider' => __('Contrast Slider', 'fluentcrm-video-thumbnails'),
            'saturationSlider' => __('Saturation Slider', 'fluentcrm-video-thumbnails'),
            'closeButton' => __('Close', 'fluentcrm-video-thumbnails'),
            'thumbnailAlt' => __('Video Thumbnail Preview', 'fluentcrm-video-thumbnails'),
            
            // Enhanced features
            'enhanceThumbnail' => __('Enhance Thumbnail', 'fluentcrm-video-thumbnails'),
            'thumbnailName' => __('Thumbnail Name', 'fluentcrm-video-thumbnails'),
            'recommendedSize' => __('Recommended size for emails: 600×338px (16:9)', 'fluentcrm-video-thumbnails'),
            'imageQuality' => __('Image Quality', 'fluentcrm-video-thumbnails'),
            'imageFormat' => __('Image Format', 'fluentcrm-video-thumbnails'),
            'currentTime' => __('Current Time', 'fluentcrm-video-thumbnails'),
            'duration' => __('Duration', 'fluentcrm-video-thumbnails'),
            
            // Play button styles
            'playButtonClassic' => __('Classic', 'fluentcrm-video-thumbnails'),
            'playButtonModern' => __('Modern', 'fluentcrm-video-thumbnails'),
            'playButtonMinimal' => __('Minimal', 'fluentcrm-video-thumbnails'),
            'playButtonYouTube' => __('YouTube Style', 'fluentcrm-video-thumbnails'),
            
            // Size options
            'small' => __('Small', 'fluentcrm-video-thumbnails'),
            'medium' => __('Medium', 'fluentcrm-video-thumbnails'),
            'large' => __('Large', 'fluentcrm-video-thumbnails'),
            
            // Error and validation messages
            'enterValidUrl' => __('Please enter a valid video URL', 'fluentcrm-video-thumbnails'),
            'selectVideoFirst' => __('Please select a video first', 'fluentcrm-video-thumbnails'),
            'thumbnailTooLarge' => __('Thumbnail file size is too large. Try reducing quality or dimensions.', 'fluentcrm-video-thumbnails'),
            'enterThumbnailName' => __('Please enter a name for your thumbnail', 'fluentcrm-video-thumbnails'),
            
            // Help and instructions
            'helpText' => __('Need help? Check out our documentation', 'fluentcrm-video-thumbnails'),
            'quickTips' => __('Quick Tips:', 'fluentcrm-video-thumbnails'),
            'tip1' => __('Use the slider to find the perfect frame', 'fluentcrm-video-thumbnails'),
            'tip2' => __('Adjust brightness and contrast for best results', 'fluentcrm-video-thumbnails'),
            'tip3' => __('Add a play button to indicate this is a video', 'fluentcrm-video-thumbnails'),
            'learnMore' => __('Learn More', 'fluentcrm-video-thumbnails'),
            
            // Confirmation
            'confirmReset' => __('Are you sure you want to reset all adjustments?', 'fluentcrm-video-thumbnails'),
            'yesReset' => __('Yes, reset', 'fluentcrm-video-thumbnails'),
            'cancel' => __('Cancel', 'fluentcrm-video-thumbnails'),
            
            // Usage
            'usingThumbnailTitle' => __('Using Your Thumbnail', 'fluentcrm-video-thumbnails'),
            'usingThumbnailText' => __('Your thumbnail is now in the Media Library and can be used in your FluentCRM emails or anywhere else in WordPress.', 'fluentcrm-video-thumbnails'),
            'viewInMediaLibrary' => __('View in Media Library', 'fluentcrm-video-thumbnails'),
            'copyImageUrl' => __('Copy Image URL', 'fluentcrm-video-thumbnails'),
            'imageUrlCopied' => __('Image URL copied to clipboard!', 'fluentcrm-video-thumbnails'),
            'createAnother' => __('Create Another Thumbnail', 'fluentcrm-video-thumbnails')
        ];
    }
    
    /**
     * Get supported video formats
     * 
     * @return array Supported video formats
     */
    private static function getSupportedVideoFormats()
    {
        $formats = [
            'mp4' => true,
            'webm' => true,
            'ogv' => true,
        ];
        
        // Check if the server supports additional formats
        if (extension_loaded('ffmpeg') || function_exists('exec')) {
            $formats['mov'] = true;
            $formats['avi'] = true;
            $formats['wmv'] = true;
            $formats['flv'] = true;
        }
        
        return apply_filters('fluentcrm_video_thumbnails_supported_formats', $formats);
    }
    
    /**
     * Admin page rendering
     */
    public static function renderAdminPage()
    {
        // Check user capability
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'fluentcrm-video-thumbnails'));
        }
        
        echo '<div class="wrap">';
        echo '<div id="fluentcrm-video-thumbnails-app"></div>';
        echo '</div>';
    }
    
    /**
     * Show activation notice
     */
    public static function activationNotice()
    {
        // Check if we should show the notice
        if (!get_option('fluentcrm_video_thumbnails_activated')) {
            return;
        }
        
        // Only show to admins
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Only show on specific pages
        $screen = get_current_screen();
        if (!in_array($screen->id, ['dashboard', 'plugins', 'fluentcrm_page_fluentcrm-video-thumbnails'])) {
            return;
        }
        
        // Get the activation time
        $activation_time = get_option('fluentcrm_video_thumbnails_activated');
        
        // Only show for 1 week after activation
        if (time() - $activation_time > WEEK_IN_SECONDS) {
            delete_option('fluentcrm_video_thumbnails_activated');
            return;
        }
        
        // Show the notice
        ?>
        <div class="notice notice-success is-dismissible fluentcrm-video-thumbnails-notice">
            <h3><?php _e('FluentCRM Video Thumbnail Generator Activated!', 'fluentcrm-video-thumbnails'); ?></h3>
            <p>
                <?php _e('Create professional video thumbnails for your emails and content. Ready to get started?', 'fluentcrm-video-thumbnails'); ?>
            </p>
            <p>
                <a href="<?php echo esc_url(admin_url('admin.php?page=fluentcrm-video-thumbnails')); ?>" class="button button-primary">
                    <?php _e('Create Your First Thumbnail', 'fluentcrm-video-thumbnails'); ?>
                </a>
                <a href="https://fluentcrm.com/docs/video-thumbnail-generator/" target="_blank" class="button button-secondary">
                    <?php _e('View Documentation', 'fluentcrm-video-thumbnails'); ?>
                </a>
                <a href="#" class="dismiss-notice" style="margin-left: 10px;">
                    <?php _e('Dismiss', 'fluentcrm-video-thumbnails'); ?>
                </a>
            </p>
        </div>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                $(document).on('click', '.fluentcrm-video-thumbnails-notice .dismiss-notice', function(e) {
                    e.preventDefault();
                    $.post(ajaxurl, {
                        action: 'fluentcrm_dismiss_video_thumbnails_notice',
                        nonce: '<?php echo wp_create_nonce('fluentcrm-video-thumbnails-dismiss-notice'); ?>'
                    });
                    $(this).closest('.fluentcrm-video-thumbnails-notice').remove();
                });
            });
        </script>
        <?php
    }
    
    /**
     * Load compatibility modules
     */
    private static function loadCompatibilityModules()
    {
        // Mobile detection
        if (!function_exists('wp_is_mobile')) {
            require_once ABSPATH . WPINC . '/functions.php';
        }
        
        // Browser detection for advanced features
        if (!class_exists('Browser')) {
            if (file_exists(FLUENTCRM_VIDEO_THUMBNAILS_PATH . 'includes/vendor/browser.php')) {
                require_once FLUENTCRM_VIDEO_THUMBNAILS_PATH . 'includes/vendor/browser.php';
            } else {
                // If the browser.php file doesn't exist, create a simple fallback
                class Browser {
                    public function supportsWebP() {
                        return false; // Default to no WebP support
                    }
                    
                    public function isMobile() {
                        return wp_is_mobile();
                    }
                }
            }
        }
        
        // Check for specific plugins and load compatibility modules
        if (defined('ELEMENTOR_VERSION')) {
            // Elementor compatibility
            add_action('elementor/editor/before_enqueue_scripts', function() {
                wp_enqueue_script(
                    'fluentcrm-video-thumbnails-elementor',
                    FLUENTCRM_VIDEO_THUMBNAILS_URL . 'assets/js/elementor-compat.js',
                    ['jquery'],
                    FLUENTCRM_VIDEO_THUMBNAILS_VERSION,
                    true
                );
            });
        }
        
        // Add compatibility with Gutenberg
        add_action('enqueue_block_editor_assets', function() {
            wp_enqueue_script(
                'fluentcrm-video-thumbnails-gutenberg',
                FLUENTCRM_VIDEO_THUMBNAILS_URL . 'assets/js/gutenberg-compat.js',
                ['wp-blocks', 'wp-element', 'wp-editor'],
                FLUENTCRM_VIDEO_THUMBNAILS_VERSION,
                true
            );
        });
    }
    
    /**
     * Register REST API routes
     */
    public static function registerRestRoutes()
    {
        register_rest_route('fluentcrm-video-thumbnails/v1', '/fetch-video-info', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'restFetchVideoInfo'],
            'permission_callback' => function() {
                return current_user_can('upload_files');
            }
        ]);
        
        register_rest_route('fluentcrm-video-thumbnails/v1', '/save-thumbnail', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'restSaveThumbnail'],
            'permission_callback' => function() {
                return current_user_can('upload_files');
            }
        ]);
    }
    
    /**
     * REST API endpoint for fetching video info
     * 
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public static function restFetchVideoInfo($request)
    {
        $url = $request->get_param('video_url');
        
        if (empty($url)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => __('No video URL provided', 'fluentcrm-video-thumbnails')
            ], 400);
        }
        
        if (AjaxHandler::isYouTubeUrl($url)) {
            $video_id = AjaxHandler::extractYouTubeId($url);
            if (!$video_id) {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => __('Invalid YouTube URL', 'fluentcrm-video-thumbnails')
                ], 400);
            }
            
            $info = Utils::getYouTubeInfo($video_id);
            return new \WP_REST_Response([
                'success' => true,
                'data' => $info
            ]);
        } 
        else if (AjaxHandler::isVimeoUrl($url)) {
            $video_id = AjaxHandler::extractVimeoId($url);
            if (!$video_id) {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => __('Invalid Vimeo URL', 'fluentcrm-video-thumbnails')
                ], 400);
            }
            
            $info = Utils::getVimeoInfo($video_id);
            if (isset($info['error'])) {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => $info['error']
                ], 400);
            }
            
            return new \WP_REST_Response([
                'success' => true,
                'data' => $info
            ]);
        }
        else {
            $info = Utils::getGenericVideoInfo($url);
            if (isset($info['error'])) {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => $info['error']
                ], 400);
            }
            
            return new \WP_REST_Response([
                'success' => true,
                'data' => $info
            ]);
        }
    }
    
    /**
     * REST API endpoint for saving thumbnails
     * 
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public static function restSaveThumbnail($request)
    {
        $thumbnail_data = $request->get_param('thumbnail_data');
        $video_title = $request->get_param('video_title');
        $video_source = $request->get_param('video_source');
        $video_id = $request->get_param('video_id');
        
        if (empty($thumbnail_data)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => __('No thumbnail data received', 'fluentcrm-video-thumbnails')
            ], 400);
        }
        
        // Process the thumbnail data (base64)
        if (preg_match('/^data:image\/(\w+);base64,/', $thumbnail_data, $matches)) {
            $image_type = $matches[1];
            $base64_data = substr($thumbnail_data, strpos($thumbnail_data, ',') + 1);
            $decoded_data = base64_decode($base64_data);
            
            if ($decoded_data === false) {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => __('Invalid image data', 'fluentcrm-video-thumbnails')
                ], 400);
            }
            
            // Create a temporary file
            $upload_dir = wp_upload_dir();
            $temp_dir = $upload_dir['basedir'] . '/fluentcrm-video-thumbnails';
            
            if (!file_exists($temp_dir)) {
                wp_mkdir_p($temp_dir);
            }
            
            $filename = 'video-thumbnail-' . time() . '.' . $image_type;
            $temp_file = $temp_dir . '/' . $filename;
            
            file_put_contents($temp_file, $decoded_data);
            
            // Prepare file for uploading to WordPress media library
            $file_array = [
                'name' => $filename,
                'tmp_name' => $temp_file,
                'error' => 0,
                'size' => filesize($temp_file),
            ];
            
            // Handle the upload process
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            require_once(ABSPATH . 'wp-admin/includes/media.php');
            
            // Upload the file to WordPress media library
            $attachment_id = media_handle_sideload($file_array, 0);
            
            // Delete the temporary file
            @unlink($temp_file);
            
            if (is_wp_error($attachment_id)) {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => $attachment_id->get_error_message()
                ], 500);
            }
            
            // Update attachment metadata with custom fields
            $title = !empty($video_title) ? sanitize_text_field($video_title) : __('Video Thumbnail', 'fluentcrm-video-thumbnails');
            $description = sprintf(
                __('Generated from %s using FluentCRM Video Thumbnail Generator', 'fluentcrm-video-thumbnails'),
                $video_source ? sanitize_text_field($video_source) : __('a video', 'fluentcrm-video-thumbnails')
            );
            
            wp_update_post([
                'ID' => $attachment_id,
                'post_title' => $title,
                'post_excerpt' => $description,
                'post_content' => $description,
            ]);
            
            // Add custom meta data
            update_post_meta($attachment_id, '_fluentcrm_video_thumbnail', '1');
            if (!empty($video_id)) {
                update_post_meta($attachment_id, '_fluentcrm_video_source_id', sanitize_text_field($video_id));
            }
            
            // Return success with attachment info
            $attachment_url = wp_get_attachment_url($attachment_id);
            $thumbnail_url = wp_get_attachment_image_url($attachment_id, 'medium');
            
            return new \WP_REST_Response([
                'success' => true,
                'data' => [
                    'id' => $attachment_id,
                    'url' => $attachment_url,
                    'thumbnail' => $thumbnail_url,
                    'title' => $title,
                    'message' => __('Thumbnail saved successfully to Media Library!', 'fluentcrm-video-thumbnails'),
                    'edit_url' => admin_url('post.php?post=' . $attachment_id . '&action=edit'),
                    'view_url' => $attachment_url
                ]
            ]);
        } else {
            return new \WP_REST_Response([
                'success' => false,
                'message' => __('Invalid image format', 'fluentcrm-video-thumbnails')
            ], 400);
        }
    }
    
    /**
     * Scheduled cleanup task
     */
    public static function scheduledCleanup()
    {
        Utils::cleanupTempFiles();
    }
}