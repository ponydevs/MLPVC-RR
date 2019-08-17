(function() {
  'use strict';

  const RGB = ['red', 'green', 'blue'];
  const ADD = { red: 0, green: 1, blue: 2 };
  const ix = (i, k) => i + ADD[k];

  const Blender = {
    normal: (A, Top, Bot) => A * Top + (1 - A) * Bot,
    normalReverse: (A, Top, Res) => (Res - (A * Top)) / (1 - A),
    normalFilter: (A, Res, Bot) => (Res - (1 - A) * Bot) / A,
    multiply: (A, Top, Bot) => A * ((Top * Bot) / 255) + (1 - A) * Bot,
    multiplyReverse: (A, Top, Res) => (Res - (A * Top)) / (1 - A),
    multiplyFilter: (A, Res, Bot) => {
      Bot /= 255;
      Res /= 255;
      return ((A * Bot - Bot + Res) / (A * Bot)) * 255;
    },
  };

  const Math_NotEvenOnce = {
    normal: (originalColors, filteredColors) => {
      const n = originalColors.length;
      const sb = new $.RGBAColor(0, 0, 0), sd = new $.RGBAColor(0, 0, 0);
      let numerator = 0, denominator = 0;

      $.RGBAColor.COMPONENTS.forEach(component => {
        originalColors.forEach((color, index) => { // iterate over each colour pair
          const b = color[component];
          const d = b - filteredColors[index][component];
          sb[component] += b;
          sd[component] += d;
          numerator -= n * Math.pow(d, 2);
          denominator -= n * b * d;
        });
        numerator += Math.pow(sd[component], 2);
        denominator += sd[component] * sb[component];
      });

      const alpha = $.clamp(numerator / denominator, 0, 1);
      sb.alpha = alpha;
      $.RGBAColor.COMPONENTS.forEach(component => {
        let z = alpha ? (sb[component] - sd[component] / alpha) / n : 0;
        sb[component] = Math.round($.clamp(z, 0, 255));
      });

      return sb;
    },
    multiply: (originalColors, filteredColors) => {
      console.log(originalColors);
      let filter = new $.RGBAColor(0, 0, 0);
      $.RGBAColor.COMPONENTS.forEach(component => {
        let numerator = originalColors.reduce((p, b, i) => p + b[component] * (b[component] - filteredColors[i][component]), 0);
        let denominator = originalColors.reduce((p, b, i) => p + Math.pow(b[component] - filteredColors[i][component], 2), 0);
        filter[component] = numerator / (denominator * 2);
      });
      let alpha = 0.5;
      let kmin = Math.max(1, 1 / filter.toRGBArray().reduce((p, c) => Math.min(p, c)));
      $.RGBAColor.COMPONENTS.forEach(component => {
        let z = 255 - 255 / (filter[component] * kmin);
        filter[component] = Math.round($.clamp(z, 0, 255));
      });
      filter.alpha = $.clamp(alpha * kmin, 0, 1);

      return filter;
    },
  };

  class MultiplyReverseForm {
    constructor() {
      this.$controls = $('#controls');
      this.$knownColorsTbody = $('#known-colors').find('tbody');
      this.$backupImage = $.mk('img');
      this.backupImage = this.$backupImage.get(0);
      this.overlayColor = new $.RGBAColor(255, 0, 255, 0.75);
      this.filteredColor = null;
      this.haveImage = false;
      this.targetType = 'image';
      this.filterOverrideActive = false;
      this.fileName = null;
      this.selectedFilterColor = null;
      this.$freezing = $('#freezing');
      this.$preview = $('#preview');
      this.$previewImageCanvas = $('#preview-image');
      this.previewImageCanvas = this.$previewImageCanvas.get(0);
      this.previewImageCtx = this.previewImageCanvas.getContext('2d');
      this.$previewOverlayCanvas = $('#preview-overlay');
      this.previewOverlayCanvas = this.$previewOverlayCanvas.get(0);
      this.previewOverlayCtx = this.previewOverlayCanvas.getContext('2d');
      this.$addKnownColor = $('#add-known-color').on('click', e => {
        e.preventDefault();

        this.addKnownValueInputRow();
      });
      this.$imageSelect = $('#image-select');
      this.$imageSelectFileInput = this.$imageSelect.children('input').on('change', e => {
        const input = e.target;
        if (input.files && input.files[0]){
          this.fileName = input.files[0].name.split(/[\/]/g).pop();
          const reader = new FileReader();
          reader.onload = e => {
            this.backupImage.src = e.target.result;
            this.$backupImage.one('load', () => {
              this.$backupImage.off('error');
              this.haveImage = true;
              this.updatePreview();
            }).one('error', () => {
              this.$backupImage.off('load');
              $.Dialog.fail('Could not load image. Please make sure it is an actual image file.');
            });
          };
          reader.readAsDataURL(input.files[0]);
        }
      });
      this.$imageSelectFileButton = this.$imageSelect.children('button').on('click', e => {
        e.preventDefault();

        this.$imageSelectFileInput.click();
      });
      this.$colorSelect = $('#color-select');
      this.$colorSelectColorInput = this.$colorSelect.find('input').on('change', e => {
        const newcolor = $.RGBAColor.parse(e.target.value);
        if (newcolor === null){
          this.haveImage = false;
          return;
        }

        e.target.value = newcolor;
        this.filteredColor = newcolor;
        this.haveImage = true;
        this.updatePreview();
      }).on('change input blur', MultiplyReverseForm.colorInputEventHandler);
      this.$filterTypeSelect = $('#filter-type').children('select').on('change', () => {
        this.updateFilterCandidateList();
        this.updatePreview();
      });
      this.$sensitivityControls = $('#sensitivity');
      this.$sensitivitySlider = this.$sensitivityControls.children('div');
      this.$sensitivityDisplay = this.$sensitivityControls.find('.display');
      this.sensitivitySlider = this.$sensitivitySlider.get(0);
      noUiSlider.create(this.sensitivitySlider, {
        start: [10],
        range: {
          'min': 0,
          'max': 255,
        },
        step: 1,
        behaviour: 'drag snap',
        format: {
          to: n => parseInt(n, 10),
          from: n => parseInt(n, 10),
        },
      });
      this.sensitivitySlider.noUiSlider.on('update', (values, handle) => {
        this.$sensitivityDisplay.text(values[handle]);
      });
      this.sensitivitySlider.noUiSlider.on('end', () => {
        this.updatePreview();
      });
      this.$resultSaveButton = $('#result').children('button').on('click', e => {
        e.preventDefault();

        if (!this.haveImage || this.selectedFilterColor === null)
          return;

        let target;
        if (this.isOverlayEnabled()){
          target = document.createElement('canvas');
          target.width = this.previewImageCanvas.width;
          target.height = this.previewImageCanvas.height;
          const targetctx = target.getContext('2d');

          targetctx.drawImage(this.previewImageCanvas, 0, 0);
          targetctx.drawImage(this.previewOverlayCanvas, 0, 0);
        }
        else target = this.previewImageCanvas;

        target.toBlob(blob => {
          const ins = ' (no ' + this.getFilterType() + ' filter)';
          saveAs(blob, this.fileName.replace(/^(.*?)(\.(?:[^.]+))?$/, `$1${ins}$2`) || 'image' + ins + '.png');
        });
      });
      this.$filterCandidates = $('#filter-candidates').children('ul');
      this.$filterCandidates.on('click', 'li', e => {
        const
          $li = $(e.target).closest('li'),
          hasClass = $li.hasClass('selected');
        this.$filterCandidates.children('.selected').removeClass('selected');
        if (!hasClass){
          $li.addClass('selected');
          this.selectedFilterColor = $.RGBAColor.parse($li.attr('data-rgba'));
        }

        this.updatePreview();
      });
      this.$overlayControls = $('#overlay');
      this.$overlayToggleInput = this.$overlayControls.find('input[type="checkbox"]').on('change input', e => {
        this.$previewOverlayCanvas[e.target.checked ? 'removeClass' : 'addClass']('hidden');
      });
      this.$overlayColorInput = this.$overlayControls.find('input[type="text"]').on('change', e => {
        const newcolor = $.RGBAColor.parse(e.target.value);
        if (newcolor === null)
          return;

        e.target.value = newcolor;
        this.overlayColor = newcolor;
        this.repaintOverlay();
      }).on('change input blur', MultiplyReverseForm.colorInputEventHandler);
      this.$overlayColorInput.val(this.overlayColor.toString()).trigger('input');
      $('#filter-override').find('input[type="checkbox"]').on('change input', e => {
        this.filterOverrideActive = e.target.checked;

        this.$filterCandidates.parent()[this.filterOverrideActive ? 'addClass' : 'removeClass']('hidden');
        if (this.filterOverrideActive)
          this.updateOverriddenFilterColor();
        else {
          const $sel = this.$filterCandidates.children('.selected');
          this.selectedFilterColor = $sel.length ? $.RGBAColor.parse($sel.attr('data-rgba')) : null;
          this.updatePreview();
        }
      });
      this.$filterOverrideOpacity = $('#filter-override-opacity').on('change', e => {
        e.target.value = $.clamp(e.target.value, 0, 100);
        this.updateOverriddenFilterColor();
      });
      this.$filterOverrideColor = $('#filter-override-color').on('change', e => {
        const newcolor = $.RGBAColor.parse(e.target.value);
        if (newcolor === null)
          return;

        e.target.value = newcolor.toHex();
        this.updateOverriddenFilterColor();
      }).on('change input blur', MultiplyReverseForm.colorInputEventHandler);
      this.$reverseWhat = $('#reverse-what').on('click change', 'input', e => {
        this.targetType = e.target.value;

        if (this.targetType !== 'image')
          this.$imageSelect.addClass('hidden');
        else this.$imageSelect.removeClass('hidden');

        if (this.targetType !== 'color')
          this.$colorSelect.addClass('hidden');
        else this.$colorSelect.removeClass('hidden');

        this.updatePreview();
      });

      this.addKnownValueInputRow(true);
      this.addKnownValueInputRow();
    }

    isOverlayEnabled() {
      return !this.$previewOverlayCanvas.hasClass('hidden');
    }

    updateOverriddenFilterColor() {
      if (!this.filterOverrideActive)
        return;

      const filterColor = $.RGBAColor.parse(this.$filterOverrideColor.val());
      if (filterColor !== null){
        filterColor.alpha = this.$filterOverrideOpacity.val() / 100;
      }
      this.selectedFilterColor = filterColor;

      this.updatePreview();
    }

    createKnownValueInput(className) {
      return $.mk('td').attr('class', 'color-cell ' + className).append(
        $.mk('input').attr({
          type: 'text',
          required: true,
          autocomplete: 'off',
          spellcheck: 'false',
        }).on('input change blur', e => {
          const
            $this = $(e.target),
            value = $this.val(),
            rgb = $.RGBAColor.parse(value);
          if (rgb === null)
            $this.css({ color: '', backgroundColor: '' });
          else $this.css({
            color: rgb.isLight() ? 'black' : 'white',
            backgroundColor: rgb.toHex(),
          });
        }).on('blur', e => {
          const
            $this = $(e.target),
            parsed = $.RGBAColor.parse($this.val());
          if (parsed !== null)
            $this.removeAttr('pattern').val(parsed);
          else $this.attr('pattern', '^[^\\s\\S]$');

          this.updateFilterCandidateList();
        }).on('paste', e => {
          window.requestAnimationFrame(function() {
            $(e.target).trigger('blur');
          });
        }),
      );
    }

    addKnownValueInputRow(anchor = false) {
      const refclass = 'reference';
      this.$knownColorsTbody.append(
        $.mk('tr').attr('class', anchor ? refclass : '').append(
          this.createKnownValueInput('original'),
          this.createKnownValueInput('filtered'),
          $.mk('td').attr('class', 'actions').append(
            $.mk('button').attr({
              disabled: anchor,
              'class': 'red typcn typcn-minus',
              title: 'Remove known color pair',
            }).on('click', e => {
              e.preventDefault();

              const $tr = $(e.target).closest('tr');
              if ($tr.siblings().length === 2)
                $tr.siblings().find('button.red').disable().addClass('hidden');
              $tr.remove();
              this.updateFilterCandidateList();
            }),
            $.mk('button').attr({
              'class': 'darkblue typcn typcn-anchor',
              title: 'Set as reference color',
              disabled: anchor,
            }).on('click', e => {
              e.preventDefault();

              const $el = $(e.target);

              if ($el.is(':disabled'))
                return;

              const
                $tr = $el.closest('tr'),
                $invalidInputs = $tr.find('input:invalid');
              if ($invalidInputs.length){
                $invalidInputs.first().focus();
                return;
              }

              $tr.addClass(refclass).siblings().removeClass(refclass).find('button').enable();
              $el.siblings().addBack().disable();
              this.updateFilterCandidateList();
            }),
          ),
        ),
      );
      let $trs = this.$knownColorsTbody.children();
      if ($trs.length > 2){
        $trs.find('button.red').removeClass('hidden');
        $trs.filter(':not(.reference)').find('button.red').enable();
      }
      else $trs.find('button.red').addClass('hidden');
    }

    redrawPreviewImage() {
      const targetIsImage = this.targetType === 'image';
      const width = targetIsImage ? this.backupImage.width : 192;
      const height = targetIsImage ? this.backupImage.height : 108;
      this.previewOverlayCanvas.width =
        this.previewImageCanvas.width = width;
      this.previewOverlayCanvas.height =
        this.previewImageCanvas.height = height;
      if (this.targetType === 'image')
        this.previewImageCtx.drawImage(this.backupImage, 0, 0);
      else {
        this.previewImageCtx.fillStyle = this.filteredColor;
        this.previewImageCtx.fillRect(0, 0, width, height);
      }
      this.previewOverlayCtx.clearRect(0, 0, this.previewOverlayCanvas.width, this.previewOverlayCanvas.height);
    }

    repaintOverlay() {
      if (!this.haveImage)
        return;

      const overlayData = this.previewOverlayCtx.getImageData(0, 0, this.previewOverlayCanvas.width, this.previewOverlayCanvas.height);
      for (let i = 0; i < overlayData.data.length; i += 4){
        if (overlayData.data[i + 3] !== 1)
          continue;

        overlayData.data[i] = this.overlayColor.red;
        overlayData.data[i + 1] = this.overlayColor.green;
        overlayData.data[i + 2] = this.overlayColor.blue;
      }
      this.previewOverlayCtx.putImageData(overlayData, 0, 0);
    }

    updatePreview() {
      if (!this.haveImage){
        this.$resultSaveButton.disable();
        return;
      }

      this.redrawPreviewImage();

      const noFilterColor = this.selectedFilterColor === null;

      this.$resultSaveButton.prop('disabled', noFilterColor);

      if (noFilterColor)
        return;

      const maxdiff = this.sensitivitySlider.noUiSlider.get();
      const imgData = this.previewImageCtx.getImageData(0, 0, this.previewImageCanvas.width, this.previewImageCanvas.height);
      const overlayData = this.previewOverlayCtx.getImageData(0, 0, this.previewOverlayCanvas.width, this.previewOverlayCanvas.height);
      const calculator = this.getReverseCalculator();

      this.$freezing.removeClass('hidden');

      setTimeout(() => {
        for (let i = 0; i < imgData.data.length; i += 4){
          let toosmall = false;
          let toobig = false;
          $.each(RGB, (_, k) => {
            const j = ix(i, k);
            const newpixel = calculator(this.selectedFilterColor.alpha, this.selectedFilterColor[k], imgData.data[j]);
            if (!toobig && newpixel - maxdiff > 255)
              toobig = true;
            if (!toosmall && newpixel + maxdiff < 0)
              toosmall = true;
            imgData.data[j] = $.clamp(newpixel, 0, 255);
          });
          if (toosmall || toobig){
            overlayData.data[i] = this.overlayColor.red;
            overlayData.data[i + 1] = this.overlayColor.green;
            overlayData.data[i + 2] = this.overlayColor.blue;
            overlayData.data[i + 3] = this.overlayColor.alpha * 255;
          }
        }
        this.previewImageCtx.putImageData(imgData, 0, 0);
        this.previewOverlayCtx.putImageData(overlayData, 0, 0);

        this.$freezing.addClass('hidden');
      }, 200);
    }

    updateFilterCandidateList() {
      const values = {
        original: [],
        filtered: [],
      };

      this.$filterCandidates.empty();
      this.selectedFilterColor = null;

      const $knownColors = this.$knownColorsTbody.children();

      if ($knownColors.length < 2)
        return;

      $knownColors.each((_, el) => {
        const
          $tr = $(el),
          $inputs = $tr.find('input:valid');
        if ($inputs.length !== 2)
          return;

        $inputs.each((_, input) => {
          values[input.parentNode.className.split(' ')[1]].push($.RGBAColor.parse(input.value));
        });
      });

      if (values.original.length < 2 || values.filtered.length < 2)
        return;

      const color = Math_NotEvenOnce[this.getFilterType()](values.original, values.filtered);

      this.$filterCandidates.append(
        MultiplyReverseForm.getFilterDisplayLi(color.round()),
      );
    }

    static getFilterDisplayLi(color) {
      console.log(color);
      const
        rgba = color.toRGBA(),
        $pairs = $.mk('ul').attr('class', 'pairs');

      return $.mk('li').attr({ 'data-rgba': rgba, title: 'Click to select & apply' }).append(
        $.mk('div').attr('class', 'color').append(
          $.mk('div').attr('class', 'color-preview').append(
            $.mk('span').css('background-color', rgba),
          ),
          $.mk('div').attr('class', 'color-rgba').append(
            `<div><strong>R:</strong> <span class="color-red">${color.red}</span></div>`,
            `<div><strong>G:</strong> <span class="color-green">${color.green}</span></div>`,
            `<div><strong>B:</strong> <span class="color-blue">${color.blue}</span></div>`,
            `<div><strong>A:</strong> <span>${Math.round(color.alpha * 100)}%</span></div>`,
          ),
        ),
        $pairs,
      );
    }

    getFilterType() {
      return this.$filterTypeSelect.children(':selected').attr('value');
    }

    getReverseCalculator() {
      switch (this.getFilterType()){
        case 'multiply':
          return Blender.multiplyReverse;
        case 'normal':
          return Blender.normalReverse;
      }
    }

    static colorInputEventHandler(e) {
      const
        $el = $(e.target),
        val = $.RGBAColor.parse(e.target.value);
      if (val === null){
        $el.css({
          color: '',
          backgroundColor: '',
        });
        return;
      }

      $el.css({
        color: val.isLight() ? 'black' : 'white',
        backgroundColor: val.toHex(),
      });
    }
  }

  new MultiplyReverseForm();
})();
