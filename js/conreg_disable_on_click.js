(function ($) {
  'use strict';

  Drupal.behaviors.disableOnClick = {
    attach: function (context, settings) {

      $('.disable-on-click', context).on('click', function(event) {
        $(event.target).prop('disabled', true);
        let button_text = $(event.target).text();
        $(event.target).html(button_text + ' <img src="/core/misc/throbber-active.gif">');
        $(event.target).parents('form').submit();
      });

    }
  };

}(jQuery));