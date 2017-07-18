function cujs_updateOrderStatus(slug){
    var display=jQuery('#cu_orderstatus_display_'+slug).val();
    if(display==''){
        shake(jQuery('#cu_orderstatus_display_'+slug));
        return;
    }
    var translate=jQuery('#cu_orderstatus_translate_'+slug+' option:selected').val();
    var position=jQuery('#cu_orderstatus_position_'+slug+' option:selected').val();
    if(slug=='') {
        slug=jQuery('#cu_orderstatus_slug_').val();
        if(slug==''){
            slug=display;
        }
    }
    var data={
        'slug':slug,
        'display':display,
        'translate':translate,
        'position':position
    };
    jQuery.ajax({
        url: php_import.ajaxurl+'?action=channelunity_update_orderstatus',
        type: 'POST',
        data: data,
        success: cujs_redrawOrderstatus
    });    
}

function cujs_deleteOrderStatus(slug){   
    var data={'slug':slug};
    jQuery.ajax({
        url: php_import.ajaxurl+'?action=channelunity_delete_orderstatus',
        type: 'POST',
        data: data,
        success: cujs_redrawOrderstatus
    });    
}

function cujs_redrawOrderstatus(data){
    jQuery('#cu_orderstatus_fields').html(data);
}
