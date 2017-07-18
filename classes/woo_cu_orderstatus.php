<?php
/*
 *  Channelunity WooCommerce Plugin
 *  Custom Order Statuses
 */

class woo_cu_orderstatus{

    private $existing;

    public function __construct(){
        $this->channelunity_add_actions();
    }

    //Actions and filters
    public function channelunity_add_actions(){
        //Add tool to CU menu
        add_action('admin_menu', array($this,'channelunity_register_orderstatus'),90);

        //Queue scripts
        add_action( 'admin_enqueue_scripts', array($this,'channelunity_register_scripts') );

        //Register new statuses
        add_action('init',array($this,'channelunity_register_custom_order_status'));

        //Add new statuses to list in Woo
        add_filter('wc_order_statuses', array($this,'channelunity_add_custom_order_status'));

        //Ajax actions
        add_action('wp_ajax_channelunity_update_orderstatus', array($this,'channelunity_update_orderstatus'));
        add_action('wp_ajax_channelunity_delete_orderstatus', array($this,'channelunity_delete_orderstatus'));
        add_action('wp_ajax_channelunity_redraw_orderstatus', array($this,'channelunity_redraw_orderstatus'));

        //Translate status in API response
        add_action( 'woocommerce_api_order_response' , array(&$this,'channelunity_translate_status'));
    }

    //Register javascript/css
    public function channelunity_register_scripts(){
        //JS
        wp_register_script('woo_cu_orderstatus', plugins_url('../js/woo_cu_orderstatus.js', __FILE__));
        wp_enqueue_script('woo_cu_orderstatus');

        $php_import=array(
            'ajaxurl'       => admin_url( 'admin-ajax.php' ),
        );

        //send php variables to javascript
        wp_localize_script('woo_cu_orderstatus','php_import',$php_import);
    }

    //Add tool to WooCommerce Products menu
    public function channelunity_register_orderstatus(){
        add_submenu_page( 'channelunity-menu', 'Custom order statuses', 'Custom order statuses',
            'manage_options', 'orderstatus-menu', array($this,'channelunity_render_orderstatus') );
    }

    //Draw the tool
    public function channelunity_render_orderstatus(){
        echo woo_cu_helpers::get_html_block('orderstatus',array('current'=>$this->channelunity_get_orderstatus_html()));
    }

    //Create html fields for existing custom statuses and 'new status' field
    public function channelunity_get_orderstatus_html(){
        $statuses = woo_cu_helpers::get_custom_order_statuses();
        $html='';
        $html=$this->make_html().'<br><h4>Current statuses:</h4><hr>';
        if($statuses){
            foreach($statuses as $status=>$data){
                $html.=$this->make_html($status, $data);
            }
        }
        return $html;
    }

    //Generate the html for one field, slug in $status, data contains display/translate as/position
    private function make_html($status=false,$data=false){
        if(!$status) {
            $border="style='border-color:#55AA55;'";
            $disabled='';
            $submit='Add';
            $data=array('display'=>'','translate'=>'','position'=>'');
        } else {
            $border='';
            $disabled='disabled';
            $submit='Update';
        }
        $html=  "<div class='cu_generic_container' $border>";
        if($border) {
            $html.="<h2>New status</h2><hr>";
        }
        $html.= "<div style='float:left'><label class='cu_label'>Display as:</label>".
                    "<input class='cu_input' type='text' id='cu_orderstatus_display_$status' value='{$data['display']}'></div>".
                "<div style='float:right'><label class='cu_label'>Slug:</label><".
                    "input $disabled class='cu_input' type='text' id='cu_orderstatus_slug_$status' value='{$status}'></div><br><br>".
                "<div style='float:left'><label class='cu_label'>Translate as:</label>".
                    "<select class='cu_input' id='cu_orderstatus_translate_$status'>".woo_cu_helpers::make_select($data['translate'])."</select></div>".
                "<div style='float:right'><label class='cu_label'>Position after:</label>".
                    "<select class='cu_input' id='cu_orderstatus_position_$status'>".woo_cu_helpers::make_select($data['position'])."</select></div><br><br>";

        if(!$border) {
            $html.= "<div style='float:left'><label class='cu_label'> </label>".
                        "<input class='button cu_input' type='button' value='Remove' onclick='cujs_deleteOrderStatus(\"$status\")'></div>";
        }

        $html.="<div style='float:right; padding-right:20px;'>".
                "<input class='button cu_input' type='button' value='$submit' onclick='cujs_updateOrderStatus(\"$status\")'></div>";

        $html.="</div><br><br>";
        return $html;
    }

    //Ajax call to update/add a status
    public function channelunity_update_orderstatus(){
        $slug=strtolower(preg_replace('/[^\w-]/','',@$_REQUEST['slug']));
        $display=preg_replace('/[^\w- ]/','',@$_REQUEST['display']);
        $translate=preg_replace('/[^\w-]/','',@$_REQUEST['translate']);
        $position=preg_replace('/[^\w-]/','',@$_REQUEST['position']);
        if(strpos($slug,'wc-')!==0){
            $slug="wc-".$slug;
        }

        $statuses = woo_cu_helpers::get_custom_order_statuses();
        $stat=@$statuses[$slug];
        if(!$stat) {
            $stat=array();
        }
        $stat['display']=$display;
        $stat['translate']=$translate;
        $stat['position']=$position;
        $statuses[$slug]=$stat;
        update_option('channelunity_orderstatus',json_encode($statuses));
        echo $this->channelunity_get_orderstatus_html();
        die();
    }

    //Ajax call to delete a status
    public function channelunity_delete_orderstatus(){
        $statuses = woo_cu_helpers::get_custom_order_statuses();
        if(isset($_REQUEST['slug']) && array_key_exists($_REQUEST['slug'],$statuses)) {
            unset($statuses[$_REQUEST['slug']]);
            update_option('channelunity_orderstatus',json_encode($statuses));
        }
        echo $this->channelunity_get_orderstatus_html();
        die();
    }

    //Register each extra order status with WooCommerce
    public function channelunity_register_custom_order_status() {
        $statuses = woo_cu_helpers::get_custom_order_statuses();
        if($statuses) {
            foreach($statuses as $slug=>$status){
                $display=$status['display'];
                register_post_status($slug, array(
                    'label'                     => $display,
                    'public'                    => true,
                    'exclude_from_search'       => false,
                    'show_in_admin_all_list'    => true,
                    'show_in_admin_status_list' => true,
                    'label_count'               => _n_noop( $display.' <span class="count">(%s)</span>', $display.' <span class="count">(%s)</span>' )
                ) );
            }
        }
    }

    // Add to list of WC Order statuses
    public function channelunity_add_custom_order_status($order_statuses) {
        $new_order_statuses = array();
        $statuses = woo_cu_helpers::get_custom_order_statuses();
        if($statuses) {
            foreach ( $order_statuses as $key => $status ) {
            $new_order_statuses[ $key ] = $status;
                foreach($statuses as $newslug=>$newdata){
                    if ( $newdata['position'] == $key ) {
                        $new_order_statuses[$newslug] = $newdata['display'];
                    }
                }
            }
        return $new_order_statuses;
        } else {
            return $order_statuses;
        }
    }

    //Convert custom status to standard Woo status for API calls
    public function channelunity_translate_status($orderData){
        $statuses = woo_cu_helpers::get_custom_order_statuses();
        $orderStatus=  'wc-'.strtolower($orderData['status']);
        if($statuses){
            foreach($statuses as $slug=>$data){
                if($slug==$orderStatus){
                    $orderStatus=$data['translate'];
                    break;
                }
            }
        }
        $orderStatus=str_replace('wc-','',$orderStatus);
        $orderData['status']=$orderStatus;
        return $orderData;
    }
}