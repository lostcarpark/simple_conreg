/**
 * @file
 * Simple Convention Registration.
 */

(function ($) {

  Drupal.behaviors.simple_conreg_registration = {
    attach: function (context, settings) {
      $('#edit-payment-global-add-on-free-amount', context).change(function () {
        $('#edit-payment-update').click();
        //alert('Handler for .change() called.');
      });
    }
  };

}(jQuery));

/*(function ($) {

  $(".edit-member-quantity").blur(function(event) {
    load();
  });

})(jQuery);*/
