//Open/close configure window
function cujs_toggleBulkOptions(){
    jQuery('#cu_bulkoptions').slideToggle();
}

//Ajax request to add or update a field
function cujs_updateExtraFields(slug){
    yaklog("Updating "+slug);
    var display=jQuery('#cu_extrafield_'+slug).val();
    if(display==''){
        shake(jQuery('#cu_extrafield_'+slug));
        return;
    }
    var position=jQuery('input[name="cu_extrafield_rad_'+slug+'"]:checked').val();
    if(typeof(position)=='undefined'){
        shake(jQuery('#cu_extrafields_position'));
        return;
    }    
    if(slug=='') {
        slug=jQuery('#cu_extrafield_slug_').val();
        if(slug==''){
            slug=display;
        }
    }
    var data={
        'slug':slug,
        'display':display,
        'position':position
    };
    jQuery.ajax({
        url: php_import.ajaxurl+'?action=channelunity_update_extrafields',
        type: 'POST',
        data: data,
        success: cujs_redrawExtraFields
    });    
}

//Ajax request to delete a field
function cujs_deleteExtraFields(slug){   
    var data={'slug':slug};
    jQuery.ajax({
        url: php_import.ajaxurl+'?action=channelunity_delete_extrafields',
        type: 'POST',
        data: data,
        success: cujs_redrawExtraFields
    });    
}

//Ajax calls respond with new html
function cujs_redrawExtraFields(data){
    jQuery('#cu_customfields_container').html(data);
}