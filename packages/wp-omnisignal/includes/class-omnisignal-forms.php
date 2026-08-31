<?php

if (! defined('ABSPATH')) {
    exit;
}

class OmniSignal_Forms
{
    public static function init(): void
    {
        // 1. Contact Form 7
        add_action('wpcf7_mail_sent', [__CLASS__, 'on_cf7_submit']);

        // 2. WPForms
        add_action('wpforms_process_complete', [__CLASS__, 'on_wpforms_submit'], 10, 4);

        // 3. Gravity Forms
        add_action('gform_after_submission', [__CLASS__, 'on_gravity_forms_submit'], 10, 2);

        // 4. Elementor Pro Forms
        add_action('elementor_pro/forms/new_record', [__CLASS__, 'on_elementor_submit'], 10, 2);

        // 5. Fluent Forms
        add_action('fluentform/submission_inserted', [__CLASS__, 'on_fluent_forms_submit'], 10, 3);

        // 6. Ninja Forms
        add_action('ninja_forms_after_submission', [__CLASS__, 'on_ninja_forms_submit']);
    }

    public static function on_cf7_submit($contact_form): void
    {
        $submission = WPCF7_Submission::get_instance();
        if (! $submission) {
            return;
        }

        $data = $submission->get_posted_data();
        $email = $data['your-email'] ?? $data['email'] ?? null;
        $phone = $data['your-tel'] ?? $data['your-phone'] ?? $data['phone'] ?? null;

        self::dispatch_lead('Contact Form 7', $email, $phone);
    }

    public static function on_wpforms_submit(array $fields, array $entry, array $form_data, int $entry_id): void
    {
        $email = null;
        $phone = null;

        foreach ($fields as $field) {
            $type = $field['type'] ?? '';
            $val = $field['value'] ?? '';

            if ($type === 'email' && ! $email) {
                $email = $val;
            } elseif ($type === 'phone' && ! $phone) {
                $phone = $val;
            }
        }

        self::dispatch_lead('WPForms', $email, $phone);
    }

    public static function on_gravity_forms_submit(array $entry, array $form): void
    {
        $email = null;
        $phone = null;

        foreach ($form['fields'] ?? [] as $field) {
            if ($field->type === 'email' && ! $email) {
                $email = rgar($entry, (string) $field->id);
            } elseif ($field->type === 'phone' && ! $phone) {
                $phone = rgar($entry, (string) $field->id);
            }
        }

        self::dispatch_lead('Gravity Forms', $email, $phone);
    }

    public static function on_elementor_submit($record, $handler): void
    {
        $raw_fields = $record->get('fields');
        $email = null;
        $phone = null;

        foreach ($raw_fields as $id => $field) {
            if (str_contains(strtolower($id), 'email') && ! $email) {
                $email = $field['value'] ?? null;
            } elseif (str_contains(strtolower($id), 'phone') && ! $phone) {
                $phone = $field['value'] ?? null;
            }
        }

        self::dispatch_lead('Elementor Form', $email, $phone);
    }

    public static function on_fluent_forms_submit(int $insert_id, array $form_data, $form): void
    {
        $email = $form_data['email'] ?? $form_data['user_email'] ?? null;
        $phone = $form_data['phone'] ?? $form_data['phone_number'] ?? null;

        self::dispatch_lead('Fluent Forms', $email, $phone);
    }

    public static function on_ninja_forms_submit(array $form_data): void
    {
        $fields = $form_data['fields'] ?? [];
        $email = null;
        $phone = null;

        foreach ($fields as $field) {
            $key = strtolower($field['key'] ?? '');
            if (str_contains($key, 'email') && ! $email) {
                $email = $field['value'] ?? null;
            } elseif (str_contains($key, 'phone') && ! $phone) {
                $phone = $field['value'] ?? null;
            }
        }

        self::dispatch_lead('Ninja Forms', $email, $phone);
    }

    private static function default_lead_value(): float
    {
        $options = get_option('omnisignal_settings', []);
        $value = $options['default_lead_value'] ?? '';

        return is_numeric($value) ? (float) $value : 25.0;
    }

    private static function dispatch_lead(string $source, ?string $email, ?string $phone): void
    {
        if (! $email && ! $phone) {
            return;
        }

        $clicks = OmniSignal_Tracker::get_click_ids();

        $payload = [
            'event_name' => 'Lead',
            'order_id' => 'LEAD_'.uniqid(),
            // Configurable in the settings screen, and reported in the store's
            // own currency rather than always USD.
            'value' => (float) apply_filters('omnisignal_default_lead_value', self::default_lead_value()),
            'currency' => function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'USD',
            'email' => $email,
            'phone' => $phone,
            'source' => $source,
            'gclid' => $clicks['gclid'],
            'gbraid' => $clicks['gbraid'],
            'wbraid' => $clicks['wbraid'],
            'fbclid' => $clicks['fbclid'],
            'msclkid' => $clicks['msclkid'],
            'ttclid' => $clicks['ttclid'],
            'li_fat_id' => $clicks['li_fat_id'],
        ];

        OmniSignal_API::send_conversion($payload);
        OmniSignal_Admin::log_conversion($payload);
    }
}
