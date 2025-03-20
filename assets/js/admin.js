/**
 * FluentCRM Video Thumbnail Generator
 * Main application script
 * 
 * @since 1.0.0
 */

(function (wp, React) {
    'use strict';
    
    const { useState, useEffect, useRef, useCallback } = React;
    const { 
        Button, 
        ToggleControl, 
        RangeControl, 
        SelectControl, 
        Spinner,
        Modal,
        Panel,
        PanelBody,
        PanelRow,
        TextControl,
        RadioControl,
        Tooltip,
        Snackbar,
        Notice,
        CheckboxControl
    } = wp.components;
    const { render } = wp.element;
    
    // Constants
    const MAX_HISTORY_STEPS = 20;
    const PLAY_BUTTON_STYLES = {
        classic: {
            name: 'Classic',
            draw: (ctx, x, y, size) => {
                // Circle with triangle
                ctx.beginPath();
                ctx.arc(x, y, size, 0, 2 * Math.PI, false);
                ctx.fillStyle = 'rgba(0, 0, 0, 0.7)';
                ctx.fill();
                
                // Draw play triangle
                ctx.beginPath();
                ctx.moveTo(x + size/2, y);
                ctx.lineTo(x - size/3, y - size/2);
                ctx.lineTo(x - size/3, y + size/2);
                ctx.closePath();
                ctx.fillStyle = 'white';
                ctx.fill();
            }
        },
        modern: {
            name: 'Modern',
            draw: (ctx, x, y, size) => {
                // Rounded rectangle
                const rectSize = size * 1.5;
                const cornerRadius = size / 4;
                
                ctx.fillStyle = 'rgba(0, 0, 0, 0.7)';
                ctx.beginPath();
                ctx.moveTo(x - rectSize/2 + cornerRadius, y - rectSize/3);
                ctx.lineTo(x + rectSize/2 - cornerRadius, y - rectSize/3);
                ctx.quadraticCurveTo(x + rectSize/2, y - rectSize/3, x + rectSize/2, y - rectSize/3 + cornerRadius);
                ctx.lineTo(x + rectSize/2, y + rectSize/3 - cornerRadius);
                ctx.quadraticCurveTo(x + rectSize/2, y + rectSize/3, x + rectSize/2 - cornerRadius, y + rectSize/3);
                ctx.lineTo(x - rectSize/2 + cornerRadius, y + rectSize/3);
                ctx.quadraticCurveTo(x - rectSize/2, y + rectSize/3, x - rectSize/2, y + rectSize/3 - cornerRadius);
                ctx.lineTo(x - rectSize/2, y - rectSize/3 + cornerRadius);
                ctx.quadraticCurveTo(x - rectSize/2, y - rectSize/3, x - rectSize/2 + cornerRadius, y - rectSize/3);
                ctx.closePath();
                ctx.fill();
                
                // Draw play triangle
                ctx.beginPath();
                ctx.moveTo(x + size/3, y);
                ctx.lineTo(x - size/4, y - size/3);
                ctx.lineTo(x - size/4, y + size/3);
                ctx.closePath();
                ctx.fillStyle = 'white';
                ctx.fill();
            }
        },
        minimal: {
            name: 'Minimal',
            draw: (ctx, x, y, size) => {
                // Just a triangle
                ctx.beginPath();
                const triangleSize = size * 1.2;
                ctx.moveTo(x + triangleSize/2, y);
                ctx.lineTo(x - triangleSize/2, y - triangleSize/2);
                ctx.lineTo(x - triangleSize/2, y + triangleSize/2);
                ctx.closePath();
                ctx.fillStyle = 'rgba(255, 255, 255, 0.9)';
                ctx.fill();
                ctx.strokeStyle = 'rgba(0, 0, 0, 0.5)';
                ctx.lineWidth = 2;
                ctx.stroke();
            }
        },
        youtube: {
            name: 'YouTube Style',
            draw: (ctx, x, y, size) => {
                // YouTube-like play button
                const outerRadius = size * 1.2;
                const innerRadius = size;
                
                // Outer circle (semi-transparent white)
                ctx.beginPath();
                ctx.arc(x, y, outerRadius, 0, 2 * Math.PI, false);
                ctx.fillStyle = 'rgba(255, 255, 255, 0.9)';
                ctx.fill();
                
                // Inner circle (red)
                ctx.beginPath();
                ctx.arc(x, y, innerRadius, 0, 2 * Math.PI, false);
                ctx.fillStyle = '#FF0000';
                ctx.fill();
                
                // Play triangle (white)
                ctx.beginPath();
                ctx.moveTo(x + innerRadius/2, y);
                ctx.lineTo(x - innerRadius/4, y - innerRadius/2);
                ctx.lineTo(x - innerRadius/4, y + innerRadius/2);
                ctx.closePath();
                ctx.fillStyle = 'white';
                ctx.fill();
            }
        }
    };
    
    // Utility functions
    const debounce = (func, wait, immediate) => {
        let timeout;
        return function() {
            const context = this, args = arguments;
            const later = function() {
                timeout = null;
                if (!immediate) func.apply(context, args);
            };
            const callNow = immediate && !timeout;
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
            if (callNow) func.apply(context, args);
        };
    };
    
    /**
     * Main Video Thumbnail Generator Component
     */
    const VideoThumbnailGenerator = () => {
        // App state
        const [videoSource, setVideoSource] = useState('external'); // 'external' or 'media'
        const [videoUrl, setVideoUrl] = useState('');
        const [videoTitle, setVideoTitle] = useState('');
        const [mediaId, setMediaId] = useState(null);
        const [thumbnailUrl, setThumbnailUrl] = useState('');
        const [originalThumbnailUrl, setOriginalThumbnailUrl] = useState('');
        const [timeframe, setTimeframe] = useState(0);
        const [duration, setDuration] = useState(0);
        const [isEditor, setIsEditor] = useState(false);
        
        // Editor state
        const [brightness, setBrightness] = useState(100);
        const [contrast, setContrast] = useState(100);
        const [saturation, setSaturation] = useState(100);
        const [aspectRatio, setAspectRatio] = useState('16:9');
        const [showPlayButton, setShowPlayButton] = useState(true);
        const [playButtonStyle, setPlayButtonStyle] = useState('classic');
        const [playButtonSize, setPlayButtonSize] = useState('medium');
        const [imageQuality, setImageQuality] = useState(95);
        const [imageFormat, setImageFormat] = useState('jpeg');
        
        // UI state
        const [isProcessing, setIsProcessing] = useState(false);
        const [networkStatus, setNetworkStatus] = useState('idle'); // 'idle', 'loading', 'success', 'error'
        const [error, setError] = useState('');
        const [success, setSuccess] = useState('');
        const [notification, setNotification] = useState('');
        const [videoInfo, setVideoInfo] = useState(null);
        const [thumbnailHistory, setThumbnailHistory] = useState([]);
        const [historyIndex, setHistoryIndex] = useState(-1);
        const [showConfirmation, setShowConfirmation] = useState(false);
        const [confirmationAction, setConfirmationAction] = useState(() => () => {});
        const [confirmationMessage, setConfirmationMessage] = useState('');
        const [isFullscreenPreview, setIsFullscreenPreview] = useState(false);
        const [savedThumbnailInfo, setSavedThumbnailInfo] = useState(null);
        const [isCopied, setIsCopied] = useState(false);
        
        // Refs
        const videoRef = useRef(null);
        const canvasRef = useRef(null);
        const editedCanvasRef = useRef(null);
        const cropperRef = useRef(null);
        
        // Get translation strings from localization data
        const i18n = window.fluentCrmVideoThumbnails.i18n;
        
        // Browser capabilities
        const supportsWebP = window.fluentCrmVideoThumbnails.supportsWebP;
        const isMobile = window.fluentCrmVideoThumbnails.isMobile;
        const isDebug = window.fluentCrmVideoThumbnails.isDebug;
        
        /**
         * Reset the confirmation dialog
         */
        const resetConfirmation = () => {
            setShowConfirmation(false);
            setConfirmationAction(() => () => {});
            setConfirmationMessage('');
        };
        
        /**
         * Show a notification message that disappears after a delay
         * 
         * @param {string} message Notification message
         * @param {string} type Type of notification ('success', 'error', 'info')
         * @param {number} timeout Timeout in milliseconds
         */
        const showNotification = (message, type = 'info', timeout = 3000) => {
            setNotification({ message, type });
            
            setTimeout(() => {
                setNotification('');
            }, timeout);
        };
        
        /**
         * Log to console in debug mode
         * 
         * @param {string} message Debug message
         * @param {any} data Optional data to log
         */
        const debugLog = (message, data = null) => {
            if (isDebug) {
                if (data) {
                    console.log(`[FluentCRM Video Thumbnails] ${message}`, data);
                } else {
                    console.log(`[FluentCRM Video Thumbnails] ${message}`);
                }
            }
        };
        
        /**
         * Handle video source change
         * 
         * @param {string} newSource New video source ('external' or 'media')
         */
        const handleSourceChange = (newSource) => {
            if (isProcessing) return;
            
            if (thumbnailUrl && !showConfirmation) {
                setConfirmationMessage(i18n.confirmReset);
                setConfirmationAction(() => () => {
                    resetEditor();
                    setVideoSource(newSource);
                    resetConfirmation();
                });
                setShowConfirmation(true);
                return;
            }
            
            resetEditor();
            setVideoSource(newSource);
        };
        
        /**
         * Reset the editor state
         */
        const resetEditor = () => {
            // Reset video info
            setVideoUrl('');
            setVideoTitle('');
            setMediaId(null);
            setThumbnailUrl('');
            setOriginalThumbnailUrl('');
            setError('');
            setSuccess('');
            setVideoInfo(null);
            setTimeframe(0);
            
            // Reset editor state
            setIsEditor(false);
            setBrightness(100);
            setContrast(100);
            setSaturation(100);
            setAspectRatio('16:9');
            setShowPlayButton(true);
            setPlayButtonStyle('classic');
            setPlayButtonSize('medium');
            
            // Reset history
            setThumbnailHistory([]);
            setHistoryIndex(-1);
            
            // Reset UI state
            setIsProcessing(false);
            setNetworkStatus('idle');
            setSavedThumbnailInfo(null);
        };
        
        /**
         * Handle external video URL input
         * 
         * @param {string} url Video URL
         */
        const handleVideoUrlChange = (url) => {
            setVideoUrl(url);
            setError('');
            setSuccess('');
            
            if (!url) {
                setThumbnailUrl('');
                setVideoInfo(null);
                return;
            }
            
            // Debounced video fetch to avoid too many requests
            debouncedFetchVideo(url);
        };
        
        /**
         * Debounced function to fetch video info
         */
        const debouncedFetchVideo = useCallback(
            debounce((url) => {
                if (url && url.trim()) {
                    fetchVideoInfo(url);
                }
            }, 800),
            []
        );
        
        /**
         * Fetch video information from server
         * 
         * @param {string} url Video URL
         */
        const fetchVideoInfo = async (url) => {
            try {
                setIsProcessing(true);
                setNetworkStatus('loading');
                setError('');
                
                debugLog('Fetching video info for:', url);
                
                // Use REST API with proper error handling
                const response = await fetch(window.fluentCrmVideoThumbnails.restApiUrl + '/fetch-video-info', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': window.fluentCrmVideoThumbnails.restNonce
                    },
                    body: JSON.stringify({
                        video_url: url,
                        nonce: window.fluentCrmVideoThumbnails.nonce
                    })
                });
                
                // Handle HTTP errors
                if (!response.ok) {
                    let errorMsg = i18n.networkError;
                    
                    try {
                        const errorData = await response.json();
                        if (errorData && errorData.message) {
                            errorMsg = errorData.message;
                        }
                    } catch (e) {
                        debugLog('Error parsing error response', e);
                    }
                    
                    throw new Error(errorMsg);
                }
                
                const result = await response.json();
                
                if (result.success) {
                    debugLog('Video info received', result.data);
                    setVideoInfo(result.data);
                    setVideoTitle(result.data.title || i18n.thumbnail);
                    setThumbnailUrl(result.data.thumbnail_url);
                    setOriginalThumbnailUrl(result.data.thumbnail_url);
                    
                    // Add to history
                    setThumbnailHistory([result.data.thumbnail_url]);
                    setHistoryIndex(0);
                    
                    // Start editor mode
                    setIsEditor(true);
                    setNetworkStatus('success');
                } else {
                    throw new Error(result.message || i18n.invalidUrl);
                }
            } catch (err) {
                debugLog('Error fetching video info', err);
                setError(err.message || i18n.invalidUrl);
                setNetworkStatus('error');
            } finally {
                setIsProcessing(false);
            }
        };
        
        /**
         * Handle WordPress Media Library video selection
         */
        const handleMediaSelection = () => {
            if (isProcessing) return;
            
            const mediaFrame = wp.media({
                title: i18n.selectVideo,
                button: {
                    text: i18n.selectVideo
                },
                library: {
                    type: 'video'
                },
                multiple: false
            });
            
            mediaFrame.on('select', () => {
                try {
                    const attachment = mediaFrame.state().get('selection').first().toJSON();
                    debugLog('Selected media', attachment);
                    
                    // Check if it's a supported video format
                    const fileExt = attachment.url.split('.').pop().toLowerCase();
                    const supportedFormats = window.fluentCrmVideoThumbnails.supportedVideoFormats;
                    
                    if (!supportedFormats[fileExt]) {
                        setError(i18n.unsupportedVideoFormat);
                        return;
                    }
                    
                    setMediaId(attachment.id);
                    setVideoUrl(attachment.url);
                    setVideoTitle(attachment.title || i18n.thumbnail);
                    
                    // Reset other states
                    setThumbnailUrl('');
                    setOriginalThumbnailUrl('');
                    setError('');
                    setSuccess('');
                    setVideoInfo(null);
                    
                    // Reset history
                    setThumbnailHistory([]);
                    setHistoryIndex(-1);
                } catch (error) {
                    debugLog('Error selecting media', error);
                    setError(i18n.mediaLibraryError);
                }
            });
            
            mediaFrame.open();
        };
        
        /**
         * Calculate play button size based on selection
         * 
         * @param {string} size Size selection ('small', 'medium', 'large')
         * @param {number} width Image width
         * @param {number} height Image height 
         * @returns {number} Size in pixels
         */
        const calculatePlayButtonSize = (size, width, height) => {
            const minDimension = Math.min(width, height);
            
            switch (size) {
                case 'small':
                    return minDimension * 0.1;
                case 'large':
                    return minDimension * 0.2;
                case 'medium':
                default:
                    return minDimension * 0.15;
            }
        };
        
        /**
         * Capture frame from video at current time
         */
        const captureFrame = () => {
            if (!videoRef.current) {
                setError(i18n.selectVideoFirst);
                return;
            }
            
            try {
                setIsProcessing(true);
                
                const video = videoRef.current;
                const canvas = canvasRef.current;
                const context = canvas.getContext('2d');
                
                // Set canvas dimensions based on aspect ratio
                const aspectRatioMultiplier = getAspectRatioMultiplier(aspectRatio);
                let width = video.videoWidth;
                let height = width / aspectRatioMultiplier;
                
                // Ensure we don't exceed max dimensions 
                // (for performance and file size reasons)
                const maxDimension = 1920; // Max width or height
                if (width > maxDimension) {
                    width = maxDimension;
                    height = width / aspectRatioMultiplier;
                }
                
                canvas.width = width;
                canvas.height = height;
                
                // Calculate vertical position for center crop
                const offsetY = (video.videoHeight - height) / 2;
                
                // Clear canvas
                context.clearRect(0, 0, width, height);
                
                // Draw the video frame to the canvas, cropping if necessary
                context.drawImage(
                    video, 
                    0, 
                    offsetY > 0 ? offsetY : 0, 
                    width, 
                    height, 
                    0, 
                    0, 
                    width, 
                    height
                );
                
                // Apply the captured frame as our thumbnail
                const imageFormat = supportsWebP ? 'webp' : 'jpeg';
                const quality = imageQuality / 100;
                const newThumbnail = canvas.toDataURL(`image/${imageFormat}`, quality);
                
                setThumbnailUrl(newThumbnail);
                setOriginalThumbnailUrl(newThumbnail);
                
                // Add to history
                addToHistory(newThumbnail);
                
                // Start editor mode
                setIsEditor(true);
                showNotification(i18n.capturing, 'success');
            } catch (error) {
                debugLog('Error capturing frame', error);
                setError(error.message || 'Error capturing frame');
            } finally {
                setIsProcessing(false);
            }
        };
        
        /**
         * Apply edits to the thumbnail (brightness, contrast, etc.)
         */
        const applyEdits = useCallback(() => {
            if (!originalThumbnailUrl) return;
            
            try {
                const img = new Image();
                img.crossOrigin = "Anonymous";
                
                img.onload = () => {
                    // Create a canvas for editing
                    const canvas = document.createElement('canvas');
                    const ctx = canvas.getContext('2d');
                    
                    // Set canvas dimensions based on aspect ratio
                    const aspectRatioMultiplier = getAspectRatioMultiplier(aspectRatio);
                    const width = img.width;
                    const height = width / aspectRatioMultiplier;
                    
                    canvas.width = width;
                    canvas.height = height;
                    
                    // Calculate vertical position for center crop
                    const offsetY = (img.height - height) / 2;
                    
                    // Apply brightness, contrast, and saturation filters
                    ctx.filter = `brightness(${brightness}%) contrast(${contrast}%) saturate(${saturation}%)`;
                    
                    // Clear canvas
                    ctx.clearRect(0, 0, width, height);
                    
                    // Draw the image with filters applied, cropping if necessary
                    ctx.drawImage(
                        img, 
                        0, 
                        offsetY > 0 ? offsetY : 0, 
                        width, 
                        height, 
                        0, 
                        0, 
                        width, 
                        height
                    );
                    
                    // Add play button overlay if enabled
                    if (showPlayButton) {
                        const size = calculatePlayButtonSize(playButtonSize, width, height);
                        const centerX = width / 2;
                        const centerY = height / 2;
                        
                        // Get the selected play button style
                        const buttonStyle = PLAY_BUTTON_STYLES[playButtonStyle] || PLAY_BUTTON_STYLES.classic;
                        buttonStyle.draw(ctx, centerX, centerY, size);
                    }
                    
                    // Update the displayed thumbnail with edits applied
                    const format = supportsWebP && imageFormat === 'webp' ? 'webp' : 'jpeg';
                    const quality = imageQuality / 100;
                    const editedThumbnail = canvas.toDataURL(`image/${format}`, quality);
                    
                    setThumbnailUrl(editedThumbnail);
                    
                    // Add to history if it's a user action (not an auto-update from prop changes)
                    if (thumbnailHistory.length > 0 && thumbnailHistory[thumbnailHistory.length - 1] !== editedThumbnail) {
                        addToHistory(editedThumbnail);
                    }
                };
                
                img.onerror = (error) => {
                    debugLog('Error loading image for editing', error);
                    setError(i18n.error);
                };
                
                img.src = originalThumbnailUrl;
            } catch (error) {
                debugLog('Error applying edits', error);
                setError(error.message || i18n.error);
            }
        }, [
            originalThumbnailUrl, 
            brightness, 
            contrast, 
            saturation, 
            aspectRatio, 
            showPlayButton, 
            playButtonStyle,
            playButtonSize,
            imageQuality,
            imageFormat
        ]);
        
        /**
         * Add thumbnail to history
         * 
         * @param {string} thumbnail Thumbnail data URL
         */
        const addToHistory = (thumbnail) => {
            if (thumbnail === thumbnailHistory[historyIndex]) {
                return; // Don't add duplicates
            }
            
            let newHistory;
            let newIndex;
            
            if (historyIndex < thumbnailHistory.length - 1) {
                // If we're in the middle of the history, truncate forward history
                newHistory = [...thumbnailHistory.slice(0, historyIndex + 1), thumbnail];
                newIndex = historyIndex + 1;
            } else {
                // Add to the end of history
                newHistory = [...thumbnailHistory, thumbnail];
                newIndex = newHistory.length - 1;
            }
            
            // Limit history size to prevent memory issues
            if (newHistory.length > MAX_HISTORY_STEPS) {
                newHistory = newHistory.slice(newHistory.length - MAX_HISTORY_STEPS);
                newIndex = newHistory.length - 1;
            }
            
            setThumbnailHistory(newHistory);
            setHistoryIndex(newIndex);
        };
        
        /**
         * Undo last edit
         */
        const handleUndo = () => {
            if (historyIndex > 0) {
                setHistoryIndex(historyIndex - 1);
                setThumbnailUrl(thumbnailHistory[historyIndex - 1]);
            }
        };
        
        /**
         * Redo last undone edit
         */
        const handleRedo = () => {
            if (historyIndex < thumbnailHistory.length - 1) {
                setHistoryIndex(historyIndex + 1);
                setThumbnailUrl(thumbnailHistory[historyIndex + 1]);
            }
        };
        
        /**
         * Reset adjustments with confirmation
         */
        const handleResetAdjustments = () => {
            if (!showConfirmation) {
                setConfirmationMessage(i18n.confirmReset);
                setConfirmationAction(() => () => {
                    resetAdjustments();
                    resetConfirmation();
                });
                setShowConfirmation(true);
            }
        };
        
        /**
         * Reset adjustments
         */
        const resetAdjustments = () => {
            setBrightness(100);
            setContrast(100);
            setSaturation(100);
            setAspectRatio('16:9');
            setShowPlayButton(true);
            setPlayButtonStyle('classic');
            setPlayButtonSize('medium');
            
            // Re-apply original thumbnail
            setThumbnailUrl(originalThumbnailUrl);
            
            // Add to history
            addToHistory(originalThumbnailUrl);
            
            showNotification(i18n.reset, 'info');
        };
        
        /**
         * Handle video time update
         */
        const handleTimeUpdate = () => {
            if (videoRef.current) {
                setTimeframe(videoRef.current.currentTime);
            }
        };
        
        /**
         * Handle video loaded
         */
        const handleVideoLoaded = () => {
            if (videoRef.current) {
                setDuration(videoRef.current.duration);
                
                // Set initial timeframe to 10% of the video
                // (often intros have ended by this point)
                const initialTime = videoRef.current.duration * 0.1;
                seekToTime(initialTime);
            }
        };
        
        /**
         * Handle video error
         */
        const handleVideoError = (event) => {
            debugLog('Video error', event);
            setError(i18n.unsupportedVideoFormat);
        };
        
        /**
         * Seek to specific time in video
         * 
         * @param {number} time Time in seconds
         */
        const seekToTime = (time) => {
            if (videoRef.current) {
                try {
                    videoRef.current.currentTime = time;
                    setTimeframe(time);
                } catch (error) {
                    debugLog('Error seeking to time', error);
                }
            }
        };
        
        /**
         * Format time in MM:SS format
         * 
         * @param {number} seconds Time in seconds
         * @returns {string} Formatted time
         */
        const formatTime = (seconds) => {
            if (isNaN(seconds)) return '00:00';
            
            const mins = Math.floor(seconds / 60);
            const secs = Math.floor(seconds % 60);
            return `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
        };
        
        /**
         * Helper function to get aspect ratio multiplier
         * 
         * @param {string} ratio Aspect ratio string
         * @returns {number} Aspect ratio multiplier
         */
        const getAspectRatioMultiplier = (ratio) => {
            switch (ratio) {
                case '16:9': return 16/9;
                case '4:3': return 4/3;
                case '1:1': return 1;
                default: return 16/9;
            }
        };
        
        /**
         * Save the thumbnail to WordPress Media Library
         */
        const saveThumbnail = async () => {
            if (!thumbnailUrl) {
                setError(i18n.noThumbnail);
                return;
            }
            
            if (!videoTitle.trim()) {
                setError(i18n.enterThumbnailName);
                return;
            }
            
            setIsProcessing(true);
            setNetworkStatus('loading');
            setError('');
            setSuccess('');
            
            try {
                debugLog('Saving thumbnail', { videoTitle, source: videoSource });
                
                // Use REST API for better error handling
                const response = await fetch(window.fluentCrmVideoThumbnails.restApiUrl + '/save-thumbnail', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': window.fluentCrmVideoThumbnails.restNonce
                    },
                    body: JSON.stringify({
                        thumbnail_data: thumbnailUrl,
                        video_title: videoTitle,
                        video_id: videoInfo ? videoInfo.video_id : (mediaId || ''),
                        video_source: videoInfo ? videoInfo.provider : (videoSource === 'media' ? 'media_library' : ''),
                        nonce: window.fluentCrmVideoThumbnails.nonce
                    })
                });
                
                // Handle HTTP errors
                if (!response.ok) {
                    let errorMsg = i18n.serverError;
                    
                    try {
                        const errorData = await response.json();
                        if (errorData && errorData.message) {
                            errorMsg = errorData.message;
                        }
                    } catch (e) {
                        debugLog('Error parsing error response', e);
                    }
                    
                    throw new Error(errorMsg);
                }
                
                const result = await response.json();
                
                if (result.success) {
                    debugLog('Thumbnail saved successfully', result.data);
                    setSuccess(result.data.message || i18n.success);
                    setNetworkStatus('success');
                    setSavedThumbnailInfo(result.data);
                    
                    // Show notification
                    showNotification(i18n.success, 'success');
                } else {
                    throw new Error(result.message || i18n.error);
                }
            } catch (err) {
                debugLog('Error saving thumbnail', err);
                setError(err.message || i18n.error);
                setNetworkStatus('error');
            } finally {
                setIsProcessing(false);
            }
        };
        
        /**
         * Copy image URL to clipboard
         */
        const copyImageUrl = () => {
            if (!savedThumbnailInfo || !savedThumbnailInfo.url) return;
            
            try {
                navigator.clipboard.writeText(savedThumbnailInfo.url);
                setIsCopied(true);
                showNotification(i18n.imageUrlCopied, 'success');
                
                setTimeout(() => {
                    setIsCopied(false);
                }, 3000);
            } catch (err) {
                debugLog('Error copying URL', err);
                showNotification(i18n.error, 'error');
            }
        };
        
        /**
         * Create another thumbnail
         */
        const createAnotherThumbnail = () => {
            resetEditor();
        };
        
        // Effect for applying edits whenever edit parameters change
        useEffect(() => {
            if (isEditor && originalThumbnailUrl) {
                applyEdits();
            }
        }, [
            brightness, 
            contrast, 
            saturation, 
            aspectRatio, 
            showPlayButton, 
            playButtonStyle,
            playButtonSize,
            isEditor,
            originalThumbnailUrl,
            applyEdits
        ]);
        
        // Render the success view after saving
        const renderSuccessView = () => {
            if (!savedThumbnailInfo) return null;
            
            return (
                <div className="thumbnail-success-view">
                    <div className="success-icon">
                        <span className="dashicons dashicons-yes-alt"></span>
                    </div>
                    
                    <h2>{i18n.success}</h2>
                    
                    <div className="saved-thumbnail-preview">
                        <img 
                            src={savedThumbnailInfo.url} 
                            alt={savedThumbnailInfo.title || i18n.thumbnail} 
                        />
                        <div className="thumbnail-metadata">
                            <p><strong>{savedThumbnailInfo.title}</strong></p>
                            {savedThumbnailInfo.dimensions && (
                                <p>{savedThumbnailInfo.dimensions} • {savedThumbnailInfo.size}</p>
                            )}
                        </div>
                    </div>
                    
                    <h3>{i18n.usingThumbnailTitle}</h3>
                    <p>{i18n.usingThumbnailText}</p>
                    
                    <div className="thumbnail-actions">
                        <Button 
                            isPrimary
                            href={savedThumbnailInfo.edit_url}
                            target="_blank"
                            icon="admin-media"
                        >
                            {i18n.viewInMediaLibrary}
                        </Button>
                        
                        <Button 
                            isSecondary
                            icon={isCopied ? "yes" : "clipboard"}
                            onClick={copyImageUrl}
                        >
                            {isCopied ? i18n.imageUrlCopied : i18n.copyImageUrl}
                        </Button>
                        
                        <Button 
                            isSecondary
                            icon="plus-alt"
                            onClick={createAnotherThumbnail}
                        >
                            {i18n.createAnother}
                        </Button>
                    </div>
                </div>
            );
        };
        
        // If we have saved a thumbnail, show the success view
        if (savedThumbnailInfo) {
            return renderSuccessView();
        }
        
        return (
            <div className="fluentcrm-video-thumbnails-container">
                <h1>{i18n.title}</h1>
                
                {/* Source Selection */}
                <div className="source-selection">
                    <ToggleControl
                        label={i18n.videoSource}
                        help={videoSource === 'external' ? i18n.externalSource : i18n.mediaLibrary}
                        checked={videoSource === 'media'}
                        onChange={() => handleSourceChange(videoSource === 'external' ? 'media' : 'external')}
                    />
                </div>
                
                {/* Video Input */}
                <div className="video-input">
                    {videoSource === 'external' ? (
                        <div className="external-url-input">
                            <label htmlFor="video-url">{i18n.externalSource}</label>
                            <input
                                id="video-url"
                                type="text"
                                placeholder={i18n.urlInputPlaceholder}
                                value={videoUrl}
                                onChange={(e) => handleVideoUrlChange(e.target.value)}
                                aria-label={i18n.externalSource}
                                disabled={isProcessing}
                            />
                            {networkStatus === 'loading' && (
                                <div className="url-loading-indicator">
                                    <Spinner />
                                </div>
                            )}
                        </div>
                    ) : (
                        <div className="media-library-input">
                            <Button
                                isPrimary
                                onClick={handleMediaSelection}
                                disabled={isProcessing}
                                icon="video-alt2"
                            >
                                {i18n.selectFromLibrary}
                            </Button>
                            {videoUrl && (
                                <div className="selected-video-info">
                                    <p>{i18n.selectedVideo} <strong>{videoUrl.split('/').pop()}</strong></p>
                                </div>
                            )}
                        </div>
                    )}
                </div>
                
                {/* Loading Indicator */}
                {isProcessing && networkStatus === 'loading' && !videoUrl && (
                    <div className="loading-indicator">
                        <Spinner />
                        <p>{i18n.processing}</p>
                    </div>
                )}
                
                {/* Error display */}
                {error && (
                    <Notice status="error" isDismissible={true} onRemove={() => setError('')}>
                        {error}
                    </Notice>
                )}
                
                {/* Success display */}
                {success && (
                    <Notice status="success" isDismissible={true} onRemove={() => setSuccess('')}>
                        <span dangerouslySetInnerHTML={{__html: success}}></span>
                    </Notice>
                )}
                
                {/* Video player for Media Library videos */}
                {videoSource === 'media' && videoUrl && (
                    <div className="video-player">
                        <video
                            ref={videoRef}
                            src={videoUrl}
                            controls
                            onTimeUpdate={handleTimeUpdate}
                            onLoadedMetadata={handleVideoLoaded}
                            onError={handleVideoError}
                            style={{ maxWidth: '100%', marginBottom: '20px' }}
                            aria-label={i18n.videoPlayer}
                        />
                        
                        <div className="video-time-display">
                            {i18n.currentTime}: {formatTime(timeframe)} / {formatTime(duration)}
                        </div>
                        
                        <input
                            type="range"
                            className="video-time-slider"
                            min="0"
                            max={duration || 0}
                            step="0.1"
                            value={timeframe}
                            onChange={(e) => seekToTime(parseFloat(e.target.value))}
                            aria-label={i18n.timeSlider}
                            aria-valuemin="0"
                            aria-valuemax={duration || 0}
                            aria-valuenow={timeframe}
                            aria-valuetext={formatTime(timeframe)}
                        />
                        
                        <div className="help-text">
                            <Tooltip text={i18n.timeframeTip}>
                                <span>ℹ️</span>
                            </Tooltip>
                            {' '}
                            {i18n.qualityNote}
                        </div>
                        
                        <Button
                            isPrimary
                            onClick={captureFrame}
                            style={{ marginTop: '10px' }}
                            disabled={isProcessing}
                            icon="camera"
                        >
                            {i18n.captureFrame}
                        </Button>
                    </div>
                )}
                
                {/* Notification Toast */}
                {notification && (
                    <Snackbar className={`notification notification-${notification.type}`}>
                        {notification.message}
                    </Snackbar>
                )}
                
                {/* Thumbnail Editor */}
                {isEditor && thumbnailUrl && (
                    <div className="thumbnail-editor">
                        <h2>{i18n.thumbnailEditor}</h2>
                        
                        {/* Preview */}
                        <div className="thumbnail-preview">
                            <PanelBody title={i18n.preview} initialOpen={true}>
                                <div className="preview-container">
                                    <img 
                                        src={thumbnailUrl} 
                                        alt={i18n.thumbnailAlt} 
                                        style={{ 
                                            maxWidth: '100%',
                                            border: '1px solid #ddd',
                                            boxShadow: '0 1px 3px rgba(0,0,0,0.1)'
                                        }} 
                                    />
                                </div>
                                
                                {/* Thumbnail name */}
                                <TextControl
                                    label={i18n.thumbnailName}
                                    value={videoTitle}
                                    onChange={setVideoTitle}
                                    className="thumbnail-name-input"
                                />
                                
                                {/* Undo/Redo */}
                                <div className="history-controls">
                                    <Tooltip text={i18n.undo}>
                                        <Button 
                                            icon="undo"
                                            onClick={handleUndo}
                                            disabled={historyIndex <= 0}
                                            aria-label={i18n.undo}
                                        />
                                    </Tooltip>
                                    <Tooltip text={i18n.redo}>
                                        <Button 
                                            icon="redo"
                                            onClick={handleRedo}
                                            disabled={historyIndex >= thumbnailHistory.length - 1}
                                            aria-label={i18n.redo}
                                        />
                                    </Tooltip>
                                </div>
                                
                                {/* Quality indicator */}
                                {videoInfo && videoInfo.quality && (
                                    <div className={`quality-indicator quality-${videoInfo.quality}`}>
                                        {videoInfo.quality === 'hd' ? i18n.hdQuality : 
                                         videoInfo.quality === 'sd' ? i18n.sdQuality : 
                                         i18n.lowQuality}
                                    </div>
                                )}
                                
                                <div className="help-text">{i18n.recommendedSize}</div>
                            </PanelBody>
                        </div>
                        
                        {/* Editing Controls */}
                        <div className="editing-controls">
                            <PanelBody title={i18n.adjustments} initialOpen={true}>
                                {/* Brightness */}
                                <RangeControl
                                    label={
                                        <span>
                                            {i18n.brightness}
                                            <Tooltip text={i18n.brightnessTip}>
                                                <span className="tip-icon">ℹ️</span>
                                            </Tooltip>
                                        </span>
                                    }
                                    value={brightness}
                                    onChange={setBrightness}
                                    min={50}
                                    max={150}
                                    step={1}
                                    aria-label={i18n.brightnessSlider}
                                />
                                
                                {/* Contrast */}
                                <RangeControl
                                    label={
                                        <span>
                                            {i18n.contrast}
                                            <Tooltip text={i18n.contrastTip}>
                                                <span className="tip-icon">ℹ️</span>
                                            </Tooltip>
                                        </span>
                                    }
                                    value={contrast}
                                    onChange={setContrast}
                                    min={50}
                                    max={150}
                                    step={1}
                                    aria-label={i18n.contrastSlider}
                                />
                                
                                {/* Saturation */}
                                <RangeControl
                                    label={
                                        <span>
                                            {i18n.saturation}
                                            <Tooltip text={i18n.saturationTip}>
                                                <span className="tip-icon">ℹ️</span>
                                            </Tooltip>
                                        </span>
                                    }
                                    value={saturation}
                                    onChange={setSaturation}
                                    min={0}
                                    max={200}
                                    step={1}
                                    aria-label={i18n.saturationSlider}
                                />
                                
                                {/* Aspect Ratio */}
                                <SelectControl
                                    label={
                                        <span>
                                            {i18n.aspectRatio}
                                            <Tooltip text={i18n.aspectRatioTip}>
                                                <span className="tip-icon">ℹ️</span>
                                            </Tooltip>
                                        </span>
                                    }
                                    value={aspectRatio}
                                    options={[
                                        { label: i18n.widescreen, value: '16:9' },
                                        { label: i18n.standard, value: '4:3' },
                                        { label: i18n.square, value: '1:1' },
                                    ]}
                                    onChange={setAspectRatio}
                                />
                            </PanelBody>
                            
                            <PanelBody title={i18n.playButton} initialOpen={true}>
                                {/* Play Button Overlay */}
                                <ToggleControl
                                    label={i18n.playButton}
                                    checked={showPlayButton}
                                    onChange={setShowPlayButton}
                                    help={i18n.playButtonTip}
                                />
                                
                                {showPlayButton && (
                                    <>
                                        {/* Play Button Style */}
                                        <RadioControl
                                            label={i18n.playButtonStyle}
                                            selected={playButtonStyle}
                                            options={[
                                                { label: i18n.playButtonClassic, value: 'classic' },
                                                { label: i18n.playButtonModern, value: 'modern' },
                                                { label: i18n.playButtonMinimal, value: 'minimal' },
                                                { label: i18n.playButtonYouTube, value: 'youtube' }
                                            ]}
                                            onChange={setPlayButtonStyle}
                                        />
                                        
                                        {/* Play Button Size */}
                                        <RadioControl
                                            label={i18n.playButtonSize}
                                            selected={playButtonSize}
                                            options={[
                                                { label: i18n.small, value: 'small' },
                                                { label: i18n.medium, value: 'medium' },
                                                { label: i18n.large, value: 'large' }
                                            ]}
                                            onChange={setPlayButtonSize}
                                        />
                                    </>
                                )}
                            </PanelBody>
                            
                            <PanelBody title={i18n.imageQuality} initialOpen={false}>
                                {/* Image Quality */}
                                <RangeControl
                                    label={i18n.imageQuality}
                                    value={imageQuality}
                                    onChange={setImageQuality}
                                    min={60}
                                    max={100}
                                    step={1}
                                />
                                
                                {/* Image Format */}
                                {supportsWebP && (
                                    <RadioControl
                                        label={i18n.imageFormat}
                                        selected={imageFormat}
                                        options={[
                                            { label: 'JPEG', value: 'jpeg' },
                                            { label: 'WebP', value: 'webp' }
                                        ]}
                                        onChange={setImageFormat}
                                    />
                                )}
                            </PanelBody>
                            
                            {/* Reset Button */}
                            <Button
                                isSecondary
                                isDestructive
                                onClick={handleResetAdjustments}
                                style={{ marginTop: '10px' }}
                                icon="image-rotate"
                                aria-label={i18n.reset}
                            >
                                {i18n.reset}
                            </Button>
                        </div>
                        
                        {/* Save Button */}
                        <div className="save-action">
                            <Button
                                isPrimary
                                isBusy={isProcessing}
                                disabled={isProcessing || !thumbnailUrl}
                                onClick={saveThumbnail}
                                size="large"
                                icon="upload"
                                style={{ padding: '0 20px', height: '40px' }}
                            >
                                {isProcessing ? i18n.uploading : i18n.saveToGallery}
                            </Button>
                        </div>
                    </div>
                )}
                
                {/* Confirmation Dialog */}
                {showConfirmation && (
                    <Modal
                        title={i18n.confirmReset}
                        onRequestClose={resetConfirmation}
                        className="fluentcrm-video-thumbnails-modal"
                    >
                        <p>{confirmationMessage}</p>
                        <div className="modal-actions">
                            <Button
                                isPrimary
                                isDestructive
                                onClick={confirmationAction}
                            >
                                {i18n.yesReset}
                            </Button>
                            <Button
                                isSecondary
                                onClick={resetConfirmation}
                            >
                                {i18n.cancel}
                            </Button>
                        </div>
                    </Modal>
                )}
                
                {/* Hidden Canvas Elements for Image Processing */}
                <canvas ref={canvasRef} style={{ display: 'none' }} />
                <canvas ref={editedCanvasRef} style={{ display: 'none' }} />
            </div>
        );
    };
    
    // Render the app when DOM is ready
    document.addEventListener('DOMContentLoaded', () => {
        const container = document.getElementById('fluentcrm-video-thumbnails-app');
        if (container) {
            render(<VideoThumbnailGenerator />, container);
        }
    });
    
})(window.wp, window.React);