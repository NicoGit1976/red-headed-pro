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

        /* Status filter — checkbox grid */
        var statusList = ( p.filters && p.filters.status ) ? ( Array.isArray( p.filters.status ) ? p.filters.status : [ p.filters.status ] ) : [];
        document.querySelectorAll( 'input[name="pl-pf-status[]"]' ).forEach( function ( cb ) {
            cb.checked = statusList.indexOf( cb.value ) !== -1;
        } );

        document.getElementById( 'pl-pf-date-from' ).value = ( p.filters && p.filters.date_from ) || '';
        document.getElementById( 'pl-pf-date-to' ).value   = ( p.filters && p.filters.date_to )   || '';

        /* Columns — rich picker (objects: { key, label }) */
        var cols = Array.isArray( p.columns ) ? p.columns : [];
        renderActiveColumns( cols );

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

    /* ────────── Column picker (v1.2.0) ────────── */
    function renderActiveColumns( colsInput ) {
        var ol = document.getElementById( 'pl-cols-active' );
        if ( ! ol ) return;
        ol.innerHTML = '';

        /* Normalize: input can be a list of strings OR objects {key, label} */
        var cols = ( colsInput || [] ).map( function ( c ) {
            if ( typeof c === 'string' ) {
                return { key: c, label: lookupLabel( c ) };
            }
            return { key: c.key || '', label: c.label || lookupLabel( c.key ) };
        } );

        if ( ! cols.length ) {
            ol.innerHTML = '<li class="pl-cols-empty pl-muted">No columns yet. Tick boxes on the left to add them.</li>';
            updateActiveCount();
            syncCatalogChecks();
            return;
        }

        cols.forEach( function ( c ) {
            ol.appendChild( buildActiveRow( c.key, c.label ) );
        } );
        updateActiveCount();
        syncCatalogChecks();
    }

    function lookupLabel( key ) {
        var row = document.querySelector( '.pl-col-row[data-key="' + cssEscape( key ) + '"]' );
        if ( row ) return row.dataset.label || key;
        if ( key && key.indexOf( 'meta:' ) === 0 ) return key.replace( /^meta:/, 'Meta — ' );
        return key;
    }

    function cssEscape( s ) {
        return ( s || '' ).replace( /["\\]/g, '\\$&' );
    }

    function buildActiveRow( key, label ) {
        var li = document.createElement( 'li' );
        li.className = 'pl-cols-active-row';
        li.draggable = true;
        li.dataset.key = key;
        li.innerHTML =
            '<span class="pl-drag-handle" aria-hidden="true">⋮⋮</span>' +
            '<input type="text" class="pl-col-active-label" value="' + ( label || key ).replace( /"/g, '&quot;' ) + '" />' +
            '<code class="pl-col-active-key">' + key + '</code>' +
            '<button type="button" class="pl-btn pl-btn-sm pl-btn-danger pl-col-rm" aria-label="Remove">×</button>';
        li.querySelector( '.pl-col-rm' ).addEventListener( 'click', function () {
            li.remove();
            updateActiveCount();
            syncCatalogChecks();
        } );
        wireDragRow( li );
        return li;
    }

    function updateActiveCount() {
        var c = document.querySelectorAll( '#pl-cols-active .pl-cols-active-row' ).length;
        var el = document.getElementById( 'pl-cols-count' );
        if ( el ) el.textContent = c;
    }

    function syncCatalogChecks() {
        var activeKeys = Array.from( document.querySelectorAll( '#pl-cols-active .pl-cols-active-row' ) ).map( function ( r ) { return r.dataset.key; } );
        document.querySelectorAll( '.pl-col-row' ).forEach( function ( row ) {
            var cb = row.querySelector( '.pl-col-toggle' );
            if ( cb ) cb.checked = activeKeys.indexOf( row.dataset.key ) !== -1;
        } );
    }

    /* Catalog checkbox → toggle in active list */
    function wireCatalog() {
        document.querySelectorAll( '.pl-col-row' ).forEach( function ( row ) {
            var cb = row.querySelector( '.pl-col-toggle' );
            if ( ! cb ) return;
            cb.addEventListener( 'change', function () {
                var key = row.dataset.key;
                var label = row.dataset.label || key;
                var ol = document.getElementById( 'pl-cols-active' );
                var empty = ol.querySelector( '.pl-cols-empty' );
                if ( empty ) empty.remove();
                if ( cb.checked ) {
                    if ( ! ol.querySelector( '[data-key="' + cssEscape( key ) + '"]' ) ) {
                        ol.appendChild( buildActiveRow( key, label ) );
                    }
                } else {
                    var rmRow = ol.querySelector( '[data-key="' + cssEscape( key ) + '"]' );
                    if ( rmRow ) rmRow.remove();
                    if ( ! ol.querySelector( '.pl-cols-active-row' ) ) {
                        ol.innerHTML = '<li class="pl-cols-empty pl-muted">No columns yet. Tick boxes on the left to add them.</li>';
                    }
                }
                updateActiveCount();
            } );
        } );

        /* Search filter */
        var search = document.getElementById( 'pl-cols-search' );
        if ( search ) {
            search.addEventListener( 'input', function () {
                var q = this.value.trim().toLowerCase();
                document.querySelectorAll( '.pl-col-row' ).forEach( function ( row ) {
                    var hay = ( row.dataset.label + ' ' + row.dataset.key ).toLowerCase();
                    row.style.display = ( ! q || hay.indexOf( q ) !== -1 ) ? '' : 'none';
                } );
                /* Hide empty groups too */
                document.querySelectorAll( '.pl-cols-group' ).forEach( function ( g ) {
                    var visible = g.querySelectorAll( '.pl-col-row[style=""], .pl-col-row:not([style])' ).length;
                    var anyVisible = Array.from( g.querySelectorAll( '.pl-col-row' ) ).some( function ( r ) { return r.style.display !== 'none'; } );
                    g.style.display = anyVisible ? '' : ( q ? 'none' : '' );
                } );
            } );
        }

        /* Defaults / clear */
        var btnDef = document.getElementById( 'pl-cols-defaults' );
        if ( btnDef ) btnDef.addEventListener( 'click', function () {
            renderActiveColumns( [
                'order_id', 'order_number', 'date_created', 'status',
                'billing_first_name', 'billing_last_name', 'billing_email',
                'billing_company', 'billing_country',
                'total', 'currency', 'payment_method',
                'item_count', 'shipping_method'
            ] );
        } );
        var btnClr = document.getElementById( 'pl-cols-clear' );
        if ( btnClr ) btnClr.addEventListener( 'click', function () { renderActiveColumns( [] ); } );

        /* Custom meta column add */
        var btnMeta = document.getElementById( 'pl-meta-add-btn' );
        if ( btnMeta ) btnMeta.addEventListener( 'click', function () {
            var k = ( document.getElementById( 'pl-meta-key' ).value || '' ).trim();
            var lbl = ( document.getElementById( 'pl-meta-label' ).value || '' ).trim();
            if ( ! k ) return;
            var key = 'meta:' + k;
            var ol = document.getElementById( 'pl-cols-active' );
            var empty = ol.querySelector( '.pl-cols-empty' );
            if ( empty ) empty.remove();
            if ( ! ol.querySelector( '[data-key="' + cssEscape( key ) + '"]' ) ) {
                ol.appendChild( buildActiveRow( key, lbl || k ) );
            }
            document.getElementById( 'pl-meta-key' ).value = '';
            document.getElementById( 'pl-meta-label' ).value = '';
            updateActiveCount();
        } );
    }

    /* HTML5 drag-drop reorder */
    function wireDragRow( li ) {
        li.addEventListener( 'dragstart', function ( e ) {
            li.classList.add( 'pl-drag-ghost' );
            try { e.dataTransfer.setData( 'text/plain', li.dataset.key ); } catch ( _ ) {}
            e.dataTransfer.effectAllowed = 'move';
        } );
        li.addEventListener( 'dragend', function () { li.classList.remove( 'pl-drag-ghost' ); } );
        li.addEventListener( 'dragover', function ( e ) { e.preventDefault(); } );
        li.addEventListener( 'drop', function ( e ) {
            e.preventDefault();
            var draggedKey = '';
            try { draggedKey = e.dataTransfer.getData( 'text/plain' ); } catch ( _ ) {}
            if ( ! draggedKey || draggedKey === li.dataset.key ) return;
            var ol = document.getElementById( 'pl-cols-active' );
            var dragged = ol.querySelector( '[data-key="' + cssEscape( draggedKey ) + '"]' );
            if ( dragged ) ol.insertBefore( dragged, li );
        } );
    }

    function readActiveColumns() {
        return Array.from( document.querySelectorAll( '#pl-cols-active .pl-cols-active-row' ) ).map( function ( row ) {
            var keyEl = row.querySelector( '.pl-col-active-key' );
            var lblEl = row.querySelector( '.pl-col-active-label' );
            return {
                key:   keyEl ? keyEl.textContent : row.dataset.key,
                label: lblEl ? lblEl.value : ''
            };
        } );
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
        var statusList = Array.from( document.querySelectorAll( 'input[name="pl-pf-status[]"]:checked' ) ).map( function ( c ) { return c.value; } );
        var profile = {
            id:     parseInt( document.getElementById( 'pl-pf-id' ).value, 10 ) || 0,
            name:   document.getElementById( 'pl-pf-name' ).value,
            format: document.getElementById( 'pl-pf-format' ).value,
            filters: {
                status:    statusList,
                date_from: document.getElementById( 'pl-pf-date-from' ).value,
                date_to:   document.getElementById( 'pl-pf-date-to' ).value
            },
            columns:      readActiveColumns(),
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
                    var msg = '✓ Export complete — job #' + r.data.job_id + ' · ' + ( r.data.records || 0 ) + ' rows.';
                    if ( r.data.warning ) {
                        msg += '\n\n⚠ ' + r.data.warning;
                    }
                    alert( msg );
                    window.location.href = '?page=red-headed-pro-exports';
                } else { alert( ( r && r.data && r.data.message ) || 'Run failed' ); }
            } )
            .fail( function () { alert( 'Network error' ); } );
    }

    /* v1.4.20 — Preview modal helpers */
    function showPreviewModal( title, data ) {
        var existing = document.getElementById( 'pl-preview-modal' );
        if ( existing ) existing.remove();
        var html = '<div id="pl-preview-modal" class="pl-modal-overlay" aria-hidden="false" style="display:flex;">' +
            '<div class="pl-modal" role="dialog" aria-modal="true">' +
                '<div class="pl-modal-head">' +
                    '<h3 style="margin:0;flex:1;">' + title + '</h3>' +
                    '<button type="button" class="pl-modal-close" id="pl-preview-close" aria-label="Close">×</button>' +
                '</div>' +
                '<div class="pl-modal-body" id="pl-preview-body"></div>' +
                '<div class="pl-modal-foot"><button type="button" class="pl-btn pl-btn-primary" id="pl-preview-done">Close</button></div>' +
            '</div></div>';
        document.body.insertAdjacentHTML( 'beforeend', html );
        var body = document.getElementById( 'pl-preview-body' );
        var content = '';
        if ( data.unsupported ) {
            content = '<p class="pl-muted">Format <code>' + data.format + '</code> can\'t be previewed inline. Click Download to inspect.</p>';
        } else if ( ! data.rows || data.rows.length === 0 ) {
            content = '<div class="pl-empty"><div class="pl-empty-icon">⚠</div><p><strong>No matching orders.</strong></p>' +
                '<p class="pl-muted">Check the profile filters: status list, date range, payment method, etc. Make sure your orders fall within the date_from / date_to window and match the selected statuses.</p></div>';
        } else {
            content = '<p class="pl-muted">Showing ' + data.rows.length + ' of ' + ( data.count || data.total || data.rows.length ) + ' matching rows.</p>' +
                '<div style="overflow-x:auto;"><table class="pl-table pl-table-zebra" style="font-size:12px;">' +
                '<thead><tr>' + ( data.columns || [] ).map( function ( c ) { return '<th>' + escHtml( c ) + '</th>'; } ).join( '' ) + '</tr></thead>' +
                '<tbody>' + data.rows.map( function ( row ) {
                    return '<tr>' + ( row || [] ).map( function ( v ) { return '<td>' + escHtml( v == null ? '' : String( v ) ) + '</td>'; } ).join( '' ) + '</tr>';
                } ).join( '' ) + '</tbody></table></div>';
        }
        body.innerHTML = content;
        var close = function () { var m = document.getElementById( 'pl-preview-modal' ); if ( m ) m.remove(); };
        document.getElementById( 'pl-preview-close' ).addEventListener( 'click', close );
        document.getElementById( 'pl-preview-done' ).addEventListener( 'click', close );
        document.getElementById( 'pl-preview-modal' ).addEventListener( 'click', function ( e ) { if ( e.target.id === 'pl-preview-modal' ) close(); } );
    }
    function escHtml( s ) { return String( s ).replace( /[&<>"']/g, function ( c ) { return ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' })[c]; } ); }
    function previewProfile( id ) {
        ajax( 'pelican_preview_profile', { id: id } )
            .done( function ( r ) {
                if ( r && r.success ) showPreviewModal( '👁 Preview profile #' + id, r.data );
                else alert( ( r && r.data && r.data.message ) || 'Preview failed' );
            } );
    }
    function previewJob( id ) {
        ajax( 'pelican_preview_job', { id: id } )
            .done( function ( r ) {
                if ( r && r.success ) showPreviewModal( '👁 Preview export #' + id, r.data );
                else alert( ( r && r.data && r.data.message ) || 'Preview failed' );
            } );
    }

    /* ────────── Boot ──────────
       v1.4.2 — script is enqueued in the footer (in_footer=true), which
       runs AFTER DOMContentLoaded has already fired. Wrapping the boot
       code in a DOMContentLoaded listener silently drops every event
       binding, killing every button on Settings → Profiles. Just call
       the boot directly — by the time the footer parses this, the DOM
       is fully built. */
    function boot() {
        var add  = document.getElementById( 'pl-add-profile' );
        var save = document.getElementById( 'pl-editor-save' );
        var cls1 = document.getElementById( 'pl-editor-close' );
        var cls2 = document.getElementById( 'pl-editor-cancel' );
        var addd = document.getElementById( 'pl-pf-add-dest' );

        /* v1.2.0 — wire the redesigned column picker (catalog checkboxes,
           search, defaults / clear, custom meta add). */
        if ( document.getElementById( 'pl-cols-catalog' ) ) wireCatalog();

        /* v1.4.12 — Field picker modal: open / close / overlay-click / Escape. */
        var modal     = document.getElementById( 'pl-cols-modal' );
        var openBtn   = document.getElementById( 'pl-cols-open-picker' );
        var closeBtn  = document.getElementById( 'pl-cols-modal-close' );
        var doneBtn   = document.getElementById( 'pl-cols-modal-done' );
        var openModal = function () { if ( modal ) modal.setAttribute( 'aria-hidden', 'false' ); };
        var closeModal = function () { if ( modal ) modal.setAttribute( 'aria-hidden', 'true' ); };
        if ( openBtn )  openBtn.addEventListener( 'click', openModal );
        if ( closeBtn ) closeBtn.addEventListener( 'click', closeModal );
        if ( doneBtn )  doneBtn.addEventListener( 'click', closeModal );
        if ( modal ) {
            modal.addEventListener( 'click', function ( e ) { if ( e.target === modal ) closeModal(); } );
            document.addEventListener( 'keydown', function ( e ) {
                if ( e.key === 'Escape' && modal.getAttribute( 'aria-hidden' ) === 'false' ) closeModal();
            } );
        }

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
        /* v1.4.20 — Preview profile (dry-run) + Preview job (read first 10 rows of file). */
        document.querySelectorAll( '.pl-btn-preview-profile' ).forEach( function ( b ) {
            b.addEventListener( 'click', function () { previewProfile( parseInt( this.dataset.id, 10 ) ); } );
        } );
        document.querySelectorAll( '.pl-btn-preview' ).forEach( function ( b ) {
            b.addEventListener( 'click', function () { previewJob( parseInt( this.dataset.job, 10 ) ); } );
        } );
    }
    if ( document.readyState === 'loading' ) {
        document.addEventListener( 'DOMContentLoaded', boot );
    } else {
        boot();
    }

} )( jQuery );
