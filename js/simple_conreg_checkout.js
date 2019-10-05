/**
 * @file
 * Simple Convention Registration Payment.
 */

// Adapted to Drupal by James Shields, 5 Oct 2019.

(function ($) {

  var stripe = Stripe(drupalSettings.simple_conreg.checkout.public_key);
  stripe.redirectToCheckout({
    // Make the id field from the Checkout Session creation API response
    // available to this file, so you can provide it as parameter here
    // instead of the {{CHECKOUT_SESSION_ID}} placeholder.
    sessionId: drupalSettings.simple_conreg.checkout.session_id
  }).then(function (result) {
    // If `redirectToCheckout` fails due to a browser or network
    // error, display the localized error message to your customer
    // using `result.error.message`.
  });

})(jQuery);
