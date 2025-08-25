<?php
/**
 * Newsletter ACF Fields Class
 * File: includes/class-newsletter-acf-fields.php
 */

if (!defined('ABSPATH')) {
    exit;
}

class Newsletter_ACF_Fields {
    
    public function __construct() {
        add_action('acf/init', array($this, 'register_fields'), 10);
    }
    
    public function register_fields() {
        // Check if ACF is available
        if (!function_exists('acf_add_local_field_group')) {
            return;
        }
        
        // Check if field group already exists to prevent duplicates
        if (function_exists('acf_get_field_group') && acf_get_field_group('group_newsletter_content')) {
            return;
        }
        
        $field_group = array(
            'key' => 'group_newsletter_content',
            'title' => 'Newsletter Content',
            'fields' => array(
                array(
                    'key' => 'field_newsletter_content_blocks',
                    'label' => 'Content Blocks',
                    'name' => 'content_blocks',
                    'type' => 'flexible_content',
                    'instructions' => 'Build your newsletter by adding content blocks.',
                    'required' => 0,
                    'conditional_logic' => 0,
                    'wrapper' => array(
                        'width' => '',
                        'class' => '',
                        'id' => '',
                    ),
                    'layouts' => array(
                        // Header Block
                        'layout_header' => array(
                            'key' => 'layout_header',
                            'name' => 'header',
                            'label' => 'Header',
                            'display' => 'block',
                            'sub_fields' => array(
                                array(
                                    'key' => 'field_header_logo',
                                    'label' => 'Logo',
                                    'name' => 'logo',
                                    'type' => 'image',
                                    'required' => 0,
                                    'return_format' => 'array',
                                    'preview_size' => 'medium',
                                    'library' => 'all'
                                ),
                                array(
                                    'key' => 'field_header_title',
                                    'label' => 'Title',
                                    'name' => 'title',
                                    'type' => 'text',
                                    'required' => 0,
                                    'default_value' => '',
                                    'placeholder' => 'Newsletter Title'
                                ),
                                array(
                                    'key' => 'field_header_subtitle',
                                    'label' => 'Subtitle',
                                    'name' => 'subtitle',
                                    'type' => 'text',
                                    'required' => 0,
                                    'default_value' => '',
                                    'placeholder' => 'Newsletter Subtitle (optional)'
                                )
                            )
                        ),
                        // Text Block
                        'layout_text' => array(
                            'key' => 'layout_text',
                            'name' => 'text',
                            'label' => 'Text Content',
                            'display' => 'block',
                            'sub_fields' => array(
                                array(
                                    'key' => 'field_text_content',
                                    'label' => 'Content',
                                    'name' => 'content',
                                    'type' => 'wysiwyg',
                                    'required' => 1,
                                    'toolbar' => 'basic',
                                    'media_upload' => 0,
                                    'delay' => 0
                                ),
                                array(
                                    'key' => 'field_text_alignment',
                                    'label' => 'Text Alignment',
                                    'name' => 'alignment',
                                    'type' => 'select',
                                    'required' => 0,
                                    'choices' => array(
                                        'left' => 'Left',
                                        'center' => 'Center',
                                        'right' => 'Right'
                                    ),
                                    'default_value' => 'left',
                                    'allow_null' => 0,
                                    'multiple' => 0
                                )
                            )
                        ),
                        // Image Block  
                        'layout_image' => array(
                            'key' => 'layout_image',
                            'name' => 'image',
                            'label' => 'Image',
                            'display' => 'block',
                            'sub_fields' => array(
                                array(
                                    'key' => 'field_image_src',
                                    'label' => 'Image',
                                    'name' => 'image',
                                    'type' => 'image',
                                    'required' => 1,
                                    'return_format' => 'array',
                                    'preview_size' => 'medium',
                                    'library' => 'all'
                                ),
                                array(
                                    'key' => 'field_image_alt',
                                    'label' => 'Alt Text',
                                    'name' => 'alt_text',
                                    'type' => 'text',
                                    'required' => 0,
                                    'default_value' => '',
                                    'placeholder' => 'Describe the image'
                                ),
                                array(
                                    'key' => 'field_image_alignment',
                                    'label' => 'Image Alignment',
                                    'name' => 'alignment',
                                    'type' => 'select',
                                    'required' => 0,
                                    'choices' => array(
                                        'left' => 'Left',
                                        'center' => 'Center',
                                        'right' => 'Right'
                                    ),
                                    'default_value' => 'center',
                                    'allow_null' => 0,
                                    'multiple' => 0
                                )
                            )
                        ),
                        // Button Block
                        'layout_button' => array(
                            'key' => 'layout_button',
                            'name' => 'button',
                            'label' => 'Button',
                            'display' => 'block',
                            'sub_fields' => array(
                                array(
                                    'key' => 'field_button_text',
                                    'label' => 'Button Text',
                                    'name' => 'text',
                                    'type' => 'text',
                                    'required' => 1,
                                    'default_value' => '',
                                    'placeholder' => 'Click Here'
                                ),
                                array(
                                    'key' => 'field_button_url',
                                    'label' => 'Button URL',
                                    'name' => 'url',
                                    'type' => 'url',
                                    'required' => 1,
                                    'default_value' => '',
                                    'placeholder' => 'https://example.com'
                                ),
                                array(
                                    'key' => 'field_button_color',
                                    'label' => 'Button Color',
                                    'name' => 'color',
                                    'type' => 'color_picker',
                                    'required' => 0,
                                    'default_value' => '#007cba'
                                ),
                                array(
                                    'key' => 'field_button_alignment',
                                    'label' => 'Button Alignment',
                                    'name' => 'alignment',
                                    'type' => 'select',
                                    'required' => 0,
                                    'choices' => array(
                                        'left' => 'Left',
                                        'center' => 'Center',
                                        'right' => 'Right'
                                    ),
                                    'default_value' => 'center',
                                    'allow_null' => 0,
                                    'multiple' => 0
                                )
                            )
                        ),
                        // Footer Block
                        'layout_footer' => array(
                            'key' => 'layout_footer',
                            'name' => 'footer',
                            'label' => 'Footer',
                            'display' => 'block',
                            'sub_fields' => array(
                                array(
                                    'key' => 'field_footer_content',
                                    'label' => 'Footer Content',
                                    'name' => 'content',
                                    'type' => 'textarea',
                                    'required' => 0,
                                    'rows' => 3,
                                    'placeholder' => 'Company address, copyright, etc.'
                                ),
                                array(
                                    'key' => 'field_footer_unsubscribe',
                                    'label' => 'Include Unsubscribe Link',
                                    'name' => 'include_unsubscribe',
                                    'type' => 'true_false',
                                    'required' => 0,
                                    'default_value' => 1,
                                    'ui' => 1,
                                    'ui_on_text' => 'Yes',
                                    'ui_off_text' => 'No'
                                )
                            )
                        )
                    ),
                    'button_label' => 'Add Content Block',
                    'min' => 0,
                    'max' => 0
                )
            ),
            'location' => array(
                array(
                    array(
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => 'newsletter_campaign'
                    )
                )
            ),
            'menu_order' => 0,
            'position' => 'normal',
            'style' => 'default',
            'label_placement' => 'top',
            'instruction_placement' => 'label',
            'hide_on_screen' => array(),
            'active' => true,
            'description' => 'Newsletter content blocks for building email campaigns.'
        );
        
        acf_add_local_field_group($field_group);
    }
}