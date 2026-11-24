jQuery(function ($) {

    // ── Uitgelicht toggle ───────────────────────────────────────────────────

    $(document).on('change', '.sta-featured-toggle', function () {
        var $cb  = $(this);
        var id   = $cb.data('id');
        var val  = $cb.is(':checked') ? 1 : 0;
        var $row = $('#sta-row-' + id);

        $cb.prop('disabled', true);

        $.post(STA.ajax_url, {
            action: 'sta_toggle_featured', nonce: STA.nonce, post_id: id, featured: val,
        }).done(function (res) {
            if (res.success) {
                $row.toggleClass('sta-is-featured', res.data.featured);
            } else {
                $cb.prop('checked', !val);
            }
        }).fail(function () {
            $cb.prop('checked', !val);
        }).always(function () {
            $cb.prop('disabled', false);
        });
    });

    // ── Import (CSV of PDF, async) ──────────────────────────────────────────

    function startImport($form, $btn, $result, fileField, fileInputId) {
        var file = $(fileInputId)[0].files[0];
        if (!file) { alert('Selecteer eerst een bestand.'); return; }

        var fd = new FormData();
        fd.append('action', 'sta_import_pdf');
        fd.append('nonce',  STA.nonce);
        fd.append(fileField, file);

        $btn.prop('disabled', true).text('Uploaden...');
        $result.hide();

        $.ajax({
            url: STA.ajax_url, type: 'POST', data: fd,
            processData: false, contentType: false,
        }).done(function (res) {
            if (res.success && res.data.job_id) {
                $btn.text('Verwerken...');
                $result.html(
                    '<div class="notice notice-info" id="sta-job-notice-' + fileField + '">' +
                    '<p><strong>Bestand geüpload.</strong> Verwerking loopt op de achtergrond...</p>' +
                    '<div class="sta-progress-bar" style="max-width:420px;margin-top:8px;"><div id="sta-job-bar-' + fileField + '" style="width:5%"></div></div>' +
                    '<p id="sta-job-msg-' + fileField + '" style="margin-top:6px;font-style:italic;color:#666;">Wachten op server...</p>' +
                    '</div>'
                ).show();
                pollImport(res.data.job_id, fileField, $btn, $result);
            } else {
                $btn.prop('disabled', false).text('Importeren');
                $result.html('<div class="notice notice-error"><p><strong>Fout:</strong> ' + $('<div>').text(res.data || 'Onbekend').html() + '</p></div>').show();
            }
        }).fail(function (xhr) {
            $btn.prop('disabled', false).text('Importeren');
            var detail = xhr.responseText
                ? '<pre style="max-height:150px;overflow:auto;font-size:11px;">' + $('<div>').text(xhr.responseText.substring(0, 2000)).html() + '</pre>'
                : '';
            $result.html('<div class="notice notice-error"><p><strong>Serverfout (HTTP ' + xhr.status + ').</strong>' + detail + '</p></div>').show();
        });
    }

    $('#sta-import-form').on('submit', function (e) {
        e.preventDefault();
        startImport($(this), $('#sta-import-btn'), $('#sta-import-result'), 'csv_file', '#csv_file');
    });

    $('#sta-import-form-pdf').on('submit', function (e) {
        e.preventDefault();
        startImport($(this), $('#sta-import-btn-pdf'), $('#sta-import-result-pdf'), 'pdf_file', '#pdf_file');
    });

    function pollImport(jobId, fileField, $btn, $result) {
        setTimeout(function () {
            $.post(STA.ajax_url, { action: 'sta_import_status', nonce: STA.nonce, job_id: jobId })
            .done(function (res) {
                if (!res.success) { importDone(null, res.data || 'Polling mislukt', fileField, $btn, $result); return; }
                var d = res.data;
                $('#sta-job-bar-' + fileField).css('width', (d.progress || 0) + '%');
                $('#sta-job-msg-' + fileField).text(d.message || '');
                if      (d.status === 'complete') { importDone(d.result, null, fileField, $btn, $result); }
                else if (d.status === 'error')    { importDone(null, d.error || 'Onbekende fout', fileField, $btn, $result); }
                else                              { pollImport(jobId, fileField, $btn, $result); }
            })
            .fail(function () { pollImport(jobId, fileField, $btn, $result); });
        }, 3000);
    }

    function importDone(result, error, fileField, $btn, $result) {
        $btn.prop('disabled', false).text('Importeren');

        var noticeId = '#sta-job-notice-' + fileField;

        if (error) {
            $(noticeId).replaceWith(
                '<div class="notice notice-error"><p><strong>Import mislukt:</strong> ' + $('<div>').text(error).html() + '</p></div>'
            );
            return;
        }

        var enrich = STA.ajax_url.replace('admin-ajax.php', 'admin.php?page=swimming-trials-enrich');
        $(noticeId).replaceWith(
            '<div class="notice notice-success"><p>' +
            '<strong>Import geslaagd!</strong><br>' +
            'Aangemaakt: ' + result.created.length + ' | Bijgewerkt: ' + result.updated.length + ' | Overgeslagen: ' + result.skipped.length +
            '</p><p><a href="' + enrich + '" class="button button-primary">Ga naar verrijken →</a></p></div>'
        );
    }

    // ── Enrich single athlete ────────────────────────────────────────────────

    function enrichAthlete(postId, $row) {
        var $btn    = $row.find('.sta-enrich-btn');
        var $status = $row.find('.sta-row-status');

        var doPhoto = $('#sta-opt-photo').length ? $('#sta-opt-photo').is(':checked') : true;
        var doBio   = $('#sta-opt-bio').length   ? $('#sta-opt-bio').is(':checked')   : true;

        $btn.prop('disabled', true).text('Bezig...');
        $status.text('');

        return $.post(STA.ajax_url, {
            action: 'sta_enrich_athlete', nonce: STA.nonce, post_id: postId,
            skip_photo: doPhoto ? '' : '1',
            skip_bio:   doBio   ? '' : '1',
        }).done(function (res) {
            if (res.success) {
                var d = res.data;

                if (d.photo_saved) {
                    var thumbHtml = d.photo_thumb
                        ? '<img src="' + d.photo_thumb + '" style="width:40px;height:40px;object-fit:cover;border-radius:3px;vertical-align:middle;margin-right:4px;">'
                        : '';
                    var pickerHtml = '';
                    if (d.photo_candidates && d.photo_candidates.length > 1) {
                        pickerHtml = '<div class="sta-photo-picker" data-id="' + postId + '" style="margin-top:4px;display:flex;gap:3px;flex-wrap:wrap;">';
                        d.photo_candidates.forEach(function(c, i) {
                            var border = i === 0 ? 'border:2px solid #2271b1;' : 'border:2px solid transparent;';
                            pickerHtml += '<img src="' + c.thumb + '" title="' + $('<div>').text(c.title).html() + '" data-url="' + c.url + '" data-id="' + postId + '" style="width:72px;height:72px;object-fit:cover;border-radius:4px;cursor:pointer;' + border + '" class="sta-candidate-thumb">';
                        });
                        pickerHtml += '</div>';
                    }
                    $row.find('.sta-status-foto').html('<span class="sta-ok">✓</span> ' + thumbHtml + pickerHtml);
                } else if (d.photo_error) {
                    var debugLines = (d.photo_debug || []).join('\n');
                    var tooltip    = d.photo_error + (debugLines ? '\n\nFlickr log:\n' + debugLines : '');
                    $row.find('.sta-status-foto').html(
                        '<span class="sta-error" title="' + $('<div>').text(tooltip).html() + '">✗ Geen foto</span>'
                    );
                }

                if (d.bio_saved) {
                    $row.find('.sta-status-bio').html('<span class="sta-ok">✓ Bio</span>');
                } else if (d.bio_error) {
                    $row.find('.sta-status-bio').html(
                        '<span class="sta-error" title="' + $('<div>').text(d.bio_error).html() + '">✗ Geen bio</span>'
                    );
                }

                var photoOk = d.photo_saved || !doPhoto;
                var bioOk   = d.bio_saved   || !doBio;
                var allOk   = photoOk && bioOk;
                var noneOk  = !d.photo_saved && !d.bio_saved && (doPhoto || doBio) && (d.photo_error || d.bio_error);
                $status.html(
                    allOk  ? '<span class="sta-ok">✓ Klaar</span>' :
                    noneOk ? '<span class="sta-error">✗ Mislukt</span>' :
                             '<span style="color:#8a6d00;">⚠ Gedeeltelijk</span>'
                );
            } else {
                $status.html('<span class="sta-error" title="' + $('<div>').text(res.data).html() + '">✗ Fout</span>');
            }
        }).fail(function () {
            $status.html('<span class="sta-error">✗ Verbindingsfout</span>');
        }).always(function () {
            $btn.prop('disabled', false).text('Verrijk');
        });
    }

    // Foto-kandidaat kiezer
    $(document).on('click', '.sta-candidate-thumb', function () {
        var $thumb   = $(this);
        var postId   = $thumb.data('id');
        var photoUrl = $thumb.data('url');
        var $picker  = $thumb.closest('.sta-photo-picker');

        $picker.find('.sta-candidate-thumb').css('border-color', 'transparent');
        $thumb.css('border-color', '#aaa');

        $.post(STA.ajax_url, {
            action: 'sta_switch_photo', nonce: STA.nonce,
            post_id: postId, photo_url: photoUrl,
        }).done(function (res) {
            if (res.success) {
                $thumb.css('border-color', '#2271b1');
                // Update de grote thumbnail links van de kiezer
                var $cell = $thumb.closest('.sta-status-foto');
                $cell.find('img:first').attr('src', res.data.thumb + '?' + Date.now());
            } else {
                alert('Foto wisselen mislukt: ' + res.data);
                $thumb.css('border-color', 'transparent');
            }
        }).fail(function () {
            alert('Verbindingsfout bij foto wisselen.');
            $thumb.css('border-color', 'transparent');
        });
    });

    // Context modal
    $('body').append(
        '<div id="sta-context-modal" style="display:none;position:fixed;inset:0;z-index:99999;background:rgba(0,0,0,.6);">' +
        '<div style="background:#fff;margin:40px auto;max-width:700px;max-height:80vh;overflow-y:auto;border-radius:6px;padding:24px;position:relative;">' +
        '<button id="sta-context-close" style="position:absolute;top:12px;right:16px;background:none;border:none;font-size:22px;cursor:pointer;">×</button>' +
        '<h2 id="sta-context-title" style="margin-top:0;font-size:16px;"></h2>' +
        '<pre id="sta-context-body" style="font-size:12px;white-space:pre-wrap;word-break:break-word;background:#f6f7f7;padding:12px;border-radius:4px;"></pre>' +
        '</div></div>'
    );

    $('#sta-context-close').on('click', function () { $('#sta-context-modal').hide(); });
    $(document).on('keydown', function (e) { if (e.key === 'Escape') $('#sta-context-modal').hide(); });

    $(document).on('click', '.sta-context-btn', function () {
        var id   = $(this).data('id');
        var $btn = $(this).prop('disabled', true).text('...');

        $.post(STA.ajax_url, { action: 'sta_get_context', nonce: STA.nonce, post_id: id })
        .done(function (res) {
            if (res.success) {
                $('#sta-context-title').text('Serper context voor: ' + res.data.name);
                $('#sta-context-body').text(res.data.context);
                $('#sta-context-modal').show();
            } else {
                alert('Fout: ' + res.data);
            }
        })
        .fail(function () { alert('Verbindingsfout bij ophalen context.'); })
        .always(function () { $btn.prop('disabled', false).text('Context'); });
    });

    // Enkele atleet
    $(document).on('click', '.sta-enrich-btn', function () {
        var id = $(this).data('id');
        enrichAthlete(id, $('#sta-row-' + id));
    });

    // ── Selectie ────────────────────────────────────────────────────────────

    function getSelectedIds() {
        return $('.sta-select-row:checked').map(function () { return $(this).data('id'); }).get();
    }

    function updateSelectionUI() {
        var count = getSelectedIds().length;
        $('#sta-selected-count').text(count);
        $('#sta-enrich-selected-btn').prop('disabled', count === 0);
        $('#sta-publish-selected-btn').prop('disabled', count === 0);
        var total = $('.sta-select-row').length;
        $('#sta-select-all').prop('indeterminate', count > 0 && count < total)
                            .prop('checked', count === total && total > 0);
    }

    var lastChecked = null;

    $(document).on('click', '.sta-select-row', function (e) {
        if ( e.shiftKey && lastChecked ) {
            var $boxes   = $('.sta-select-row');
            var start    = $boxes.index(lastChecked);
            var end      = $boxes.index(this);
            var checked  = $(this).is(':checked');
            $boxes.slice(Math.min(start, end), Math.max(start, end) + 1).prop('checked', checked);
        }
        lastChecked = this;
        updateSelectionUI();
    });

    $('#sta-select-all').on('change', function () {
        $('.sta-select-row').prop('checked', $(this).is(':checked'));
        lastChecked = null;
        updateSelectionUI();
    });

    // Batch runner
    function runBatch(ids, $btn, label) {
        var total = ids.length, current = 0;
        $('#sta-batch-progress').show();
        $('#sta-progress-total').text(total);
        $('#sta-progress-current').text(0);
        $('#sta-progress-fill').css('width', '0%');
        $btn.prop('disabled', true);

        function next() {
            if (!ids.length) { $btn.prop('disabled', false).text(label + ' ✓ Klaar'); return; }
            var id = ids.shift();
            current++;
            $('#sta-progress-current').text(current);
            $('#sta-progress-fill').css('width', Math.round((current / total) * 100) + '%');
            enrichAthlete(id, $('#sta-row-' + id)).always(function () { setTimeout(next, 1200); });
        }
        next();
    }

    $('#sta-enrich-selected-btn').on('click', function () {
        var ids = getSelectedIds();
        if (!ids.length) return;
        if (!confirm('Verrijk ' + ids.length + ' geselecteerde atleet(en)?')) return;
        runBatch(ids.slice(), $(this), 'Verrijk selectie');
    });

    $('#sta-enrich-all-btn').on('click', function () {
        if (!window._staAthleteIds || !window._staAthleteIds.length) return;
        if (!confirm('Alle ' + window._staAthleteIds.length + ' atleten verrijken? Dit kan lang duren.')) return;
        runBatch(window._staAthleteIds.slice(), $(this), 'Alles verrijken');
    });

    // ── Publiceren ──────────────────────────────────────────────────────────

    function publishAthlete(postId) {
        return $.post(STA.ajax_url, { action: 'sta_publish_athlete', nonce: STA.nonce, post_id: postId })
        .done(function (res) {
            if (res.success) {
                var $row = $('#sta-row-' + postId);
                $row.attr('data-status', 'publish').addClass('sta-is-published');
                $row.find('.sta-status-publish').html('<span class="sta-ok">✓ Gepubliceerd</span>');
                $row.find('.sta-publish-btn').hide();
            }
        });
    }

    $(document).on('click', '.sta-publish-btn', function () {
        var id = $(this).data('id');
        $(this).prop('disabled', true).text('...');
        publishAthlete(id).always(function () {
            $('#sta-row-' + id + ' .sta-publish-btn').prop('disabled', false).text('Publiceer');
        });
    });

    $('#sta-publish-selected-btn').on('click', function () {
        var ids = getSelectedIds();
        if (!ids.length) return;
        if (!confirm('Publiceer ' + ids.length + ' geselecteerde atleet(en)?')) return;
        var $btn = $(this).prop('disabled', true);
        function next() {
            if (!ids.length) { $btn.prop('disabled', false); updateSelectionUI(); return; }
            publishAthlete(ids.shift()).always(function () { setTimeout(next, 200); });
        }
        next();
    });

    $('#sta-publish-all-btn').on('click', function () {
        var ids = (window._staAthleteIds || []).slice();
        if (!ids.length) return;
        if (!confirm('Alle ' + ids.length + ' atleten publiceren?')) return;
        var $btn = $(this).prop('disabled', true);
        function next() {
            if (!ids.length) { $btn.prop('disabled', false); return; }
            publishAthlete(ids.shift()).always(function () { setTimeout(next, 200); });
        }
        next();
    });

});
