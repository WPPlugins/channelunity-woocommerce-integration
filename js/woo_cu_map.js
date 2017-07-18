var orderData;
var metaData;
var geoAddress;
var geocoder;
var geoCounter;
var geoCurrent;
var geoPass=0;

jQuery(function(){
    jQuery('#cu_mapstat').append(php_map.mapselect);
    var key=jQuery('#cu_mapapikey').val(php_map.mapapikey);
    if(php_map.mapapikey.length==39){
        jQuery('#cu_mapoptions2').show();
    }
    cujs_drawMap();
});

//Open/close configure window
function cujs_toggleMapOptions(){
    jQuery('#cu_mapoptions').slideToggle();
}

//Open/close colour configure section
function cujs_configureMapColours(){
    jQuery('#cu_marker_config').slideToggle();
    yaklog(php_map.markercolours);
}

//Save API key via AJAX
function cujs_saveApiKey(){
    var key=jQuery('#cu_mapapikey').val();
    yaklog("uploading key");
    jQuery.ajax({
        url: php_map.ajaxurl+'?action=channelunity_save_api_key',
        type: 'POST',
        data: {'key':key},
        success: cujs_apiKeySaved
    });    
}

//Reload once we have a valid-looking API key
function cujs_apiKeySaved(data){
    if(data.trim()=='ok'){
        location.reload();
    } else {
	jQuery('#cu_mapoptions2').slideUp();
        jQuery('#cu_invalid_mapkey').fadeOut(100);
        jQuery('#cu_invalid_mapkey').fadeIn(200);
    }
    
}

function cujs_updateMapColours(){
    var colours=cujs_getMarkerColours();
        jQuery.ajax({
            url: php_map.ajaxurl+'?action=channelunity_update_marker_colours',
            type: 'POST',
            data: {'colours':colours},
            success: cujs_mapColoursUpdated
        });  
        php_map.markercolours=colours;
}

function cujs_mapColoursUpdated(data){
    jQuery('#cu_mapcolourtable').html(data);
    cujs_configureMapColours();
}

function cujs_getMarkerColours(){
    var cols=new Object();
    jQuery("input[name='cu_mapcolour']").each(function(i){
        yaklog(jQuery(this));
        var stat=this.id.replace('cu_mc_','');
        var col=jQuery(this).val();
        cols[stat]=col;
    });
    return cols;
}

//Get orders that match the requested parameters
function cujs_updateMap(toggle){
    var status=jQuery('#cu_mapstat').val().join();
    yaklog("Statuses are:");
    yaklog(status);
    var start=jQuery('#cu_map_start_date').val();
    var end=jQuery('#cu_map_end_date').val();
    data={
        'status':status,
        'start':start,
        'end':end
    };
    
    //Call and return the results to look up coordinates
    jQuery.ajax({
        url: php_map.ajaxurl+'?action=channelunity_get_order_markers',
        type: 'POST',
        data: data,
        dataType:'JSON',
        success: cujs_getCoords
    });
    if(toggle!=1){
        cujs_toggleMapOptions();
    }
    jQuery('#cu_actualmap').html("<div class='cu_mapmessage'>Looking up orders</div>"); 
}

//Get coordinates for each order, calling geolocator if we don't have them
function cujs_getCoords(data){
    orderData=data;
    if(!metaData) {
      metaData=data.meta
    }
    if(metaData.results==0){
        jQuery('#cu_actualmap').html("<div class='cu_mapmessage'>There are no orders matching these parameters</div>");   
        return;
    } else {
        jQuery('#cu_actualmap').html("<div class='cu_mapmessage'>Found " + metaData.results + " orders. Looking up addresses<br><div id='cu_addressesfound'></div></div>"); 
    }
    delete data.meta;
 
    geoAddress=new Object();
    geocoder = new google.maps.Geocoder();
    geoCounter=0;
    geoCurrent=1;
    
    for(var order in data){
        var odata=data[order];
        var address=odata['_shipping_address_1']+', '+odata['_shipping_city']+', '+odata['_shipping_country']+', '+ odata['_shipping_postcode'];
        yaklog("Check Coordinates in database for "+address);
        if(!odata['_cu_coordinates']){
            var id=odata['id'];
            yaklog("Order id is "+id);
            yaklog('Not currently in database - queue Coordinates for geocoder');
	    geoCounter++;
            geoAddress[geoCounter]={id:id,address:address};
        } else {
            yaklog('Coordinates in database are '+odata['_cu_coordinates']);
        }
        jQuery('#cu_addressesfound').html('Processing address '+geoCounter);
    }
    
    if(geoCounter>0 && geoPass==0){
        geoPass++;
        yaklog("Now process the geocode queue");
	yaklog(geoAddress);
        jQuery('#cu_actualmap').html("<div class='cu_mapmessage'>Looking up address co-ordinates<br><div id='cu_addressesfound'></div></div>");
        cujs_process_geo_queue();
    } else {
        delete orderData.meta;
        cujs_drawMap(orderData);
    }
}

function cujs_process_geo_queue(){
    jQuery('#cu_addressesfound').html('Processing co-ordinates '+geoCurrent+" of "+geoCounter);
    if(geoCurrent<=geoCounter){
         cujs_geocode(geoCurrent);
    } else {
      yaklog("Go around again");
      yaklog(orderData);
        cujs_updateMap(1);
    }
}

function cujs_geocode(gcount){
    yaklog("Geoqueue - processing "+gcount+" of "+geoCounter);
    id=geoAddress[gcount]['id'];
    address=geoAddress[gcount]['address'];
    geocoder.geocode( { 'address': address}, function(results, status) {
        if (status == google.maps.GeocoderStatus.OK) {
            if (status != google.maps.GeocoderStatus.ZERO_RESULTS) {  
                var coords=results[0].geometry.location.lat().toString()+','+results[0].geometry.location.lng().toString();
                yaklog("Got coords from geocoder: "+coords);
                cujs_addCoordsToDb(id,coords);
                geoCurrent++;
                setTimeout(cujs_process_geo_queue,800);
            }
        } else {
	  yaklog("Couldn't get results for address "+address);
	  geoCurrent++;
	  setTimeout(cujs_process_geo_queue,800);
	}
    });    
}


//Add async geolocator result coords to order meta with AJAX call
function cujs_addCoordsToDb(id,coords){
    data={
        'id':id,
        'coords':coords
    };
    jQuery.ajax({
        url: php_map.ajaxurl+'?action=channelunity_add_coords',
        type: 'POST',
        data: data,
        success: cujs_coordsStored
    });    
}

function cujs_coordsStored(data){
    yaklog(data);
}

//Generate map and create a marker for each order
function cujs_drawMap(data){
    if(php_map.mapapikey && data){
        jQuery('#cu_mapoptions').slideUp();
        var markers=new Array();
        var infoWindowContent=new Array();
        var odata;
        var map;
        var bounds = new google.maps.LatLngBounds();
        var mapOptions = {
            mapTypeId: 'roadmap'
        };
	var locErrors=0;
        var locErrorAddresses='';
        map = new google.maps.Map(document.getElementById("cu_actualmap"), mapOptions);
        
        //Create marker for each order
        for(var order in data){
            odata=data[order];
            yaklog("Constructing order marker");
            yaklog(odata);
            var id=odata['id'];
            var status=odata['status'];
            var co=odata['_cu_coordinates'];
            var customerName=odata['_billing_first_name'] + " " + odata['_billing_last_name'];
//            if(customerName.trim()==''){
//                customerName=odata['_shipping_first_name'] + " " + odata['_shipping_last_name'];
//            }
            var address=odata['_shipping_address_1'] + ', ' +odata['_shipping_country'] + '<br>' + odata['_shipping_postcode'];
	    if(typeof co !== 'undefined' && co.includes(",")){
		var coords=co.split(',');
		infoWindowContent.push(
		    ['<div class="info_content">' +
			'<a href="post.php?post=' + 
			id +'&action=edit"' +
			'<b>Order #' + id + '</b></a><br>' + 
			'(' + status + ')<br><br>' + 
                        customerName + "<br>"+
			address + '<br><br>' +
			'Order total: ' + odata['total'] + " " + 
			odata['_order_currency'] + '</div>'
		    ]
		);
		markers.push([id,parseFloat(coords[0]),parseFloat(coords[1]),php_map.markercolours[status]]);
	    } else {
	      locErrors++;
              locErrorAddresses=locErrorAddresses + 
                      "Order " + id + ": " + 
                      customerName + ', ' +
                      odata['_shipping_address_1'] + ', ' + 
                      odata['_shipping_country'] + 
                      odata['_shipping_postcode'] + 
                      "<br>";
	    }
        }

        var infoWindow = new google.maps.InfoWindow(), marker, i;

        //Put markers on the map
        for( i = 0; i < markers.length; i++ ) {
            var position = new google.maps.LatLng(markers[i][1], markers[i][2]);
            yaklog("colour "+markers[i][3]);
            bounds.extend(position);
            marker = new google.maps.Marker({
                position: position,
                map: map,
                icon:"http://chart.googleapis.com/chart?chst=d_map_pin_letter&chld=•|"+markers[i][3],
                title: markers[i][0]
            });

            //Info window listener for each marker
            google.maps.event.addListener(marker, 'click', (function(marker, i) {
                return function() {
                    infoWindow.setContent(infoWindowContent[i][0]);
                    infoWindow.open(map, marker);
                }
            })(marker, i));
        }

        //Stop zoom level being too close, and set map zoom/position
        if (bounds.getNorthEast().equals(bounds.getSouthWest())) {
           var extendPoint1 = new google.maps.LatLng(bounds.getNorthEast().lat() + 0.01, bounds.getNorthEast().lng() + 0.01);
           var extendPoint2 = new google.maps.LatLng(bounds.getNorthEast().lat() - 0.01, bounds.getNorthEast().lng() - 0.01);
           bounds.extend(extendPoint1);
           bounds.extend(extendPoint2);
        }
        map.fitBounds(bounds); 
        jQuery('#cu_actualmap').css({"border-width":"1px","border-color":"#444444","border-style":"solid","border-radius":"2px"});
	if(locErrors>0){
            var p=(locErrors==1)?'':'s';
	    jQuery('#cu_locationerrors').html("<span class='cu_error'>Couldn't determine location for "+locErrors+" order"+p+":</span><br>"+locErrorAddresses);
	} else {
            jQuery('#cu_locationerrors').html();
        }
    } else {
        if(!php_map.mapapikey) {
            jQuery('#cu_actualmap').html("<div class='cu_mapmessage'>Please configure your map API key by clicking 'Configure' above</div>");
        } else {
            jQuery('#cu_actualmap').html("<div class='cu_mapmessage'>Please select the orders you wish to display by clicking 'Configure' above</div>");
        }
    }
    
    function getIcon(colour) {
        var iconUrl = "http://chart.googleapis.com/chart?chst=d_map_pin_letter&chld=•|"+colour;
        yaklog(iconUrl);
        return iconUrl;
    }
}