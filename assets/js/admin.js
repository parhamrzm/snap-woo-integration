jQuery( function( $ ) {

    /* ---------- دکمه همگام‌سازی محصول ---------- */
    $( document ).on( 'click', '.snapwoo-sync-btn', function( e ) {
        e.preventDefault();

        var $btn      = $( this );
        var productId = $btn.data( 'product-id' );
        var $result   = $btn.siblings( '.snapwoo-result' );

        $btn.prop( 'disabled', true ).text( snapwoo_ajax.strings.syncing );
        $result.removeClass( 'snapwoo-result-success snapwoo-result-error' ).html( '' );

        $.post( snapwoo_ajax.ajax_url, {
            action:     'snapwoo_sync_single',
            nonce:      snapwoo_ajax.nonce,
            product_id: productId
        } )
        .done( function( response ) {
            if ( response.success ) {
                $result
                    .addClass( 'snapwoo-result-success' )
                    .html( '✓ ' + response.data.message );
            } else {
                $result
                    .addClass( 'snapwoo-result-error' )
                    .html( '✗ ' + snapwoo_ajax.strings.error + response.data.message );
            }
        } )
        .fail( function() {
            $result
                .addClass( 'snapwoo-result-error' )
                .html( '✗ ' + snapwoo_ajax.strings.error + 'خطای شبکه' );
        } )
        .always( function() {
            $btn.prop( 'disabled', false ).text( 'همگام‌سازی این محصول' );
        } );
    } );

    /* ---------- افزودن ردیف قانون دسته‌بندی ---------- */
    var ruleCounter = 1000;

    $( document ).on( 'click', '.snapwoo-add-rule', function( e ) {
        e.preventDefault();

        var template = $( '#snapwoo-rule-template' ).html();
        if ( ! template ) {
            return;
        }

        var index = 'n' + ( ruleCounter++ );
        var html  = template.replace( /__INDEX__/g, index );

        var $tbody = $( '.snapwoo-rules-table tbody' );
        $tbody.find( '.snapwoo-empty-row' ).remove();
        $tbody.append( html );
    } );

    /* ---------- حذف ردیف قانون دسته‌بندی ---------- */
    $( document ).on( 'click', '.snapwoo-remove-rule', function( e ) {
        e.preventDefault();
        $( this ).closest( 'tr' ).remove();

        if ( $( '.snapwoo-rules-table tbody tr' ).length === 0 ) {
            $( '.snapwoo-rules-table tbody' ).append(
                '<tr class="snapwoo-empty-row"><td colspan="4">هنوز قانونی تعریف نشده. با دکمه زیر اضافه کنید.</td></tr>'
            );
        }
    } );

} );