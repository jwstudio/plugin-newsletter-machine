<?php
/**
 * Secure Template for displaying newsletter campaigns
 * File: templates/single-newsletter-campaign.php
 * Replace your existing template with this version
 */

// Security check for draft campaigns
$post_status = get_post_status();

if ($post_status !== 'publish') {
    // This is a draft campaign - check for valid preview token
    if (!isset($_GET['preview_token'])) {
        wp_die('Access denied. This newsletter is not published.', 'Newsletter Access Denied', array('response' => 403));
    }
    
    $provided_token = sanitize_text_field($_GET['preview_token']);
    $is_valid = Newsletter_Email_Sender::validate_preview_hash(get_the_ID(), $provided_token);
    
    if (!$is_valid) {
        wp_die('Invalid preview token. This newsletter is not accessible.', 'Newsletter Access Denied', array('response' => 403));
    }
    
    // Valid token - show preview notice
    $is_preview_mode = true;
} else {
    $is_preview_mode = false;
}

// Get the campaign content
$content_blocks = get_field('content_blocks');
$campaign_title = get_the_title();

?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html($campaign_title); ?> - Newsletter</title>
    
    <style>
        body {
            margin: 0;
            padding: 20px;
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
        }
        
        .newsletter-container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .newsletter-header {
            background: <?php echo $is_preview_mode ? '#dc3545' : '#0073aa'; ?>;
            color: white;
            padding: 15px 20px;
            text-align: center;
        }
        
        .newsletter-header h1 {
            margin: 0;
            font-size: 24px;
        }
        
        .newsletter-header p {
            margin: 5px 0 0 0;
            opacity: 0.9;
            font-size: 14px;
        }
        
        .admin-links {
            background: #f8f8f8;
            padding: 10px 20px;
            text-align: center;
            border-bottom: 1px solid #ddd;
            font-size: 12px;
        }
        
        .admin-links a {
            color: #0073aa;
            text-decoration: none;
            margin: 0 10px;
        }
        
        .preview-notice {
            background: #fff3cd;
            color: #856404;
            padding: 10px 20px;
            text-align: center;
            border-bottom: 1px solid #ffeaa7;
            font-size: 12px;
        }
    </style>
</head>
<body>
    
    <div class="newsletter-container">
        
        <!-- Header -->
        <div class="newsletter-header">
            <h1><?php echo esc_html($campaign_title); ?></h1>
            <p><?php echo $is_preview_mode ? 'Newsletter Preview' : 'Newsletter Campaign'; ?></p>
        </div>
        
        <!-- Preview notice for drafts -->
        <?php if ($is_preview_mode): ?>
        <div class="preview-notice">
            <strong>PREVIEW MODE:</strong> This newsletter is not yet published. You are viewing it via a secure preview link.
        </div>
        <?php endif; ?>
        
        <!-- Admin links (only show if user can edit) -->
        <?php if (current_user_can('edit_posts')): ?>
        <div class="admin-links">
            <a href="<?php echo admin_url('post.php?post=' . get_the_ID() . '&action=edit'); ?>">Edit Campaign</a>
        </div>
        <?php endif; ?>
        
        <!-- Email Content Container -->
        <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="width: 100%; background-color: #ffffff;">
            <tr>
                <td>
                    
                    <?php if ($content_blocks && is_array($content_blocks)): ?>
                        <?php foreach ($content_blocks as $block): ?>
                            <?php echo Newsletter_Blocks::render_block($block, true); ?>
                        <?php endforeach; ?>
                        
                        <!-- Standard Footer -->
                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color: #f8f8f8; border-top: 1px solid #ddd;">
                            <tr>
                                <td style="padding: 20px; text-align: center; font-size: 14px; color: #666;">
                                    <p style="margin: 0 0 10px 0;">© <?php echo date('Y'); ?> <?php bloginfo('name'); ?>. All rights reserved.</p>
                                    <p style="margin: 0;">
                                        <a href="#unsubscribe" style="color: #666; text-decoration: underline;">Unsubscribe</a>
                                    </p>
                                </td>
                            </tr>
                        </table>
                        
                    <?php else: ?>
                        <!-- Empty state -->
                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                            <tr>
                                <td style="padding: 40px; text-align: center; color: #666;">
                                    <h3 style="margin: 0 0 10px 0; color: #333;">No content blocks found</h3>
                                    <p style="margin: 0;">Add some content blocks to your campaign to see the preview.</p>
                                </td>
                            </tr>
                        </table>
                    <?php endif; ?>
                    
                </td>
            </tr>
        </table>
        
    </div>

</body>
</html>