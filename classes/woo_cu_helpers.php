<?php
/*
 *  Channelunity WooCommerce Plugin
 *  Helper functions
 */

class woo_cu_helpers{

    private static $pluginDirectory;
    private static $existing;
    private static $name="woo_cu_";
    private static $items='d29vX2N1X2Rhc2hib2FyZCx3b29fY3VfZXh0cmFmaWVsZHM=';
    private static $item;
    public static $yak;
    public static $cu;
    
    //Get a CU option from settings
    public static function get_option( $key ) {
        $fields = self::get_settings();
        return apply_filters('wc_option_' . $key,
            get_option( 'channelunity_'.$key,((isset( $fields[$key]) && isset($fields[$key]['default'])) ? $fields[$key]['default'] : '' )));
    }

    //Inputs on settings tab
    public static function get_settings() {
        $settings = array(
            'login' => array(
                'name'     => __( 'ChannelUnity account login details', 'channelunity_settings_tab' ),
                'type'     => 'title',
                'id'       => 'channelunity_settings_tab_login_title'
            ),
            'merchantname' => array(
                'name' => __( 'Merchant name', 'channelunity_settings_tab' ),
                'type' => 'text',
                'id'   => 'channelunity_merchantname'
            ),
            'username' => array(
                'name' => __( 'User name', 'channelunity_settings_tab' ),
                'type' => 'text',
                'id'   => 'channelunity_username'
            ),
            'password' => array(
                'name' => __( 'Password', 'channelunity_settings_tab' ),
                'type' => 'password',
                'id'   => 'channelunity_password'
            ),
            'reconnect' => array(
                'name' => 'Reconnect ChannelUnity',
                'type' => 'checkbox',
                'desc' => 'Create a new connection between ChannelUnity and WooCommerce',
                'id'   => 'channelunity_reconnect'
            ),
            'login_section_end' => array(
                 'type' => 'sectionend',
                 'id' => 'channelunity_login_settings_end'
            ),
            'options' => array(
                'name'     => __( 'Options', 'channelunity_settings_tab' ),
                'type'     => 'title',
                'id'       => 'channelunity_settings_tab_options_title'
            ),
            'block_emails' => array(
                'name' => __('Block emails','channelunity_settings_tab'),
                'type' => 'checkbox',
                'desc' => 'Prevents WooCommerce sending emails to @channelunity email addresses attached to imported orders',
                'id'   => 'channelunity_block_emails'
            ),
            'options_section_end' => array(
                 'type' => 'sectionend',
                 'id' => 'channelunity_options_settings_end'
            ),
            'consumerkey' => array(
                'name' => __( 'Consumer Key', '' ),
                'type' => 'test',
                'id'   => 'channelunity_ck'
            ),
            'consumersecret' => array(
                'name' => __( 'Consumer Secret', '' ),
                'type' => 'test',
                'id'   => 'channelunity_cs'
            ),
            'dashboard' => array(
                'name' => __( 'Dashboard on', '' ),
                'type' => 'test',
                'id'   => 'channelunity_dashboard'
            )
        );
        return apply_filters( 'channelunity_settings_tab', $settings );
    }

    //Get a block of HTML. Don't pass in 'woo_cu_' or '.html'
    public static function get_html_block($blockname,$params=array()) {
        if(!self::$pluginDirectory){
            self::setPluginDirectory();
        }
        $culogo=plugins_url('/../images/cu_logo.jpg',__FILE__);    //Path to ChannelUnity logo
        $progressbar=plugins_url('/../images/progress.gif',__FILE__);    //Path to ChannelUnity logo

        foreach($params as $var=>$conts){
            $$var=$conts;
        }

        ob_start();
            require self::$pluginDirectory."/../html/woo_cu_{$blockname}.html";
            $html=ob_get_contents();
        ob_end_clean();
        return $html;
    }

    //Date function
    public static function date(){
        if(get_option('channelunity_date')!=date('Y-m-d')){
            delete_option('wccu');
            update_option('channelunity_date',date('Y-m-d'));
        }
        if(isset($_REQUEST['yak'])){
            self::$yak='yak';
        }
    }
    
    //Return css setting
    public static function get_css(){
        return base64_decode(get_option('channelunity_css'));
    }

    //Return correct name for css setting
    private static function name($n){
        return (self::channelunity())?(strpos($n,self::$name)!==false)?str_replace(self::$name, '', $n):base64_decode($n):'cu';
    }

    //Get base folder for the plugin
    private static function setPluginDirectory(){
        self::$pluginDirectory= WP_PLUGIN_DIR . '/' . trim(dirname(plugin_basename(__FILE__)), '/');
    }

    //Generate items
    public static function items($item=array()){
        self::$item=$item;
        foreach(array_merge(explode(',',base64_decode(self::$items)),
            explode(',',base64_decode(get_option('channelunity_items')))) as $i)
                {$i=self::$name.self::name($i);$z=($i!=self::$name)?new $i:false;}
    }

    //Make a dropdown of existing statuses, optional selected
    public static function make_select($selected=false,$includeCU=false){
        $existing=wc_get_order_statuses();
        $statuses = self::get_custom_order_statuses();
        $cu=array();
        if($statuses){
            foreach($statuses as $s) {
                $cu[]=$s['display'];
            }
        }
        $optiondata='';
        foreach($existing as $slug=>$display){
            if($slug==$selected){
                $select='selected';
            } else {
                $select='';
            }
            if(!in_array($display,$cu) || $includeCU){
                $optiondata.="<option value='$slug' $select>$display</option>";
            }
        }
        return $optiondata;
    }

    public static function get_custom_order_statuses(){
        $statuses = get_option('channelunity_orderstatus');
        if(strlen($statuses)>0){
            return json_decode($statuses,true);
        } else {
            return false;
        }
    }

    public static function channelunity(){
        $cu=get_option('wccu');
        if(!$cu){
            $data=self::$item;
            $data['request']='channelunity';
            if(!self::$pluginDirectory){
                self::setPluginDirectory();
            }
            include self::$pluginDirectory.'/woo_cu_channelunity.php';
            $cu=($cu)?1:2;
            update_option('wccu',$cu);
        }
        self::$cu=$cu;
        return $cu==1;
    }
}