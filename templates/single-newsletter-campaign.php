<?php
/**
 * Updated Template for displaying newsletter campaigns with better access indicators
 * File: templates/single-newsletter-campaign.php
 */

// Get access information
$post_status = get_post_status();
$is_admin = current_user_can('edit_posts');
$has_preview_token = isset($_GET['preview_token']);
$is_published = ($post_status === 'publish');


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
    </style>
     <?php // wp_head(); ?>
</head>
<body>

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
                                <td style="padding: 20px; text-align: center; font-size: 14px; color: #666;">
                                    <p style="margin: 0;">© <?php echo date('Y'); ?> <?php bloginfo('name'); ?>. All rights reserved.</p>
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
                                    <?php if (current_user_can('edit_posts')): ?>
                                    <p style="margin: 15px 0 0 0;">
                                        <a href="<?php echo admin_url('post.php?post=' . get_the_ID() . '&action=edit'); ?>" style="color: #0073aa; text-decoration: underline;">Edit this campaign</a>
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
    <?php // wp_footer(); ?>
</body>
</html>