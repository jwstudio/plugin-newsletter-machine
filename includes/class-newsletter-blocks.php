<?php
/**
 * Block Renderer Class
 * File: includes/class-newsletter-blocks.php
 */
class Newsletter_Blocks {
    
    /**
     * Main render method that delegates to specific block renderers
     */
    public static function render_block($block, $is_email_version = false) {
        if (!isset($block['acf_fc_layout'])) {
            return '';
        }
        
        $layout = $block['acf_fc_layout'];
        
        switch ($layout) {
            case 'header':
                return self::render_header($block, $is_email_version);
            case 'text':
                return self::render_text($block, $is_email_version);
            case 'image':
                return self::render_image($block, $is_email_version);
            case 'button':
                return self::render_button($block, $is_email_version);
            case 'footer':
                return self::render_footer($block, $is_email_version);
            default:
                return '';
        }
    }
    
    public static function render_header($block, $is_email_version = false) {
        $output = '<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color: #333;">';
        $output .= '<tr><td style="padding: 20px; text-align: center; color: white;">';
        
        if (!empty($block['logo'])) {
            $output .= '<img src="' . esc_url($block['logo']['url']) . '" alt="' . esc_attr($block['logo']['alt']) . '" style="max-width: 200px; height: auto; margin-bottom: 10px; display: block; margin-left: auto; margin-right: auto;">';
        }
        
        if (!empty($block['title'])) {
            $output .= '<h1 style="margin: 10px 0; color: white; font-size: 24px; line-height: 1.2;">' . esc_html($block['title']) . '</h1>';
        }
        
        if (!empty($block['subtitle'])) {
            $output .= '<p style="margin: 5px 0 0 0; color: #ccc; font-size: 16px; line-height: 1.4;">' . esc_html($block['subtitle']) . '</p>';
        }
        
        $output .= '</td></tr></table>';
        return $output;
    }
    
    public static function render_text($block, $is_email_version = false) {
        $alignment = !empty($block['alignment']) ? $block['alignment'] : 'left';
        $output = '<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">';
        $output .= '<tr><td style="padding: 20px; text-align: ' . esc_attr($alignment) . '; line-height: 1.6;">';
        $output .= wp_kses_post($block['content']);
        $output .= '</td></tr></table>';
        return $output;
    }
    
    public static function render_image($block, $is_email_version = false) {
        if (empty($block['image'])) {
            return '';
        }
        
        $alignment = !empty($block['alignment']) ? $block['alignment'] : 'center';
        $output = '<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">';
        $output .= '<tr><td style="padding: 20px; text-align: ' . esc_attr($alignment) . ';">';
        $output .= '<img src="' . esc_url($block['image']['url']) . '" alt="' . esc_attr($block['alt_text']) . '" style="max-width: 100%; height: auto; display: block;';
        if ($alignment === 'center') {
            $output .= ' margin-left: auto; margin-right: auto;';
        }
        $output .= '">';
        $output .= '</td></tr></table>';
        return $output;
    }
    
    public static function render_button($block, $is_email_version = false) {
        $alignment = !empty($block['alignment']) ? $block['alignment'] : 'center';
        $color = !empty($block['color']) ? $block['color'] : '#007cba';
        
        $output = '<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">';
        $output .= '<tr><td style="padding: 20px; text-align: ' . esc_attr($alignment) . ';">';
        $output .= '<a href="' . esc_url($block['url']) . '" style="display: inline-block; padding: 12px 24px; background-color: ' . esc_attr($color) . '; color: white; text-decoration: none; border-radius: 4px; font-weight: bold; font-size: 16px;">';
        $output .= esc_html($block['text']);
        $output .= '</a>';
        $output .= '</td></tr></table>';
        return $output;
    }
    
    public static function render_footer($block, $is_email_version = false) {
        $output = '<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color: #f8f8f8; border-top: 1px solid #ddd;">';
        $output .= '<tr><td style="padding: 20px; text-align: center; font-size: 14px; color: #666; line-height: 1.4;">';
        
        if (!empty($block['content'])) {
            $output .= '<p style="margin: 0 0 10px 0;">' . nl2br(esc_html($block['content'])) . '</p>';
        }
        
        if (!empty($block['include_unsubscribe'])) {
            $output .= '<p style="margin: 10px 0 0 0;"><a href="#unsubscribe" style="color: #666; text-decoration: underline;">Unsubscribe</a></p>';
        }
        
        $output .= '</td></tr></table>';
        return $output;
    }
}