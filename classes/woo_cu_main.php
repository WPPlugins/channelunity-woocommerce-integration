<?php

/*
 * ChannelUnity WooCommerce Integration
 * Main Class
 */

class woo_cu_main {
    private $version=2.5;
    private $trytoauthenticate=false;   //Manual authentication running flag
    private $api=false;                 //Indicates API call in progress
    private $params;

    public function __construct(){
        if($this->channelunity_allowed_to_run()){
            $this->channelunity_add_actions();
            $this->channelunity_enable_webhooks();
        }
    }

    //Disallow direct access, no point in running if WooCommerce isn't installed/activated
    private function channelunity_allowed_to_run() {
        $allowed=true;
        if (!defined('ABSPATH')) {
            $allowed=false;
        }

        //Check if WooCommerce is active
        if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
            add_action('admin_notices',array($this,'channelunity_activatewoo'));
            $allowed=false;
        }

        //Merchant info
        $this->params=array(
            'merchantname'  => woo_cu_helpers::get_option('merchantname'),
            'username'      => woo_cu_helpers::get_option('username'),
            'password'      => woo_cu_helpers::get_option('password')
        );

        woo_cu_helpers::date();
        $this->check_first_run();
        
        return $allowed;
    }

        //Set version number, run any updates
    private function check_first_run(){
        global $wpdb;

        //Check if version number exists
        $version=get_option('channelunity_version');

        //Anything before 2.5
        if(!$version){
            //Update old fields from pre 2.3
            if(strlen(get_option('channelunity_extrafields'))==0){
                $update=array("cu_upc");
                for($i=1;$i<4;$i++){
                    if($wpdb->query("SELECT meta_id FROM {$wpdb->postmeta} where meta_key='cu_custom_option_$i'")){
                        $update[]="cu_custom_option_$i";
                    }
                }
                $fields=array();
                foreach($update as $u){
                    $display=ucwords(str_replace('_',' ',$u));
                    if($display=='Cu Upc') {
                        $display='Barcode';
                    }
                    $fields['_'.$u]=array("display"=>$display,"position"=>"both");
                    $wpdb->query("UPDATE {$wpdb->postmeta} SET meta_key='_$u' where meta_key='$u'");
                }
                update_option('channelunity_extrafields',json_encode($fields));
            }

        }

        //Set the version number
        update_option('channelunity_version',$this->version);
    }

    //**Hooks and filters**//
    private function channelunity_add_actions(){
        //Queue scripts
        add_action( 'admin_enqueue_scripts', array($this,'channelunity_register_scripts') );

        //Check if permalinks are set on plugin activation
        register_activation_hook( __FILE__, array($this,'channelunity_is_permalink_activate') );

        //Put CU in the WooCommerce Menu
        add_action('admin_menu', array($this,'channelunity_register_cu_page'),90);

        //Add CU settings to a tab in WooCommerce
        add_filter( 'woocommerce_settings_tabs_array', array($this,'channelunity_add_settings_tab'), 50 );

        //Create the settings entries in the WooCommerce-ChannelUnity tab
        add_action( 'woocommerce_settings_tabs_channelunity_settings_tab', array($this,'channelunity_settings_tab') );
        add_action('shutdown',array($this,'channelunity_authenticate'),92);

        //Save CU settings
        add_action('woocommerce_update_options_channelunity_settings_tab',array($this,'channelunity_update_settings'));

        //Ajax action to copy login details from signup form to settings tab
        add_action('wp_ajax_channelunity_save_settings', array($this,'channelunity_save_settings'));

        //AJAX to add woo credentials from signup form
        add_action('wp_ajax_channelunity_install', array($this,'channelunity_install'));

        //API Endpoint
        add_filter( 'woocommerce_api_classes', function( $classes ){$classes[] = 'woo_cu_api';return $classes;});
        add_action( 'woocommerce_api_loaded', function(){include_once( 'woo_cu_api.php' );});
        woo_cu_helpers::items($this->params);

        //Add tracking information to order hooks
        add_action('woocommerce_api_order_response', array($this,'channelunity_add_tracking_data'));

        //Store tracking numbers recieved from ShipStation if WC_Shipment_Tracking not installed
        add_action('woocommerce_shipstation_shipnotify', array($this,'channelunity_add_tracking_to_order'),1,2);

        //Block sending emails to @channelunity addresses
        add_filter('is_email' , array($this,'channelunity_block_emails'),50);
    }
    
    public function channelunity_register_scripts() {
        //Stylesheet
        wp_register_style('woo_cu_css', plugins_url('../styles/woo_cu_styles.css', __FILE__));
        wp_enqueue_style('woo_cu_css');

        //Main plugin javascript
        wp_register_script('woo_cu_js', plugins_url('../js/woo_cu_main.js', __FILE__));
        wp_enqueue_script('woo_cu_js');

        //sha256 hashing for building CU Auth
        wp_register_script('woo_cu_sha256', plugins_url('../js/woo_cu_sha256.js', __FILE__));
        wp_enqueue_script('woo_cu_sha256');

        //jquery
        wp_enqueue_script('jquery');

        //Date picker
        wp_enqueue_script('jquery-ui-datepicker');
        wp_enqueue_style('jquery-ui-css', 'http://ajax.googleapis.com/ajax/libs/jqueryui/1.8.2/themes/smoothness/jquery-ui.css');

        //Hidden form to log on to ChannelUnity(to write to cuiframe2
        $iframeContent=woo_cu_helpers::get_html_block('login',$this->params);

        //Get absolute paths etc for javascript
        $php_woocu=array(
            'channelunity'    => plugins_url('woo_cu_channelunity.php',__FILE__),
            'callbackUrl'   => str_replace('classes/','',plugins_url('woo_cu_authcomplete.php',__FILE__)),
            'culogo'        => plugins_url('html/woo_cu_logo.jpg',__FILE__),
            'siteUrl'       => get_site_url(),
            'merchantname'  => woo_cu_helpers::get_option('merchantname'),
            'username'      => woo_cu_helpers::get_option('username'),
            'password'      => woo_cu_helpers::get_option('password'),
            'css'           => woo_cu_helpers::get_css(),
            'msg'           => woo_cu_helpers::get_html_block('activate'),
            'yak'           => woo_cu_helpers::$yak,
            'iframeContent' => $iframeContent
        );

        //send php variables to javascript
        wp_localize_script('woo_cu_js','php_woocu',$php_woocu);
    }

    //Add notice if Woocommerce isn't installed
    public function channelunity_activatewoo(){
        echo '<div id="message" class="error">Please install and enable WooCommerce in order to use the ChannelUnity plugin!</div>';
    }

    //add notice if user needs to enable permalinks
    public function channelunity_is_permalink_activate() {
        if (! get_option('permalink_structure') )
            add_action('admin_notices', array(&$this,'channelunity_permalink_structure_admin_notice'));
    }

    //Actual permalink message
    public function channelunity_permalink_structure_admin_notice(){
        echo '<div id="message" class="error"><p>Please Make sure to enable <a href="options-permalink.php">Permalinks</a>.</p></div>';
        return;
    }

    //Put CU in the WordPress menu
    public function channelunity_register_cu_page() {
        //add_submenu_page( 'woocommerce', 'ChannelUnity', 'ChannelUnity', 'manage_options', 'channelunity-menu', array(&$this,'channelunity_make_connection') );
        add_menu_page(
            'ChannelUnity',         //Page title
            'ChannelUnity',         //Menu title
            'manage_options',       //Capabilities
            'channelunity-menu',    //Slug
            array(&$this,'channelunity_make_connection'), //Click function
            plugins_url('channelunity-woocommerce-integration/images/cumenu.png'),
            30
            );
    }

    //Re-enable any disabled webhooks
    private function channelunity_enable_webhooks(){
        global $wpdb;
        //Delete duplicate webhooks, keeping most recent (once per day)
        if(get_option('channelunity_delete_duplicate_hooks')!=date('Y-m-d')){
            $wpdb->query("DELETE from {$wpdb->posts} WHERE post_title LIKE 'ChannelUnity webhook%' AND ID NOT IN (
                            SELECT ID FROM (SELECT MAX(wp2.ID) AS ID FROM {$wpdb->posts} wp2
                            WHERE wp2.post_title LIKE 'ChannelUnity webhook%'
                                GROUP BY wp2.post_title) x)");
            update_option('channelunity_delete_duplicate_hooks',date('Y-m-d'));
        }

        //Enable any disabled CU hooks
        $wpdb->query("UPDATE {$wpdb->posts} SET post_status='publish' WHERE post_type='shop_webhook' AND
                        post_status='pending' AND post_title LIKE 'ChannelUnity webhook%'");
    }

    //Block emails to ***@channelunity if option is enabled
    public function channelunity_block_emails($is_email){
        if(get_option('channelunity_block_emails')=='yes' && strpos($is_email,'@channelunity')!==false){
            $is_email=false;
        }
        return $is_email;
    }

    /*
     * Settings tab functions
     */

    //Add CU settings to a tab in WooCommerce
    public function channelunity_add_settings_tab( $settings_tabs ) {
        $settings_tabs['channelunity_settings_tab'] =  'ChannelUnity' ;
        return $settings_tabs;
    }

    //Create the settings entries in the WooCommerce-ChannelUnity tab
    public function channelunity_settings_tab() {
        woocommerce_admin_fields( woo_cu_helpers::get_settings() );
    }

    //Save CU settings
    public function channelunity_update_settings(){
        woocommerce_update_options(woo_cu_helpers::get_settings());

        //If reconnect checkbox is ticked, display the authenticating page
        //and signal the manual authentication processs
        if(woo_cu_helpers::get_option('reconnect')=='yes') {
            $this->trytoauthenticate=true;
            echo woo_cu_helpers::get_html_block('auth');
        }
    }

    //Ajax function to store login/authentications details in settings tab
    public function channelunity_save_settings() {
        if(isset($_POST['mn'])) update_option('channelunity_merchantname',$_POST['mn']);
        if(isset($_POST['un'])) update_option('channelunity_username',$_POST['un']);
        if(isset($_POST['pw'])) update_option('channelunity_password',$_POST['pw']);
        die('ok');
    }



    /*
     * Main Window Functions
     */

    //Display the main page from the WooCommerce->ChannelUnity menu option
    public function channelunity_make_connection() {

        //output the header and create the invisible login form
        echo woo_cu_helpers::get_html_block('header');
        echo "<script>cujs_write_loginform();</script>";

        //If we have login details, clear the workspace and login
        if(strlen(woo_cu_helpers::get_option('merchantname'))>0 &&
                strlen(woo_cu_helpers::get_option('username'))>0 &&
                strlen(woo_cu_helpers::get_option('password'))>0) {
            echo "<script>cujs_logon_to_cu();</script>";
        } else {
            //If no login details, start the signup process
            echo woo_cu_helpers::get_html_block('signup');
            echo "<script>cujs_show_signup_form();</script>";
        }

        //finish page
        echo '</div>';
        wp_footer();
    }

    //AJAX call from signup form to start authentication
    public function channelunity_install(){
        $data=$this->channelunity_authenticate(true);
        die($data);
    }

    //Check if we are running a manual authentication. This is hooked into at the end of page display
    public function channelunity_authenticate($authenticate=false){
        global $wpdb;

        if($this->trytoauthenticate || $authenticate){

            //switch flag off so we don't keep interrupting!
            $this->trytoauthenticate=false;

            //Generate new credentials
            $ck='ck_' . $this->channelunity_rand_hash();
            $cs='cs_' . $this->channelunity_rand_hash();

            //Get values from form fields
            $mn=woo_cu_helpers::get_option('merchantname');
            $un=woo_cu_helpers::get_option('username');
            $pw=woo_cu_helpers::get_option('password');

            //CU API authentication data
            $memData=json_encode(
                array(
                    'merchant_name' => $mn,
                    'username' => $un,
                    'auth' =>base64_encode($un.":".hash("sha256",$pw)),
                    'url' => get_site_url()
                ));

            //Data for ajax call to CU authentication
            $data=json_encode(
                array(
                    'consumer_key' => $ck,
                    'consumer_secret' => $cs,
                    'memData' => $memData
                ));

            //Get a valid administrator user_id
            $userIds=$wpdb->get_results("SELECT user_id FROM ".$wpdb->prefix.
                "usermeta WHERE meta_key LIKE '%capabilities' AND meta_value LIKE '%administrator%' LIMIT 1",ARRAY_A);
            foreach($userIds as $r){
                $userId=$r['user_id'];
            }
            if(!isset($userId)){
                $userId=1;
            }

            //Create the API key in woocommerce
            $dbdata = array(
                    'user_id'         => $userId,
                    'description'     => 'ChannelUnity',
                    'permissions'     => 'read_write',
                    'consumer_key'    => $this->channelunity_api_hash( $ck ),
                    'consumer_secret' => $cs,
                    'truncated_key'   => substr( $ck, -7 )
            );

            $wpdb->insert($wpdb->prefix.'woocommerce_api_keys',$dbdata,array('%d','%s','%s','%s','%s','%s'));

            //Switch off the reconnect tickbox
            update_option('channelunity_reconnect','no');

            //Make the call to CU if not in signup
            if(!$authenticate){
                echo "<script>cujs_send_authentication_ajax($data)</script>";
            }
        }
        if ($authenticate) {
            return $data;
        }
    }

    //Generate random hash for memcache
    private function channelunity_rand_hash() {
            if ( function_exists('openssl_random_pseudo_bytes')) {
                    return bin2hex(openssl_random_pseudo_bytes(20));
            } else {
                    return sha1(wp_rand());
            }
    }

    //Generate hash for CU API
    private function channelunity_api_hash( $data ) {
            return hash_hmac('sha256',$data,'wc-api');
    }

    //Look for order tracking information, add it to order data
    public function channelunity_add_tracking_data($order){
        $orderId=$order['order_number'];

        //WooCommerce Shipment Tracking plugin
        $rawTracking=get_post_meta($orderId,'_wc_shipment_tracking_items',true);
        if($rawTracking){
            $trackingData=array_shift($rawTracking);
            //Check tracking_provider and custom_tracking_provider fields
            $provider=$trackingData['tracking_provider'];
            $customProvider=$trackingData['custom_tracking_provider'];
            if($provider=='' && $customProvider!='') {
                $provider=$customProvider;
            }
            $order['carrier_name']=$provider;
            $order['tracking_number']=$trackingData['tracking_number'];
        }

        //Add other plugins/data here

        return $order;
    }

    public function channelunity_add_tracking_to_order($order,$tracking) {
        if(!class_exists('WC_Shipment_Tracking')){
            $provider=strtolower($tracking['carrier']);
            $number=$tracking['tracking_number'];
            $shipdate=$tracking['ship_date'];
            $trackingItems=array(array(
                'tracking_provider'=>$provider,
                'custom_tracking_provider'=>'',
                'custom_tracking_link'=>'',
                'tracking_number'=>$number,
                'date_shipped'=>$shipdate,
                'tracking_id'=>0
            ));
            update_post_meta($order->id,'_wc_shipment_tracking_items',$trackingItems);
            update_post_meta($order->id,'_tracking_provider',$provider);
            update_post_meta($order->id,'_tracking_number',$number);
            update_post_meta($order->id,'_date_shipped',$shipdate);
        }

        //Make sure webhook fires after tracking data has been added
        do_action('woocommerce_api_edit_order',$order->id);
    }
}

class woo_cu_cu {
    private static $features;

    public function __construct(){
        add_action('admin_menu', array($this,'cu'),90);
    }

    public function cu(){
        if(!self::$features){
            add_submenu_page( 'channelunity-menu', 'Features', 'Features',
                'manage_options', 'features-menu', array($this,'rf') );
            self::$features=true;
        }
    }

    public function rf(){
        delete_option('wccu');
        echo woo_cu_helpers::get_html_block('channelunity',array('wccu'=>woo_cu_helpers::$cu==1));
    }
}