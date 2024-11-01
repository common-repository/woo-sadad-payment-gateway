<?php
/*
  Plugin Name: Sadad QA Payment
  Version: 1.25
  Description: This plugin using visitors to pay via Sadad.
  Author: Sadad Developer
  Author URI: https://sadad.qa/
 */
define('SADAD_PLUGIN_PATH', plugin_dir_path(__FILE__));
require_once(SADAD_PLUGIN_PATH . 'include/functions.php');
define('SADAD_PLUGIN_URl', __FILE__);
add_action('plugins_loaded', 'WC_sadadpay_init');

function WC_sadadpay_init()
{

    if (!class_exists('WC_Payment_Gateway'))
        return;

    if (isset($_GET['msg'])) {
        add_action('the_content', 'SadadPayShowMsg');
    }

    function SadadPayShowMsg($content)
    {
        return '<div class="box ' . htmlentities($_GET['type']) . '-box">' . htmlentities(urldecode($_GET['msg'])) . '</div>' . $content;
    }

    /**
     * WC_Gateway_sadadpay class
     */
    class WC_Gateway_sadadpay extends WC_Payment_Gateway
    {

        protected $msg = array();

        /**
         * Constructor for the gateway.
         */
        public function __construct()
        {

            $this->id = 'sadadpay';
            $this->has_fields = false;
            //$this->order_button_text = __('Pay', 'woocommerce');
            $this->method_title = __('Pay With Sadad');
            $this->method_description = __('Pay with Credit card, Debit card.');
            $this->icon = WP_PLUGIN_URL . "/" . plugin_basename(dirname(__FILE__)) . '/images/logo.png';
            $this->init_form_fields();
            $this->init_settings();
            $this->title = "Pay with Credit/Debit card";
            $this->description = "";
            $this->merchantID = $this->settings['merchantID'];
            $this->merchant_key = $this->settings['merchant_key'];
            $this->website = $this->settings['website'];
            $this->language = $this->settings['language'];
            $this->checkoutType = $this->settings['checkoutType'];
            $this->checkout2Type = $this->settings['checkout2Type'];
            $this->checkoutloader = $this->settings['checkoutloader'];

            $this->msg['message'] = "";
            $this->msg['class'] = "";

            add_action('init', array(
                &$this,
                'capture_sadad_response'
            ));

            add_action('woocommerce_api_' . strtolower(get_class($this)), array(
                $this,
                'capture_sadad_response'
            ));
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array(
                &$this,
                'process_admin_options'
            ));
            add_action('woocommerce_receipt_' . $this->id, array(
                &$this,
                'receipt_page'
            ));
            wp_enqueue_style('woo-sadad-payment-gateway', plugins_url('/stylesadad.css', SADAD_PLUGIN_URl), array(), WC_VERSION . '2');
        }

        function init_form_fields()
        {
            $checkout_url = wc_get_checkout_url();
            $checkout_url = trim($checkout_url, '/');

            if (strstr($checkout_url, "?")) {
                $webhookUrl = $checkout_url . "&wc-api=WC_Gateway_sadadpay&sadadwebhook=1";
            } else {
                $webhookUrl = $checkout_url . "/wc-api/WC_Gateway_sadadpay?sadadwebhook=1";
            }

            $this->form_fields = array(
                'enabled' => array(
                    'title' => __('Enable/Disable'),
                    'type' => 'checkbox',
                    'label' => __('Enable Sadad Payment Gateway.'),
                    'default' => 'no'
                ),
                'merchantID' => array(
                    'title' => __('Merchant ID'),
                    'type' => 'text',
                    'description' => __('This is the Sadad id given by Sadad.')
                ),
                'merchant_key' => array(
                    'title' => __('Merchant Key'),
                    'type' => 'text',
                    'description' => __('This is the Secret key.')
                ),
                'website' => array(
                    'title' => __('Website'),
                    'type' => 'text',
                    'description' => __('')
                ),
                'language' => array(
                    'title' => __('Language'),
                    'type' => 'select',
                    'options' => array(
                        'arb' => 'Arabic',
                        'eng' => 'English'
                    ),
                    'label' => __('Select language'),
                    'description' => "Select the language for payment gateway page.",
                    'default' => 'arb'
                ),
                'checkoutType' => array(
                    'title' => __('Checkout Type'),
                    'type' => 'select',
                    'options' => array(
                        '1' => 'Web checkout',
                        '2' => 'Web checkout 2.2'
                    ),
                    'default' => '1'
                ),
                'checkout2Type' => array(
                    'title' => __('Checkout 2.2 Type'),
                    'type' => 'select',
                    'options' => array(
                        '0' => 'Iframe',
                        '1' => 'Modal Popup'
                    ),
                    'default' => '1'
                ),
                'checkoutloader' => array(
                    'title' => __('Hide Loader'),
                    'type' => 'select',
                    'options' => array(
                        'NO' => 'NO',
                        'YES' => 'YES'
                    ),
                    'description' => "Hide loader on Sadad checkout page",
                    'default' => 'YES'
                ),
                'webhookUrl' => array(
                    'title' => __('Webhook (click text box to copy the URL)'),
                    'type' => 'text',
                    'description' => __('To get transaction updates seamleslly, login to https://panel.sadad.qa , select Online Payments from top > Then click Payment Gateway from left side menu and go to Webhook tab. There enter the URL from this textbox and enter your email address. The email address is to receive notification if webhook calls fails due to any problem with your website/server.'),
                    'default' => $webhookUrl
                ),
            );
        }

        /**
         *  Payment form on checkout page.
         * */
        function payment_fields()
        {
            $description = $this->get_description();
            if ($description) {
                echo wpautop(wptexturize(trim($description)));
            }
        }

        public function is_valid_for_use()
        {
            return in_array(get_woocommerce_currency(), apply_filters('woocommerce_paypal_supported_currencies', array(
                'QAR'
            )), true);
        }

        /**
         * Admin Panel Options
         * - Options for bits like 'title' and availability on a country-by-country basis
         * */
        public function admin_options()
        {
            if ($this->is_valid_for_use()) {
                echo '<h3>' . __('Sadad Payment Gateway') . '</h3>';
                echo '<p>' . __('Qatar online payment solutions for all your transactions by sadad') . '</p>';
                echo '<table class="form-table">';
                $this->generate_settings_html();
                echo '</table>';
?>
                <script>
                    jQuery(document).ready(function($) {
                        $('#woocommerce_sadadpay_webhookUrl').attr('readonly', true);
                        $('#woocommerce_sadadpay_webhookUrl').on('click', function() {
                            var input = document.createElement('input');
                            input.setAttribute('value', $(this).val());
                            document.body.appendChild(input);
                            input.select();
                            var result = document.execCommand('copy');
                            document.body.removeChild(input);
                            return result;
                        });
                        if ($('#woocommerce_sadadpay_checkoutType').val() == 1) {
                            $('#woocommerce_sadadpay_checkout2Type').parent().parent().parent().hide();
                            //$('#woocommerce_sadadpay_checkoutloader').parent().parent().parent().hide();

                        }
                        $('#woocommerce_sadadpay_checkoutType').on('change', function() {
                            $('#woocommerce_sadadpay_checkout2Type').parent().parent().parent().toggle();
                            //$('#woocommerce_sadadpay_checkoutloader').parent().parent().parent().toggle();
                        });
                    });
                </script>
            <?php
            } else {
            ?>
                <div class="inline error">
                    <p>
                        <strong><?php
                                esc_html_e('Gateway disabled', 'woocommerce');
                                ?></strong>: <?php
                                            esc_html_e('Sadad does not support your store currency.', 'woocommerce');
                                            ?>
                    </p>
                </div>
            <?php
            }
        }

        /**
         * Return the gateway's description.
         *
         * @return string
         */
        public function get_description()
        {
            return apply_filters('woocommerce_gateway_description', $this->description, $this->id);
        }

        /**
         * Receipt Page
         * */
        function receipt_page($order)
        {
            echo $this->generate_sadad_form($order);
        }

        /**
         * Process the payment and return the result
         * */
        function process_payment($order_id)
        {

            $order = new WC_Order($order_id);

            return array(
                'result' => 'success',
                'redirect' => add_query_arg('order-pay', $order_id, add_query_arg('key', $order->get_order_key(), $order->get_checkout_payment_url(true)))
            );
        }

        /*
         * Logs
         */

        protected function debugLogs($data)
        {
            $storage_path = SADAD_PLUGIN_PATH;

            $fp = fopen($storage_path . "/logs/log-" . date('Y-m-d') . ".txt", "a");
            fwrite($fp, date('Y-m-d H:i:s') . ': ' . $data . PHP_EOL);
            fclose($fp);
        }

        /*
         * Receive webhook and update the order.
         */

        protected function process_webhook()
        {

            $data = file_get_contents("php://input");

            $this->debugLogs($data);

            $jsonParams = json_decode($data, true);

            if (isset($jsonParams['checksumhash']) && !empty($jsonParams['checksumhash']) && isset($jsonParams['transactionNumber']) && !empty($jsonParams['transactionNumber'])) {
                $signature = sanitize_text_field($jsonParams['checksumhash']);

                unset($jsonParams['checksumhash']);

                ksort($jsonParams);

                $signatureStr = trim($this->merchant_key);

                foreach ($jsonParams as $k => $v) {
                    $signatureStr .= $v;
                }

                $signatureGenerated = hash('sha256', $signatureStr, false);

                if ($signatureGenerated == $signature) {
                    $orderId = sanitize_text_field($jsonParams['websiteRefNo']);

                    $order = new WC_Order($orderId);

                    if (!$order) {
                        exit;
                    }

                    update_post_meta($orderId, '_sadadTransUpdating', 'Y');

                    $transactionNumber = sanitize_text_field($jsonParams['transactionNumber']);

                    if ($jsonParams['transactionStatus'] == 3) {

                        $order_amount = $order->get_total();

                        if ((sanitize_text_field($jsonParams['txnAmount']) == $order_amount)) {


                            if ($order->has_status('pending') or $order->has_status('failed')) {
                                $orderNote = "Order updated via webhook. Transaction successful. Sadad Transaction reference: " . $transactionNumber;

                                $order->payment_complete();
                                $order->add_order_note($orderNote);
                                update_post_meta($orderId, '_transaction_id', $transactionNumber);
                                update_post_meta($orderId, '_sadadTransUpdating', 'N');
                            }
                        } else {

                            $orderNote = "Order updated via webhook. Order has failed. Transaction amount does not match order amount. Sadad Transaction reference: " . $transactionNumber;
                            $order->update_status('failed');
                            $order->add_order_note($orderNote);
                        }
                    } else {
                        $orderNote = "Order updated via webhook. Transaction failed. Sadad Transaction reference: " . $transactionNumber;
                        $order->update_status('failed');
                        $order->add_order_note($orderNote);
                    }

                    $this->debugLogs('Order Update Status: ' . $orderNote);
                } else {
                    $this->debugLogs('Signature Generation Failed. Signature Generated: ' . $signatureGenerated);
                }

                http_response_code(200);
                echo '{"status":"success"}';
            }
        }

        /**
         * Check for valid sadad server callback // response processing //
         * */
        function capture_sadad_response()
        {

            global $woocommerce;

            if (isset($_GET['sadadwebhook']) && $_GET['sadadwebhook'] == 1) {
                $this->process_webhook();
                exit;
            }

            if (isset($_POST['ORDERID']) && isset($_POST['RESPCODE'])) {
                $order_sent = sanitize_text_field($_POST['ORDERID']);
                $responseDescription = sanitize_text_field($_POST['RESPMSG']);

                $order = new WC_Order($order_sent);

                $status = (isset($_POST['transaction_status']) && $_POST['transaction_status'] == 3) ? "Successful" : "Failed";

                $failedReason = "";

                if ($status == 'Failed') {
                    $failedReason = " For Reason  : " . $responseDescription;
                }

                $this->msg['class'] = 'error';
                $this->msg['message'] = "Thank you for shopping with us. The transaction has been " . $status . $failedReason;

                $checksum_response = sanitize_text_field($_POST['checksumhash']);
                unset($_POST['checksumhash']);
                $sadad_id = $this->merchantID;
                $sadad_secrete_key = trim($this->merchant_key);
                $data_repsonse = array();

                $pData = array();

                foreach ($_POST as $key => $vl) {
                    $pData[$key] = sanitize_text_field($vl);
                }

                $data_repsonse['postData'] = $pData;
                $data_repsonse['secretKey'] = $this->merchant_key;
                $key = $sadad_secrete_key . $sadad_id;


                if (verifychecksum_eFromStr(json_encode($data_repsonse), $key, $checksum_response) === "TRUE") {

                    $order_amount = $order->get_total();

                    if ((sanitize_text_field($_POST['TXNAMOUNT']) == $order_amount)) {

                        $checkOrderStatus = get_post_meta($order_sent, '_sadadTransUpdating', true);

                        if (isset($checkOrderStatus) && $checkOrderStatus == 'Y') {
                            usleep(2000);

                            if (sanitize_text_field($_POST['STATUS']) == 'TXN_SUCCESS') {
                                $this->msg['message'] = "Thank you for your order. Your " . sanitize_text_field($_POST['transaction_number']) . " transaction has been successful.";
                                $this->msg['class'] = 'success';
                                $woocommerce->cart->empty_cart();
                            }
                        } else {

                            if ($_POST['transaction_status'] == 3) {

                                if ($order->has_status('pending') or $order->has_status('failed')) {
                                    $this->msg['message'] = "Thank you for your order. Your " . sanitize_text_field($_POST['transaction_number']) . " transaction has been successful.";
                                    $this->msg['class'] = 'success';

                                    $order->payment_complete();
                                    $order->add_order_note($this->msg['message']);
                                    $tnx_id_msg = "Sadad Transaction ID: " . sanitize_text_field($_POST['transaction_number']);
                                    $order->add_order_note($tnx_id_msg);
                                    $woocommerce->cart->empty_cart();
                                    update_post_meta($order->get_id(), '_transaction_id', sanitize_text_field($_POST['transaction_number']));
                                }
                            } else {
                                $this->msg['class'] = 'error';
                                $this->msg['message'] = "The transaction is failed. The payment is cancelled by customer or failed.";
                                $order->update_status('failed');
                                $order->add_order_note($this->msg['message']);
                            }
                        }
                    } else {
                        $this->msg['class'] = 'error';

                        $this->msg['message'] = "Order amount does not match with the paid amount.";
                        $order->update_status('failed');
                        $order->add_order_note($this->msg['message']);
                    }
                } else {

                    $this->msg['class'] = 'error';
                    $this->msg['message'] = "Checksum didn't match";
                    //$order->update_status('failed');
                    $order->add_order_note($this->msg['message']);
                }


                $redirect_url = $order->get_checkout_order_received_url();

                $redirect_url = add_query_arg(array('msg' => urlencode($this->msg['message']), 'type' => $this->msg['class']), $redirect_url);

                wp_redirect($redirect_url);
                exit;
            }
        }

        /**
         * Generate sadad button link
         * */
        public function generate_sadad_form($order_id)
        {
            global $woocommerce;

            $order = new WC_Order($order_id);

            //Check if order already paid
            if ($order->is_paid())
                return;
            //Set to pending so that if user order failed for a reason and he's trying again for same order.
            $order->update_status('pending');

            $orderTotal = number_format($order->get_total(), 2, '.', '');

            $email = $order->get_billing_email();
            $mobile_no = $order->get_billing_phone();
            $mobile_no = preg_replace('/[^0-9]/', '', $mobile_no);

            $sadad__checksum_data = array();

            $order_items = $order->get_items(array(
                'line_item'
            )); // line_item | fee | shipping

            $sadad_checksum_array = array();
            $incVal = 0;

            if (!is_wp_error($order_items)) {
                foreach ($order_items as $item_id => $order_item) {
                    if ($order_item->get_total() > 0) {
                        $product = wc_get_product($order_item['product_id']);

                        $sadad_checksum_array['productdetail'][$incVal]['order_id'] = (string) $order_id;
                        //$sadad_checksum_array['productdetail'][$incVal]['itemname']    = html_entity_decode($order_item->get_name());		
                        $sadad_checksum_array['productdetail'][$incVal]['amount'] = number_format($order_item->get_total(), 2, '.', '');
                        $sadad_checksum_array['productdetail'][$incVal]['quantity'] = (string) $order_item->get_quantity();
                        $incVal++;
                    }
                }
            }


            $checkout_url = wc_get_checkout_url();
            $checkout_url = trim($checkout_url, '/');

            if (strstr($checkout_url, "?")) {
                $call = $checkout_url . "&wc-api=WC_Gateway_sadadpay";
            } else {
                $call = $checkout_url . "/wc-api/WC_Gateway_sadadpay";
            }
            $txnDate = date('Y-m-d H:i:s');

            $secretKeyy = trim($this->merchant_key);

            $sadad_checksum_array['merchant_id'] = $this->merchantID;
            $sadad_checksum_array['ORDER_ID'] = (string) $order_id;
            $sadad_checksum_array['WEBSITE'] = $this->website;
            $sadad_checksum_array['TXN_AMOUNT'] = $orderTotal;
            $sadad_checksum_array['CUST_ID'] = $email;
            $sadad_checksum_array['VERSION'] = '1.1';
            $sadad_checksum_array['EMAIL'] = $email;
            $sadad_checksum_array['MOBILE_NO'] = $mobile_no;
            $sadad_checksum_array['SADAD_WEBCHECKOUT_PAGE_LANGUAGE'] = $this->language;
            $sadad_checksum_array['CALLBACK_URL'] = $call;
            $sadad_checksum_array['txnDate'] = $txnDate;
            $sadad_checksum_array['SADAD_WEBCHECKOUT_HIDE_LOADER'] = $this->checkoutloader;

            //if webcheckout 2.2, add these fields to checksum
            if ($this->checkoutType == 2) {
                $sadad_checksum_array['showdialog'] = $this->checkout2Type;
            }



            $sAry1 = array();
            $sadad_checksum_array1 = array();
            foreach ($sadad_checksum_array as $pK => $pV) {
                if ($pK == 'checksumhash')
                    continue;
                if (is_array($pV)) {
                    $prodSize = sizeof($pV);
                    for ($i = 0; $i < $prodSize; $i++) {
                        foreach ($pV[$i] as $innK => $innV) {
                            $sAry1[] = "<input type='hidden' name='productdetail[$i][" . $innK . "]' value='" . trim($innV) . "'/>";
                            $sadad_checksum_array1['productdetail'][$i][$innK] = trim($innV);
                        }
                    }
                } else {
                    $sAry1[] = "<input type='hidden' name='" . $pK . "' value='" . trim($pV) . "'/>";
                    $sadad_checksum_array1[$pK] = trim($pV);
                }
            }

            $formFieldss = implode('', $sAry1);

            $sadad__checksum_data['postData'] = $sadad_checksum_array1;
            $sadad__checksum_data['secretKey'] = $secretKeyy;
            $checksumGenerated = getChecksumFromString(json_encode($sadad__checksum_data), $secretKeyy . $this->merchantID);

            if (empty($this->checkoutType) or $this->checkoutType == 1) {
                $action_url = 'https://sadadqa.com/webpurchase';

                return '<form action="' . $action_url . '" method="post" id="sadad_payment_form" name="gosadad">
                    ' . $formFieldss . '<input type="hidden" name="checksumhash" value="' . $checksumGenerated . '"/>
                    <script type="text/javascript">
                        document.gosadad.submit();
                    </script>
                    
                </form>';
            } else {
                $action_url = 'https://secure.sadadqa.com/webpurchasepage';

                echo '<form action="' . esc_url($action_url) . '" method="post" id="paymentform" name="paymentform" data-link="' . esc_url($action_url) . '">
                    ' . wp_kses($formFieldss, array('input' => array('type' => [], 'value' => [], 'name' => []))) . '<input type="hidden" name="checksumhash" value="' . esc_html($checksumGenerated) . '"/>
                </form>';

                $redirect_url = $order->get_checkout_order_received_url();
            ?>


                <style>
                    .close-btn {
                        height: auto;
                        width: auto;
                        -webkit-appearance: none !important;
                        background: none !important;
                        border: 0px;
                        position: absolute;
                        right: 10px;
                        z-index: 11;
                        cursor: pointer;
                        outline: 0px !important;
                        box-shadow: none;
                        top: 15px;
                    }

                    .close,
                    .close:hover {
                        color: #000;
                        font-size: 30px;
                    }

                    .modal-body {
                        padding: 0px;
                        border-radius: 15px;
                    }

                    #onlyiframe {
                        width: 100% !important;
                        height: 115vh !important;
                        overflow: visible !important;
                        border: 0;

                        top: 0;
                        left: 0;
                        bottom: 0;
                        right: 0;

                    }

                    #includeiframe {
                        height: 115vh !important;
                        overflow: visible !important;
                        border: 0;
                        width: 450px;
                        margin: 0 auto;
                    }

                    .modal-backdrop {
                        background-color: #000 !important;
                    }

                    ul.order_details {
                        display: none !important;
                    }
                </style>
                <!-- Modal -->
                <div id="container_div_sadad">
                    <div class="modal fade not_hide_sadad" id="exampleModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
                        <div class="modal-dialog" role="document">
                            <div class="modal-content">
                                <button type="button" class="close-btn" onClick="closemodal();" aria-label="Close">
                                    <span class="close">&times;</span>
                                </button>
                                <div class="modal-body">
                                    <iframe name="includeiframe" id="includeiframe" frameborder="0" scrolling="no"></iframe>
                                </div>
                            </div>
                        </div>
                    </div>
                    <iframe name="onlyiframe" id="onlyiframe" border="0" class="not_hide_sadad" frameborder="0" scrolling="no"></iframe>
                </div>

                <script>
                    function closemodal() {
                        //$('#exampleModal').modal('hide');
                        window.location.href = '<?php echo wc_get_checkout_url(); ?>';
                    }
                    jQuery(document).ready(function($) {

                        if ($('input[name="showdialog"]').val() == 1) {

                            $('#exampleModal').modal('show');
                            $('#paymentform').attr('target', 'includeiframe').submit();
                            $('#onlyiframe').remove();

                        } else {
                            $('#exampleModal').remove();
                            $('#paymentform').attr('target', 'onlyiframe').submit();

                        }
                        $('iframe').load(function() {
                            $(this).height($(this).contents().find("body").height());
                            if (this.contentWindow.location == '<?php
                                                                echo esc_url($call);
                                                                ?>') {

                                $(this).hide();
                                $.ajax({
                                    type: "POST",
                                    url: "<?php echo site_url(); ?>/wp-admin/admin-ajax.php",
                                    data: 'action=get_current_order_redirect',
                                    success: function(response) {
                                        window.location.href = response;
                                    }
                                });

                            }
                        });
                    });
                </script>

<?php
            }
        }
    }

    /**
     * Add the Gateway to WooCommerce
     * */
    function woocommerce_add_sadad_gateway($methods)
    {
        $methods[] = 'WC_Gateway_sadadpay';
        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'woocommerce_add_sadad_gateway');

    function woocommerce_sadad_order_button_html($button_html)
    {
        $button_html = '<button type="submit" class="button alt" name="woocommerce_checkout_place_order" id="place_order">Pay Now</button>';
        //<img src="' . $btn_img . '" style="display:inline;">
        return $button_html;
    }

    add_filter('woocommerce_order_button_html', 'woocommerce_sadad_order_button_html');

    function sadadqa_enqueue_ss()
    {
        if (is_checkout()) {
            $plugUrl = WP_PLUGIN_URL . "/" . plugin_basename(dirname(__FILE__));
            wp_enqueue_style('sdbmodalcss', $plugUrl . '/css/bootstrap-modal.min.css', array(), '1.0.1', 'all');
            wp_enqueue_style('sdbmodalptcss', $plugUrl . '/css/bootstrap-modal-bs3patch.min.css', array(), '1.0.0', 'all');

            wp_enqueue_script('sdpoperjs', $plugUrl . '/js/popper.min.js', array(), '1.0.1', 'true');
            wp_enqueue_script('sdbootstrpjs', $plugUrl . '/js/bootstrap.min.js', array(), '1.0.1', 'true');
            wp_enqueue_script('sdbmodaljs', $plugUrl . '/js/bootstrap-modal.min.js', array(), '1.0.1', 'true');
            wp_enqueue_script('sdbmodalmgrjs', $plugUrl . '/js/bootstrap-modalmanager.js', array(), '1.0.1', 'true');
        }
    }

    add_action('wp_enqueue_scripts', 'sadadqa_enqueue_ss');

    function sadadqa_cart_checkout_blocks_compatibility()
    {
        if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
        }
    }
    add_action('before_woocommerce_init', 'sadadqa_cart_checkout_blocks_compatibility');


    add_action('woocommerce_blocks_loaded', 'sadadqa_register_order_approval_payment_method_type');
    function sadadqa_register_order_approval_payment_method_type()
    {
        if (!class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
            return;
        }
        require_once plugin_dir_path(__FILE__) . 'include/class-sadadqa-woocommerce-block-checkout.php';
        add_action('woocommerce_blocks_payment_method_type_registration', function (Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry) {

            $payment_method_registry->register(new WC_SadadQa_Blocks);
        });
    }
}
?>