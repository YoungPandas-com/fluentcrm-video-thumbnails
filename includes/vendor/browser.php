<?php
/**
 * Browser detection for FluentCRM Video Thumbnail Generator
 * 
 * Simple browser detection class to determine capabilities
 * 
 * @package FluentCrmVideoThumbnails
 */

class Browser {
    private $userAgent;
    private $browserName;
    private $browserVersion;
    private $platform;
    private $isMobile;
    private $isTablet;
    
    /**
     * Constructor
     */
    public function __construct($userAgent = null) {
        $this->userAgent = $userAgent ?: $_SERVER['HTTP_USER_AGENT'] ?? '';
        $this->detectBrowser();
    }
    
    /**
     * Detect browser details from user agent
     */
    private function detectBrowser() {
        $ua = $this->userAgent;
        
        // Detect platform
        if (preg_match('/android/i', $ua)) {
            $this->platform = 'android';
        } elseif (preg_match('/ipad|iphone|ipod/i', $ua)) {
            $this->platform = 'ios';
        } elseif (preg_match('/windows/i', $ua)) {
            $this->platform = 'windows';
        } elseif (preg_match('/macintosh/i', $ua)) {
            $this->platform = 'mac';
        } elseif (preg_match('/linux/i', $ua)) {
            $this->platform = 'linux';
        } else {
            $this->platform = 'unknown';
        }
        
        // Detect mobile/tablet
        $this->isMobile = preg_match('/mobile|android|iphone|ipod|iemobile|blackberry/i', $ua) > 0;
        $this->isTablet = preg_match('/ipad|tablet/i', $ua) > 0;
        
        // Detect browser
        if (preg_match('/MSIE|Trident/i', $ua)) {
            $this->browserName = 'ie';
            preg_match('/(?:MSIE |rv:)(\d+(\.\d+)?)/', $ua, $matches);
            $this->browserVersion = $matches[1] ?? '';
        } elseif (preg_match('/Firefox/i', $ua)) {
            $this->browserName = 'firefox';
            preg_match('/Firefox\/(\d+(\.\d+)?)/', $ua, $matches);
            $this->browserVersion = $matches[1] ?? '';
        } elseif (preg_match('/Chrome/i', $ua) && !preg_match('/Edg/i', $ua)) {
            $this->browserName = 'chrome';
            preg_match('/Chrome\/(\d+(\.\d+)?)/', $ua, $matches);
            $this->browserVersion = $matches[1] ?? '';
        } elseif (preg_match('/Safari/i', $ua) && !preg_match('/Chrome/i', $ua)) {
            $this->browserName = 'safari';
            preg_match('/Version\/(\d+(\.\d+)?)/', $ua, $matches);
            $this->browserVersion = $matches[1] ?? '';
        } elseif (preg_match('/Edg/i', $ua)) {
            $this->browserName = 'edge';
            preg_match('/Edg\/(\d+(\.\d+)?)/', $ua, $matches);
            $this->browserVersion = $matches[1] ?? '';
        } elseif (preg_match('/Opera|OPR/i', $ua)) {
            $this->browserName = 'opera';
            preg_match('/(?:Opera|OPR)\/(\d+(\.\d+)?)/', $ua, $matches);
            $this->browserVersion = $matches[1] ?? '';
        } else {
            $this->browserName = 'unknown';
            $this->browserVersion = '';
        }
    }
    
    /**
     * Check if browser supports WebP
     * 
     * @return bool
     */
    public function supportsWebP() {
        // Chrome 9+, Edge 18+, Firefox 65+, Opera 12.1+
        if (
            ($this->browserName === 'chrome' && (int)$this->browserVersion >= 9) ||
            ($this->browserName === 'edge' && (int)$this->browserVersion >= 18) ||
            ($this->browserName === 'firefox' && (int)$this->browserVersion >= 65) ||
            ($this->browserName === 'opera' && (float)$this->browserVersion >= 12.1)
        ) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Check if the device is mobile
     * 
     * @return bool
     */
    public function isMobile() {
        return $this->isMobile;
    }
    
    /**
     * Check if the device is a tablet
     * 
     * @return bool
     */
    public function isTablet() {
        return $this->isTablet;
    }
    
    /**
     * Get browser name
     * 
     * @return string
     */
    public function getBrowser() {
        return $this->browserName;
    }
    
    /**
     * Get browser version
     * 
     * @return string
     */
    public function getVersion() {
        return $this->browserVersion;
    }
    
    /**
     * Get platform name
     * 
     * @return string
     */
    public function getPlatform() {
        return $this->platform;
    }
}