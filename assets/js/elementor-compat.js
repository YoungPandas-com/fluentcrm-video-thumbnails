/**
 * FluentCRM Video Thumbnail Generator
 * Elementor Integration
 * 
 * @since 1.0.0
 */

(function($, elementor) {
    'use strict';

    // Check if Elementor and the required API are available
    if (!elementor || !window.fluentCrmVideoThumbnails || !window.fluentCrmVideoThumbnails.restApiUrl) {
        return;
    }

    // Register a new category for FluentCRM widgets
    elementor.channels.editor.on('editor:init', function() {
        elementor.channels.editor.on('panel:init', function() {
            const config = elementor.config.widgets_settings || {};
            if (!config.categories) {
                return;
            }

            // Add new category if it doesn't exist
            if (!config.categories.find(cat => cat.name === 'fluentcrm')) {
                config.categories.push({
                    name: 'fluentcrm',
                    title: 'FluentCRM',
                    icon: 'eicon-mail'
                });
            }
        });
    });

    // Register the Video Thumbnail Generator widget
    elementor.hooks.addAction('panel/open_editor/widget', function(panel, model, view) {
        // Detect if we're editing a video widget
        if (model.get('widgetType') === 'video') {
            // Check if our button already exists to avoid duplicates
            if (panel.$el.find('.fluentcrm-video-thumbnail-button').length === 0) {
                // Add a button to generate a thumbnail
                const videoControlsSection = panel.$el.find('.elementor-control-section_video');
                
                if (videoControlsSection.length > 0) {
                    const thumbnailButton = $('<div class="elementor-control elementor-control-type-section">' +
                        '<div class="elementor-control-content">' +
                        '<div class="elementor-control-field">' +
                        '<button class="elementor-button elementor-button-success fluentcrm-video-thumbnail-button">' +
                        'Generate Custom Thumbnail with FluentCRM' +
                        '</button>' +
                        '</div>' +
                        '</div>' +
                        '</div>'
                    );
                    
                    videoControlsSection.after(thumbnailButton);
                    
                    // Add click handler
                    thumbnailButton.on('click', '.fluentcrm-video-thumbnail-button', function(e) {
                        e.preventDefault();
                        
                        // Get the video URL from the widget
                        const videoUrl = model.getSetting('youtube_url') || 
                                         model.getSetting('vimeo_url') || 
                                         model.getSetting('dailymotion_url') || 
                                         model.getSetting('hosted_url');
                        
                        if (!videoUrl) {
                            // Show a message if no video URL is set
                            elementor.notifications.showToast({
                                message: 'Please set a video URL first.',
                                position: 'center'
                            });
                            return;
                        }
                        
                        // Open the FluentCRM Video Thumbnail Generator in a new tab
                        const generatorUrl = window.fluentCrmVideoThumbnails.adminUrl + 
                                           '?page=fluentcrm-video-thumbnails&video_url=' + 
                                           encodeURIComponent(videoUrl);
                        
                        window.open(generatorUrl, '_blank');
                    });
                }
            }
        }
    });
    
    // Create a new Elementor widget for the Video Thumbnail
    elementor.widgets.registerWidget('fluentcrm-video-thumbnail', {
        // Widget definition
        name: 'fluentcrm-video-thumbnail',
        title: 'FluentCRM Video Thumbnail',
        icon: 'eicon-featured-image',
        categories: ['fluentcrm', 'basic'],
        
        // Get widget settings fields
        getControlsConfig: function() {
            return {
                'section_video': {
                    type: 'section',
                    tab: 'content',
                    label: 'Video Source',
                    controls: {
                        'video_type': {
                            type: 'select',
                            label: 'Video Type',
                            default: 'youtube',
                            options: {
                                'youtube': 'YouTube',
                                'vimeo': 'Vimeo',
                                'hosted': 'Self Hosted'
                            }
                        },
                        'youtube_url': {
                            type: 'text',
                            label: 'YouTube URL',
                            condition: {
                                'video_type': 'youtube'
                            }
                        },
                        'vimeo_url': {
                            type: 'text',
                            label: 'Vimeo URL',
                            condition: {
                                'video_type': 'vimeo'
                            }
                        },
                        'hosted_url': {
                            type: 'media',
                            media_type: 'video',
                            label: 'Select Video',
                            condition: {
                                'video_type': 'hosted'
                            }
                        },
                        'thumbnail_url': {
                            type: 'media',
                            media_type: 'image',
                            label: 'Custom Thumbnail',
                            description: 'Select a custom thumbnail or use the "Generate Thumbnail" button below.'
                        },
                        'generate_thumbnail': {
                            type: 'button',
                            text: 'Generate Thumbnail',
                            event: 'fluentcrm:generateThumbnail',
                            button_type: 'success'
                        },
                        'play_icon': {
                            type: 'switcher',
                            label: 'Play Icon',
                            default: 'yes'
                        },
                        'play_icon_color': {
                            type: 'color',
                            label: 'Play Icon Color',
                            default: '#ffffff',
                            condition: {
                                'play_icon': 'yes'
                            }
                        },
                        'play_icon_size': {
                            type: 'slider',
                            label: 'Play Icon Size',
                            size_units: ['px', '%'],
                            range: {
                                'px': {
                                    'min': 10,
                                    'max': 200
                                },
                                '%': {
                                    'min': 1,
                                    'max': 100
                                }
                            },
                            default: {
                                'unit': 'px',
                                'size': 60
                            },
                            condition: {
                                'play_icon': 'yes'
                            }
                        }
                    }
                }
            };
        },
        
        // Widget front-end render method
        renderPreview: function($element, settings) {
            // Get the video URL based on the type
            let videoUrl = '';
            
            switch (settings.video_type) {
                case 'youtube':
                    videoUrl = settings.youtube_url;
                    break;
                case 'vimeo':
                    videoUrl = settings.vimeo_url;
                    break;
                case 'hosted':
                    videoUrl = settings.hosted_url ? settings.hosted_url.url : '';
                    break;
            }
            
            // Get the thumbnail URL
            const thumbnailUrl = settings.thumbnail_url ? settings.thumbnail_url.url : '';
            
            if (!thumbnailUrl) {
                $element.html('<div class="fluentcrm-video-thumbnail-placeholder">' +
                    '<p>Please select or generate a thumbnail</p>' +
                    '</div>');
                return;
            }
            
            // Create the thumbnail container
            const $thumbnailContainer = $('<div class="fluentcrm-video-thumbnail-container"></div>');
            
            // Add the thumbnail image
            const $thumbnailImage = $('<img src="' + thumbnailUrl + '" alt="Video Thumbnail" />');
            $thumbnailContainer.append($thumbnailImage);
            
            // Add play icon if enabled
            if (settings.play_icon === 'yes') {
                const $playIcon = $('<div class="fluentcrm-video-play-icon"></div>');
                $playIcon.css({
                    'color': settings.play_icon_color,
                    'font-size': settings.play_icon_size.size + settings.play_icon_size.unit
                });
                
                $thumbnailContainer.append($playIcon);
            }
            
            // Add click handler to play the video
            if (videoUrl) {
                $thumbnailContainer.on('click', function() {
                    // Create and append the video iframe
                    let videoHtml = '';
                    
                    switch (settings.video_type) {
                        case 'youtube':
                            const youtubeId = getYoutubeId(videoUrl);
                            if (youtubeId) {
                                videoHtml = '<iframe src="https://www.youtube.com/embed/' + youtubeId + '?autoplay=1" frameborder="0" allowfullscreen></iframe>';
                            }
                            break;
                        case 'vimeo':
                            const vimeoId = getVimeoId(videoUrl);
                            if (vimeoId) {
                                videoHtml = '<iframe src="https://player.vimeo.com/video/' + vimeoId + '?autoplay=1" frameborder="0" allowfullscreen></iframe>';
                            }
                            break;
                        case 'hosted':
                            videoHtml = '<video src="' + videoUrl + '" autoplay controls></video>';
                            break;
                    }
                    
                    if (videoHtml) {
                        $(this).html(videoHtml);
                    }
                });
                
                $thumbnailContainer.css('cursor', 'pointer');
            }
            
            // Add to the element
            $element.html($thumbnailContainer);
        },
        
        // Handle events
        onElementorInit: function() {
            var self = this;
            
            // Handle the generate thumbnail button
            elementor.channels.editor.on('fluentcrm:generateThumbnail', function() {
                const settings = self.getElementSettings();
                
                // Get the video URL based on the type
                let videoUrl = '';
                
                switch (settings.video_type) {
                    case 'youtube':
                        videoUrl = settings.youtube_url;
                        break;
                    case 'vimeo':
                        videoUrl = settings.vimeo_url;
                        break;
                    case 'hosted':
                        videoUrl = settings.hosted_url ? settings.hosted_url.url : '';
                        break;
                }
                
                if (!videoUrl) {
                    elementor.notifications.showToast({
                        message: 'Please set a video URL first.',
                        position: 'center'
                    });
                    return;
                }
                
                // Open the FluentCRM Video Thumbnail Generator in a new tab
                const generatorUrl = window.fluentCrmVideoThumbnails.adminUrl + 
                                   '?page=fluentcrm-video-thumbnails&video_url=' + 
                                   encodeURIComponent(videoUrl);
                
                window.open(generatorUrl, '_blank');
            });
        }
    });
    
    /**
     * Helper function to extract YouTube video ID
     */
    function getYoutubeId(url) {
        const regex = /(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/;
        const match = url.match(regex);
        return match ? match[1] : null;
    }
    
    /**
     * Helper function to extract Vimeo video ID
     */
    function getVimeoId(url) {
        const regex = /(?:vimeo\.com\/(?:video\/)?|player\.vimeo\.com\/video\/)(\d+)/;
        const match = url.match(regex);
        return match ? match[1] : null;
    }

})(jQuery, window.elementor);