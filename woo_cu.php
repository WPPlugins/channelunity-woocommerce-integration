<?php
/*
  Plugin Name: Amazon, eBay, NewEgg, Rakuten and more. WooCommerce Multichannel Integration by ChannelUnity
  Plugin URI: http://www.channelunity.com/plugins/wc_cu_plugin.zip
  Description: This plugin incorporates ChannelUnity into WooCommerce
  Author: ChannelUnity
  Author URI: http://www.channelunity.com
  Version: 2.6

 *
 * This program is supplied free of charge to connect WooCommerce
 * to ChannelUnity Ltd. You may not modify the code in any way
 * without the permission of ChannelUnity.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 */


//Set autoload function
spl_autoload_register('channelunity_autoload');

//Create plugin
new woo_cu_main();

function channelunity_autoload($class){
    $filename=plugin_dir_path( __FILE__ ).
        'classes/'.
        strtolower($class).
        '.php';
    if(file_exists($filename)){
        require_once $filename;
    }
}