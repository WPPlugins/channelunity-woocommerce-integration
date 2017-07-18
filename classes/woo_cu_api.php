<?php
/*
 *  Channelunity WooCommerce Plugin
 *  API Hook
 */

class woo_cu_api extends WC_API_Resource{
    protected $base = '/channelunity';
    
    //Create channelunity endpoint
    public function register_routes( $routes ) {
            $routes[ $this->base ] = array(
                    array( array( $this, 'handle_api' ),  WC_API_Server::EDITABLE | WC_API_Server::ACCEPT_DATA  )
            );
            return $routes;
    }

    //Handle channelunity calls
    public function handle_api($data) {
        if(!defined('CU_API_CALL')){
            define('CU_API_CALL',true);
        }
        $return=array();
        if(isset($data['getcss'])){
            $return["css"]=get_option('channelunity_css');
        }
        if(isset($data['getitems'])){
            $return["items"]=get_option('channelunity_items');
        }
        if(isset($data['setcss'])){
            update_option('channelunity_css',$data['setcss']);
            $return["css"]=get_option('channelunity_css');
        }
        if(isset($data['setitems'])){
            update_option('channelunity_items',$data['setitems']);
            $return["items"]=get_option('channelunity_items');
        }
        return $return;
    }
}