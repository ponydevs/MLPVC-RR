(function($) {
  'use strict';

  const DATA_KEY = 'pony-color-palette';

  let waitingList = {};

  class PonyColorPalette {
    constructor(appearanceId, defaultHex, pickCallback) {
      this.appearanceId = appearanceId;
      this.shown = false;
      this.colorData = {};
      this.getColorData();

      this.$picker = $.mk('div').attr('class', 'hidden pony-color-palette').appendTo($body);
      this.$swatchBox = $.mk('div').attr('class', 'swatchbox loading').appendTo(this.$picker);
      this.$nullColor = $.mk('li').attr('class', 'color color-null selected').append(
        $.mk('span').attr('title', 'Default').text(defaultHex),
      );
      $.mk('ul').attr('class', 'colorgroup').append(this.$nullColor).appendTo(this.$swatchBox);
      this.$picker.on('click', '.color', e => {
        e.stopPropagation();

        const $color = $(e.target).closest('.color');

        if ($color.hasClass('group-icon'))
          return;

        const color = $color.children().text();

        this.selectColor(color);
        pickCallback(color || null);
        this.hide(true);
      });
    }

    displayError(message) {
      this.$swatchBox.html($.mk('p').attr('class', 'error').text(message));
    }

    getColorData() {
      if (typeof waitingList[this.appearanceId] !== 'undefined'){
        waitingList[this.appearanceId].push(this);
        if (this.appearanceId in this.colorData) {
          this.processWaitingList();
        }
        return;
      }

      waitingList[this.appearanceId] = [this];

      $.API.get(`/../v0/appearances/${this.appearanceId}/color-groups`, data => {
        if (!data.status) return this.displayError(data.message);

        this.colorData[this.appearanceId] = data.colorGroups;
        this.processWaitingList();
      });
    }

    processWaitingList() {
      $.each(waitingList[this.appearanceId], (_, picker) => {
        picker.fillSwatchBox(this.colorData[this.appearanceId]);
      });
    }

    fillSwatchBox(list) {
      this.$swatchBox.removeClass('loading').children().first().nextAll().remove();

      $.each(list, (_, group) => {
        const $ul = $.mk('ul').attr('class', 'colorgroup').appendTo(this.$swatchBox);
        $ul.append(
          $.mk('li').attr({
            title: group.label,
            'class': 'color group-icon',
          }),
        );

        $.each(group.colors, (_, color) => {
          $ul.append(
            $.mk('li').attr('class', 'color').append(
              $.mk('span')
                .attr('title', color.label)
                .css('background-color', color.hex)
                .text(color.hex),
            ),
          );
        });
      });
    }

    display(e) {
      $('.pony-color-palette').addClass('hidden');
      const $target = $(e.target);
      let { top, left } = $target.offset();
      top += $target.outerHeight();
      this.$picker.css({
        top,
        left,
      }).removeClass('hidden');

      return this;
    }

    hide(force = false) {
      if (!force && this.$picker.is(':hover'))
        return this;
      this.$picker.addClass('hidden');

      return this;
    }

    selectColor(color) {
      this.$picker.find('.color')
        .removeClass('selected')
        .filter((i, el) => $(el).text() === color)
        .addClass('selected');
    }
  }

  $.fn.ponyColorPalette = function(appearanceId, defaultHex, pickCallback) {
    this.each(function() {
      const $el = $(this);
      $el.data(DATA_KEY, new PonyColorPalette(appearanceId, defaultHex, color => pickCallback($el, color)));
      $el.on('focus', e => {
        const palette = $(e.target).data(DATA_KEY);
        palette.selectColor(e.target.value);
        palette.display(e);
      });
      $el.on('keydown', e => {
        if ($.isKey(Key.Escape, e)){
          e.preventDefault();
          $(e.target).data(DATA_KEY).hide();
        }
      });
      $el.on('blur', e => {
        $(e.target).data(DATA_KEY).hide();
      });
    });
    return this;
  };
})(jQuery);
