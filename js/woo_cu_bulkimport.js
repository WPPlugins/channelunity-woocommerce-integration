//Object to store field mapping
var cu_woolinks=new Object();
var importer;

//Make a div a drop target
function cujs_allowDrop(ev) {
    ev.preventDefault();
}

//Store data in dragged div
function cujs_drag(ev) {
    ev.dataTransfer.setData("text", jQuery(ev.target).text());
    ev.dataTransfer.setData("id", jQuery(ev.target).attr('id'));
}

//Make a link when field is dropped
//Links are stored in the cu_woolinks object. The data is stored as
//cu_woolinks[woofield_array_index]={csv_column_number(from 0)}
function cujs_drop(ev) {
    ev.preventDefault();
    var data = ev.dataTransfer.getData("text");
    var id = ev.dataTransfer.getData("id");
    var drp=jQuery(ev.target);
    var fldId=drp.attr('id').replace('cuwoofield','');
    var fldName=drp.attr('name').replace('cuwoofield','');
    if(cu_woolinks[fldId]!==undefined){
        clearId=cu_woolinks[fldId];
        var usedCount=0;
        for(var f in cu_woolinks) {
            if(cu_woolinks[f]==clearId){
                usedCount++;
            }
        }        
        if(usedCount==1) {
            jQuery('#cucsvfield'+cu_woolinks[fldId]).css("background-color","#FAFAFA");
        }
    }
    cu_woolinks[fldId]=id.replace('cucsvfield','');
    drp.html(fldName+" uses "+data+"<span class='cu_redcross' onclick='cujs_clearfield(this)'>X</span>").css("background-color","#DDFFDD");
    jQuery('#'+id).css("background-color","#DDFFDD");
    if(typeof cu_woolinks[0]!='undefined'){
        jQuery('#cu_beginbulkupdate').removeClass('cu_csvbutton_disabled');
    }
}

//Clear a link
function cujs_clearfield(cross){
    var fld=jQuery(cross).parent();
    var id=fld.attr('id');
    var name=fld.attr('name');
    var fldId=id.replace('cuwoofield','');
    var fldName=name.replace('cuwoofield','');
    fld.html(fldName);
    fld.css("background-color","#FAFAFA");
    clearId=cu_woolinks[fldId];
    delete cu_woolinks[fldId];
    for(var f in cu_woolinks) {
        if(cu_woolinks[f]==clearId){
            return;
        }
    }
    jQuery('#cucsvfield'+clearId).css("background-color","#FAFAFA");
    if(typeof cu_woolinks[0]=='undefined'){
        jQuery('#cu_beginbulkupdate').addClass('cu_csvbutton_disabled');
    }    
}

function cujs_setCsvFilename(){
    var fname=jQuery('#importcsv').val();
    yaklog(fname);
    fname=fname.replace('C:\\fakepath\\','');
    jQuery('#cu_csvfilename').html(fname);
}

//Start CSV upload process and set up polling
function cujs_uploadcsv(){
    var cufile = jQuery('#importcsv');
    var fData = new FormData('cu_csvimport');
    yaklog(cufile);
    fData.append('csvimport', cufile[0].files[0]);
    fData.append('nonce', php_import.nonce);
    fData.append('noncetime', php_import.noncetime);

    jQuery.ajax({
        url: php_import.ajaxurl+'?action=channelunity_upload_csv',
        type: 'POST',
        data: fData,
        contentType: false,
        processData: false
    });

    jQuery('#cu_csvimport').hide();
    jQuery('#notacsv').hide();
    jQuery('#csvprogress').show();
    checkcsv=window.setInterval(cujs_checkuploadstatus,2000);
}
    
//Poll the CSV upload process
function cujs_checkuploadstatus(){
    yaklog("Checking csv status");
    jQuery.ajax({
        url: php_import.ajaxurl+'?action=channelunity_check_csv',
        type: 'POST',
        data: {'none':'none'},
        dataType: 'json',
        success: cujs_uploadstatusreturn
    });    
}

//CSV Upload polling callback
function cujs_uploadstatusreturn(data){
    yaklog(data);
    if(data.fields!='notready'){
        clearInterval(checkcsv);
        cujs_loadHeaders(data);
    } else {
        yaklog("not ready");
    }
}

//CSV upload complete - extract headers and show mapping tool
function cujs_loadHeaders(data){
    yaklog(data);
    php_import.nonce=data.nonce;
    php_import.noncetime=data.noncetime;
    jQuery('#csvprogress').hide();
    if(data.fields=='invalid_csv'){
        jQuery('#notacsv').show();
        jQuery('#cu_csvimport').show();
    } else {
        jQuery('#uploadcsvblock').hide();
        
        var csvfields='';
        var fields=JSON.parse(data.fields);
        for(var i=0; i<fields.length; i++){
            csvfields+='<div id="cucsvfield' + i + '" class="cu_woofield" draggable="true" ondragstart="cujs_drag(event)">' + fields[i] + '</div><br>';
        }
        if(csvfields.length==0) {
            jQuery('#notacsv').show();
            jQuery('#cu_csvimport').show();
            jQuery('#uploadcsvblock').show();
        } else {
            cujs_populateWoofields()
            jQuery('#csv_field_container').html('<h4 style="margin-top:-10px;">CSV fields</h4>'+csvfields);
            jQuery('#csv_field_mapper').show();
        }
        
    }
}

//Create the Woo fields drop targets for mapping tool
function cujs_populateWoofields(){
    if(jQuery('#woo_field_container').html()==''){
        var woofieldshtml='<h4 style="margin-top:-10px;">WooCommerce fields</h4>';
        var woofields=php_import.woofields;     
        for(var i=0; i<woofields.length; i++){
            if(woofields[i].split('~')[0]!='*'){
                
                woofieldshtml+='<div id="cuwoofield' + i + '" name="cuwoofield' + woofields[i] 
                             + '" class="cu_woofield" ondrop="cujs_drop(event)" ondragover="cujs_allowDrop(event)">' 
                             + woofields[i] + '</div><br>';
            } else {
                 woofieldshtml+='<div class="cu_fieldbreak">'+woofields[i].split('~')[1]+'</div>';
            }
        }         
        jQuery('#woo_field_container').html(woofieldshtml);
    }
}

//Mapping tool upload different file button
function cujs_uploadDifferent(){
    jQuery('#csv_field_mapper').hide();
    jQuery('#notacsv').hide();
    jQuery('#cu_csvimport').show();
    jQuery('#uploadcsvblock').show();    
}

//Mapping finished - start importing in worker process
function cujs_finishedMapping(){
    if(jQuery('#cu_beginbulkupdate').hasClass('cu_csvbutton_disabled')){
        return;
    } else {
        jQuery('#csv_field_mapper').hide();
        jQuery('#notacsv').hide();    
        jQuery('#cu_buttonholder').hide();    
        jQuery('#csvimportingprogress').show();   
        cujs_importOne('start');
    }
}

//Send instruction to import one product
function cujs_importOne(start){
    var data={
        'links':cu_woolinks,
        'nonce':php_import.nonce,
        'noncetime':php_import.noncetime,
    };
    
    //First product
    if(start!=''){
        data['start']='start';
    }
    
    jQuery.ajax({
        url: php_import.ajaxurl+'?action=channelunity_import_products',
        type: 'POST',
        data: data,
        dataType: 'json',
        success: cujs_reportStatus
    });
    
    jQuery('#csv_field_mapper').hide();
    jQuery('#notacsv').hide();    
    jQuery('#cu_buttonholder').hide();    
    jQuery('#csvimportingprogress').show();      
}

//Callback for import process
function cujs_reportStatus(data){
    yaklog("Received message from importer");
    yaklog(data);
    //When complete, report
    if(data.status=='finished'){
        jQuery('#csvimportingprogress').hide();  
        cujs_importComplete(data);
    } else {
    //Otherwise do the next product
        jQuery('#cu_importedcount').text(data.products);
        cujs_importOne('');
    }
}

//Import process complete
function cujs_importComplete(data){
    jQuery('#cu_container').append("<h4>Update Complete</h4>We updated "+data.products+" skus");
    if(typeof data.failed!='undefined'){
        jQuery('#cu_container').append("We were unable to update the following skus (usually because we couldn't find the sku in WooCommerce)<br><br>"); 
        jQuery('#cu_container').append(data.failed); 
    }
}