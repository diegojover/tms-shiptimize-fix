<?php
namespace Automattic\WooCommerce\StoreApi\Exceptions {
    class RouteException extends \Exception { public function __construct($code, $message, $status) { parent::__construct($message); } }
}
namespace Automattic\WooCommerce\Blocks {
    class Package { public static function container() { return new \TestFields(); } }
}
namespace {
    define('ABSPATH', __DIR__);
    function add_action(...$args) {}
    function add_filter(...$args) {}
    function determine_locale() { return 'pt_PT'; }
    function woocommerce_register_additional_checkout_field($field) { $GLOBALS['field'] = $field; }
    function WC() { return (object) array('countries' => new TestCountries()); }
    class TestCountries { public function get_countries() { return array('PT'=>'Portugal','GB'=>'United Kingdom','US'=>'United States','IT'=>'Italy'); } }
    class TestFields {
        public function get($name) { return $this; }
        public function get_field_from_object($field, $order, $type) { return $order->regions[$type] ?? ''; }
    }
    class TestProduct { public function needs_shipping() { return true; } }
    class TestItem { public function get_product() { return new TestProduct(); } }
    class TestOrder {
        public $billing = ''; public $shipping = ''; public $regions = array(); public $saved = 0; public $physical = true;
        public function get_items() { return $this->physical ? array(new TestItem()) : array(); }
        public function get_billing_phone($context = '') { return $this->billing; }
        public function get_shipping_phone($context = '') { return $this->shipping; }
        public function set_billing_phone($value) { $this->billing = $value; }
        public function set_shipping_phone($value) { $this->shipping = $value; }
        public function get_shipping_country($context = '') { return 'GB'; }
        public function get_billing_country($context = '') { return 'PT'; }
        public function save() { ++$this->saved; }
    }
    require __DIR__ . '/../tms-shiptimize-fix/tms-shiptimize-fix.php';
    $count = 0;
    function check($actual, $expected, $label) {
        global $count; ++$count;
        if ($actual !== $expected) { throw new \RuntimeException($label . ': ' . var_export($actual, true)); }
    }
    $cases = array(
        array('07400 123456','GB','+447400123456'),
        array('020 7946 0018','GB','+442079460018'),
        array('912 345 678','PT','+351912345678'),
        array('06 6982','IT','+39066982'),
        array('(202) 555-0123','US','+12025550123'),
        array('+351 912 345 678','GB','+351912345678'),
        array('0044 7400 123456','','+447400123456'),
        array('+44 (0)20 7946 0018','','+442079460018'),
        array('07400123456','',false),
        array('123','PT',false),
        array('+999123456789','',false),
        array('+351912345678 ext 12','',false),
        array('abc912345678','PT',false),
        array('','GB',false),
    );
    foreach ($cases as $case) { check(TMS_Shiptimize_Fix::parse_phone($case[0], $case[1]), $case[2], 'parse ' . $case[0]); }
    TMS_Shiptimize_Fix::register_field();
    check($GLOBALS['field']['location'], 'address', 'Native address field');
    check($GLOBALS['field']['options'][1]['label'], 'Portugal (+351)', 'Calling code option');
    $order = new TestOrder(); $order->billing = '912 345 678'; $order->regions['billing'] = 'PT';
    TMS_Shiptimize_Fix::validate_blocks($order);
    check($order->billing, '+351912345678', 'Local phone persisted in E164');
    check($order->saved, 1, 'Saved');
    $order = new TestOrder(); $order->billing = '+351912345678'; $order->shipping = '07400123456'; $order->regions['shipping'] = 'GB';
    TMS_Shiptimize_Fix::validate_blocks($order);
    check($order->shipping, '+447400123456', 'Separate shipping phone');
    check($order->billing, '+351912345678', 'Separate billing phone');
    $order = new TestOrder(); $order->billing = '912345678';
    try { TMS_Shiptimize_Fix::validate_blocks($order); throw new \RuntimeException('Missing selector accepted'); }
    catch (\Automattic\WooCommerce\StoreApi\Exceptions\RouteException $e) { check($order->saved, 0, 'Invalid phone not saved'); }
    $order = new TestOrder(); $order->billing = '912345678'; $order->regions['billing'] = 'PT'; $order->shipping = '123';
    try { TMS_Shiptimize_Fix::validate_blocks($order); throw new \RuntimeException('Invalid shipping accepted'); }
    catch (\Automattic\WooCommerce\StoreApi\Exceptions\RouteException $e) { check($order->billing, '912345678', 'No partial changes on error'); }
    $order = new TestOrder(); $order->physical = false;
    TMS_Shiptimize_Fix::validate_blocks($order); check($order->saved, 0, 'Virtual checkout unaffected');
    class WooShiptimizeOrder {
        public function postcode($value, $order) { return TMS_Shiptimize_Fix::export_postcode($value, $order); }
        public function phone($value) { return TMS_Shiptimize_Fix::export_phone($value); }
    }
    $adapter = new WooShiptimizeOrder();
    check($adapter->postcode('wv6 9  pq', new TestOrder()), 'WV69PQ', 'Shiptimize postcode');
    check(TMS_Shiptimize_Fix::export_postcode('WV6 9PQ', new TestOrder()), 'WV6 9PQ', 'Outside adapter unchanged');
    check($adapter->phone('0044 7400 123456'), '+447400123456', 'Shiptimize phone');
    check(TMS_Shiptimize_Fix::export_phone('0044 7400 123456'), '0044 7400 123456', 'Outside adapter phone unchanged');
    echo "PASS: $count assertions\n";
}
