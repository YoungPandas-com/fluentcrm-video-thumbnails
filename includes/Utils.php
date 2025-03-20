<?php
namespace FluentCrmVideoThumbnails;

/**
 * Utility class for FluentCRM Video Thumbnail Generator
 */
class Utils
{
    /**
     * Get the effective image quality based on dimensions
     *
     * @param int $width Image width
     * @param int $height Image height
     * @return string 'hd', 'sd', or 'low'
     */
    public static function getImageQuality($width, $height)
    {
        if ($width >= 1280 && $height >= 720) {
            return 'hd';
        } else if ($width >= 640 && $height >= 360) {
            return 'sd';
        } else {
            return 'low';
        }
    }
    
    /**
     * Sanitize aspect ratio string
     *
     * @param string $ratio Aspect ratio string
     * @return string Sanitized aspect ratio
     */
    public static function sanitizeAspectRatio($ratio)
    {
        $allowed_ratios = ['16:9', '4:3', '1:1'];
        return in_array($ratio, $allowed_ratios) ? $ratio : '16:9';
    }
    
    /**
     * Get aspect ratio multiplier
     *
     * @param string $ratio Aspect ratio (16:9, 4:3, 1:1)
     * @return float Multiplier value
     */
    public static function getAspectRatioMultiplier($ratio)
    {
        switch ($ratio) {
            case '16:9': return 16/9;
            case '4:3': return 4/3;
            case '1:1': return 1;
            default: return 16/9;
        }
    }
    
    /**
     * Get YouTube video information
     *
     * @param string $video_id YouTube video ID
     * @return array Video information
     */
    public static function getYouTubeInfo($video_id)
    {
        // Try to get maxresdefault first
        $thumbnail_url = 'https://img.youtube.com/vi/' . $video_id . '/maxresdefault.jpg';
        
        // Check if maxresdefault is available by making a HEAD request
        $response = wp_remote_head($thumbnail_url);
        
        // If maxresdefault is not available (404), fallback to hqdefault
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) === 404) {
            $thumbnail_url = 'https://img.youtube.com/vi/' . $video_id . '/hqdefault.jpg';
        }
        
        // Get video title using oEmbed
        $oembed_url = 'https://www.youtube.com/oembed?url=' . urlencode('https://www.youtube.com/watch?v=' . $video_id) . '&format=json';
        $response = wp_remote_get($oembed_url);
        $title = '';
        
        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            $data = json_decode(wp_remote_retrieve_body($response), true);
            if (isset($data['title'])) {
                $title = $data['title'];
            }
        }
        
        // Try to get video dimensions for quality indicator
        list($width, $height) = self::getImageDimensions($thumbnail_url);
        $quality = self::getImageQuality($width, $height);
        
        return [
            'provider' => 'youtube',
            'video_id' => $video_id,
            'title' => $title,
            'thumbnail_url' => $thumbnail_url,
            'fallback_thumbnail' => 'https://img.youtube.com/vi/' . $video_id . '/hqdefault.jpg',
            'sddefault_thumbnail' => 'https://img.youtube.com/vi/' . $video_id . '/sddefault.jpg',
            'mqdefault_thumbnail' => 'https://img.youtube.com/vi/' . $video_id . '/mqdefault.jpg',
            'width' => $width,
            'height' => $height,
            'quality' => $quality,
            'supports_timeframe' => true,
            'suggested_timeframes' => [
                ['time' => 0, 'label' => __('Start', 'fluentcrm-video-thumbnails')],
                ['time' => 'middle', 'label' => __('Middle', 'fluentcrm-video-thumbnails')],
                ['time' => 'end', 'label' => __('End', 'fluentcrm-video-thumbnails')],
            ]
        ];
    }
    
    /**
     * Get Vimeo video information
     *
     * @param string $video_id Vimeo video ID
     * @return array Video information
     */
    public static function getVimeoInfo($video_id)
    {
        // Get video info from Vimeo API
        $vimeo_api_url = 'https://vimeo.com/api/v2/video/' . $video_id . '.json';
        $response = wp_remote_get($vimeo_api_url);
        
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return [
                'provider' => 'vimeo',
                'video_id' => $video_id,
                'title' => '',
                'thumbnail_url' => '',
                'fallback_thumbnail' => '',
                'width' => 0,
                'height' => 0,
                'quality' => 'unknown',
                'supports_timeframe' => false,
                'error' => __('Could not fetch Vimeo video information', 'fluentcrm-video-thumbnails')
            ];
        }
        
        $data = json_decode(wp_remote_retrieve_body($response), true);
        
        if (empty($data[0])) {
            return [
                'provider' => 'vimeo',
                'video_id' => $video_id,
                'title' => '',
                'thumbnail_url' => '',
                'fallback_thumbnail' => '',
                'width' => 0,
                'height' => 0,
                'quality' => 'unknown',
                'supports_timeframe' => false,
                'error' => __('Invalid Vimeo video data', 'fluentcrm-video-thumbnails')
            ];
        }
        
        $video_info = $data[0];
        
        // Attempt to get the highest quality thumbnail
        // Vimeo thumbnail naming convention may change, but typically larger sizes end with larger numbers
        $thumbnail_url = '';
        
        if (!empty($video_info['thumbnail_large'])) {
            // Replace _640 with _1280 to attempt to get higher resolution
            $thumbnail_url = str_replace('_640', '_1280', $video_info['thumbnail_large']);
            
            // Check if high-res thumbnail exists with HEAD request
            $response = wp_remote_head($thumbnail_url);
            if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
                $thumbnail_url = $video_info['thumbnail_large']; // Fallback to large
            }
        } else if (!empty($video_info['thumbnail_medium'])) {
            $thumbnail_url = $video_info['thumbnail_medium'];
        } else if (!empty($video_info['thumbnail_small'])) {
            $thumbnail_url = $video_info['thumbnail_small'];
        }
        
        // Get image dimensions for quality indicator
        list($width, $height) = self::getImageDimensions($thumbnail_url);
        $quality = self::getImageQuality($width, $height);
        
        return [
            'provider' => 'vimeo',
            'video_id' => $video_id,
            'title' => isset($video_info['title']) ? $video_info['title'] : '',
            'thumbnail_url' => $thumbnail_url,
            'fallback_thumbnail' => isset($video_info['thumbnail_large']) ? $video_info['thumbnail_large'] : $thumbnail_url,
            'width' => $width,
            'height' => $height,
            'quality' => $quality,
            'supports_timeframe' => false
        ];
    }
    
    /**
     * Get generic video information using oEmbed
     *
     * @param string $url Video URL
     * @return array Video information
     */
    public static function getGenericVideoInfo($url)
    {
        // Try WordPress built-in oEmbed first
        $wp_oembed = _wp_oembed_get_object();
        $oembed_data = $wp_oembed->get_data($url);
        
        if ($oembed_data && $oembed_data->type === 'video' && !empty($oembed_data->thumbnail_url)) {
            // Get image dimensions for quality indicator
            list($width, $height) = self::getImageDimensions($oembed_data->thumbnail_url);
            $quality = self::getImageQuality($width, $height);
            
            return [
                'provider' => isset($oembed_data->provider_name) ? strtolower($oembed_data->provider_name) : 'generic',
                'video_id' => '',
                'title' => isset($oembed_data->title) ? $oembed_data->title : '',
                'thumbnail_url' => $oembed_data->thumbnail_url,
                'fallback_thumbnail' => $oembed_data->thumbnail_url,
                'width' => $width,
                'height' => $height,
                'quality' => $quality,
                'supports_timeframe' => false
            ];
        }
        
        // Fallback to iframely service
        $oembed_url = 'https://open.iframe.ly/api/oembed?url=' . urlencode($url) . '&origin=fluentcrm';
        $response = wp_remote_get($oembed_url);
        
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return [
                'provider' => 'generic',
                'video_id' => '',
                'title' => '',
                'thumbnail_url' => '',
                'fallback_thumbnail' => '',
                'width' => 0,
                'height' => 0,
                'quality' => 'unknown',
                'supports_timeframe' => false,
                'error' => __('Could not fetch video information', 'fluentcrm-video-thumbnails')
            ];
        }
        
        $data = json_decode(wp_remote_retrieve_body($response), true);
        
        if (empty($data) || $data['type'] !== 'video' || empty($data['thumbnail_url'])) {
            return [
                'provider' => 'generic',
                'video_id' => '',
                'title' => '',
                'thumbnail_url' => '',
                'fallback_thumbnail' => '',
                'width' => 0,
                'height' => 0,
                'quality' => 'unknown',
                'supports_timeframe' => false,
                'error' => __('Not a valid video URL or thumbnail not available', 'fluentcrm-video-thumbnails')
            ];
        }
        
        // Get image dimensions for quality indicator
        list($width, $height) = self::getImageDimensions($data['thumbnail_url']);
        $quality = self::getImageQuality($width, $height);
        
        return [
            'provider' => isset($data['provider_name']) ? strtolower($data['provider_name']) : 'generic',
            'video_id' => '',
            'title' => isset($data['title']) ? $data['title'] : '',
            'thumbnail_url' => $data['thumbnail_url'],
            'fallback_thumbnail' => $data['thumbnail_url'],
            'width' => $width,
            'height' => $height,
            'quality' => $quality,
            'supports_timeframe' => false
        ];
    }
    
    /**
     * Get image dimensions from URL
     *
     * @param string $url Image URL
     * @return array Array with [width, height]
     */
    public static function getImageDimensions($url)
    {
        if (empty($url)) {
            return [0, 0];
        }
        
        // Try to get dimensions without downloading the full image
        $response = wp_remote_head($url);
        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            // Some servers provide dimensions in Content-Disposition header
            $headers = wp_remote_retrieve_headers($response);
            if (!empty($headers['content-disposition'])) {
                if (preg_match('/width=(\d+).*height=(\d+)/', $headers['content-disposition'], $matches)) {
                    return [(int)$matches[1], (int)$matches[2]];
                }
            }
        }
        
        // Fallback to downloading the image and getting its dimensions
        // This is not ideal for performance but we need the dimensions
        $temp_file = download_url($url);
        if (!is_wp_error($temp_file)) {
            $size = getimagesize($temp_file);
            @unlink($temp_file); // Delete the temp file
            
            if ($size) {
                return [$size[0], $size[1]];
            }
        }
        
        // Default dimensions if we can't determine them
        return [0, 0];
    }
    
    /**
     * Clean up temporary files
     */
    public static function cleanupTempFiles()
    {
        $upload_dir = wp_upload_dir();
        $temp_dir = $upload_dir['basedir'] . '/fluentcrm-video-thumbnails';
        
        if (file_exists($temp_dir) && is_dir($temp_dir)) {
            $files = glob($temp_dir . '/*');
            $now = time();
            
            foreach ($files as $file) {
                // Skip .htaccess and index.php
                if (basename($file) === '.htaccess' || basename($file) === 'index.php') {
                    continue;
                }
                
                // Delete files older than 24 hours
                if ($now - filemtime($file) >= 86400) {
                    @unlink($file);
                }
            }
        }
    }
    
    /**
     * Check if browser supports WebP
     * 
     * @return bool
     */
    public static function browserSupportsWebP()
    {
        // Check Accept header for webp
        if (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'image/webp') !== false) {
            return true;
        }
        
        // Check User-Agent for browsers known to support WebP
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
        
        // Chrome 9+, Opera 12.1+, Firefox 65+
        if (
            (strpos($user_agent, 'Chrome/') && preg_match('/Chrome\/(\d+)/', $user_agent, $matches) && (int) $matches[1] >= 9) ||
            (strpos($user_agent, 'Opera/') && preg_match('/Version\/(\d+\.\d+)/', $user_agent, $matches) && (float) $matches[1] >= 12.1) ||
            (strpos($user_agent, 'Firefox/') && preg_match('/Firefox\/(\d+)/', $user_agent, $matches) && (int) $matches[1] >= 65)
        ) {
            return true;
        }
        
        return false;
    }
}