/* Auto SEO for Yoast — Admin JS v1.2.0 */
(function ($) {
    'use strict';

    // ── Placeholder copy/insert ───────────────────────────────────────────────
    $(document).on('click', '.asy-placeholder-btn', function () {
        var $btn = $(this), tag = $btn.data('tag');
        var $focused = $('input.asy-tpl-input:focus');
        if ($focused.length) { insertAtCursor($focused[0], tag); return; }
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(tag).then(function () { flashCopied($btn); });
        } else {
            var $t = $('<input>').val(tag).appendTo('body').select();
            document.execCommand('copy'); $t.remove(); flashCopied($btn);
        }
    });

    function flashCopied($btn) {
        var orig = $btn.html();
        $btn.addClass('asy-copied').html('<code>✓ copied</code>');
        setTimeout(function () { $btn.removeClass('asy-copied').html(orig); }, 1400);
    }

    function insertAtCursor(input, text) {
        var s = input.selectionStart, e = input.selectionEnd, v = input.value;
        input.value = v.substring(0, s) + text + v.substring(e);
        input.selectionStart = input.selectionEnd = s + text.length;
        input.focus();
    }

    // ── Row active state ──────────────────────────────────────────────────────
    $(document).on('change', '.asy-enable-cb', function () {
        $(this).closest('.asy-row').toggleClass('asy-row--active', $(this).is(':checked'));
    });

    // ── Save all settings ─────────────────────────────────────────────────────
    $('#asy-save-btn').on('click', function () {
        var $btn = $(this);
        $btn.prop('disabled', true).text('Saving…');

        var templates = {};
        $('.asy-row').each(function () {
            var pt = $(this).data('pt');
            templates[pt] = {
                enabled:             $(this).find('.asy-enable-cb').is(':checked')                    ? '1' : '0',
                template:            $(this).find('.asy-tpl-input').val(),
                set_keyphrase:       $(this).find('input[name*="set_keyphrase"]').is(':checked')      ? '1' : '0',
                slug_keyphrase:      $(this).find('input[name*="slug_keyphrase"]').is(':checked')     ? '1' : '0',
                top_word_keyphrase:  $(this).find('input[name*="top_word_keyphrase"]').is(':checked') ? '1' : '0',
                ai_keyphrases:       $(this).find('input[name*="ai_keyphrases"]').is(':checked')      ? '1' : '0',
            };
        });

        $.post(ASY.ajax_url, {
            action:    'asy_save_templates',
            nonce:     ASY.nonce,
            templates: templates,
            kp_count:  $('#asy_kp_count').val(),
        })
        .done(function (r) { showNotice(r.success ? ASY.saved : ASY.error, r.success ? 'success' : 'error'); })
        .fail(function ()  { showNotice(ASY.error, 'error'); })
        .always(function () { $btn.prop('disabled', false).text('Save Settings'); });
    });

    // ── Reprocess a single post (synchronous — fast, no polling needed) ───────
    $('#asy-reprocess-btn').on('click', function () {
        var $btn    = $(this);
        var $result = $('#asy-reprocess-result');
        var postId  = parseInt($('#asy_reprocess_id').val(), 10);

        if (!postId || postId < 1) {
            setResult($result, 'error', 'Please enter a valid post ID.');
            return;
        }

        $btn.prop('disabled', true).text('Generating…');
        $result.hide();

        $.post(ASY.ajax_url, {
            action:  'asy_reprocess_post',
            nonce:   ASY.nonce,
            post_id: postId,
        })
        .done(function (r) {
            if (r.success && r.data && r.data.keyphrases) {
                var pills = r.data.keyphrases.map(function (kp) {
                    return '<span class="asy-kp-pill">' + escHtml(kp) + '</span>';
                }).join('');
                setResult($result, 'ok',
                    '✓ Saved to post ' + postId + '. <strong>Reload the post editor</strong> to see them in Yoast.<br>' + pills
                );
            } else {
                var msg = (r.data && typeof r.data === 'string') ? r.data : ASY.error;
                setResult($result, 'error', 'Error: ' + msg);
            }
        })
        .fail(function () {
            setResult($result, 'error', ASY.error);
        })
        .always(function () { $btn.prop('disabled', false).text('Generate Keyphrases'); });
    });

    // ── Helpers ───────────────────────────────────────────────────────────────

    function setResult($el, type, html) {
        $el.removeClass('asy-reprocess-result--ok asy-reprocess-result--err asy-reprocess-result--info')
           .addClass('asy-reprocess-result--' + type)
           .html(html)
           .show();
    }

    function escHtml(str) {
        return $('<span>').text(str).html();
    }

    function showNotice(msg, type) {
        $('#asy-notice').removeClass('asy-notice--success asy-notice--error')
            .addClass('asy-notice--' + type).text(msg).fadeIn(200);
        setTimeout(function () { $('#asy-notice').fadeOut(400); }, 3500);
    }

}(jQuery));
