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
        add_action('wp_ajax_omnisignal_send_test_event', [__CLASS__, 'ajax_send_test_event']);
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
        register_setting('omnisignal_settings_group', 'omnisignal_settings', [
            'type' => 'array',
            'sanitize_callback' => [__CLASS__, 'sanitize_settings'],
            'default' => [],
        ]);
    }

    public static function log_conversion(array $payload): void
    {
        $logs = get_option('omnisignal_conversion_logs', []);
        $email = $payload['email'] ?? '';
        $masked_email = $email ? substr($email, 0, 2).'***@'.explode('@', $email)[1] : 'N/A';

        array_unshift($logs, [
            'event' => $payload['event_name'] ?? 'Conversion',
            'value' => $payload['value'] ?? 0.0,
            'currency' => $payload['currency'] ?? 'USD',
            'order_id' => $payload['order_id'] ?? 'N/A',
            'masked_email' => $masked_email,
            'has_click_id' => ! empty($payload['gclid'] || $payload['fbclid'] || $payload['ttclid']),
            'timestamp' => time(),
        ]);

        $logs = array_slice($logs, 0, 15);
        update_option('omnisignal_conversion_logs', $logs, false);
    }

    public static function ajax_send_test_event(): void
    {
        check_ajax_referer('omnisignal_test_event_nonce', 'nonce');

        if (! current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $channel = sanitize_text_field($_POST['channel'] ?? 'meta');
        $test_payload = [
            'event_name' => 'Purchase',
            'order_id' => 'TEST_'.time(),
            'value' => 99.00,
            'currency' => 'USD',
            'email' => 'test@omnisignal.dev',
            'phone' => '+15550192834',
            'fbclid' => 'test_fbclid_live_verification',
            'ttclid' => 'test_ttclid_live_verification',
        ];

        OmniSignal_API::send_conversion($test_payload);
        self::log_conversion($test_payload);

        wp_send_json_success(['message' => "Live test conversion event dispatched successfully to {$channel}!"]);
    }

    /**
     * Sanitize the settings array before it is written.
     *
     * Anything not on this list is discarded rather than stored.
     *
     * @param  mixed  $input
     * @return array<string, string>
     */
    public static function sanitize_settings($input): array
    {
        $allowed = [
            'license_key',
            'meta_pixel_id', 'meta_access_token', 'meta_test_event_code',
            'tiktok_pixel_code', 'tiktok_access_token',
            'microsoft_developer_token', 'microsoft_customer_id',
            'microsoft_account_id', 'microsoft_access_token',
            'linkedin_access_token', 'linkedin_conversion_rule_id',
            'default_lead_value', 'default_calling_code',
        ];

        $existing = get_option('omnisignal_settings', []);
        $clean = [];

        foreach ($allowed as $key) {
            $value = isset($input[$key]) ? sanitize_text_field(wp_unslash((string) $input[$key])) : '';

            // A masked secret field submitted unchanged must not wipe the
            // stored value.
            if ($value === '' && str_ends_with($key, '_token') && ! empty($existing[$key])) {
                $clean[$key] = $existing[$key];

                continue;
            }

            $clean[$key] = $value;
        }

        return $clean;
    }

    public static function render_page(): void
    {
        $options = get_option('omnisignal_settings', []);
        $logs = get_option('omnisignal_conversion_logs', []);
        $license_key = $options['license_key'] ?? '';
        $license = OmniSignal_License::state();
        // Verified against omnisignal.dev, not merely "the box is not empty".
        $is_pro = OmniSignal_License::is_valid();
        ?>
        <div class="wrap" style="max-width: 950px; margin-top: 20px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
            
            <!-- Header Banner -->
            <div style="background: #0b0f17; color: #fff; padding: 26px 32px; border-radius: 16px; margin-bottom: 24px; box-shadow: 0 10px 30px rgba(0,0,0,0.2);">
                <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px;">
                    <div style="display: flex; align-items: center; gap: 14px;">
                        <span style="font-size: 32px;">🕉️</span>
                        <div>
                            <h1 style="color: #fff; font-size: 24px; margin: 0; font-weight: 700;">OmniSignal &bull; Conversion Tracking</h1>
                            <p style="color: #94a3b8; font-size: 13px; margin: 4px 0 0 0;">Server-Side Offline Conversion Tracking for WooCommerce (<a href="https://omnisignal.dev" target="_blank" style="color: #34d399; text-decoration: none;">omnisignal.dev</a>)</p>
                        </div>
                    </div>
                    <div>
                        <?php if ($is_pro) { ?>
                            <span style="background: rgba(16, 185, 129, 0.15); color: #34d399; font-size: 12px; padding: 6px 14px; border-radius: 20px; font-weight: 600; border: 1px solid rgba(16, 185, 129, 0.3);">
                                ● <?php echo esc_html(sprintf(__('%s licence active', 'omnisignal'), ucfirst($license['tier'] ?: 'Pro'))); ?>
                            </span>
                        <?php } else { ?>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <span style="background: rgba(148, 163, 184, 0.15); color: #cbd5e1; font-size: 12px; padding: 6px 12px; border-radius: 20px; font-weight: 600;">
                                    Community Edition (Google Ads Free)
                                </span>
                                <a href="https://omnisignal.dev/#pricing" target="_blank" style="background: #10b981; color: #090d16; font-size: 12px; padding: 6px 14px; border-radius: 20px; font-weight: 700; text-decoration: none;">
                                    Unlock Pro CAPI →
                                </a>
                            </div>
                        <?php } ?>
                    </div>
                </div>
            </div>

            <!-- In-Admin Live Conversion Stream -->
            <div style="background: #fff; padding: 24px 30px; border-radius: 16px; border: 1px solid #e2e8f0; margin-bottom: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px;">
                    <h2 style="font-size: 16px; margin: 0; font-weight: 700; display: flex; align-items: center; gap: 8px;">
                        <span style="width: 8px; height: 8px; border-radius: 50%; background: #10b981;"></span>
                        Live Conversion Stream (Last 15 Events)
                    </h2>
                    <button type="button" id="omnisignal-send-test-btn" class="button" style="font-size: 12px; padding: 0 12px;">
                        ⚡ Dispatch Test Event
                    </button>
                </div>

                <?php if (empty($logs)) { ?>
                    <p style="color: #94a3b8; font-size: 13px; margin: 0; font-style: italic;">No conversions recorded yet. Complete a test purchase or click "Dispatch Test Event" above.</p>
                <?php } else { ?>
                    <table class="wp-list-table widefat fixed striped" style="border: 0; font-size: 12px;">
                        <thead>
                            <tr>
                                <th>Event</th>
                                <th>Value</th>
                                <th>Order ID</th>
                                <th>Customer (Masked)</th>
                                <th>Click ID Match</th>
                                <th>Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log) { ?>
                                <tr>
                                    <td><strong style="color: #0f172a;"><?php echo esc_html($log['event']); ?></strong></td>
                                    <td style="color: #059669; font-weight: 600;"><?php echo esc_html($log['currency'].' '.number_format($log['value'], 2)); ?></td>
                                    <td><code><?php echo esc_html($log['order_id']); ?></code></td>
                                    <td><?php echo esc_html($log['masked_email']); ?></td>
                                    <td>
                                        <?php if ($log['has_click_id']) { ?>
                                            <span style="color: #059669; font-weight: 600;">✓ Captured</span>
                                        <?php } else { ?>
                                            <span style="color: #94a3b8;">Direct / Organic</span>
                                        <?php } ?>
                                    </td>
                                    <td style="color: #64748b;"><?php echo esc_html(human_time_diff($log['timestamp'], time()).' ago'); ?></td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                <?php } ?>
            </div>

            <!-- Settings Form -->
            <?php $failures = OmniSignal_API::recent_failures(); ?>
            <?php if (! empty($failures)) { ?>
                <div style="background: #fff; border: 1px solid #fecaca; border-left: 4px solid #dc2626; border-radius: 12px; padding: 20px 24px; margin-bottom: 24px;">
                    <h2 style="font-size: 15px; margin: 0 0 4px; font-weight: 700; color: #991b1b;">
                        <?php esc_html_e('Recent delivery failures', 'omnisignal'); ?>
                    </h2>
                    <p style="margin: 0 0 12px; color: #64748b; font-size: 13px;">
                        <?php esc_html_e('These conversions were not accepted by the ad platform. Fix the cause and the next conversion will send normally.', 'omnisignal'); ?>
                    </p>
                    <table class="widefat striped" style="border: none;">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('When', 'omnisignal'); ?></th>
                                <th><?php esc_html_e('Channel', 'omnisignal'); ?></th>
                                <th><?php esc_html_e('Event', 'omnisignal'); ?></th>
                                <th><?php esc_html_e('What the platform said', 'omnisignal'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($failures as $failure) { ?>
                                <tr>
                                    <td style="white-space: nowrap;"><?php echo esc_html(human_time_diff((int) $failure['at']).' ago'); ?></td>
                                    <td><?php echo esc_html(ucfirst((string) $failure['channel'])); ?></td>
                                    <td><?php echo esc_html((string) $failure['event']); ?></td>
                                    <td style="color: #991b1b;"><?php echo esc_html((string) $failure['message']); ?></td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            <?php } ?>

            <form method="post" action="options.php" style="background: #fff; padding: 32px; border-radius: 16px; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                <?php settings_fields('omnisignal_settings_group'); ?>

                <!-- License Key -->
                <h2 style="font-size: 16px; margin-top: 0; padding-bottom: 12px; border-bottom: 2px solid #f1f5f9; font-weight: 700;">🔑 OmniSignal License</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="license_key">License Key</label></th>
                        <td>
                            <input type="text" id="license_key" name="omnisignal_settings[license_key]" value="<?php echo esc_attr($license_key); ?>" class="regular-text" placeholder="OMNI-XXXX-XXXX-XXXX-XXXX" />
                            <p class="description">
                                <?php
                                    $tone = ['valid' => '#059669', 'invalid' => '#dc2626', 'unreachable' => '#b45309'];
        printf(
            '<strong style="color:%s">%s</strong> %s',
            esc_attr($tone[$license['status']] ?? '#64748b'),
            esc_html(ucfirst($license['status'])),
            esc_html($license['message'])
        );
        ?>
                            </p>
                            <p class="description">
                                <?php esc_html_e('Manage your key and activated domains at', 'omnisignal'); ?>
                                <a href="https://omnisignal.dev/portal" target="_blank">omnisignal.dev/portal</a>.
                            </p>
                        </td>
                    </tr>
                </table>

                <!-- Google Ads (Free) -->
                <h2 style="font-size: 16px; margin-top: 30px; padding-bottom: 12px; border-bottom: 2px solid #f1f5f9; font-weight: 700; display: flex; align-items: center; justify-content: space-between;">
                    <span>🟢 Google Ads Offline Conversions (100% Free)</span>
                    <span style="background: #ecfdf5; color: #059669; font-size: 10px; padding: 2px 8px; border-radius: 10px; font-weight: 700;">FREE FOREVER</span>
                </h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">Tracking Engine</th>
                        <td>
                            <p style="margin: 0; color: #334155; font-size: 13px;">Auto-captures GCLID, GBRAID, WBRAID, and stores click attribution on every order.</p>
                        </td>
                    </tr>
                </table>

                <!-- Meta CAPI (Pro) -->
                <h2 style="font-size: 16px; margin-top: 30px; padding-bottom: 12px; border-bottom: 2px solid #f1f5f9; font-weight: 700;">🔵 Meta (Facebook & Instagram) CAPI</h2>
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

                <!-- TikTok Events API (Pro) -->
                <h2 style="font-size: 16px; margin-top: 30px; padding-bottom: 12px; border-bottom: 2px solid #f1f5f9; font-weight: 700;">🎵 TikTok Events API (Server-to-Server)</h2>
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

                <!-- Google Ads via omnisignal.dev -->
                <h2 style="font-size: 16px; margin-top: 30px; padding-bottom: 12px; border-bottom: 2px solid #f1f5f9; font-weight: 700;">🔴 Google Ads (connected at omnisignal.dev)</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Status', 'omnisignal'); ?></th>
                        <td>
                            <?php $hosted = OmniSignal_License::hosted_channels(); ?>
                            <?php if (in_array('google', $hosted, true)) { ?>
                                <span style="background:#dcfce7;color:#166534;padding:4px 10px;border-radius:12px;font-size:12px;font-weight:600;">
                                    ● <?php esc_html_e('Connected', 'omnisignal'); ?>
                                </span>
                                <p class="description">
                                    <?php esc_html_e('Purchases and leads from this site are uploaded to your Google Ads account.', 'omnisignal'); ?>
                                    <a href="https://omnisignal.dev/portal" target="_blank"><?php esc_html_e('Manage the connection', 'omnisignal'); ?></a>
                                </p>
                            <?php } elseif (OmniSignal_License::is_valid()) { ?>
                                <span style="background:#fef3c7;color:#92400e;padding:4px 10px;border-radius:12px;font-size:12px;font-weight:600;">
                                    <?php esc_html_e('Not connected', 'omnisignal'); ?>
                                </span>
                                <p class="description">
                                    <?php esc_html_e('Google Ads is authorised on omnisignal.dev rather than here, because it needs an OAuth client secret and a developer token that cannot ship inside a plugin.', 'omnisignal'); ?>
                                    <br>
                                    <a href="https://omnisignal.dev/portal" target="_blank"><strong><?php esc_html_e('Connect your Google Ads account →', 'omnisignal'); ?></strong></a>
                                </p>
                            <?php } else { ?>
                                <p class="description">
                                    <?php esc_html_e('Enter a valid licence key above to connect Google Ads.', 'omnisignal'); ?>
                                </p>
                            <?php } ?>
                        </td>
                    </tr>
                </table>

                <!-- Microsoft Advertising -->
                <h2 style="font-size: 16px; margin-top: 30px; padding-bottom: 12px; border-bottom: 2px solid #f1f5f9; font-weight: 700;">🟦 Microsoft Advertising (Bing) Offline Conversions</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="microsoft_developer_token">Developer Token</label></th>
                        <td><input type="password" id="microsoft_developer_token" name="omnisignal_settings[microsoft_developer_token]" value="<?php echo esc_attr($options['microsoft_developer_token'] ?? ''); ?>" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="microsoft_customer_id">Customer (Manager) ID</label></th>
                        <td>
                            <input type="text" id="microsoft_customer_id" name="omnisignal_settings[microsoft_customer_id]" value="<?php echo esc_attr($options['microsoft_customer_id'] ?? ''); ?>" class="regular-text" />
                            <p class="description"><?php esc_html_e('The manager account ID, not the ad account.', 'omnisignal'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="microsoft_account_id">Ad Account ID</label></th>
                        <td>
                            <input type="text" id="microsoft_account_id" name="omnisignal_settings[microsoft_account_id]" value="<?php echo esc_attr($options['microsoft_account_id'] ?? ''); ?>" class="regular-text" />
                            <p class="description"><?php esc_html_e('Both IDs are required; Microsoft rejects the upload without them.', 'omnisignal'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="microsoft_access_token">OAuth Access Token</label></th>
                        <td><input type="password" id="microsoft_access_token" name="omnisignal_settings[microsoft_access_token]" value="<?php echo esc_attr($options['microsoft_access_token'] ?? ''); ?>" class="regular-text" /></td>
                    </tr>
                </table>

                <!-- LinkedIn -->
                <h2 style="font-size: 16px; margin-top: 30px; padding-bottom: 12px; border-bottom: 2px solid #f1f5f9; font-weight: 700;">🔗 LinkedIn Conversions API</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="linkedin_access_token">Access Token</label></th>
                        <td><input type="password" id="linkedin_access_token" name="omnisignal_settings[linkedin_access_token]" value="<?php echo esc_attr($options['linkedin_access_token'] ?? ''); ?>" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="linkedin_conversion_rule_id">Conversion Rule ID</label></th>
                        <td><input type="text" id="linkedin_conversion_rule_id" name="omnisignal_settings[linkedin_conversion_rule_id]" value="<?php echo esc_attr($options['linkedin_conversion_rule_id'] ?? ''); ?>" class="regular-text" /></td>
                    </tr>
                </table>

                <!-- Lead defaults -->
                <h2 style="font-size: 16px; margin-top: 30px; padding-bottom: 12px; border-bottom: 2px solid #f1f5f9; font-weight: 700;">⚙️ Lead Defaults</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="default_lead_value">Default lead value</label></th>
                        <td>
                            <input type="number" step="0.01" min="0" id="default_lead_value" name="omnisignal_settings[default_lead_value]" value="<?php echo esc_attr($options['default_lead_value'] ?? '25'); ?>" class="small-text" />
                            <span><?php echo esc_html(function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'USD'); ?></span>
                            <p class="description"><?php esc_html_e('Value attached to a form lead. Reported in your store currency.', 'omnisignal'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="default_calling_code">Default calling code</label></th>
                        <td>
                            <input type="text" id="default_calling_code" name="omnisignal_settings[default_calling_code]" value="<?php echo esc_attr($options['default_calling_code'] ?? ''); ?>" class="small-text" placeholder="1" />
                            <p class="description"><?php esc_html_e('For phone numbers stored without a country code (1 = US, 44 = UK). Left blank, the store country is used; numbers that still cannot be resolved are dropped rather than hashed under a guessed country.', 'omnisignal'); ?></p>
                        </td>
                    </tr>
                </table>

                <!-- Form Tracking -->
                <h2 style="font-size: 16px; margin-top: 30px; padding-bottom: 12px; border-bottom: 2px solid #f1f5f9; font-weight: 700;">📋 Form Lead Capture</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">Supported Form Builders</th>
                        <td>
                            <fieldset>
                                <label><input type="checkbox" checked disabled /> Contact Form 7</label><br>
                                <label><input type="checkbox" checked disabled /> WPForms</label><br>
                                <label><input type="checkbox" checked disabled /> Gravity Forms</label><br>
                                <label><input type="checkbox" checked disabled /> Elementor Pro Forms</label><br>
                                <label><input type="checkbox" checked disabled /> Fluent Forms</label><br>
                                <label><input type="checkbox" checked disabled /> Ninja Forms</label>
                                <p class="description">Auto-captures form submissions and transmits offline conversion signals with auto-hashed email and phone numbers.</p>
                            </fieldset>
                        </td>
                    </tr>
                </table>

                <p class="submit" style="margin-top: 30px;">
                    <input type="submit" name="submit" id="submit" class="button button-primary button-hero" value="Save OmniSignal Settings" style="background: #059669; border-color: #059669;" />
                </p>
            </form>
        </div>

        <script>
            document.getElementById('omnisignal-send-test-btn').addEventListener('click', function() {
                var btn = this;
                btn.disabled = true;
                btn.textContent = 'Sending...';

                var data = new FormData();
                data.append('action', 'omnisignal_send_test_event');
                data.append('nonce', '<?php echo wp_create_nonce('omnisignal_test_event_nonce'); ?>');
                data.append('channel', 'meta');

                fetch(ajaxurl, {
                    method: 'POST',
                    body: data
                })
                .then(r => r.json())
                .then(res => {
                    alert(res.data.message || 'Test event dispatched!');
                    location.reload();
                })
                .catch(err => {
                    alert('Error sending test event.');
                    btn.disabled = false;
                    btn.textContent = '⚡ Dispatch Test Event';
                });
            });
        </script>
        <?php
    }
}
