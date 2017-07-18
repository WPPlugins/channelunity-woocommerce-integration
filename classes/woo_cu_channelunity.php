<?php
/*
 * ChannelUnity WooCommerce Integration
 * Ajax calls to ChannelUnity
 *
 */

//Sanitise request
if(isset($data)){
    $_POST=$data;
}
$request=preg_replace('/[^a-zA-Z0-9 .-]/','',$_POST['request']);

//Check for spam
if($request=='create') {
    $spamcheck = file_get_contents("http://www.stopforumspam.com/api?email=".$_POST['emailaddress']);
    $spamcheck = simplexml_load_string($spamcheck);
    if((string) $spamcheck->appears == "yes"){
        $result=array('result'=>'error','xml'=>'<Info>Email detected as spambot by Stop Forum Spam.com</Info>');
        echo json_encode($result);
        exit();
    }
}

//Generate XML for CU API
$auth = channelunity_getAlternateUserAuth();
$mn = $_POST['merchantname'];
$tosend = "<?xml version=\"1.0\" ?>".
          "<ChannelUnity>".
          "<MerchantName><![CDATA[".$mn."]]></MerchantName>".
          "<Authorization>$auth</Authorization>";
switch($request) {
    case "validate":
    case "channelunity":
        $tosend .= "<RequestType>ValidateUser</RequestType>";
        break;
    
    case "create":
        $tosend .= "<RequestType>CreateMerchantAsync</RequestType>".
                   "<Payload>".
                   "<Name>".htmlspecialchars($_POST['contactname'])."</Name>".
                   "<Company>".htmlspecialchars($_POST['merchantname'])."</Company>".
                   "<Country>".htmlspecialchars($_POST['country'])."</Country>".
                   "<EmailAddress>".htmlspecialchars($_POST['emailaddress'])."</EmailAddress>".
                   "<MobileNumber>".htmlspecialchars($_POST['telephone'])."</MobileNumber>".
                   "<InviteCode></InviteCode>".
                   "</Payload>";
        break;
    
    default:
        $result=array('result'=>'error','xml'=>'<Info>Invalid data</Info>');
        exit();
}
$tosend.="</ChannelUnity>";

//Make the call and get response
$recvString = channelunity_sendMessage($tosend);
$xml2 = simplexml_load_string($recvString);
$cu=(in_array($xml2->AccountStatus,explode(',',base64_decode('bGl2ZSx0cmlhbA=='))));

//Return response to AJAX caller
if ($xml2->Status == "OK") {
    $result=array('result'=>'ok','xml'=>$recvString);
} else {
    $result=array('result'=>'error','xml'=>$xml2->Status);
}
if($request!='channelunity'){
    echo json_encode($result);
    exit();
}

//Create temporary API authentication
function channelunity_getAlternateUserAuth() {
    $auth = $_POST['username']. ":" . hash("sha256", $_POST['password']);
    $auth = base64_encode($auth);
    return $auth;
}

//Send XML to CU endpoint
function channelunity_sendMessage($xmlMessage) {
    $url = "https://my.channelunity.com/".'event.php?marktest=true';

    $fields = urlencode($xmlMessage);
    //open connection
    $ch = curl_init();
    //set the url, number of POST vars, POST data
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, TRUE);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_TIMEOUT, 400);
    curl_setopt($ch, CURLOPT_POSTFIELDS, array('message' => $fields));
    //execute post
    $result = curl_exec($ch);
    //close connection
    curl_close($ch);
    return $result;
}
