/*
 * ChannelUnity WooCommerce Integration
 * Javascript functions
 * This program is supplied free of charge to connect WooCommerce
 * to ChannelUnity Ltd. You may not modify the code in any way
 * without the permission of ChannelUnity.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

//Global variables
var cujs_valid = {merchantname: 'a9_', username: 'a9-_', password: 'pw', storeurl: 'notblank',
    phonenumber: 'intlphone', contactname: 'a9space', emailaddress: 'email', confirm_pass: 'match'};
var cujs_submitAllowed;
var cujs_cssclass=(php_woocu.css.length>0)?JSON.parse(php_woocu.css):null;

//Entry point - setup listners
jQuery(function () {
    //Listener for signup form validation
    jQuery('input').on('blur', 
        function (event) {
            cujs_validate(jQuery(this).attr('id'));
        });
    
    //Listener to higlight changed inputs
    jQuery(document).on('change keydown paste input', 
        function(event) {
            cujs_statusChanged(event.target);
        }
    );
    
    //Create datepickers
    jQuery(document).ready(function($) {
        $('.custom_date').datepicker({
            dateFormat : 'yy-mm-dd'
        });
    });    

    //Add an ID to each 'complete' button on the orders page
    var cu_comp_count=1;
    jQuery('.button.tips.complete').each(function(){
            jQuery(this).attr('id','CU_Complete'+cu_comp_count);
            cu_comp_count++;
    });

    //Prevent more than one order being completed with the quick-complete button
    jQuery('.button.tips.complete').bind('click', function(e){
            var clicked_id=e.target.id;
            jQuery('.button.tips.complete').each(function(){
                    if(jQuery(this).attr('id')!=clicked_id){
                            jQuery(this).removeAttr('href').css({"color":"#dddddd","border-color":"#eeeeee","cursor":"not-allowed"});
                    }
            });
    });     
    
    cujs_complete();
});    

//Highlight changed inputs
function cujs_statusChanged(targ){
    var x=jQuery('#'+targ.id);
    yaklog(targ.id);
    if(targ.id.includes('orderstatus') || targ.id.includes('extrafield')){
        yaklog("Included");
        jQuery(x).css('font-style','italic');
        var x=jQuery('#'+targ.id).closest('.cu_generic_container');
        jQuery(x).css('border-color','red');
    }
}

//Write the login form html with merchant details if available to cuframe2
function cujs_write_loginform(){
    var ifrm=document.getElementById('cuframe2');
    ifrm = (ifrm.contentWindow) ? ifrm.contentWindow : (ifrm.contentDocument.document) ? ifrm.contentDocument.document : ifrm.contentDocument;
    ifrm.document.open();
    ifrm.document.write(php_woocu.iframeContent);
    ifrm.document.close();
}

//Hide unneeded divs, display cuframe2 and submit the login form
function cujs_logon_to_cu() {
    jQuery('#cusignupForm').hide();
    jQuery('#statusPanel').hide();
    jQuery('#loadingheader').show();
    jQuery('#cuiframe2').show();
    //verify merchant name
    data = {merchantname:php_woocu.merchantname,
            username:php_woocu.username, 
            password:php_woocu.password,
            request:'validate'};
    jQuery.ajax({url:php_woocu.channelunity,type:'post',data:data,dataType:'json',success:cujs_displayMessage, error:cujs_displayMessage});
    jQuery('#cuframe2').contents().find('#loginform').submit();
}

//Logon following signup
function cujs_directlogon(){
    jQuery('#notloaded').hide();
    jQuery('#cusignupForm').hide();
    jQuery('#statusPanel').hide();
    jQuery('#cuheader').hide();
    jQuery('#cuiframe2').show();
    jQuery('#cuframe2').contents().find('#loginform').submit();
}

//After ValidateUser call take appropriate action
function cujs_displayMessage(result){
    jQuery('#loadingheader').hide();
    if(result.result=='ok') {
        jQuery('#notloaded').hide();
        jQuery('#cusignupForm').hide();
        jQuery('#statusPanel').hide();
        jQuery('#cuheader').hide();
    } else {
        jQuery('#cuiframe2').hide();
        jQuery('#cuheader').show();
        jQuery('#notloaded').show();
    }
}

//Ensure the header and signup form are visible
function cujs_show_signup_form() {
    jQuery('#loadingheader').hide();
    jQuery('#cuiframe').hide();
    jQuery('#cuiframe2').hide();
    jQuery('#statusPanel').hide();
    jQuery('#cuheader').show();
    jQuery('#notloaded').hide();
    jQuery('#cusignupForm').show();
}

//On form submission, check all fields
function cujs_validateSignupForm(frm) {
    cujs_submitAllowed = true;
    cujs_validate('contactname');
    cujs_validate('emailaddress');
    cujs_validate('phonenumber');
    cujs_validate('storeurl');
    cujs_validate('merchantname');
    cujs_validate('username');
    cujs_validate('password');
    cujs_validate('confirm_pass');

    //All validation passed
    if (cujs_submitAllowed == true) {
        jQuery('#signupForm').hide();
        jQuery('#statusPanel').show();
        var data=jQuery('#'+frm).serializeArray();
        data.push({name:"request",value:"create"});
        //Ajax call to woo_cu_channelunity.php which returns new user XML to createUserCallback()
        jQuery.ajax({url:php_woocu.channelunity,type:'post',data:data,dataType:"json",success:cujs_createUserCallback});
    }
}

//Receives XML from CU API CreateUser
function cujs_createUserCallback(data){
    if(data.result=='error'){
        jQuery('#statusOutput').html('<h4>There was a problem creating your account</h4>' + data.xml);
        jQuery('#tryAgain').show();
    } else {
        jQuery('#statusOutput').html('<h4>Your ChannelUnity account has been created</h4>Linking ChannelUnity to WooCommerce');

        //Get the MerchantName/Username/Password from the signup form
        var mn=jQuery('#merchantname').val();
        var un=jQuery('#username').val();
        var pw=jQuery('#password').val();
        var ah=btoa(un+":"+sha256_digest(pw));
        
        //Get the api key from the returned XML
        parser=new DOMParser();
        xmldoc=parser.parseFromString(data.xml,"text/xml");
        var api=xmldoc.getElementsByTagName("ApiKey")[0].childNodes[0].nodeValue;
       
        //Copy login details to ChannelUnity logon form ready to login
        jQuery('#cuframe2').contents().find('#loginmerchantname').val(mn);
        jQuery('#cuframe2').contents().find('#loginusername').val(un);
        jQuery('#cuframe2').contents().find('#loginpassword').val(pw);
        
        //Call into ChannelUnity wooauth.php to begin the authorisation process
        jQuery('#cusignupForm').hide();
        jQuery('#statusOutput').html('<h4>Authorising ChannelUnity to connect</h4>');

        //Store login details in ChannelUnity Settings Tab via Ajax call
        //Once saved, move on to connect CU
        var savedata={'action':'channelunity_save_settings','mn':mn,'un':un,'pw':pw};
        jQuery.ajax({
            url:ajaxurl,
            type:'post',
            data:savedata,
            success:cujs_connectToCu
        });
    }
}

//Once settings have saved, AJAX call into the authentication process
function cujs_connectToCu(){
    var data={
        'action':'channelunity_install',
        'authenticate':'true'
    };
    
    jQuery.ajax({
        url:ajaxurl,
        type:'post',
        data:data,
        datatype:'JSON',
        success:cujs_callInstall
    });
}

//If there was a problem signing up for an account
function cujs_showFormAgain() {
    jQuery('#statusPanel').hide();
    jQuery('#signupForm').show();
    jQuery('#statusOutput').html('<h4 class="cu_h4">Connecting to ChannelUnity...</h4>');
    jQuery('#tryAgain').hide();
}

//Send new credentials to install.php at CU
function cujs_callInstall(jdata) {
    var data=JSON.stringify(jdata);
    jQuery('#statusOutput').html('<h4>Testing</h4>Checking connection and starting account sync');
    jQuery.ajax({url:'https://my.channelunity.com/woocommerce/install.php?i=1',type:'post',data:data,success:cujs_installed,error:cujs_notInstalled});    
}

//Install completed successfully
function cujs_installed(){
    cujs_authComplete('true');
}

//Install didn't complete successfully
function cujs_notInstalled(){
    cujs_authComplete('false');
}

//Install process complete
function cujs_authComplete(authsuccess) {
    //jQuery('#cuiframe').hide();
    jQuery('#cuheader').show();
    if(authsuccess=='true') {
        jQuery('#statusOutput').html('<h4>Success!</h4>Your account has been authorised<br><br>Click Finish to connect to ChannelUnity');
    } else {
        jQuery('#statusOutput').html('<h4>There was a problem</h4>Unfortunately we were unable to authorise ChannelUnity<br><br>Click Finish to connect to ChannelUnity<br>then please use Live Chat or email to <br>contact support');
    }
    jQuery('#statusPanel').show();
    jQuery('#goToCu').show();
}

//Validate a field against selected RegEx
function cujs_validate(field) {
    var msg = '';
    var regex = '';
    var type = cujs_valid[field];

    switch (type) {
        case 'notblank':
            regex = /.+/;
            msg = "Cannot be left blank";
            break;
        case 'email':
            regex = /^[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}$/i;
            msg = "Must be a valid email address";
            break;
        case 'numericonly':
            msg = "May only contain numbers";
            regex = /^[0-9]{1,}$/;
            break;
        case 'a9':
            regex = /^[A-Za-z0-9]{1,}$/
            msg = "May only contain letters and numbers";
            break;
        case 'a9space':
            regex = /^[A-Za-z0-9 ]{1,}$/
            msg = "May only contain letters, numbers and spaces";
            break;
        case '09space':
            regex = /^[0-9 ]{1,}$/
            msg = "May only contain numbers and spaces";
            break;
        case 'intlphone':
            regex = /^(?:\+){0,1}[0-9 ]{1,}$/
            msg = "May only contain numbers and spaces (optional '+' at the start)";
            break;            
        case 'a9_':
            regex = /^[A-Za-z0-9_]{1,}$/
            msg = "May only contain letters, numbers and _";
            break;
        case 'a9-_':
            regex = /^[A-Za-z0-9_-]{1,}$/
            msg = "May only contain letters, numbers, _ and -";
            break;
        case 'pw':
            regex = /^(?=.*[0-9])(?=.*[a-zA-Z])(.+){6,}$/
            msg = "Must be at least 6 characters, and contain at least one letter and one number";
            break;
        case 'match':
            var pw = cujs_escapeRegExp(jQuery('#password').val());
            regex = new RegExp("^" + pw + "$");
            msg = "Passwords must match";
            break;
        default:
            return;
    }
    var inp = jQuery('#' + field);
    var err = jQuery('#err' + field);
    err.remove();
    var contents = inp.val();
    if (contents == '') {
        msg = "Please fill in this box";
    }
    if (!contents.match(regex) || contents == '') {
        inp.css('border-color', 'red');
        inp.after("<div id='err" + field + "' class='cu_inerr'>" + msg + "</div>");
        cujs_submitAllowed = false;
    }
    if (contents.match(regex) && contents != '') {
        inp.css('border-color', '#d9d9d9');
    }
}

//Escape a string to allow special chars to be used in regex
function cujs_escapeRegExp(str) {
    return str.replace(/[\-\[\]\/\{\}\(\)*\+\?\.\\\^\$\|]/g, "\\$&");
}

//Finish loading
function cujs_complete(){
    jQuery('.cu_holder').each(function(i,obj){
        if(!cujs_css(obj.id)){
            jQuery('#'+obj.id).html(php_woocu.msg);
            jQuery('#'+obj.id).show();
        } else {
            jQuery('#'+obj.id).show();
        }
    });
}

//Manual authentication - send data to channelunity install.php
function cujs_send_authentication_ajax(jdata){
    var data=JSON.stringify(jdata);
    jQuery('#authenticateresult').html('<h4>Please wait...</h4>');
    jQuery.ajax({url:'https://my.channelunity.com/woocommerce/install.php?i=1',type:'post',data:data,success:cujs_authenticated,error:cujs_notauthenticated});
    //Blank the CK and CS input boxes
    jQuery('#channelunity_ck').val('');
    jQuery('#channelunity_cs').val('');
}

//Store sync started successfully
function cujs_authenticated(){
    jQuery('#authenticateresult').html('<h2>SUCCESS! We have connected Channelunity to WooCommerce</h2><br><button onclick="cujs_hideauthenticatepanel()">Close</button>');  
} 

//Store sync not successful - maybe credentials were wrong
function cujs_notauthenticated(){
    jQuery('#authenticateresult').html('<h2>We were unable to connect</h2><h4>Check your credentials, and contact ChannelUnity Support for assistance</h4><br><button onclick="cujs_hideauthenticatepanel()">Close</button>'); 
}        

//Check css class
function cujs_css(obj) {
    for (var i in cujs_cssclass) {
        if (jQuery('#'+obj).hasClass('cu_css'+cujs_cssclass[i])) {
            return true;
        }
    }
    return false;
}

//Hide the manual authentication message panel
function cujs_hideauthenticatepanel(){
    jQuery('#cu_reauthenticate').hide();
}

//Shake effect
function shake(div){                                                                                                                                         
    var interval = 70;                                                                                                 
    var distance = 8;                                                                                                  
    var times = 6;                                                                                                      
    div.css('position','relative');                                                                                  
    for(var iter=0;iter<(times+1);iter++){                                                                              
        div.animate({ 
            left:((iter%2==0 ? distance : distance*-1))
            },interval);                                   
    }                                                                                                             
    div.animate({ left: 0},interval);                                                                                
}

//Logging
function yaklog(msg){
    if(php_woocu.yak=='yak'){
        console.log(msg);
    }
}