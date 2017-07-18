<?php
/*
 *  Channelunity WooCommerce Plugin
 *  Map
 */

class woo_cu_map{
    public function __construct(){
        $this->channelunity_add_actions();
    }

    //Actions and filters
    public function channelunity_add_actions(){
        //Add tool to CU menu
        add_action('admin_menu', array($this,'channelunity_register_map'),90);

        //Queue scripts
        add_action( 'admin_enqueue_scripts', array($this,'channelunity_register_scripts') );

        //Ajax actions
        add_action('wp_ajax_channelunity_save_api_key', array($this,'channelunity_save_api_key'));
        add_action('wp_ajax_channelunity_get_order_markers', array($this,'channelunity_get_order_markers'));
        add_action('wp_ajax_channelunity_add_coords', array($this,'channelunity_add_coords'));
        add_action('wp_ajax_channelunity_update_marker_colours', array($this,'channelunity_update_marker_colours'));
    }

    //Register javascript/css
    public function channelunity_register_scripts(){
        //JS
        //Googlemap API
        $mapApiKey=get_option('channelunity_map_api_key');
        wp_register_script('woo_cu_mapapi', "http://maps.googleapis.com/maps/api/js?key=$mapApiKey");
        wp_enqueue_script('woo_cu_mapapi');
        
        wp_register_script('woo_cu_map', plugins_url('../js/woo_cu_map.js', __FILE__));
        wp_enqueue_script('woo_cu_map');

        //Stylesheet
        wp_register_style('woo_cu_map_styles', plugins_url('../styles/woo_cu_map.css', __FILE__));
        wp_enqueue_style('woo_cu_map_styles');

        $php_map=array(
            'ajaxurl'       => admin_url( 'admin-ajax.php' ),
            'mapselect'     => woo_cu_helpers::make_select('',true),
            'mapapikey'     => $mapApiKey,
            'markercolours' => $this->getMarkerColours()
        );

        //send php variables to javascript
        wp_localize_script('woo_cu_map','php_map',$php_map);
    }

    //Add tool to WooCommerce Products menu
    public function channelunity_register_map(){
        add_submenu_page( 'channelunity-menu', 'Map orders', 'Map orders',
            'manage_options', 'map-menu', array($this,'channelunity_render_map') );
    }

    //AJAX callback
    public function channelunity_save_api_key(){
        $key=preg_replace('/[^\w-]/', '', @$_REQUEST['key']);
        if(strlen($key)==39){
            update_option('channelunity_map_api_key',$key);
            echo "ok";
        } else {
            echo "invalid";
        }
        die();
    }

    //Draw the tool
    public function channelunity_render_map(){
        echo woo_cu_helpers::get_html_block('map',array('colourtab'=>$this->getColoursTable()));
    }

    //Create the marker colour info
    private function getColoursTable(){
        $existing=wc_get_order_statuses();
        $colours=$this->getMarkerColours();

        $cTab='<table><tr><td>Status</td><td>Colour in RGB Hex (e.g. 00CCFF)</td></tr>';
        foreach($existing as $status){
            $col=@$colours[$status];
            if(!$col) {
                $col='FF6340';
            }
            $cTab.="<tr><td>$status</td><td><input type='text' id='cu_mc_$status' name='cu_mapcolour' value='$col'></td></tr>";
        }
        $cTab.='</table>';
        return $cTab;
    }

    private function getMarkerColours(){
        $colours=array(
            'Pending Payment'=>'FFB60D',
            'Processing'=>'00ECFC',
            'On Hold'=>'F0FC00',
            'Completed'=>'00B81C',
            'Cancelled'=>'E97AFF',
            'Refunded'=>'174DFF',
            'Failed'=>'FF0000'
        );
        $override=json_decode(get_option('channelunity_marker_colours'),true);
        if($override){
            foreach($override as $s=>$c){
                $colours[$s]=$c;
            }
        }
        return $colours;
    }

    public function channelunity_update_marker_colours(){
        $cols=@$_REQUEST['colours'];
        if($cols){
            foreach($cols as $s=>$c){
                if(!preg_match('/[0-9a-fA-F]{6}/', $c)){
                    unset($cols[$s]);
                }
            }
            update_option('channelunity_marker_colours',json_encode($cols));
        }
        die($this->getColoursTable());
    }

    //Get metadata and coordinates if already available for matching orders
    public function channelunity_get_order_markers(){
        global $wpdb;
        $post_status=preg_replace('/[^\w-],/', '',@$_REQUEST['status']);
        $post_status=str_replace(",", "','", $post_status);

        $date_from=preg_replace('/[^\w-]/', '',@$_REQUEST['start']);
        if(!$date_from) $date_from='00-01-01';
        
        $date_to=preg_replace('/[^\w-]/', '',@$_REQUEST['end']);
        if(!$date_to) $date_to=date('Y-m-d');

        $statuses=wc_get_order_statuses();

        //Get order meta data
        $data = $wpdb->get_results("
            SELECT p.ID, m.meta_key, m.meta_value, p.post_status
            FROM $wpdb->posts p
            LEFT JOIN $wpdb->postmeta m
            ON p.ID=m.post_id
            WHERE p.post_type = 'shop_order'
            AND p.post_status IN ('{$post_status}')
            AND p.post_date BETWEEN '{$date_from}  00:00:00' AND '{$date_to} 23:59:59'
            AND m.meta_key IN (
                '_cu_coordinates',
                '_billing_first_name',
                '_billing_last_name',
                '_shipping_first_name',
                '_shipping_last_name',
                '_shipping_address_1',
                '_shipping_city',
                '_shipping_postcode',
                '_shipping_country',
                '_order_currency'
            )
        ",ARRAY_A);

        //Parse into array with id as key
        $results=array();
        foreach($data as $d){
            $results[$d['ID']][$d['meta_key']]=$d['meta_value'];
            $results[$d['ID']]['id']=$d['ID'];
            $results[$d['ID']]['post_status']=  $d['post_status'];
            $results[$d['ID']]['status']=  $statuses[$d['post_status']];
        }

        //Add order total for each order
        foreach($results as $k=>$v){
            $order=new WC_Order($k);
            $results[$k]['total']=$order->get_formatted_order_total();
        }

        //Get total count
        $count = $wpdb->get_results("
            SELECT COUNT(DISTINCT(p.ID)) AS cc FROM $wpdb->posts p
            WHERE p.post_type = 'shop_order' AND p.post_status IN ('{$post_status}')
            AND p.post_date BETWEEN '{$date_from}  00:00:00' AND '{$date_to} 23:59:59'",ARRAY_A);
        $results['meta']=array('results'=>$count[0]['cc'],
            'post_status'=>$post_status
            );

        //Output
        die(json_encode($results));
    }

    //AJAX Add geolocator coords to order metadata
    public function channelunity_add_coords(){
        global $wpdb;
        $id=preg_replace('/[^0-9]/', '',@$_REQUEST['id']);
        $coords=preg_replace('/[^\w-,.]/', '',@$_REQUEST['coords']);
        $wpdb->insert(
            $wpdb->postmeta,
            array('post_id'=>$id,'meta_key'=>'_cu_coordinates','meta_value'=>$coords),
            array('%d','%s','%s')
        );
        die("Coords for $id stored as $coords");
    }
}