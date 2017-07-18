<?php
/*
 * ChannelUnity WooCommerce Plugin
 * ©2016 ChannelUnity
 * Author: Adam Hembrough
 *
 *
 * NOT REQUIRED FROM V2.3
 *
 * /wc-auth/v1/authorize endpoint return_url
 * 
 * After successful authorisation/connection at ChannelUnity
 * WooCommerce Auth endpoint returns here via absolute URL
 * This page is loaded in the cuframe auth iframe, and
 * calls parent JS funtion to close the iframe and pass
 * control back to the CU WooCommerce Plugin
 */

if (@$_REQUEST['success']=='1') {
    $success='true';
} else {
    $success='false';
}

echo "<script>parent.cujs_authComplete('$success');</script>";