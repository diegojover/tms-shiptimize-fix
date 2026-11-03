<?php
/**
 * Plugin Name: TMS — Shiptimize Address Fix
 * Description: GB postcodes for Shiptimize and international telephone format at checkout.
 * Version: 1.2.0
 * Update URI: https://github.com/diegojover/tms-shiptimize-fix
 * Plugin URI: https://github.com/diegojover/tms-shiptimize-fix
 * Requires PHP: 8.1
 * Requires at least: 6.5
 * WC requires at least: 8.9
 * Requires Plugins: woocommerce, shiptimize-for-woocommerce
 * Author: Tibetan Meditation Shop
 */
defined('ABSPATH') || exit;
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/includes/github-updates.php';

final class TMS_Shiptimize_Fix {
    const FIELD = 'tms-phone/country';

    public static function message($english, $portuguese) {
        return strpos(determine_locale(), 'pt') === 0 ? $portuguese : $english;
    }

    public static function register_field() {
        if (!function_exists('woocommerce_register_additional_checkout_field')) { return; }
        $util = \TMSVendor\Phone\PhoneNumberUtil::getInstance();
        $countries = WC()->countries->get_countries();
        $options = array(array('value' => 'INTL', 'label' => self::message(
            'Number already includes +country code', 'O número já inclui +indicativo'
        )));
        // Telephone country is independent of the shop's shipping destinations.
        foreach ($countries as $region => $name) {
            $code = $util->getCountryCodeForRegion($region);
            if ($code) { $options[] = array('value' => $region, 'label' => $name . ' (+' . $code . ')'); }
        }
        woocommerce_register_additional_checkout_field(array(
            'id' => self::FIELD,
            'label' => self::message('Phone country / calling code', 'País do telefone / indicativo'),
            'optionalLabel' => self::message('Phone country (for local numbers)', 'País do telefone (para números locais)'),
            'location' => 'address', 'type' => 'select', 'required' => false,
            'options' => $options,
        ));
    }

    // Validate against numbering plans and return E.164. Never infer from address.
    public static function parse_phone($value, $region = '') {
        $value = trim((string) $value);
        $value = preg_replace('/[\s\x{00A0}().\-]+/u', '', $value);
        if (!is_string($value) || !preg_match('/^(?:\+|00)?[0-9]+$/D', $value)) { return false; }
        if (substr($value, 0, 2) === '00') { $value = '+' . substr($value, 2); }
        $util = \TMSVendor\Phone\PhoneNumberUtil::getInstance();
        $region = strtoupper((string) $region);
        if (substr($value, 0, 1) !== '+' && !in_array($region, $util->getSupportedRegions(), true)) { return false; }
        try {
            $number = $util->parse($value, substr($value, 0, 1) === '+' ? null : $region);
            if ($number->hasExtension() || !$util->isValidNumber($number)) { return false; }
            return $util->format($number, \TMSVendor\Phone\PhoneNumberFormat::E164);
        } catch (\TMSVendor\Phone\NumberParseException $exception) {
            return false;
        }
    }

    public static function phone_error() {
        return self::message(
            'Enter a valid phone number and select its phone country, or include the international calling code (for example +44 or +351).',
            'Introduza um telefone válido e selecione o país do telefone, ou inclua o indicativo internacional (por exemplo +44 ou +351).'
        );
    }
    // Scope getter changes to the adapter supplied in Shiptimize 4.1.4.
    // Changing the HTTP body later would invalidate its HMAC signature.
    private static function in_adapter() {
        foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 24) as $frame) {
            if (isset($frame['class']) && $frame['class'] === 'WooShiptimizeOrder') {
                return true;
            }
        }
        return false;
    }

    public static function phone($value) {
        return is_string($value) ? (self::parse_phone($value) ?: $value) : $value;
    }

    public static function international($value) {
        return is_string($value) && self::parse_phone($value) !== false;
    }

    public static function export_phone($value) {
        return self::in_adapter() ? self::phone($value) : $value;
    }

    public static function export_postcode($value, $order) {
        if (!self::in_adapter() || !is_string($value)) { return $value; }
        // Match the adapter's effective destination country, including its fallback.
        $country = $order->get_shipping_country('edit') ?: $order->get_billing_country('edit');
        if (strtoupper($country) !== 'GB') { return $value; }
        $compact = preg_replace('/[\s\x{00A0}]+/u', '', strtoupper($value));
        // Only compact recognisable GB structures; never rewrite letters/digits.
        if (is_string($compact) && preg_match('/^(?:GIR0AA|[A-Z]{1,2}[0-9][A-Z0-9]?[0-9][A-Z]{2})$/D', $compact)) {
            return $compact;
        }
        return $value;
    }

    public static function validate_classic($data, $errors) {
        if (function_exists('WC') && WC()->cart && !WC()->cart->needs_shipping()) { return; }
        $shipping = isset($data['shipping_phone']) ? trim((string) $data['shipping_phone']) : '';
        $billing = isset($data['billing_phone']) ? trim((string) $data['billing_phone']) : '';
        $phone = !empty($data['ship_to_different_address']) && $shipping !== '' ? $shipping : $billing;
        if (!self::international($phone)) {
            $errors->add('tms_international_phone', self::phone_error());
        }
    }

    public static function validate_blocks($order) {
        $physical = false;
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            if ($product && $product->needs_shipping()) { $physical = true; break; }
        }
        if (!$physical) { return; }
        $fields = \Automattic\WooCommerce\Blocks\Package::container()->get(
            \Automattic\WooCommerce\Blocks\Domain\Services\CheckoutFields::class
        );
        $normalised = array();
        foreach (array('billing', 'shipping') as $type) {
            $getter = 'get_' . $type . '_phone';
            $phone = trim((string) $order->$getter('edit'));
            if ($phone === '') { continue; }
            $region = $fields->get_field_from_object(self::FIELD, $order, $type);
            $parsed = self::parse_phone($phone, $region);
            if ($parsed === false) {
                throw new \Automattic\WooCommerce\StoreApi\Exceptions\RouteException(
                    'tms_invalid_' . $type . '_phone', self::phone_error(), 400
                );
            }
            $normalised[$type] = $parsed;
        }
        if (!$normalised) {
            throw new \Automattic\WooCommerce\StoreApi\Exceptions\RouteException(
                'tms_international_phone', self::phone_error(), 400
            );
        }
        // Apply only after every provided number has passed validation.
        foreach ($normalised as $type => $phone) {
            $setter = 'set_' . $type . '_phone';
            $order->$setter($phone);
        }
        $order->save();
    }
}

foreach (array('billing', 'shipping') as $type) {
    add_filter('woocommerce_order_get_' . $type . '_postcode', array('TMS_Shiptimize_Fix', 'export_postcode'), 20, 2);
    add_filter('woocommerce_order_get_' . $type . '_phone', array('TMS_Shiptimize_Fix', 'export_phone'), 20, 1);
}
add_action('woocommerce_after_checkout_validation', array('TMS_Shiptimize_Fix', 'validate_classic'), 20, 2);
add_action('woocommerce_store_api_checkout_order_processed', array('TMS_Shiptimize_Fix', 'validate_blocks'), 5, 1);
add_action('woocommerce_init', array('TMS_Shiptimize_Fix', 'register_field'));
add_action('before_woocommerce_init', static function () {
    if (class_exists('Automattic\\WooCommerce\\Utilities\\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
    }
});
