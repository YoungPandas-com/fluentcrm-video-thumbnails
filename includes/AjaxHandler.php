<?php
namespace FluentCrmVideoThumbnails;

/**
 * AJAX Handler for FluentCRM Video Thumbnail Generator
 */
class AjaxHandler
{
    /**
     * Initialize AJAX handlers
     */
    public static function init()
    {
        // Register AJAX endpoints with proper security
        add_action('wp_ajax_fluentcrm_save_video_thumbnail', [__CLASS__, 'saveVideoThumbnail']);
        add_action('wp_ajax_fluentcrm_fetch_video_info', [__CLASS__, 'fetchVideoInfo']);
        add_action('wp_ajax_fluentcrm_dismiss_video_thumbnails_notice', [__CLASS__, 'dismissActivationNotice']);
        
        // Add filter for CORS headers to allow cross-domain image loading
        add_filter('allowed_http_origins', [__CLASS__, 'allowVideoOrigins']);
    }
    
    /**
     * Save video thumbnail to WordPress media library
     */
    public static function saveVideoThumbnail()
    {
        try {
            // Verify nonce
            if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'fluentcrm-video-thumbnails')) {
                wp_send_json_error(['message' => __('Security validation failed', 'fluentcrm-video-thumbnails'), 'code' => 'invalid_nonce'], 403);
                return;
            }
            
            // Check user capabilities
            if (!current_user_can('upload_files')) {
                wp_send_json_error(['message' => __('You do not have permission to upload files', 'fluentcrm-video-thumbnails'), 'code' => 'insufficient_permissions'], 403);
                return;
            }
            
            // Check if file data was sent
            if (empty($_POST['thumbnail_data'])) {
                wp_send_json_error(['message' => __('No thumbnail data received', 'fluentcrm-video-thumbnails'), 'code' => 'no_data']);
                return;
            }
            
            $thumbnail_data = sanitize_text_field($_POST['thumbnail_data']);
            $video_title = isset($_POST['video_title']) ? sanitize_text_field($_POST['video_title']) : '';
            $video_source = isset($_POST['video_source']) ? sanitize_text_field($_POST['video_source']) : '';
            $video_id = isset($_POST['video_id']) ? sanitize_text_field($_POST['video_id']) : '';
            
            // Decode base64 image data
            if (preg_match('/^data:image\/(\w+);base64,/', $thumbnail_data, $matches)) {
                $image_type = $matches[1];
                
                // Validate image type
                if (!in_array($image_type, ['jpeg', 'jpg', 'png', 'webp'])) {
                    wp_send_json_error(['message' => __('Unsupported image format. Please use JPEG, PNG, or WebP.', 'fluentcrm-video-thumbnails'), 'code' => 'invalid_format']);
                    return;
                }
                
                $base64_data = substr($thumbnail_data, strpos($thumbnail_data, ',') + 1);
                $decoded_data = base64_decode($base64_data);
                
                if ($decoded_data === false) {
                    wp_send_json_error(['message' => __('Invalid image data', 'fluentcrm-video-thumbnails'), 'code' => 'invalid_data']);
                    return;
                }
                
                // Check file size before saving
                $data_size = strlen($decoded_data);
                $max_size = wp_max_upload_size();
                if ($data_size > $max_size) {
                    wp_send_json_error([
                        'message' => sprintf(
                            __('Image size (%s) exceeds maximum upload size (%s). Try reducing quality or dimensions.', 'fluentcrm-video-thumbnails'),
                            size_format($data_size),
                            size_format($max_size)
                        ),
                        'code' => 'file_too_large'
                    ]);
                    return;
                }
                
                // Create a temporary file with proper security
                $upload_dir = wp_upload_dir();
                $temp_dir = $upload_dir['basedir'] . '/fluentcrm-video-thumbnails';
                
                if (!file_exists($temp_dir)) {
                    wp_mkdir_p($temp_dir);
                    
                    // Create index.php for security
                    if (!file_exists($temp_dir . '/index.php')) {
                        file_put_contents($temp_dir . '/index.php', '<?php // Silence is golden');
                    }
                }
                
                // Generate a unique filename with a random prefix for security
                $random_prefix = wp_generate_password(8, false);
                $filename = sanitize_file_name('video-thumbnail-' . $random_prefix . '-' . time() . '.' . $image_type);
                $temp_file = $temp_dir . '/' . $filename;
                
                // Save the image with proper permissions
                $saved = file_put_contents($temp_file, $decoded_data);
                if (!$saved) {
                    wp_send_json_error(['message' => __('Failed to save thumbnail temporarily. Check directory permissions.', 'fluentcrm-video-thumbnails'), 'code' => 'save_failed']);
                    return;
                }
                
                // Set proper file permissions
                chmod($temp_file, 0644);
                
                // Prepare file for uploading to WordPress media library
                $file_array = [
                    'name' => $filename,
                    'tmp_name' => $temp_file,
                    'error' => 0,
                    'size' => filesize($temp_file),
                ];
                
                // Ensure required WordPress functions are available
                require_once(ABSPATH . 'wp-admin/includes/file.php');
                require_once(ABSPATH . 'wp-admin/includes/image.php');
                require_once(ABSPATH . 'wp-admin/includes/media.php');
                
                // Use the normal upload directory
                add_filter('upload_dir', function($uploads) use ($upload_dir) {
                    return $upload_dir;
                });
                
                // Upload the file to WordPress media library
                $attachment_id = media_handle_sideload($file_array, 0);
                
                // Remove the filter
                remove_all_filters('upload_dir');
                
                // Delete the temporary file regardless of the outcome
                @unlink($temp_file);
                
                if (is_wp_error($attachment_id)) {
                    wp_send_json_error([
                        'message' => $attachment_id->get_error_message(),
                        'code' => 'media_upload_failed'
                    ]);
                    return;
                }
                
                // Update attachment metadata with custom fields
                $title = !empty($video_title) ? $video_title : __('Video Thumbnail', 'fluentcrm-video-thumbnails');
                $description = sprintf(
                    __('Generated from %s using FluentCRM Video Thumbnail Generator', 'fluentcrm-video-thumbnails'),
                    $video_source ?: __('a video', 'fluentcrm-video-thumbnails')
                );
                
                $update_result = wp_update_post([
                    'ID' => $attachment_id,
                    'post_title' => $title,
                    'post_excerpt' => $description,
                    'post_content' => $description,
                ]);
                
                if (is_wp_error($update_result)) {
                    wp_send_json_error([
                        'message' => $update_result->get_error_message(),
                        'code' => 'update_failed'
                    ]);
                    return;
                }
                
                // Add custom meta data
                update_post_meta($attachment_id, '_fluentcrm_video_thumbnail', '1');
                update_post_meta($attachment_id, '_fluentcrm_video_thumbnail_created', current_time('mysql'));
                
                if (!empty($video_id)) {
                    update_post_meta($attachment_id, '_fluentcrm_video_source_id', sanitize_text_field($video_id));
                }
                
                if (!empty($video_source)) {
                    update_post_meta($attachment_id, '_fluentcrm_video_source', sanitize_text_field($video_source));
                }
                
                // Get image dimensions for UI display
                $image_path = get_attached_file($attachment_id);
                $image_size = @getimagesize($image_path);
                $dimensions = $image_size ? $image_size[0] . 'x' . $image_size[1] : '';
                $quality = $image_size ? Utils::getImageQuality($image_size[0], $image_size[1]) : '';
                
                // Generate attachment edit URL
                $edit_url = admin_url('post.php?post=' . $attachment_id . '&action=edit');
                
                // Return success with comprehensive attachment info
                $attachment_url = wp_get_attachment_url($attachment_id);
                $thumbnail_url = wp_get_attachment_image_url($attachment_id, 'medium');
                
                wp_send_json_success([
                    'id' => $attachment_id,
                    'url' => $attachment_url,
                    'thumbnail' => $thumbnail_url,
                    'title' => $title,
                    'dimensions' => $dimensions,
                    'size' => size_format(@filesize($image_path)),
                    'quality' => $quality,
                    'edit_url' => $edit_url,
                    'mime_type' => get_post_mime_type($attachment_id),
                    'date_created' => get_the_date('Y-m-d H:i:s', $attachment_id),
                    'message' => __('Thumbnail saved successfully to Media Library!', 'fluentcrm-video-thumbnails'),
                ]);
                
            } else {
                wp_send_json_error([
                    'message' => __('Invalid image format. Base64 encoded image data required.', 'fluentcrm-video-thumbnails'),
                    'code' => 'invalid_format'
                ]);
            }
        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage(),
                'code' => 'exception',
                'trace' => WP_DEBUG ? $e->getTraceAsString() : null
            ]);
        }
    }
    
    /**
     * Fetch information about a video URL
     */
    public static function fetchVideoInfo()
    {
        try {
            // Verify nonce
            if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'fluentcrm-video-thumbnails')) {
                wp_send_json_error([
                    'message' => __('Security validation failed', 'fluentcrm-video-thumbnails'),
                    'code' => 'invalid_nonce'
                ], 403);
                return;
            }
            
            // Check if URL was provided
            if (empty($_POST['video_url'])) {
                wp_send_json_error([
                    'message' => __('No video URL provided', 'fluentcrm-video-thumbnails'),
                    'code' => 'missing_url'
                ]);
                return;
            }
            
            $video_url = esc_url_raw($_POST['video_url']);
            
            // Check for YouTube
            if (self::isYouTubeUrl($video_url)) {
                $video_id = self::extractYouTubeId($video_url);
                if (!$video_id) {
                    wp_send_json_error([
                        'message' => __('Invalid YouTube URL', 'fluentcrm-video-thumbnails'),
                        'code' => 'invalid_youtube_url'
                    ]);
                    return;
                }
                
                $info = Utils::getYouTubeInfo($video_id);
                wp_send_json_success($info);
            } 
            // Check for Vimeo
            else if (self::isVimeoUrl($video_url)) {
                $video_id = self::extractVimeoId($video_url);
                if (!$video_id) {
                    wp_send_json_error([
                        'message' => __('Invalid Vimeo URL', 'fluentcrm-video-thumbnails'),
                        'code' => 'invalid_vimeo_url'
                    ]);
                    return;
                }
                
                $info = Utils::getVimeoInfo($video_id);
                if (isset($info['error'])) {
                    wp_send_json_error([
                        'message' => $info['error'],
                        'code' => 'vimeo_api_error'
                    ]);
                    return;
                }
                
                wp_send_json_success($info);
            } 
            // Handle other URLs with generic providers
            else {
                $info = Utils::getGenericVideoInfo($video_url);
                if (isset($info['error'])) {
                    wp_send_json_error([
                        'message' => $info['error'],
                        'code' => 'generic_video_error'
                    ]);
                    return;
                }
                
                wp_send_json_success($info);
            }
        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage(),
                'code' => 'exception',
                'trace' => WP_DEBUG ? $e->getTraceAsString() : null
            ]);
        }
    }
    
    /**
     * Dismiss the activation notice
     */
    public static function dismissActivationNotice()
    {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'fluentcrm-video-thumbnails-dismiss-notice')) {
            wp_send_json_error('Invalid security token', 403);
            return;
        }
        
        delete_option('fluentcrm_video_thumbnails_activated');
        wp_send_json_success();
    }
    
    /**
     * Allow cross-origin requests for video services
     * 
     * @param array $origins Allowed origins
     * @return array Modified origins
     */
    public static function allowVideoOrigins($origins)
    {
        $video_origins = [
            'https://www.youtube.com',
            'https://youtube.com',
            'https://img.youtube.com',
            'https://vimeo.com',
            'https://i.vimeocdn.com',
            'https://player.vimeo.com'
        ];
        
        return array_merge($origins, $video_origins);
    }
    
    /**
     * Check if URL is a YouTube URL
     * 
     * @param string $url URL to check
     * @return bool True if YouTube URL
     */
    public static function isYouTubeUrl($url)
    {
        return (bool) preg_match('#^https?://(?:www\.|m\.)?(?:youtube\.com|youtu\.be)/#i', $url);
    }
    
    /**
     * Extract YouTube video ID from URL
     * 
     * @param string $url YouTube URL
     * @return string|false Video ID or false if not found
     */
    public static function extractYouTubeId($url)
    {
        // Support various YouTube URL formats
        $patterns = [
            // Standard watch URLs
            '#(?:https?://)?(?:www\.|m\.)?youtube\.com/watch\?(?:[^&]+&)*v=([\w-]+)(?:&[^&]+)*#i',
            // Short URLs
            '#(?:https?://)?(?:www\.|m\.)?youtu\.be/([\w-]+)(?:\?[^&]+)?#i',
            // Embed URLs
            '#(?:https?://)?(?:www\.|m\.)?youtube\.com/embed/([\w-]+)(?:\?[^&]+)?#i',
            // Shortened URLs with features
            '#(?:https?://)?(?:www\.|m\.)?youtube\.com/v/([\w-]+)(?:[?&][^&]+)?#i',
            // Youtube shorts
            '#(?:https?://)?(?:www\.|m\.)?youtube\.com/shorts/([\w-]+)(?:[?&][^&]+)?#i',
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url, $matches)) {
                return $matches[1];
            }
        }
        
        return false;
    }
    
    /**
     * Check if URL is a Vimeo URL
     * 
     * @param string $url URL to check
     * @return bool True if Vimeo URL
     */
    public static function isVimeoUrl($url)
    {
        return (bool) preg_match('#^https?://(?:www\.|player\.)?vimeo\.com/#i', $url);
    }
    
    /**
     * Extract Vimeo video ID from URL
     * 
     * @param string $url Vimeo URL
     * @return string|false Video ID or false if not found
     */
    public static function extractVimeoId($url)
    {
        // Support various Vimeo URL formats
        $patterns = [
            // Standard URLs
            '#(?:https?://)?(?:www\.)?vimeo\.com/(\d+)(?:/|\?|$)#i',
            // Channel URLs
            '#(?:https?://)?(?:www\.)?vimeo\.com/channels/(?:[\w-]+)/(\d+)(?:/|\?|$)#i',
            // Group URLs
            '#(?:https?://)?(?:www\.)?vimeo\.com/groups/(?:[\w-]+)/videos/(\d+)(?:/|\?|$)#i',
            // Album URLs
            '#(?:https?://)?(?:www\.)?vimeo\.com/album/(?:[\w-]+)/video/(\d+)(?:/|\?|$)#i',
            // Player URLs
            '#(?:https?://)?(?:www\.)?player\.vimeo\.com/video/(\d+)(?:/|\?|$)#i',
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url, $matches)) {
                return $matches[1];
            }
        }
        
        return false;
    }
}