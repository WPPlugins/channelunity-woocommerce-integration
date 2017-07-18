<?php

/*
 *  Channelunity WooCommerce Plugin
 *  Dashboard widget
 */

class woo_cu_dashboard {

    public function __construct() {
        $this->channelunity_add_actions();
    }

    //If CU dashboard setting is enabled, display the widget
    private function channelunity_add_actions() {
        if (woo_cu_helpers::get_option('dashboard') == 'true') {
            add_action('wp_dashboard_setup', array(&$this, 'channelunity_add_dashboard_widget'));
        }
    }

    //Create the dashboard widget
    public function channelunity_add_dashboard_widget() {
        wp_add_dashboard_widget(
                'channelunity_dashboard_widget', // Widget slug
                'ChannelUnity', // Title
                array(&$this, 'channelunity_display_dashboard_widget'), // Display function
                array(&$this, 'channelunity_dashboard_controller')      // Handle form options if needed
        );

        global $wp_meta_boxes;
        unset($wp_meta_boxes['dashboard']['normal']['core']['dashboard_right_now']);
        unset($wp_meta_boxes['dashboard']['normal']['core']['dashboard_activity']);
        unset($wp_meta_boxes['dashboard']['side']['core']['dashboard_primary']);
        unset($wp_meta_boxes['dashboard']['side']['core']['dashboard_quick_press']);

        $currDomainName = get_option('channelunity_domain_name');
        if (!$currDomainName) {
            add_meta_box('channelunity_dashboard_widget_domain', 'Domain Name Search',
                    array(&$this, 'channelunity_display_domain_widget'), 'dashboard', 'side', 'high');
        }
    }

    function channelunity_display_domain_widget() {
        echo file_get_contents('https://my.channelunity.com/woocommerce/woo_domain_search.php');
    }

    //Display dashboard widget
    public function channelunity_display_dashboard_widget() {
        echo file_get_contents('https://my.channelunity.com/woocommerce/woo_cu_dashboard.html');
    }

    //Handle any widget callback stuff
    public function channelunity_dashboard_controller() {
        $currDomainName = get_option('channelunity_domain_name');
        global $wpdb;
        if ('POST' == $_SERVER['REQUEST_METHOD']) {

            $domainName = $_POST['domain_name'];

            // If domain Name is valid, submit this to DNS settings
            if (preg_match_all('~^[A-Za-z0-9-.]{0,255}$~', $domainName)) {

                $mn = get_option('channelunity_merchantname');
                $username = get_option('channelunity_username');
                $password = get_option('channelunity_password');

                //Update webhooks to point to the new domain
                $hookIds = $wpdb->get_results("SELECT id FROM " . $wpdb->prefix . "posts WHERE post_title LIKE 'ChannelUnity webhook%'", ARRAY_A);
                $hId = array();
                foreach ($hookIds as $hookId) {
                    $hId[] = $hookId['id'];
                }
                $hookIds = implode(',', $hId);
                $hookUrl = "https://my.channelunity.com/woocommerce/webhook_endpoint.php?merch={$mn}&store={$domainName}";
                $wpdb->query("UPDATE " . $wpdb->prefix . "postmeta SET meta_value='{$hookUrl}'
                              WHERE post_id IN ({$hookIds}) AND meta_key='_delivery_url'");

                $auth = $username . ":" . hash("sha256", $password);
                $auth = base64_encode($auth);

                $xmlMessage = "<?xml version=\"1.0\" ?>\n" .
                        "<ChannelUnity>" .
                        "<MerchantName>$mn</MerchantName>" .
                        "<Authorization>$auth</Authorization>" .
                        "<RequestType>UpdateHostingSettings</RequestType>" .
                        "<Payload><DomainName>$domainName</DomainName></Payload>" .
                        "</ChannelUnity>";

                $url = "https://my.channelunity.com/event.php";

                $fields = urlencode($xmlMessage);

                $ch = curl_init();

                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_POST, TRUE);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
                curl_setopt($ch, CURLOPT_POSTFIELDS, array('message' => $fields));
                //execute post
                $result = curl_exec($ch);
                //close connection
                curl_close($ch);

                update_option('channelunity_domain_name', $domainName);
            }
        } else {
            echo file_get_contents('https://my.channelunity.com/woocommerce/woo_cu_configure.php?d=' . urlencode($currDomainName));
        }
    }

}
