<?php

/**
 * Remove everything the plugin stored when it is deleted.
 *
 * The settings array holds API access tokens, so leaving it behind means a
 * deleted plugin keeps live credentials in the database indefinitely.
 */
if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

delete_option('omnisignal_settings');
delete_option('omnisignal_license_state');
delete_option('omnisignal_license_last_valid');
delete_option('omnisignal_conversion_logs');
delete_option('omnisignal_recent_failures');

$timestamp = wp_next_scheduled('omnisignal_refresh_license');
if ($timestamp) {
    wp_unschedule_event($timestamp, 'omnisignal_refresh_license');
}
