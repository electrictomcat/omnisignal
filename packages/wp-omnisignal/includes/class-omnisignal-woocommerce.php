<?php

if (! defined('ABSPATH')) {
    exit;
}

class OmniSignal_WooCommerce
{
    public static function init(): void
    {
        // 1. Purchase Events
        add_action('woocommerce_payment_complete', [__CLASS__, 'on_payment_complete']);
        add_action('woocommerce_thankyou', [__CLASS__, 'on_thankyou'], 10, 1);

        // 2. Add to Cart (Server-Side)
        add_action('woocommerce_add_to_cart', [__CLASS__, 'on_add_to_cart'], 10, 6);

        // 3. Initiate Checkout
        add_action('woocommerce_before_checkout_form', [__CLASS__, 'on_initiate_checkout']);

        // 4. Order Refunded
        add_action('woocommerce_order_status_refunded', [__CLASS__, 'on_order_refunded'], 10, 1);

        // 5. Stamp the visitor's click IDs onto the order while we still have
        //    their cookies. woocommerce_payment_complete often fires from a
        //    gateway webhook — a server-to-server request carrying no cookies
        //    at all — so reading them at that point attributed nothing.
        add_action('woocommerce_checkout_create_order', [__CLASS__, 'stamp_click_ids'], 10, 2);
        add_action('woocommerce_store_api_checkout_update_order_from_request', [__CLASS__, 'stamp_click_ids'], 10, 2);
    }

    /**
     * Persist the current visitor's click identifiers on the order.
     *
     * @param  WC_Order  $order
     */
    public static function stamp_click_ids($order, $data = null): void
    {
        if (! is_object($order) || ! method_exists($order, 'update_meta_data')) {
            return;
        }

        foreach (OmniSignal_Tracker::get_click_ids() as $key => $value) {
            if ($value !== '') {
                $order->update_meta_data('_omnisignal_'.$key, $value);
            }
        }
    }

    /**
     * Click identifiers for an order: what was stamped at checkout, falling
     * back to the current request's cookies for orders created another way.
     *
     * @param  WC_Order  $order
     * @return array<string, string>
     */
    private static function click_ids_for_order($order): array
    {
        $live = OmniSignal_Tracker::get_click_ids();
        $clicks = [];

        foreach ($live as $key => $value) {
            $stored = $order->get_meta('_omnisignal_'.$key);
            $clicks[$key] = $stored !== '' && $stored !== null ? (string) $stored : $value;
        }

        return $clicks;
    }

    public static function on_payment_complete(int $order_id): void
    {
        self::process_purchase($order_id);
    }

    public static function on_thankyou(int $order_id): void
    {
        if (! $order_id) {
            return;
        }

        self::process_purchase($order_id);
    }

    public static function on_add_to_cart(string $cart_item_key, int $product_id, int $quantity, int $variation_id, array $variation, array $cart_item_data): void
    {
        $product = wc_get_product($variation_id ?: $product_id);
        if (! $product) {
            return;
        }

        $clicks = OmniSignal_Tracker::get_click_ids();
        $payload = [
            'event_name' => 'AddToCart',
            'order_id' => 'CART_'.uniqid(),
            'value' => (float) $product->get_price() * $quantity,
            'currency' => get_woocommerce_currency(),
            'custom_data' => [
                'content_name' => $product->get_name(),
                'content_ids' => [(string) $product->get_id()],
                'content_type' => 'product',
                'num_items' => $quantity,
            ],
            'gclid' => $clicks['gclid'],
            'gbraid' => $clicks['gbraid'],
            'wbraid' => $clicks['wbraid'],
            'fbclid' => $clicks['fbclid'],
            'msclkid' => $clicks['msclkid'],
            'ttclid' => $clicks['ttclid'],
            'li_fat_id' => $clicks['li_fat_id'],
        ];

        OmniSignal_API::send_conversion($payload);
    }

    public static function on_initiate_checkout(): void
    {
        if (! WC()->cart || WC()->cart->is_empty()) {
            return;
        }

        $clicks = OmniSignal_Tracker::get_click_ids();
        $payload = [
            'event_name' => 'InitiateCheckout',
            'order_id' => 'CHECKOUT_'.uniqid(),
            'value' => (float) WC()->cart->get_total('edit'),
            'currency' => get_woocommerce_currency(),
            'custom_data' => [
                'num_items' => WC()->cart->get_cart_contents_count(),
            ],
            'gclid' => $clicks['gclid'],
            'gbraid' => $clicks['gbraid'],
            'wbraid' => $clicks['wbraid'],
            'fbclid' => $clicks['fbclid'],
            'msclkid' => $clicks['msclkid'],
            'ttclid' => $clicks['ttclid'],
            'li_fat_id' => $clicks['li_fat_id'],
        ];

        OmniSignal_API::send_conversion($payload);
    }

    public static function on_order_refunded(int $order_id): void
    {
        $order = wc_get_order($order_id);
        if (! $order) {
            return;
        }

        $clicks = self::click_ids_for_order($order);
        $payload = [
            'event_name' => 'Refund',
            'order_id' => $order->get_id(),
            'value' => (float) $order->get_total_refunded(),
            'currency' => $order->get_currency(),
            'email' => $order->get_billing_email(),
            'phone' => $order->get_billing_phone(),
            'gclid' => $clicks['gclid'],
            'fbclid' => $clicks['fbclid'],
            'ttclid' => $clicks['ttclid'],
        ];

        OmniSignal_API::send_conversion($payload);
    }

    private static function process_purchase(int $order_id): void
    {
        $order = wc_get_order($order_id);
        if (! $order) {
            return;
        }

        // Order meta, not post meta: under High-Performance Order Storage —
        // the WooCommerce default — orders are not posts, so get_post_meta()
        // always returned nothing and both purchase hooks fired.
        if ($order->get_meta('_omnisignal_uploaded')) {
            return;
        }

        $clicks = self::click_ids_for_order($order);

        $items = [];
        foreach ($order->get_items() as $item) {
            $items[] = [
                'id' => (string) $item->get_product_id(),
                'name' => $item->get_name(),
                'quantity' => $item->get_quantity(),
                'item_price' => (float) ($item->get_total() / max(1, $item->get_quantity())),
            ];
        }

        $payload = [
            'event_name' => 'Purchase',
            'order_id' => $order->get_id(),
            'value' => (float) $order->get_total(),
            'currency' => $order->get_currency(),
            'email' => $order->get_billing_email(),
            'phone' => $order->get_billing_phone(),
            'first_name' => $order->get_billing_first_name(),
            'last_name' => $order->get_billing_last_name(),
            'city' => $order->get_billing_city(),
            'state' => $order->get_billing_state(),
            'postcode' => $order->get_billing_postcode(),
            'country' => $order->get_billing_country(),
            'custom_data' => [
                'contents' => $items,
                'num_items' => count($items),
                'tax' => (float) $order->get_total_tax(),
                'shipping' => (float) $order->get_shipping_total(),
            ],
            'gclid' => $clicks['gclid'],
            'gbraid' => $clicks['gbraid'],
            'wbraid' => $clicks['wbraid'],
            'fbclid' => $clicks['fbclid'],
            'msclkid' => $clicks['msclkid'],
            'ttclid' => $clicks['ttclid'],
            'li_fat_id' => $clicks['li_fat_id'],
        ];

        // Claim the order before dispatching, so a second hook firing while
        // the first is still in flight cannot send a duplicate.
        $order->update_meta_data('_omnisignal_uploaded', time());
        $order->save();

        OmniSignal_API::send_conversion($payload);

        // Store log for in-admin analytics
        OmniSignal_Admin::log_conversion($payload);
    }
}
