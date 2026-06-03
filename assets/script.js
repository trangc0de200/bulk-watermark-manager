/* Bulk Watermark Manager — script.js */
(function ($) {
  'use strict';

  /* ── State ─────────────────────────────────────── */
  const state = {
    selected: new Set(),
    page: 1,
    hasMore: true,
    loading: false,
    search: '',
    filterMonth: '',
    filterWm: '',
    wmId: 0,
    wmUrl: '',
    firstThumb: '',   // full URL of first selected item (for preview)
    firstId: 0,
  };

  /* ── Init ──────────────────────────────────────── */
  $(function () {
    loadImages(true);
    bindEvents();
  });

  /* ── Bind events ───────────────────────────────── */
  function bindEvents() {
    /* Search & filters */
    let searchTimer;
    $('#bwm-search').on('input', function () {
      clearTimeout(searchTimer);
      searchTimer = setTimeout(() => {
        state.search = this.value.trim();
        loadImages(true);
      }, 350);
    });

    $('#bwm-filter-cat').on('change', function () {
      state.filterMonth = this.value;
      loadImages(true);
    });

    $('#bwm-filter-wm').on('change', function () {
      state.filterWm = this.value;
      loadImages(true);
    });

    /* Select all / deselect */
    $('#bwm-select-all').on('click', function () {
      $('#bwm-grid .bwm-item').each(function () {
        const id = +$(this).data('id');
        state.selected.add(id);
        $(this).addClass('selected');
        if (!state.firstId) {
          state.firstId = id;
          state.firstThumb = $(this).data('full');
        }
      });
      updateSelCount();
    });

    $('#bwm-deselect-all').on('click', function () {
      state.selected.clear();
      state.firstId = 0;
      state.firstThumb = '';
      $('#bwm-grid .bwm-item').removeClass('selected');
      updateSelCount();
    });

    /* Open watermark panel */
    $('#bwm-open-wm-panel').on('click', openWmPanel);

    /* Close panel */
    $('#bwm-close-panel, #bwm-cancel-panel').on('click', function () {
      $('#bwm-wm-panel').hide();
    });

    /* Pick watermark from media */
    $('#bwm-pick-wm').on('click', function () {
      const frame = wp.media({
        title: BWM.mediaTitle,
        button: { text: BWM.mediaBtn },
        multiple: false,
        library: { type: 'image' },
      });
      frame.on('select', function () {
        const att = frame.state().get('selection').first().toJSON();
        state.wmId  = att.id;
        state.wmUrl = att.url;
        $('#bwm-wm-id').val(att.id);
        $('#bwm-wm-url').val(att.url);

        $('#bwm-wm-thumb').empty().removeClass('bwm-wm-thumb-empty')
          .addClass('bwm-wm-thumb-preview')
          .html('<img src="' + att.url + '" alt="">');

        $('#bwm-apply-wm').prop('disabled', false);
        drawPreview();
      });
      frame.open();
    });

    /* Sliders live update */
    $('#bwm-wm-size').on('input', function () {
      $('#bwm-size-val').text(this.value + '%');
      drawPreview();
    });
    $('#bwm-wm-opacity').on('input', function () {
      $('#bwm-opacity-val').text(this.value + '%');
      drawPreview();
    });
    $('#bwm-wm-pad').on('input', function () {
      $('#bwm-pad-val').text(this.value + 'px');
      drawPreview();
    });
    $('input[name="bwm_pos"]').on('change', function () {
      drawPreview();
    });

    /* Apply watermark */
    $('#bwm-apply-wm').on('click', applyWatermarks);

    /* Load more */
    $('#bwm-load-more').on('click', function () {
      if (state.hasMore && !state.loading) loadImages(false);
    });
  }

  /* ── Load images ───────────────────────────────── */
  function loadImages(reset) {
    if (state.loading) return;
    if (reset) {
      state.page = 1;
      state.hasMore = true;
      state.selected.clear();
      state.firstId = 0;
      state.firstThumb = '';
      updateSelCount();
    }
    state.loading = true;

    if (reset) {
      $('#bwm-grid').html(
        Array(8).fill('<div class="bwm-skeleton"></div>').join('')
      );
    }

    $.post(BWM.ajaxurl, {
      action: 'bwm_load_images',
      nonce: BWM.nonce,
      page: state.page,
      search: state.search,
      month: state.filterMonth,
      filter_wm: state.filterWm,
    }, function (res) {
      state.loading = false;
      if (!res.success) return;

      if (reset) $('#bwm-grid').empty();

      res.data.items.forEach(function (item) {
        const badge = item.watermarked
          ? '<span class="bwm-wm-badge">WM</span>'
          : '';
        const el = $(`
          <div class="bwm-item" data-id="${item.id}" data-full="${item.full}">
            <img src="${item.thumb}" alt="${item.title}" loading="lazy">
            <div class="bwm-item-check"></div>
            ${badge}
            <div class="bwm-item-overlay">
              <span class="bwm-item-title">${item.title}</span>
            </div>
          </div>
        `);

        el.on('click', function () {
          const id = +$(this).data('id');
          if (state.selected.has(id)) {
            state.selected.delete(id);
            $(this).removeClass('selected');
            if (state.firstId === id) {
              state.firstId = state.selected.size ? [...state.selected][0] : 0;
              state.firstThumb = state.firstId
                ? $(`[data-id="${state.firstId}"]`).data('full')
                : '';
            }
          } else {
            state.selected.add(id);
            $(this).addClass('selected');
            if (!state.firstId) {
              state.firstId = id;
              state.firstThumb = $(this).data('full');
            }
          }
          updateSelCount();
        });

        $('#bwm-grid').append(el);
      });

      state.hasMore = res.data.has_more;
      state.page++;
      $('#bwm-load-more-wrap').toggle(state.hasMore);
    });
  }

  /* ── Update counter ────────────────────────────── */
  function updateSelCount() {
    const n = state.selected.size;
    $('#bwm-selected-count, #bwm-sel-cnt, #bwm-apply-cnt').text(n);
    $('#bwm-open-wm-panel').prop('disabled', n === 0);
  }

  /* ── Open panel ────────────────────────────────── */
  function openWmPanel() {
    $('#bwm-apply-cnt').text(state.selected.size);
    $('#bwm-wm-panel').show();
    if (state.wmId && state.firstThumb) drawPreview();
  }

  /* ── Draw canvas preview ───────────────────────── */
  function drawPreview() {
    if (!state.wmUrl || !state.firstThumb) return;

    const canvas  = document.getElementById('bwm-preview-canvas');
    const ctx     = canvas.getContext('2d');
    const size    = parseInt($('#bwm-wm-size').val());
    const opacity = parseInt($('#bwm-wm-opacity').val()) / 100;
    const pad     = parseInt($('#bwm-wm-pad').val());
    const pos     = $('input[name="bwm_pos"]:checked').val() || 'bottom-right';

    const baseImg = new Image();
    baseImg.crossOrigin = 'anonymous';
    baseImg.onload = function () {
      // Fit to 360×270 preview
      const maxW = 360, maxH = 270;
      let w = baseImg.naturalWidth, h = baseImg.naturalHeight;
      const ratio = Math.min(maxW / w, maxH / h);
      w = Math.round(w * ratio);
      h = Math.round(h * ratio);

      canvas.width  = w;
      canvas.height = h;
      ctx.clearRect(0, 0, w, h);
      ctx.drawImage(baseImg, 0, 0, w, h);

      const wmImg = new Image();
      wmImg.crossOrigin = 'anonymous';
      wmImg.onload = function () {
        const wmW = Math.round(w * size / 100);
        const wmH = Math.round(wmImg.naturalHeight * (wmW / wmImg.naturalWidth));

        const [posH, posV] = parsePosition(pos);
        const dx = posH === 'left'   ? pad
                 : posH === 'right'  ? w - wmW - pad
                 : Math.round((w - wmW) / 2);
        const dy = posV === 'top'    ? pad
                 : posV === 'bottom' ? h - wmH - pad
                 : Math.round((h - wmH) / 2);

        ctx.globalAlpha = opacity;
        ctx.drawImage(wmImg, dx, dy, wmW, wmH);
        ctx.globalAlpha = 1;

        $('#bwm-preview-canvas').show();
        $('#bwm-preview-placeholder').hide();
      };
      wmImg.src = state.wmUrl + '?' + Date.now();
    };
    baseImg.src = state.firstThumb + '?' + Date.now();
  }

  function parsePosition(pos) {
    const map = {
      'top-left':      ['left',   'top'],
      'top-center':    ['center', 'top'],
      'top-right':     ['right',  'top'],
      'center-left':   ['left',   'center'],
      'center':        ['center', 'center'],
      'center-right':  ['right',  'center'],
      'bottom-left':   ['left',   'bottom'],
      'bottom-center': ['center', 'bottom'],
      'bottom-right':  ['right',  'bottom'],
    };
    return map[pos] || ['right', 'bottom'];
  }

  /* ── Apply watermarks ──────────────────────────── */
  async function applyWatermarks() {
    const ids    = [...state.selected];
    const total  = ids.length;
    const wmId   = state.wmId;
    const pos    = $('input[name="bwm_pos"]:checked').val() || 'bottom-right';
    const size   = $('#bwm-wm-size').val();
    const opacity = parseInt($('#bwm-wm-opacity').val()) / 100;
    const pad    = $('#bwm-wm-pad').val();
    const backup = $('#bwm-backup').is(':checked') ? 1 : 0;

    if (!wmId) { alert('Please select a watermark image first!'); return; }

    $('#bwm-wm-panel').hide();
    $('#bwm-progress-overlay').show();
    $('#bwm-prog-title').text('Applying watermark...');
    $('#bwm-prog-log').empty();
    $('#bwm-prog-bar').css('width', '0%');
    $('#bwm-prog-status').text('0 / ' + total);

    let done = 0;

    for (const id of ids) {
      try {
        const res = await $.post(BWM.ajaxurl, {
          action: 'bwm_apply_watermark',
          nonce: BWM.nonce,
          attachment_id: id,
          wm_id: wmId,
          position: pos,
          size: size,
          opacity: opacity,
          padding: pad,
          backup: backup,
        });

        done++;
        const pct = Math.round((done / total) * 100);
        $('#bwm-prog-bar').css('width', pct + '%');
        $('#bwm-prog-status').text(done + ' / ' + total);

        if (res.success) {
          addLog('OK ID ' + id + ' — done', 'ok');
          // Update thumb in grid
          $('[data-id="' + id + '"] img').attr('src', res.data.url);
          if (!$('[data-id="' + id + '"] .bwm-wm-badge').length) {
            $('[data-id="' + id + '"]').append('<span class="bwm-wm-badge">WM</span>');
          }
        } else {
          addLog('FAIL ID ' + id + ' — ' + (res.data || 'error'), 'err');
        }
      } catch (e) {
        addLog('FAIL ID ' + id + ' — connection failed', 'err');
        done++;
      }
    }

    $('#bwm-prog-title').text('Complete! ' + done + '/' + total + ' images');
    $('#bwm-prog-bar').css('width', '100%');

    const closeBtn = $('<button class="bwm-btn bwm-btn-primary" style="margin-top:16px">Close</button>');
    closeBtn.on('click', function () {
      $('#bwm-progress-overlay').hide();
      state.selected.clear();
      state.firstId = 0;
      state.firstThumb = '';
      updateSelCount();
      $('#bwm-grid .bwm-item').removeClass('selected');
    });
    $('.bwm-progress-box').append(closeBtn);
  }

  function addLog(msg, type) {
    const el = $('<div>').text(msg).addClass('log-' + type);
    $('#bwm-prog-log').append(el).scrollTop(9999);
  }

})(jQuery);
