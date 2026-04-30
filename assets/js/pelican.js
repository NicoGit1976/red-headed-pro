/**
 * Harlequin — admin JS (profile editor + actions).
 *
 * Uses the suite-wide pattern: lightweight jQuery for AJAX, vanilla DOM for the rest.
 */
( function ( $ ) {
    'use strict';

    var PD = window.PelicanData || {};
    var ed = document.getElementById( 'pl-profile-editor' );

    function ajax( action, data ) {
        return $.post( PD.ajaxurl, $.extend( { action: action, nonce: PD.nonce }, data || {} ) );
    }

    /* ────────── Editor open/close ────────── */
    function openEditor( profile ) {
        if ( ! ed ) return;
        ed.hidden = false;
        var p = profile || {};
        document.getElementById( 'pl-pf-id' ).value      = p.id || '';
        document.getElementById( 'pl-pf-name' ).value    = p.name || '';
        document.getElementById( 'pl-pf-format' ).value  = p.format || 'csv';
        document.getElementById( 'pl-pf-statuses' ).value  = ( p.filters && p.filters.status ) ? ( Array.isArray( p.filters.status ) ? p.filters.status.join( ', ' ) : p.filters.status ) : '';
        document.getElementById( 'pl-pf-date-from' ).value = ( p.filters && p.filters.date_from ) || '';
        document.getElementById( 'pl-pf-date-to' ).value   = ( p.filters && p.filters.date_to )   || '';
        document.getElementById( 'pl-pf-columns' ).value   = Array.isArray( p.columns ) ? p.columns.join( ', ' ) : '';

        if ( document.getElementById( 'pl-pf-schedule' ) ) document.getElementById( 'pl-pf-schedule' ).value = p.schedule || 'manual';
        if ( document.getElementById( 'pl-pf-auto-status' ) ) {
            var at = p.auto_trigger || {};
            document.getElementById( 'pl-pf-auto-status' ).value   = Array.isArray( at.on_status ) ? at.on_status.join( ', ' ) : ( at.on_status || '' );
            document.getElementById( 'pl-pf-auto-mintotal' ).value = at.min_total || '';
            document.getElementById( 'pl-pf-auto-fireonce' ).checked = !! at.fire_once;
        }

        renderDestinations( p.destinations || [] );
        document.getElementById( 'pl-editor-title' ).textContent = p.id ? ( 'Edit profile · ' + ( p.name || '' ) ) : 'New profile';
    }

    function closeEditor() { if ( ed ) ed.hidden = true; }

    /* ────────── Destinations rows ────────── */
    function renderDestinations( dests ) {
        var box = document.getElementById( 'pl-pf-destinations' );
        if ( ! box ) return;
        box.innerHTML = '';
        dests.forEach( function ( d, i ) { box.appendChild( buildDestRow( d, i ) ); } );
    }

    function buildDestRow( d, i ) {
        var wrap = document.createElement( 'div' );
        wrap.className = 'pl-card';
        wrap.dataset.idx = i;
        var t = d.type || 'email';
        wrap.innerHTML =
            '<div style="display:flex;gap:8px;align-items:center;margin-bottom:8px">' +
                '<select class="pl-dest-type">' +
                    '<option value="email">✉️ Email</option>' +
                    '<option value="sftp">📡 SFTP</option>' +
                    '<option value="gdrive">📁 Google Drive 🔒Pro</option>' +
                    '<option value="rest">🔗 REST 🔒Pro</option>' +
                    '<option value="local_zip">🗜 Local ZIP 🔒Pro</option>' +
                    '<option value="download">⬇ Download 🔒Pro</option>' +
                '</select>' +
                '<button type="button" class="pl-btn pl-btn-sm pl-btn-danger pl-dest-rm">×</button>' +
            '</div>' +
            '<div class="pl-dest-fields"></div>';
        wrap.querySelector( '.pl-dest-type' ).value = t;
        wrap.querySelector( '.pl-dest-type' ).addEventListener( 'change', function () { renderDestFields( wrap, this.value, {} ); } );
        wrap.querySelector( '.pl-dest-rm' ).addEventListener( 'click', function () { wrap.remove(); } );
        renderDestFields( wrap, t, d );
        return wrap;
    }

    function renderDestFields( wrap, type, d ) {
        var box = wrap.querySelector( '.pl-dest-fields' );
        d = d || {};
        if ( type === 'email' ) {
            box.innerHTML =
                '<input type="email" class="pl-dest-to" placeholder="recipient@example.com" value="' + ( d.to || '' ) + '" />';
        } else if ( type === 'sftp' ) {
            box.innerHTML =
                '<input type="text" class="pl-dest-host" placeholder="host" value="' + ( d.host || '' ) + '" />' +
                '<input type="number" class="pl-dest-port" placeholder="22" value="' + ( d.port || 22 ) + '" />' +
                '<input type="text" class="pl-dest-user" placeholder="user" value="' + ( d.user || '' ) + '" />' +
                '<input type="password" class="pl-dest-pass" placeholder="password" autocomplete="new-password" />' +
                '<input type="text" class="pl-dest-path" placeholder="/incoming/" value="' + ( d.path || '/' ) + '" />';
        } else if ( type === 'rest' ) {
            box.innerHTML =
                '<input type="url" class="pl-dest-url" placeholder="https://api.example.com/orders" value="' + ( d.url || '' ) + '" />' +
                '<select class="pl-dest-auth"><option value="bearer">Bearer</option><option value="basic">Basic</option><option value="header">Custom header</option></select>' +
                '<input type="text" class="pl-dest-token" placeholder="token / user:pass / header value" />';
        } else {
            box.innerHTML = '<p class="pl-muted">' + type + ' — configured in next step (Pro).</p>';
        }
    }

    function readDestinations() {
        var rows = document.querySelectorAll( '#pl-pf-destinations .pl-card' );
        var out  = [];
        rows.forEach( function ( wrap ) {
            var type = wrap.querySelector( '.pl-dest-type' ).value;
            var d = { type: type };
            if ( type === 'email' )   d.to = wrap.querySelector( '.pl-dest-to' ).value;
            if ( type === 'sftp' ) {
                d.host = wrap.querySelector( '.pl-dest-host' ).value;
                d.port = parseInt( wrap.querySelector( '.pl-dest-port' ).value, 10 ) || 22;
                d.user = wrap.querySelector( '.pl-dest-user' ).value;
                var pw = wrap.querySelector( '.pl-dest-pass' ).value;
                if ( pw ) d.pass = pw;
                d.path = wrap.querySelector( '.pl-dest-path' ).value;
            }
            if ( type === 'rest' ) {
                d.url   = wrap.querySelector( '.pl-dest-url' ).value;
                d.auth  = wrap.querySelector( '.pl-dest-auth' ).value;
                d.token = wrap.querySelector( '.pl-dest-token' ).value;
            }
            out.push( d );
        } );
        return out;
    }

    /* ────────── Save / delete / run ────────── */
    function saveProfile() {
        var commaList = function ( s ) { return ( s || '' ).split( ',' ).map( function ( x ) { return x.trim(); } ).filter( Boolean ); };
        var profile = {
            id:     parseInt( document.getElementById( 'pl-pf-id' ).value, 10 ) || 0,
            name:   document.getElementById( 'pl-pf-name' ).value,
            format: document.getElementById( 'pl-pf-format' ).value,
            filters: {
                status:    commaList( document.getElementById( 'pl-pf-statuses' ).value ),
                date_from: document.getElementById( 'pl-pf-date-from' ).value,
                date_to:   document.getElementById( 'pl-pf-date-to' ).value
            },
            columns:      commaList( document.getElementById( 'pl-pf-columns' ).value ),
            destinations: readDestinations()
        };
        if ( document.getElementById( 'pl-pf-schedule' ) ) profile.schedule = document.getElementById( 'pl-pf-schedule' ).value;
        if ( document.getElementById( 'pl-pf-auto-status' ) ) {
            profile.auto_trigger = {
                on_status: commaList( document.getElementById( 'pl-pf-auto-status' ).value ),
                min_total: parseFloat( document.getElementById( 'pl-pf-auto-mintotal' ).value ) || 0,
                fire_once: !! document.getElementById( 'pl-pf-auto-fireonce' ).checked
            };
        }

        ajax( 'pelican_save_profile', { profile: JSON.stringify( profile ) } )
            .done( function ( r ) {
                if ( r && r.success ) { window.location.reload(); }
                else { alert( ( r && r.data && r.data.message ) || 'Save failed' ); }
            } )
            .fail( function () { alert( 'Network error' ); } );
    }

    function deleteProfile( id ) {
        if ( ! window.confirm( 'Delete this profile? Past export logs will be kept.' ) ) return;
        ajax( 'pelican_delete_profile', { id: id } ).done( function () { window.location.reload(); } );
    }

    function runProfile( id ) {
        ajax( 'pelican_run_profile', { id: id } )
            .done( function ( r ) {
                if ( r && r.success ) {
                    alert( '✓ Export started — job #' + r.data.job_id );
                    window.location.href = '?page=pelican-exports';
                } else { alert( ( r && r.data && r.data.message ) || 'Run failed' ); }
            } )
            .fail( function () { alert( 'Network error' ); } );
    }

    /* ────────── Boot ────────── */
    document.addEventListener( 'DOMContentLoaded', function () {
        var add  = document.getElementById( 'pl-add-profile' );
        var save = document.getElementById( 'pl-editor-save' );
        var cls1 = document.getElementById( 'pl-editor-close' );
        var cls2 = document.getElementById( 'pl-editor-cancel' );
        var addd = document.getElementById( 'pl-pf-add-dest' );

        if ( add )  add.addEventListener( 'click', function () { openEditor( null ); } );
        if ( save ) save.addEventListener( 'click', saveProfile );
        if ( cls1 ) cls1.addEventListener( 'click', closeEditor );
        if ( cls2 ) cls2.addEventListener( 'click', closeEditor );
        if ( addd ) addd.addEventListener( 'click', function () {
            var box = document.getElementById( 'pl-pf-destinations' );
            box.appendChild( buildDestRow( { type: 'email' }, box.children.length ) );
        } );

        document.querySelectorAll( '.pl-btn-edit' ).forEach( function ( b ) {
            b.addEventListener( 'click', function () {
                var id = parseInt( this.dataset.id, 10 );
                /* Read profile via REST to keep payload fresh */
                $.ajax( {
                    url: PD.restUrl + 'pelican/v1/profiles/' + id,
                    headers: { 'X-WP-Nonce': PD.restNonce }
                } ).done( function ( p ) { openEditor( p ); } );
            } );
        } );
        document.querySelectorAll( '.pl-btn-del' ).forEach( function ( b ) {
            b.addEventListener( 'click', function () { deleteProfile( parseInt( this.dataset.id, 10 ) ); } );
        } );
        document.querySelectorAll( '.pl-btn-run, .pl-btn-rerun' ).forEach( function ( b ) {
            b.addEventListener( 'click', function () { runProfile( parseInt( this.dataset.id || this.dataset.profile, 10 ) ); } );
        } );
    } );

} )( jQuery );
