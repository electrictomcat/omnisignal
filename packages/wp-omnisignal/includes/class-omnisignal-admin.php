<?php

if (! defined('ABSPATH')) {
    exit;
}

class OmniSignal_Admin
{
    public static function init(): void
    {
        add_action('admin_menu', [__CLASS__, 'add_menu_page']);
        add_action('admin_init', [__CLASS__, 'register_settings']);
    }

    public static function add_menu_page(): void
    {
        add_submenu_page(
            'woocommerce',
            'OmniSignal CAPI',
            'OmniSignal ॐ',
            'manage_woocommerce',
            'omnisignal-settings',
            [__CLASS__, 'render_page']
        );
    }

    public static function register_settings(): void
    {
        register_setting('omnisignal_settings_group', 'omnisignal_settings');
    }

    public static function render_page(): void
    {
        $options = get_option('omnisignal_settings', []);
        $license_key = $options['license_key'] ?? '';
        $is_licensed = ! empty($license_key);
        ?>
        <div class="wrap" style="max-width: 900px; margin-top: 20px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
            
            <!-- Header Banner -->
            <div style="background: #0b0f17; color: #fff; padding: 26px 32px; border-radius: 16px; margin-bottom: 24px; box-shadow: 0 10px 30px rgba(0,0,0,0.2);">
                <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px;">
                    <div style="display: flex; align-items: center; gap: 14px;">
                        <span style="font-size: 32px;">🕉️</span>
                        <div>
                            <h1 style="color: #fff; font-size: 24px; margin: 0; font-weight: 700;">OmniSignal &bull; Attribution Nirvana</h1>
                            <p style="color: #94a3b8; font-size: 13px; margin: 4px 0 0 0;">Server-Side Offline Conversion Tracking & CAPI for WooCommerce (<a href="https://omnisignal.dev" target="_blank" style="color: #34d399; text-decoration: none;">omnisignal.dev</a>)</p>
                        </div>
                    </div>
                    <div>
                        <?php if ($is_licensed) : ?>
                            <span style="background: rgba(16, 185, 129, 0.15); color: #34d399; font-size: 12px; padding: 6px 14px; border-radius: 20px; font-weight: 600; border: 1px solid rgba(16, 185, 129, 0.3);">
                                ● Pro License Active
                            </span>
                        <?php else : ?>
                            <a href="https://omnisignal.dev/#pricing" target="_blank" style="background: #10b981; color: #090d16; font-size: 12px; padding: 6px 14px; border-radius: 20px; font-weight: 700; text-decoration: none;">
                                Upgrade to Pro →
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Settings Form -->
            <form method="post" action="options.php" style="background: #fff; padding: 32px; border-radius: 16px; border: 1px solid #e2e8f0; box-shadow: 0 2px 4px rgba(0,0,0,0.04);">
                <?php settings_fields('omnisignal_settings_group'); ?>

                <!-- License Key -->
                <h2 style="font-size: 17px; margin-top: 0; padding-bottom: 12px; border-bottom: 2px solid #f1f5f9; font-weight: 700;">🔑 OmniSignal License</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="license_key">License Key</label></th>
                        <td>
                            <input type="text" id="license_key" name="omnisignal_settings[license_key]" value="<?php echo esc_attr($license_key); ?>" class="regular-text" placeholder="OMNI-XXXX-XXXX-XXXX-XXXX" />
                            <p class="description">Activate your domain on <a href="https://omnisignal.dev/portal" target="_blank">omnisignal.dev/portal</a> or get a key at <a href="https://omnisignal.dev/#pricing" target="_blank">omnisignal.dev</a>.</p>
                        </td>
                    </tr>
                </table>

                <!-- Meta CAPI -->
                <h2 style="font-size: 17px; margin-top: 35px; padding-bottom: 12px; border-bottom: 2px solid #f1f5f9; font-weight: 700;">🔵 Meta (Facebook & Instagram) CAPI</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="meta_pixel_id">Meta Pixel / Dataset ID</label></th>
                        <td>
                            <input type="text" id="meta_pixel_id" name="omnisignal_settings[meta_pixel_id]" value="<?php echo esc_attr($options['meta_pixel_id'] ?? ''); ?>" class="regular-text" placeholder="1234567890123456" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="meta_access_token">Conversions API Access Token</label></th>
                        <td>
                            <input type="password" id="meta_access_token" name="omnisignal_settings[meta_access_token]" value="<?php echo esc_attr($options['meta_access_token'] ?? ''); ?>" class="regular-text" />
                            <p class="description">System User permanent token from Meta Events Manager > Settings > Conversions API.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="meta_test_event_code">Test Event Code (Optional)</label></th>
                        <td>
                            <input type="text" id="meta_test_event_code" name="omnisignal_settings[meta_test_event_code]" value="<?php echo esc_attr($options['meta_test_event_code'] ?? ''); ?>" class="small-text" placeholder="TEST1234" />
                        </td>
                    </tr>
                </table>

                <!-- TikTok Events API -->
                <h2 style="font-size: 17px; margin-top: 35px; padding-bottom: 12px; border-bottom: 2px solid #f1f5f9; font-weight: 700;">🎵 TikTok Events API (Server-to-Server)</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="tiktok_pixel_code">TikTok Pixel Code</label></th>
                        <td>
                            <input type="text" id="tiktok_pixel_code" name="omnisignal_settings[tiktok_pixel_code]" value="<?php echo esc_attr($options['tiktok_pixel_code'] ?? ''); ?>" class="regular-text" placeholder="C1234567890ABC" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="tiktok_access_token">TikTok Access Token</label></th>
                        <td>
                            <input type="password" id="tiktok_access_token" name="omnisignal_settings[tiktok_access_token]" value="<?php echo esc_attr($options['tiktok_access_token'] ?? ''); ?>" class="regular-text" />
                        </td>
                    </tr>
                </table>

                <!-- Form Tracking -->
                <h2 style="font-size: 17px; margin-top: 35px; padding-bottom: 12px; border-bottom: 2px solid #f1f5f9; font-weight: 700;">📋 Form Lead Capture</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">Supported Form Builders</th>
                        <td>
                            <fieldset>
                                <label><input type="checkbox" checked disabled /> Contact Form 7</label><br>
                                <label><input type="checkbox" checked disabled /> WPForms</label><br>
                                <label><input type="checkbox" checked disabled /> Gravity Forms</label><br>
                                <label><input type="checkbox" checked disabled /> Elementor Pro Forms</label>
                                <p class="description">Automatically captures lead submissions and sends offline conversion signals with auto-hashed email and phone numbers.</p>
                            </fieldset>
                        </td>
                    </tr>
                </table>

                <p class="submit" style="margin-top: 30px;">
                    <input type="submit" name="submit" id="submit" class="button button-primary button-hero" value="Save OmniSignal Settings" style="background: #059669; border-color: #059669;" />
                </p>
            </form>
        </div>
        <?php
    }
}
