<?php

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class WC_SadadQa_Blocks extends AbstractPaymentMethodType
{
    private $gateway;
    protected $name = 'sadadpay';
    public function initialize()
    {
        $this->settings = get_option('woocommerce_sadadpay_settings', []);
        $this->gateway = new WC_SadadQa_Blocks();
    }
    public function is_active()
    {
        return true;
    }
    public function get_payment_method_script_handles()
    {
        wp_register_script('wc-sadadqa-blocks-integration',            plugin_dir_url(__DIR__) . 'js/checkout.js',            ['wc-blocks-registry',                'wc-settings',                'wp-element',                'wp-html-entities',                'wp-i18n',],            null,            true);
       
        return ['wc-sadadqa-blocks-integration'];
    }
    public function get_payment_method_data()
    {
        return ['title' => $this->gateway->title,            'description' => $this->gateway->description];
    }
}