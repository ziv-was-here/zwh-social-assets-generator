(function ($) {
  'use strict';

  var assets       = window.sagSavedAssets || null;
  var imagePayload = null;

  $(document).ready(function () {
    if (assets) renderAll(assets);

    // -----------------------------------------------------------------------
    // Image format picker
    // -----------------------------------------------------------------------
    $(document).on('click', '.sag-format-btn', function () {
      $('.sag-format-btn').removeClass('active');
      $(this).addClass('active');
    });

    // -----------------------------------------------------------------------
    // Generate text assets
    // -----------------------------------------------------------------------
    $('#sag-generate-btn').on('click', function () {
      setStatus('text', 'loading', 'Generating social assets… this may take up to 60 seconds.');
      $.post(sagData.ajaxUrl, {
        action:  'sag_generate',
        nonce:   sagData.nonce,
        post_id: sagData.postId,
        tone:    $('#sag-tone').val()
      })
      .done(function (res) {
        if (!res.success) { setStatus('text', 'error', res.data || 'Something went wrong.'); return; }
        assets = res.data;
        renderAll(assets);
        setStatus('text', 'success', 'Done! Select a title and subject, then save.');
        $('#sag-results').removeAttr('hidden');
        updateSaveBtn();
      })
      .fail(function (xhr) {
        var msg = xhr.status === 500
          ? 'Server error (500). Check your API key and PHP error log.'
          : 'Request failed. Check your connection.';
        setStatus('text', 'error', msg);
      });
    });

    // -----------------------------------------------------------------------
    // Generate image
    // -----------------------------------------------------------------------
    $('#sag-image-btn').on('click', function () {
      var format   = $('.sag-format-btn.active').data('format') || 'banner';
      var provider = sagData.imageProviderLabel || 'Image';
      setStatus('image', 'loading', 'Generating ' + format.replace('_', ' ') + ' image via ' + provider + '… up to 90 seconds.');
      $('#sag-image-panel').removeAttr('hidden');
      $('#sag-image-preview').html('<div class="sag-image-placeholder"><span class="sag-spinner"></span> Creating image…</div>');
      $('#sag-image-actions').attr('hidden', true);
      $('#sag-image-prompt-panel').attr('hidden', true);
      imagePayload = null;

      $.post(sagData.ajaxUrl, {
        action:  'sag_generate_image',
        nonce:   sagData.nonce,
        post_id: sagData.postId,
        format:  format
      })
      .done(function (res) {
        $('#sag-image-status').attr('hidden', true);
        if (!res.success) {
          setStatus('image', 'error', res.data || 'Image generation failed.');
          $('#sag-image-preview').html('');
          return;
        }
        var data = res.data;

        if (data.type === 'image') {
          imagePayload = { save_url: data.save_url || data.preview_url || '' };
          $('#sag-image-preview').html(
            '<img src="' + esc(data.preview_url) + '" alt="Social share image" class="sag-generated-img">'
          );
          if (imagePayload.save_url && imagePayload.save_url.indexOf('data:') !== 0) {
            $('#sag-image-actions').removeAttr('hidden');
          }
          $('#sag-image-saved-msg').attr('hidden', true).text('');
        } else {
          imagePayload = null;
          $('#sag-image-preview').html('');
          $('#sag-image-prompt-text').text(data.prompt);
          $('#sag-image-prompt-raw').text(data.prompt);
          $('#sag-image-prompt-panel').removeAttr('hidden');
        }
      })
      .fail(function (xhr) {
        var msg = xhr.status === 500
          ? 'Server error (500). Check your image API key and PHP error log.'
          : 'Request failed.';
        setStatus('image', 'error', msg);
        $('#sag-image-preview').html('');
      });
    });

    // -----------------------------------------------------------------------
    // Save image to media library
    // -----------------------------------------------------------------------
    $('#sag-save-image-btn').on('click', function () {
      if (!imagePayload) return;
      var $btn = $(this);
      $btn.prop('disabled', true).text('Saving…');

      $.post(sagData.ajaxUrl, {
        action:   'sag_save_image',
        nonce:    sagData.nonce,
        post_id:  sagData.postId,
        title:    $('input#title').val() || 'Social share image',
        save_url: imagePayload.save_url
      })
      .done(function (res) {
        $btn.prop('disabled', false).text('⬆ Save to media library');
        if (res.success) {
          var editLink = res.data.edit_url
            ? ' <a href="' + res.data.edit_url + '" target="_blank">View in media library →</a>'
            : '';
          $('#sag-image-saved-msg').removeAttr('hidden').html('✓ Saved.' + editLink);
        } else {
          $('#sag-image-saved-msg').removeAttr('hidden').css('color','#b91c1c').text('Error: ' + (res.data || 'Could not save.'));
        }
      })
      .fail(function () {
        $btn.prop('disabled', false).text('⬆ Save to media library');
        $('#sag-image-saved-msg').removeAttr('hidden').css('color','#b91c1c').text('Save failed. Try again.');
      });
    });

    // -----------------------------------------------------------------------
    // Select title or subject ("Use this" button)
    // -----------------------------------------------------------------------
    $(document).on('click', '.sag-select-btn', function () {
      var $block = $(this).closest('.sag-copy-block');
      var $panel = $(this).closest('.sag-panel');

      // Deselect sibling blocks in the same panel
      $panel.find('.sag-copy-block').removeClass('sag-selected');
      $panel.find('.sag-select-btn').text('Use this');

      // Select this one
      $block.addClass('sag-selected');
      $(this).text('✓ Selected');

      updateSaveBtn();
    });

    // -----------------------------------------------------------------------
    // Save text assets to post meta
    // -----------------------------------------------------------------------
    $('#sag-save-btn').on('click', function () {
      if (!assets) return;

      // Gather selections
      var chosenTitle   = $('#sag-panel-titles .sag-selected .sag-copy-target').first().text().trim();
      var chosenSubject = $('#sag-panel-subjects .sag-selected .sag-copy-target').first().text().trim();

      var savePayload = jQuery.extend( {}, assets, {
        chosen_title:   chosenTitle,
        chosen_subject: chosenSubject
      });

      $.post(sagData.ajaxUrl, {
        action:  'sag_save_assets',
        nonce:   sagData.nonce,
        post_id: sagData.postId,
        assets:  savePayload
      }).done(function (res) {
        if (res.success) {
          $('#sag-save-hint').hide();
          $('#sag-saved-msg').removeAttr('hidden');
          setTimeout(function () { $('#sag-saved-msg').attr('hidden', true); }, 3000);
        }
      });
    });

    // -----------------------------------------------------------------------
    // Copy image prompt
    // -----------------------------------------------------------------------
    $(document).on('click', '.sag-copy-prompt-btn', function () {
      var text = $('#sag-image-prompt-raw').text();
      copyToClipboard(text);
      var $btn = $(this);
      $btn.text('Copied!');
      setTimeout(function () { $btn.text('Copy'); }, 2000);
    });

    // Tab switching
    $(document).on('click', '.sag-tab', function () {
      var tab = $(this).data('tab');
      $('.sag-tab').removeClass('active');
      $(this).addClass('active');
      $('.sag-panel').attr('hidden', true);
      $('#sag-panel-' + tab).removeAttr('hidden');
    });

    // Copy buttons
    $(document).on('click', '.sag-copy-btn', function () {
      var text = $(this).closest('.sag-copy-block').find('.sag-copy-target').text();
      copyToClipboard(text);
      var $btn = $(this);
      $btn.text('Copied!');
      setTimeout(function () { $btn.text('Copy'); }, 2000);
    });
  });

  // -------------------------------------------------------------------------
  // Renderers
  // -------------------------------------------------------------------------

  function renderAll(data) {
    renderTitles(data.titles || []);
    renderSubjects(data.subject_lines || []);
    renderText('linkedin',  data.linkedin  || '');
    renderTwitter(data.twitter_thread || []);
    renderText('instagram', data.instagram || '');
    renderText('facebook',  data.facebook  || '');
    renderHashtags(data.hashtags || {});
    $('#sag-results').removeAttr('hidden');
    updateSaveBtn();
  }

  function renderTitles(titles) {
    var html = '';
    titles.forEach(function (item, i) {
      var text = (i + 1) + '. ' + item.title;
      var note = item.note ? '<small class="sag-note">' + esc(item.note) + '</small>' : '';
      html += selectBlock(text, note);
    });
    $('#sag-panel-titles').html(html);
    // Re-apply saved selection if present
    if (window.sagSavedAssets && window.sagSavedAssets.chosen_title) {
      restoreSelection('#sag-panel-titles', window.sagSavedAssets.chosen_title);
    }
  }

  function renderSubjects(lines) {
    var html = '';
    lines.forEach(function (item) {
      var content = '<strong>Subject:</strong> ' + esc(item.subject) +
        (item.preview ? '<br><span class="sag-note"><strong>Preview:</strong> ' + esc(item.preview) + '</span>' : '');
      html += selectBlock(item.subject, content, false);
    });
    $('#sag-panel-subjects').html(html);
    if (window.sagSavedAssets && window.sagSavedAssets.chosen_subject) {
      restoreSelection('#sag-panel-subjects', window.sagSavedAssets.chosen_subject);
    }
  }

  function renderText(panel, text) {
    $('#sag-panel-' + panel).html(copyBlock(text));
  }

  function renderTwitter(tweets) {
    var html = '';
    tweets.forEach(function (tweet) { html += copyBlock(tweet); });
    $('#sag-panel-twitter').html(html);
  }

  function renderHashtags(tags) {
    var platforms = { linkedin: 'LinkedIn', twitter: 'Twitter / X', instagram: 'Instagram' };
    var html = '';
    Object.keys(platforms).forEach(function (p) {
      if (!tags[p] || !tags[p].length) return;
      var tagStr = tags[p].map(function (t) { return t.startsWith('#') ? t : '#' + t; }).join(' ');
      html += '<div class="sag-hashtag-group"><h4>' + platforms[p] + '</h4>' + copyBlock(tagStr) + '</div>';
    });
    $('#sag-panel-hashtags').html(html);
  }

  // -------------------------------------------------------------------------
  // Helpers
  // -------------------------------------------------------------------------

  /** Copy block with "Use this" select button (for titles + subjects) */
  function selectBlock(text, innerHtml) {
    var displayContent = (innerHtml !== undefined) ? innerHtml : esc(text).replace(/\n/g, '<br>');
    return '<div class="sag-copy-block">' +
      '<div class="sag-copy-content">' +
        '<div class="sag-copy-target" style="white-space:pre-wrap;display:none;">' + esc(text) + '</div>' +
        '<div class="sag-copy-display">' + displayContent + '</div>' +
      '</div>' +
      '<div class="sag-block-actions">' +
        '<button type="button" class="button sag-select-btn">Use this</button>' +
        '<button type="button" class="button sag-copy-btn">Copy</button>' +
      '</div>' +
    '</div>';
  }

  /** Copy block — copy only, no selection (LinkedIn, Twitter, etc.) */
  function copyBlock(text, innerHtml) {
    var displayContent = (innerHtml !== undefined) ? innerHtml : esc(text).replace(/\n/g, '<br>');
    return '<div class="sag-copy-block">' +
      '<div class="sag-copy-content">' +
        '<div class="sag-copy-target" style="white-space:pre-wrap;display:none;">' + esc(text) + '</div>' +
        '<div class="sag-copy-display">' + displayContent + '</div>' +
      '</div>' +
      '<button type="button" class="button sag-copy-btn">Copy</button>' +
    '</div>';
  }

  /** Re-select a previously saved choice by matching copy-target text */
  function restoreSelection(panelSelector, savedText) {
    $(panelSelector).find('.sag-copy-block').each(function () {
      if ($(this).find('.sag-copy-target').text().trim() === savedText.trim()) {
        $(this).addClass('sag-selected');
        $(this).find('.sag-select-btn').text('✓ Selected');
        return false; // break
      }
    });
    updateSaveBtn();
  }

  function updateSaveBtn() {
    var titleChosen = $('#sag-panel-titles .sag-selected').length > 0;
    $('#sag-save-btn').prop('disabled', !titleChosen);
    if (titleChosen) {
      $('#sag-save-hint').hide();
    } else {
      $('#sag-save-hint').show();
    }
  }

  function setStatus(type, level, msg) {
    var $el = type === 'image' ? $('#sag-image-status') : $('#sag-status');
    $el.removeAttr('hidden').removeClass('sag-loading sag-error sag-success').addClass('sag-' + level).html(
      level === 'loading' ? '<span class="sag-spinner"></span> ' + esc(msg) : esc(msg)
    );
    if (level !== 'loading') {
      setTimeout(function () { $el.attr('hidden', true); }, 6000);
    }
  }

  function esc(str) {
    return String(str)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  function copyToClipboard(text) {
    if (navigator.clipboard && window.isSecureContext) {
      navigator.clipboard.writeText(text);
    } else {
      var el = document.createElement('textarea');
      el.value = text; el.style.position = 'absolute'; el.style.left = '-9999px';
      document.body.appendChild(el); el.select(); document.execCommand('copy');
      document.body.removeChild(el);
    }
  }

})(jQuery);
