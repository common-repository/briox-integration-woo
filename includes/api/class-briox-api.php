<?php

/**
 * Constant that contains the briox endpoint and the integrations client secret
 *
 */

defined('ABSPATH') || exit;

/**
 * Briox_API
 */

if (!class_exists('Briox_API', false)) {
    class Briox_API
    {

        const SERVICE_URL = 'briox.bjorntech.net/v1';

        private static $service_url = null;

        /**
         *
         * Variable containing the client secret
         *
         * @access private
         */
        private $client_secret;

        public static function service_url()
        {

            if (null === self::$service_url) {
                self::$service_url = trailingslashit(get_option('briox_service_url') ?: self::SERVICE_URL);
            }

            return self::$service_url;

        }

        public static function get_access_token()
        {
            $time_now = time();

            if ($refresh_token = get_option('refresh_token')) {

                if (intval($time_now) >= intval(get_option('token_expiry', 0))) {

                    $body = array(
                        'grant_type' => 'refresh_token',
                        'refresh_token' => $refresh_token,
                        'plugin_version' => Briox_Integration_Woo::$plugin_version,
                    );

                    $args = array(
                        'headers' => array(
                            'Content-Type' => 'application/x-www-form-urlencoded',
                        ),
                        'timeout' => 20,
                        'body' => $body,
                    );

                    $url = 'https://' . self::service_url() . 'token';

                    $response = wp_remote_post($url, $args);

                    if (is_wp_error($response)) {

                        $code = $response->get_error_code();

                        $error = $response->get_error_message($code);

                        throw new Briox_API_Exception($error, 0, null, $url, $body);

                    } else {

                        $response_body = json_decode(wp_remote_retrieve_body($response));

                        if (($http_code = wp_remote_retrieve_response_code($response)) != 200) {

                            $error_message = isset($response_body->error) ? $response_body->error : 'Unknown error message';
                            Briox_Integration_Woo_Logger::log('debug',sprintf('Error %s when asking for access token from service: %s', $http_code, $error_message));
                            throw new Briox_API_Exception($error_message, $http_code, null, $url, $body, $response);

                        }

                        update_option('briox_valid_to', $response_body->valid_to);
                        update_option('briox_access_token', $response_body->access_token);
                        update_option('token_expiry', $time_now + $response_body->expires_in);
                        update_option('refresh_token', $response_body->refresh_token);

                        Briox_Integration_Woo_Logger::log('debug',sprintf('Got access token from service: %s', $response_body->access_token));

                        return $response_body->access_token;

                    }

                }

                return get_option('briox_access_token');
            }

            throw new Briox_API_Exception('Not connected to service', null, null);

        }

        public static function ratelimiter()
        {

            $current = microtime(true);
            $time_passed = $current - (float) get_site_transient('briox_api_limiter', microtime(true));
            set_site_transient('briox_api_limiter', $current);

            if ($time_passed < 250000) {
                usleep(250000 - $time_passed);
            }

        }

        /**
         * Function to make a "raw" call to Briox
         *
         * @return the response from Briox
         */
        public static function apiCall($method, $entity, $body = null, $assoc = true)
        {

            self::ratelimiter();

            $url = 'https://' . self::service_url() . 'v2/' . $entity;

            $request_body = $body;
            if ($method == 'POST' || $method == 'PUT') {
                $request_body = json_encode($body);
            }

            $args = array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . self::get_access_token(),
                ),
                'body' => $request_body,
                'method' => $method,
            );

            $response = wp_remote_request($url, $args);

            if (is_wp_error($response)) {

                throw new Briox_API_Exception('Connection error', null, null, $url, json_encode($args), null);

            } else {

                $http_code = wp_remote_retrieve_response_code($response);

                if ($http_code >= 200 && $http_code < 300) {

                    $json_response = json_decode($response['body'], true);

                    if (is_array($json_response) && array_key_exists('ErrorInformation', $json_response)) {
                        $error_code = array_key_exists('code', $json_response['ErrorInformation']) ? $json_response['ErrorInformation']['code'] : $response['response']['code'];
                        throw new Briox_API_Exception($json_response['ErrorInformation']['message'], $error_code, null, $url, print_r($args, true), print_r($response['response'], true));
                    }

                    if ($assoc === true) {
                        if ($json_response == null) {
                            return $response['body'];
                        } else {
                            return $json_response;
                        }
                    } else {
                        return json_decode($response['body'], false);
                    }

                } elseif (429 == $http_code) {

                    Briox_Integration_Woo_Logger::log('debug','Unexpectedly htting the Briox API rate limit , waiting 30 seconds to repair');
                    sleep(30);
                    self::apiCall($method, $entity, $body, $assoc);

                } else {

                    throw new Briox_API_Exception('Http error', $http_code, null, $url, print_r($args, true), print_r($response['body'], true));

                }
            }
        }

        // Briox get pricelist/{priceListCode}
        public static function get_pricelist($pricelist = '')
        {
            $response = self::apiCall('GET', 'pricelist/' . urlencode($pricelist));
            return $response['data'];
        }

        /**
         * Get price from alternate pricelist
         *
         * @return Prices
         */
        public static function get_prices($item_id, $pricelist)
        {
            $response = self::apiCall('GET', 'prices/' . urlencode($pricelist) . '/' . urlencode($item_id) . '/0');
            return $response['Price'];
        }

        public static function getAllCustomers($only_active = true, $last_modified = false, $filters = false)
        {
            $last_modified !== false ? $modified_field = '&lastmodified=' . $last_modified : $modified_field = "";
            $only_active === true ? $active_field = '&filter=active' : $active_field = "";

            $i = 1;
            $returnarray = array();
            do {
                $jsonarray = self::apiCall('GET', 'customers/?page=' . $i . '&sortby=customernumbernumber&sortorder=ascending' . $active_field . $modified_field);
                if ($filters !== false) {
                    foreach ($jsonarray['Customers'] as $customer) {
                        foreach ($filters as $filter) {
                            if ($filter['value'] == $customer[$filter['field']]) {
                                array_push($returnarray, $customer);
                                break;
                            }
                        }
                    }
                } else {
                    $returnarray = array_merge($jsonarray['Customers'], $returnarray);
                }
                $totalpages = $jsonarray["MetaInformation"]["@TotalPages"];
            } while ($i++ < $totalpages);

            return $returnarray;
        }

        // Briox get /customer/email/{customerEmail}
        public static function get_customers_by_email($email)
        {
            $response = self::apiCall('GET', 'customer/email/' . $email);
            return $response['data']['customer'];
        }

        // Briox post /customer
        public static function create_customer($customer)
        {
            $api_request_data['customer'] = $customer;
            return self::apiCall(
                'POST',
                'customer',
                $api_request_data
            )[0];
        }

        // Briox put /customer/{customerID}
        public static function update_customer($customer_id, $customer)
        {
            $api_request_data['customer'] = $customer;
            $response = self::apiCall(
                'PUT',
                'customer/' . $customer_id,
                $api_request_data
            );
            return $response['data']['customer'];
        }

        public static function create_order($order)
        {
            $api_request_data['Order'] = $order;
            return self::apiCall(
                'POST',
                'orders',
                $api_request_data
            )['Order'];
        }

        public static function updateOrder($order_id, $order)
        {
            $api_request_data['Order'] = $order;
            return self::apiCall(
                'PUT',
                'orders/' . $order_id,
                $api_request_data
            )['Order'];
        }

        public static function cancelOrder($order_id)
        {
            return self::apiCall(
                'PUT',
                'orders/' . $order_id . '/cancel'
            );
        }

        public static function cancelInvoice($id)
        {
            return self::apiCall(
                'PUT',
                'invoices/' . $id . '/cancel'
            );
        }

        public static function finishOrder($order_id)
        {
            self::apiCall(
                'PUT',
                'orders/' . $order_id . '/createinvoice'
            );
        }

        public static function getOrderPDF($order_id)
        {
            return self::apiCall(
                'GET',
                'orders/' . $order_id . '/preview'
            );
        }

        public static function getInvoicePDF($invoice_id)
        {
            return self::apiCall(
                'GET',
                'invoices/' . $invoice_id . '/preview'
            );
        }

        public static function warehouseOrder($order_id)
        {
            self::apiCall(
                'GET',
                'orders/' . $order_id . '/warehouseready'
            );
        }

        // Briox get /item/{itemID}
        public static function get_item($id)
        {
            return self::apiCall(
                'GET',
                'item/' . urlencode($id)
            )['data'];
        }

        // Briox post /item
        public static function create_item($item)
        {
            $api_request_data['item'] = $item;
            return self::apiCall(
                'POST',
                'item/',
                $api_request_data
            )['data'];
        }

        // Briox get /costcenter
        public static function get_cost_center($param)
        {
            return self::apiCall(
                'GET',
                'costcenter/' . $param
            );
        }

        public static function get_all_cost_centers()
        {
            $i = 1;
            $returnarray = array();
            do {
                $jsonarray = self::apiCall('GET', 'costcenter/?page=' . $i);
                $returnarray = array_merge($jsonarray['data']['costcenters'], $returnarray);
                $totalpages = $jsonarray['data']["metainformation"]["total_pages"];
            } while ($i++ < $totalpages);
            return $returnarray;
        }

        public static function get_all_projects()
        {
            $i = 1;
            $returnarray = array();
            do {
                $jsonarray = self::apiCall('GET', 'project/?page=' . $i);
                $returnarray = array_merge($jsonarray['data']['projects'], $returnarray);
                $totalpages = $jsonarray['data']["metainformation"]["total_pages"];
            } while ($i++ < $totalpages);
            return $returnarray;
        }

        public static function update_item($item_id, $item)
        {
            $api_request_data['item'] = $item;
            return self::apiCall(
                'PUT',
                'item/' . urlencode($item_id),
                $api_request_data
            )['item'];
        }

        public static function get_all_items($filters = false)
        {
            $i = 1;
            $returnarray = array();
            do {
                $jsonarray = self::apiCall('GET', 'item/?page=' . $i);
                if ($filters !== false) {
                    foreach ($jsonarray['data']['items'] as $item) {
                        foreach ($filters as $filter) {
                            if ($filter['value'] == $item[$filter['field']]) {
                                array_push($returnarray, $item);
                                break;
                            }
                        }
                    }
                } else {
                    $returnarray = array_merge($jsonarray['data']['items'], $returnarray);
                }
                $totalpages = $jsonarray['data']["metainformation"]["total_pages"];
            } while ($i++ < $totalpages);

            return $returnarray;
        }

        public static function get_all_accounts()
        {
            $i = 1;
            $returnarray = array();
            do {
                $jsonarray = self::apiCall('GET', 'account/?page=' . $i);
                $returnarray = array_merge($jsonarray['data']['accounts'], $returnarray);
                $totalpages = $jsonarray['data']["metainformation"]["total_pages"];
            } while ($i++ < $totalpages);
            return $returnarray;
        }

        public static function getOrder($order_id)
        {
            $result = self::apiCall(
                'GET',
                'orders/' . $order_id
            )['Order'];
            return $result;
        }

        public static function get_invoice($invoice_id)
        {
            $result = self::apiCall(
                'GET',
                'invoices/' . $invoice_id
            )['Invoice'];
            return $result;
        }

        // Briox get /payment/term
        public static function get_terms_of_payments($id = '')
        {
            $result = self::apiCall(
                'GET',
                'payment/term' . urlencode($id)
            );
            return $response['data']['paymentterms'];
        }

        // Briox get /payment/method
        public static function get_payment_methods($id = '')
        {
            $result = self::apiCall(
                'GET',
                'payment/method' . urlencode($id)
            );
            return $response['data']['paymentmethods'];
        }

        public static function create_invoice($invoice)
        {
            $api_request_data['customerinvoice'] = $invoice;
            return self::apiCall(
                'POST',
                'customerinvoice/',
                $api_request_data
            )['data']['customerinvoice'];
        }

        public static function update_invoice($invoice_id, $invoice)
        {
            $api_request_data['customerinvoice'] = $invoice;
            return self::apiCall(
                'PUT',
                'customerinvoice/' . urlencode($invoice_id),
                $api_request_data
            )[0];
        }

        public static function external_print_invoice($invoice_id)
        {
            self::apiCall(
                'PUT',
                'invoices/' . $invoice_id . '/externalprint'
            );
        }

        public static function credit_invoice($invoice_id)
        {
            self::apiCall(
                'PUT',
                'invoices/' . $invoice_id . '/credit'
            );
        }

        public static function email_invoice($invoice_id)
        {
            self::apiCall(
                'GET',
                'invoices/' . $invoice_id . '/email'
            );
        }

        public static function getInvoicePaymentsByInvoiceNumber($search_for)
        {
            $response = self::apiCall('GET', 'invoicepayments/?invoicenumber=' . (string) $search_for);
            if ($response["MetaInformation"]["@TotalResources"] == 0) {
                return false;
            } else {
                return $response['InvoicePayments'];
            }
        }

        public static function createInvoicePayment($invoicepayment)
        {
            $api_request_data['InvoicePayment'] = $invoicepayment;
            return self::apiCall(
                'POST',
                'invoicepayments/',
                $api_request_data
            )['InvoicePayment'];
        }

        public static function getInvoicePayments()
        {
            $response = self::apiCall('GET', 'invoicepayments/' . (string) $search_for);
            if ($response["MetaInformation"]["@TotalResources"] == 0) {
                return false;
            } else {
                return $response['InvoicePayments'];
            }
        }

        public static function bookkeepInvoicePayment($payment_number)
        {
            $response = self::apiCall('PUT', 'invoicepayments/' . (string) $payment_number . '/bookkeep');
            return $response;
        }

        public static function get_first_customer_by_organisation_number($organisation_number)
        {
            return ($customers = self::get_customers_by_organisation_number($organisation_number)) ? $customers[0] : $customers;
        }

        public static function get_customers_by_organisation_number($organisation_number)
        {
            if ($organisation_number) {
                $response = self::apiCall('GET', 'customers/?organisationnumber=' . $organisation_number);
                if ($response["MetaInformation"]["@TotalResources"] != 0) {
                    return $response['Customers'];
                }
            }
            return false;
        }

    }

}
