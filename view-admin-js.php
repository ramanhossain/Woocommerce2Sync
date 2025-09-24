<?php
// Printed in admin_footer-edit.php on /edit.php?post_type=product
$ajax = admin_url('admin-ajax.php');
?>
<script>
jQuery(function($){
    const ajaxUrl = <?php echo json_encode($ajax); ?>;

    function toast(msg, isErr){
        try{
            const d = wp?.data?.dispatch('core/notices');
            d?.[isErr ? 'createErrorNotice' : 'createSuccessNotice'](msg, {isDismissible:true});
        }catch(e){}
    }
    function setRowState($label, value){
        $label.attr('data-bol', value);
        $label.find('.bol-sync-toggle').prop('checked', value === 'true');
    }

    // LIST COLUMN: save immediately when clicking the checkbox
    $(document).on('change', '.bol-sync-toggle', function(){
        const $label = $(this).closest('.bol-sync-label');
        const postId = parseInt($label.attr('data-id'), 10);
        const nonce  = $label.attr('data-nonce');
        const value  = $(this).is(':checked') ? 'true' : 'false';
        $(this).prop('disabled', true);

        $.post(ajaxUrl, { action:'bol_sync_toggle', _ajax_nonce:nonce, post_id:postId, value:value })
         .done(function(r){
            if(r && r.success){ setRowState($label, value); toast('Saved', false); }
            else { setRowState($label, (value==='true'?'false':'true')); toast('Error saving. Please retry.', true); }
         })
         .fail(function(){
            setRowState($label, (value==='true'?'false':'true'));
            toast('Error saving. Please retry.', true);
         })
         .always(() => $label.find('.bol-sync-toggle').prop('disabled', false));
    });

    // QUICK EDIT: prefill + instant save on change
    const baseInlineEdit = inlineEditPost.edit;
    inlineEditPost.edit = function( id ) {
        baseInlineEdit.apply(this, arguments);
        let postId = 0;
        if ( typeof id === 'object' ) postId = parseInt(this.getId(id), 10);
        if ( !postId ) return;

        const $row   = $('#post-' + postId);
        const $cell  = $row.find('.column-bol_sync .bol-sync-label');
        const bolVal = $cell.attr('data-bol') || 'false';
        const nonce  = $cell.attr('data-nonce') || '';

        const $edit  = $('#edit-' + postId);
        const $field = $edit.find('input[name="bol_sync"]');

        $field.prop('checked', bolVal === 'true');

        $field.off('change.bolSync').on('change.bolSync', function(){
            const value = $(this).is(':checked') ? 'true' : 'false';
            $.post(ajaxUrl, { action:'bol_sync_toggle', _ajax_nonce:nonce, post_id:postId, value:value })
             .done(function(r){
                if(r && r.success){ setRowState($cell, value); toast('Saved', false); }
                else { toast('Error saving. Please retry.', true); }
             })
             .fail(function(){ toast('Error saving. Please retry.', true); });
        });
    };

    // BULK EDIT: instant apply to selected rows
    $(document).on('change', 'select[name="bol_sync_bulk"]', function(){
        const choice = $(this).val(); // '', 'true', 'false'
        if (choice !== 'true' && choice !== 'false') return;

        const ids = $('#the-list input[name="post[]"]:checked').map(function(){ return parseInt(this.value,10); }).get();
        if (!ids.length){ toast('No products selected.', true); return; }

        // Per-row nonces
        const nonceMap = {};
        ids.forEach(function(id){
            nonceMap[id] = $('#post-' + id + ' .column-bol_sync .bol-sync-label').attr('data-nonce') || '';
        });

        $.post(ajaxUrl, { action:'bol_sync_bulk_toggle', ids:ids, value:choice, nonceMap:nonceMap })
         .done(function(r){
            if(r && r.success){
                ids.forEach(function(id){
                    const $cell = $('#post-' + id + ' .column-bol_sync .bol-sync-label');
                    setRowState($cell, choice);
                });
                toast('Saved', false);
            } else { toast('Error saving. Please retry.', true); }
         })
         .fail(function(){ toast('Error saving. Please retry.', true); });
    });
});
</script>
