<?php
/*
 *  Channelunity WooCommerce Plugin
 *  Bulk import products from CSV
 */



class woo_cu_bulkimport
{
    private $tempdir;
    private $woofields;
    private $productsImported;
    private $woolinks;
    private $product;
    private $post_id;

    public function __construct()
    {
        $this->tempdir=WP_PLUGIN_DIR.'/channelunity-woocommerce-integration/temp/';
        $this->channelunity_add_actions();
        $this->productsImported=0;
    }

    //Hooks and filters
    public function channelunity_add_actions(){
        //Put Bulk import tool in the CU menu
        add_action('admin_menu', array($this,'channelunity_register_bulkimport'),90);

        //Queue scripts
        add_action( 'admin_enqueue_scripts', array($this,'channelunity_register_scripts') );

        //Remove update notice
        add_action( 'admin_head', array($this,'channelunity_hide_update_notice'), 1 );

        //Ajax actions
        //Start uploading CSV
        add_action('wp_ajax_channelunity_upload_csv', array($this,'channelunity_upload_csv'));
        //Polling to see if file has uploaded
        add_action('wp_ajax_channelunity_check_csv', array($this,'channelunity_check_csv'));
        //Start importing
        add_action('wp_ajax_channelunity_import_products', array($this,'channelunity_import_products'));
    }

    //Register javascript/css
    public function channelunity_register_scripts(){

        //JS
        wp_register_script('woo_cu_bulkimport', plugins_url('../js/woo_cu_bulkimport.js', __FILE__));
        wp_enqueue_script('woo_cu_bulkimport');

        //Stylesheet
        wp_register_style('woo_cu_bulkimport_styles', plugins_url('../styles/woo_cu_bulkimport.css', __FILE__));
        wp_enqueue_style('woo_cu_bulkimport_styles');

        //Getvariables for javascript
        $noncetime=time();
        $php_import=array(
            'ajaxurl'       => admin_url( 'admin-ajax.php' ),
            'noncetime'     => $noncetime,
            'nonce'         => wp_create_nonce('importcsv'+$noncetime),
            'woofields'     => $this->get_woofields(true)
        );

        //send php variables to javascript
        wp_localize_script('woo_cu_bulkimport','php_import',$php_import);
    }

    //Remove 'update wordpress' nag
    public function channelunity_hide_update_notice() {
        if(@strpos($_REQUEST['page'],'bulkimport-menu')!==false){
            remove_action( 'admin_notices', 'update_nag', 3 );
        }
    }
    
    //Add tool to WooCommerce Products menu
    public function channelunity_register_bulkimport(){
        add_submenu_page( 'channelunity-menu', 'CSV Product Update', 'CSV Product Update',
            'manage_options', 'bulkimport-menu', array($this,'channelunity_render_importer') );
    }

    //Draw the tool
    public function channelunity_render_importer(){
        echo woo_cu_helpers::get_html_block('bulkimport');
    }

    //Ajax endpoint to upload csv
    public function channelunity_upload_csv(){
        if(file_exists($this->tempdir.'csvjson')){
            unlink($this->tempdir.'csvjson');
        }
        if(wp_verify_nonce(@$_REQUEST['nonce'],'importcsv'+@$_REQUEST['noncetime'])){
            if(!empty($_FILES) && isset($_FILES['csvimport'])) {
                $csvfile = @file_get_contents($_FILES['csvimport']['tmp_name']);
                $fields=$this->extract_woofields($csvfile);
            } else {
            $fields='invalid_csv';
            }
        } else {
            $fields= "Invalid request".print_r($_FILES,true);
            //die();
        }

        //Create new nonce for next call
        $noncetime=time();
        $csvjson=json_encode(array(
            'fields'    => $fields,
            'noncetime' => $noncetime,
            'nonce'     => wp_create_nonce('importcsv'+$noncetime)
        ));

        //Temporarily store headers in csvjson and full csv in csvfile
        file_put_contents($this->tempdir.'csvjson', $csvjson);
        file_put_contents($this->tempdir.'csvfile', $csvfile);
        echo "ok";
        die();
    }

    //Extract headers from CSV file
    private function extract_woofields($csvfile){
        $json="invalid_csv";
        $lines = explode(PHP_EOL,$csvfile);
        $array = array();
        foreach ($lines as $line) {
            $array = str_getcsv($line);
            break;
        }
        if(is_array($array)){
            $headers=  array_values($array);
            if(is_array($headers)){
                $json=json_encode($headers);
            }
        }
        return $json;
    }

    //Ajax polling to see if csv file has uploaded
    public function channelunity_check_csv(){
        if(file_exists($this->tempdir.'csvjson')){
            echo file_get_contents($this->tempdir.'csvjson');
        } else {
            echo '{"fields":"notready","temp":"'.$this->tempdir.'csvjson"}';
        }
        die();
    }

    //Set up woocommerce field data
    private function get_woofields($pretty=false){

    //"attributes","backorders","button_text","catalog_visibility","categories","cross_sell_ids","default_attributes",
    //"description","dimensions","download_expiry","download_limit","download_type","downloadable","downloads",
    //"enable_html_description","enable_html_short_description","featured","featured_src","images","in_stock",
    //"managing_stock","menu_order","parent","parent_id","product_url","purchase_note","regular_price","reviews_allowed",
    //"sale_price","sale_price_dates_from","sale_price_dates_to","shipping_class","short_description","sku",
    //"sold_individually","status","stock_quantity","tags","tax_class","tax_status","total_sales","type","upsell_ids",
    //"variations","virtual","weight"

        $this->woofields=array(
            '_sku',
            '*~Basic fields','_title','description','_price','quantity','barcode',
            '*~Dimensions','_weight','_length','_width','_height',
            '*~Other','_sale_price',
            '*~Attributes'
            );
        $prettyfields=array();
        foreach($this->woofields as $field){
            $prettyfields[]=ucwords(trim(str_replace('_', ' ', $field)));
        }
        if($pretty) {
            return array_merge($prettyfields,$this->get_attributes());
        } else {
            array_walk($this->woofields, function(&$v,$k){
               if(!is_numeric($k)){
                   $v=$k;
               }
            });
            return array_merge($this->woofields,$this->get_attributes(true));
        }
    }

    //Get attribute names or slugs
    private function get_attributes($slugs=false){
        global $wpdb;
        if(!$slugs) {
            return $wpdb->get_col('SELECT attribute_label FROM '.$wpdb->prefix.'woocommerce_attribute_taxonomies',0);
        } else {
            return $wpdb->get_col('SELECT attribute_name FROM '.$wpdb->prefix.'woocommerce_attribute_taxonomies',0);
        }
    }

    //Start importing products
    public function channelunity_import_products(){
        error_log("AJAX request to import products: ".print_r($_REQUEST,true)."\n", 3, "php://stdout");
        $this->woolinks=$_REQUEST['links'];
        $start=@$_REQUEST['start']; // initialise the import
        $report=array();
        if(!file_exists($this->tempdir.'csvfile')){
            error_log("Error - CSV couldn't be found\n", 3, "php://stdout");
            $report=array(
                "status"=>"error",
                "error"=>"CSV file did not save correctly - please check your WordPress folder permissions and restart"
            );
        } else {
            $csvhandle=fopen($this->tempdir.'csvfile','r');
            if($start){
                //reset pointers and discard the header
                $imported=0;
                $pointer=0;
                $header=fgetcsv($csvhandle);
            } else {
                $imported=get_option('channelunity_bulkimported', 0);
                $pointer=get_option('channelunity_csvpointer', 0);
                fseek($csvhandle,$pointer);
            }
            $this->product=fgetcsv($csvhandle);
            $oldPointer=$pointer;
            $pointer=ftell($csvhandle);
            if($oldPointer!=$pointer){
                //$success=$this->create_product();
                $success=$this->update_product();
                if($success){
                    $imported++;
                }
                update_option('channelunity_bulkimported', $imported);
                update_option('channelunity_csvpointer', $pointer);
                error_log("Done csv row ".print_r($this->product,true)."\n", 3, "php://stdout");
                $report=array(
                    "status"=>"importing",
                    "products"=>$imported
                );
            } else {
                $report=array(
                    "status"=>"finished",
                    "products"=>$imported
                );
                $failed=get_option('channelunity_bulkfailed');
                $failed=json_decode($failed,true);
                if(count($failed)>0){
                    $report['failed']=implode('<br>', $failed);
                }
            }
        }
        die(json_encode($report));
    }

    //Update product data in WooCommmerce
    private function update_product(){
        global $wpdb;
        $sku=$this->get_data('_sku');
        $post_id_result=$wpdb->get_results("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key='_sku' AND meta_value='$sku'",ARRAY_A);
        if(count($post_id_result)>0) {
            $this->post_id=$post_id_result[0]['post_id'];
            error_log("Post_id is{$this->post_id}");
        } else {
            $failed=get_option('channelunity_bulkfailed');
            $failed=  json_decode($failed,true);
            $failed[]=$sku;
            $failed=json_encode($failed);
            update_option('channelunity_bulkfailed',$failed);
            error_log("Couldn't find post_id for sku $sku");
            return false;
        }

        //Update product
        $this->add_meta('_price');
        $this->add_meta('_regular_price','_price');
        $this->add_meta('_stock','quantity');
        $this->add_meta('cu_upc','barcode');
        $this->add_meta('_length');
        $this->add_meta('_width');
        $this->add_meta('_height');
        $this->add_meta('_weight');
        
        return $this->post_id;

    }

    //Create new product data in WooCommmerce
    private function create_product(){
        $post = array(
            'post_author'   => get_current_user_id(),
            'post_status'   => "publish",
            'post_title'    => $this->get_data('_title'),
            'post_content'  => $this->get_data('description'),
            'post_parent' => '',
            'post_type' => "product",
        );

        //Create post
        $this->post_id = wp_insert_post($post);
        if ($this->post_id) {
            wp_set_object_terms($this->post_id, 'simple', 'product_type');
            $this->add_meta('_sku');
            $this->add_meta('_price');
            $this->add_meta('_regular_price','_price');
            $this->add_meta('_stock','quantity');
            $this->add_meta('cu_upc','barcode');
            $this->add_meta('_length');
            $this->add_meta('_width');
            $this->add_meta('_height');
            $this->add_meta('_weight');

            update_post_meta($this->post_id, '_manage_stock', "yes");
        }

        return $this->post_id;
    }

    //Add data for selected WooCommerce field. Some fields are displayed with a
    //'friendly name', passed in the 2nd parameter
    private function add_meta($woofield,$friendlyName=false){
        //Get the value from the CSV
        if(!$friendlyName) {
            $data=$this->get_data($woofield);
        } else {
            $data=$this->get_data($friendlyName);
        }
        if(strlen($data)>0){
            error_log("Updating postmeta for {$this->post_id} key $woofield with data $data");
            update_post_meta($this->post_id, "$woofield", $data);
            if($woofield=='_stock'){
                $stockstatus=($data)?'instock':'outofstock';
                update_post_meta($this->post_id, '_stock_status', $stockstatus);
            }
        }
        error_log("Adding meta for $woofield ($friendlyName): $data\n", 3, "php://stdout");
    }

    //Return the value from the CSV current product for the given mapped field name
    private function get_data($woofield){
        if(!$this->woofields){
            $this->get_woofields();
        }
        $index=array_search($woofield, $this->woofields);
        
        if(isset($this->woolinks[$index])){
            $col=$this->woolinks[$index];
            $data=$this->product[$col];

        } else {
            $data=false;
        }
        return $data;
    }
}