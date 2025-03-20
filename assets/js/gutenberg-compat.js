/**
 * FluentCRM Video Thumbnail Generator
 * Gutenberg Block Editor Integration
 * 
 * @since 1.0.0
 */

(function(wp) {
    'use strict';

    const { registerBlockType } = wp.blocks;
    const { Button, Placeholder, ToggleControl, Spinner } = wp.components;
    const { Fragment, useState } = wp.element;
    const { MediaUpload, MediaUploadCheck } = wp.blockEditor;
    const { useSelect } = wp.data;
    const { __ } = wp.i18n;

    // Only register if we can access the FluentCRM thumbnail API
    if (!window.fluentCrmVideoThumbnails || !window.fluentCrmVideoThumbnails.restApiUrl) {
        return;
    }

    /**
     * Register the Video Thumbnail Generator block
     */
    registerBlockType('fluentcrm/video-thumbnail-generator', {
        title: __('Video Thumbnail Generator', 'fluentcrm-video-thumbnails'),
        icon: 'format-image',
        category: 'media',
        keywords: [
            __('video', 'fluentcrm-video-thumbnails'),
            __('thumbnail', 'fluentcrm-video-thumbnails'),
            __('fluentcrm', 'fluentcrm-video-thumbnails')
        ],
        
        attributes: {
            mediaId: {
                type: 'number'
            },
            mediaUrl: {
                type: 'string'
            },
            thumbnailId: {
                type: 'number'
            },
            thumbnailUrl: {
                type: 'string'
            },
            linkToVideo: {
                type: 'boolean',
                default: true
            }
        },
        
        edit: function(props) {
            const { attributes, setAttributes } = props;
            const { mediaId, mediaUrl, thumbnailId, thumbnailUrl, linkToVideo } = attributes;
            
            const [isGenerating, setIsGenerating] = useState(false);
            const [error, setError] = useState('');
            
            // Get the media object from the store
            const media = useSelect(select => {
                return mediaId ? select('core').getMedia(mediaId) : null;
            }, [mediaId]);
            
            // Get the thumbnail object
            const thumbnail = useSelect(select => {
                return thumbnailId ? select('core').getMedia(thumbnailId) : null;
            }, [thumbnailId]);
            
            /**
             * Handle video selection
             */
            const onSelectMedia = (media) => {
                if (!media || !media.url) {
                    return;
                }
                
                // Check if it's a video
                if (media.type !== 'video') {
                    setError(__('Please select a video file', 'fluentcrm-video-thumbnails'));
                    return;
                }
                
                setAttributes({
                    mediaId: media.id,
                    mediaUrl: media.url
                });
                
                setError('');
            };
            
            /**
             * Generate thumbnail from the video
             */
            const generateThumbnail = async () => {
                if (!mediaId || !mediaUrl) {
                    setError(__('Please select a video first', 'fluentcrm-video-thumbnails'));
                    return;
                }
                
                setIsGenerating(true);
                setError('');
                
                try {
                    // Redirect to the thumbnail generator page
                    const generatorUrl = `${window.fluentCrmVideoThumbnails.adminUrl}?page=fluentcrm-video-thumbnails&video_id=${mediaId}`;
                    
                    // Open in a new tab
                    window.open(generatorUrl, '_blank');
                } catch (err) {
                    setError(err.message || __('Error generating thumbnail', 'fluentcrm-video-thumbnails'));
                } finally {
                    setIsGenerating(false);
                }
            };
            
            /**
             * Clear the selection
             */
            const clearSelection = () => {
                setAttributes({
                    mediaId: undefined,
                    mediaUrl: undefined,
                    thumbnailId: undefined,
                    thumbnailUrl: undefined
                });
            };
            
            // If we have a thumbnail, render it
            if (thumbnailId && thumbnailUrl) {
                return (
                    <Fragment>
                        <div className="fluentcrm-video-thumbnail-block">
                            {linkToVideo && mediaUrl ? (
                                <a href={mediaUrl} className="fluentcrm-video-thumbnail-link" target="_blank" rel="noopener noreferrer">
                                    <img src={thumbnailUrl} alt={__('Video Thumbnail', 'fluentcrm-video-thumbnails')} />
                                </a>
                            ) : (
                                <img src={thumbnailUrl} alt={__('Video Thumbnail', 'fluentcrm-video-thumbnails')} />
                            )}
                        </div>
                        
                        <div className="fluentcrm-video-thumbnail-controls">
                            <ToggleControl
                                label={__('Link to video', 'fluentcrm-video-thumbnails')}
                                checked={linkToVideo}
                                onChange={(value) => setAttributes({ linkToVideo: value })}
                            />
                            
                            <Button
                                isSecondary
                                onClick={clearSelection}
                            >
                                {__('Replace thumbnail', 'fluentcrm-video-thumbnails')}
                            </Button>
                        </div>
                    </Fragment>
                );
            }
            
            // If no thumbnail yet, show the video selection UI
            return (
                <div className="fluentcrm-video-thumbnail-generator-block">
                    <Placeholder
                        icon="format-image"
                        label={__('Video Thumbnail Generator', 'fluentcrm-video-thumbnails')}
                        instructions={__('Select a video to generate a thumbnail, or generate one from the selected video.', 'fluentcrm-video-thumbnails')}
                    >
                        {error && (
                            <div className="fluentcrm-video-thumbnail-error">
                                {error}
                            </div>
                        )}
                        
                        <div className="fluentcrm-video-thumbnail-controls">
                            {!mediaId ? (
                                <MediaUploadCheck>
                                    <MediaUpload
                                        onSelect={onSelectMedia}
                                        allowedTypes={['video']}
                                        value={mediaId}
                                        render={({ open }) => (
                                            <Button
                                                isPrimary
                                                onClick={open}
                                            >
                                                {__('Select Video', 'fluentcrm-video-thumbnails')}
                                            </Button>
                                        )}
                                    />
                                </MediaUploadCheck>
                            ) : (
                                <Fragment>
                                    <div className="fluentcrm-selected-video">
                                        <strong>{__('Selected video:', 'fluentcrm-video-thumbnails')}</strong>
                                        <span>{media ? media.title.rendered : mediaUrl}</span>
                                    </div>
                                    
                                    <div className="fluentcrm-video-thumbnail-actions">
                                        <Button
                                            isPrimary
                                            onClick={generateThumbnail}
                                            isBusy={isGenerating}
                                            disabled={isGenerating}
                                        >
                                            {isGenerating ? (
                                                <Fragment>
                                                    <Spinner />
                                                    {__('Generating...', 'fluentcrm-video-thumbnails')}
                                                </Fragment>
                                            ) : (
                                                __('Generate Thumbnail', 'fluentcrm-video-thumbnails')
                                            )}
                                        </Button>
                                        
                                        <Button
                                            isSecondary
                                            onClick={clearSelection}
                                        >
                                            {__('Cancel', 'fluentcrm-video-thumbnails')}
                                        </Button>
                                    </div>
                                </Fragment>
                            )}
                        </div>
                    </Placeholder>
                </div>
            );
        },
        
        save: function(props) {
            const { attributes } = props;
            const { mediaUrl, thumbnailUrl, linkToVideo } = attributes;
            
            if (!thumbnailUrl) {
                return null;
            }
            
            if (linkToVideo && mediaUrl) {
                return (
                    <a href={mediaUrl} className="fluentcrm-video-thumbnail-link" target="_blank" rel="noopener noreferrer">
                        <img src={thumbnailUrl} alt="" className="fluentcrm-video-thumbnail" />
                    </a>
                );
            }
            
            return (
                <img src={thumbnailUrl} alt="" className="fluentcrm-video-thumbnail" />
            );
        }
    });

})(window.wp);