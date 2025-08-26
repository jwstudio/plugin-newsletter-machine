<?php
/**
 * Updated Template for displaying newsletter campaigns with improved preview system
 * File: templates/single-newsletter-campaign.php
 */

// Get campaign info
$post_status = get_post_status();
$is_preview = (get_query_var('_npp') !== '');
$is_admin = current_user_can('edit_posts');

// Get the campaign content
$content_blocks = get_field('content_blocks');
$campaign_title = get_the_title();

// Determine the view context
$view_context = '';
if ($post_status === 'publish') {
    $view_context = 'published';
} elseif ($is_preview) {
    $view_context = 'preview';
} elseif ($is_admin) {
    $view_context = 'admin_draft';
} else {
    $view_context = 'blocked';
}

?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html($campaign_title); ?> - Newsletter</title>
    
    <?php if ($is_preview): ?>
    <!-- No-index for preview mode -->
    <meta name="robots" content="noindex, nofollow">
    <?php endif; ?>
    
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            line-height: 1.6;
        }
        
        /* Status bar styles */
        .newsletter-status-bar {
            background: #333;
            color: white;
            padding: 8px 0;
            text-align: center;
            font-size: 12px;
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        
        .status-preview {
            background: #ff6900;
        }
        
        .status-published {
            background: #00a32a;
        }
        
        .status-admin {
            background: #0073aa;
        }
        
        .newsletter-container {
            max-width: 600px;
            margin: 20px auto;
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-radius: 4px;
            overflow: hidden;
        }
        
        .newsletter-container table {
            border-collapse: collapse;
        }
        
        /* Print styles */
        @media print {
            .newsletter-status-bar {
                display: none;
            }
            body {
                background: white;
            }
            .newsletter-container {
                box-shadow: none;
                margin: 0;
            }
        }
        
        /* Mobile responsive */
        @media (max-width: 600px) {
            .newsletter-container {
                margin: 10px;
                max-width: none;
            }
        }
    </style>
</head>
<body>

    <?php if ($view_context !== 'blocked'): ?>
    
    <!-- Status Bar -->
    <?php 
    $status_class = '';
    $status_text = '';
    $status_icon = '';
    
    switch ($view_context) {
        case 'published':
            $status_class = 'status-published';
            $status_text = '✓ Published Newsletter';
            $status_icon = '🌐';
            break;
        case 'preview':
            $status_class = 'status-preview';
            $status_text = '👁 Secure Preview Mode (Draft)';
            $status_icon = '🔒';
            break;
        case 'admin_draft':
            $status_class = 'status-admin';
            $status_text = '⚡ Admin Preview (Draft)';
            $status_icon = '🔧';
            break;
    }
    ?>
    
    <div class="newsletter-status-bar <?php echo esc_attr($status_class); ?>">
        <strong><?php echo $status_icon; ?> <?php echo esc_html($status_text); ?></strong>
        
        <?php if ($view_context === 'preview'): ?>
            - This preview link expires when the newsletter is published
        <?php elseif ($view_context === 'admin_draft'): ?>
            - <a href="<?php echo admin_url('post.php?post=' . get_the_ID() . '&action=edit'); ?>" style="color: #87ceeb; text-decoration: underline;">Edit Campaign</a>
        <?php endif; ?>
    </div>

    <div class="newsletter-container">
        
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
                                <td style="padding: 20px; text-align: center; font-size: 14px; color: #666; line-height: 1.4;">
                                    <p style="margin: 0 0 10px 0;">© <?php echo date('Y'); ?> <?php bloginfo('name'); ?>. All rights reserved.</p>
                                    
                                    <?php if ($view_context === 'preview'): ?>
                                    <p style="margin: 0; font-size: 12px; color: #888;">
                                        📧 This is a secure preview of a draft newsletter<br>
                                        Preview links are automatically included in test emails
                                    </p>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </table>
                        
                    <?php else: ?>
                        <!-- Empty state -->
                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                            <tr>
                                <td style="padding: 60px 40px; text-align: center; color: #666;">
                                    <div style="font-size: 48px; margin-bottom: 20px;">📝</div>
                                    <h3 style="margin: 0 0 15px 0; color: #333; font-size: 24px;">No content blocks found</h3>
                                    <p style="margin: 0 0 20px 0; font-size: 16px; color: #666;">This campaign doesn't have any content blocks yet.</p>
                                    
                                    <?php if ($is_admin): ?>
                                    <a href="<?php echo admin_url('post.php?post=' . get_the_ID() . '&action=edit'); ?>" 
                                       style="display: inline-block; background: #0073aa; color: white; padding: 12px 24px; text-decoration: none; border-radius: 4px; font-weight: bold;">
                                        Add Content Blocks
                                    </a>
                                    <?php else: ?>
                                    <p style="margin: 0; font-size: 14px; color: #999;">
                                        Content will appear here once the campaign is configured.
                                    </p>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </table>
                    <?php endif; ?>
                    
                </td>
            </tr>
        </table>
        
    </div>
    
    <?php else: ?>
    
    <!-- Blocked access content handled by Newsletter_Public class -->
    
    <?php endif; ?>
    
</body>
</html>