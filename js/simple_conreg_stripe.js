/**
 * @file
 * Simple Convention Registration Payment.
 */

// Based on "buy.js" by Larry Ullman, www.larryullman.com, @LarryUllman
// Posted as part of the series "Processing Payments with Stripe"
// http://www.larryullman.com/series/processing-payments-with-stripe/
// Adapted to Drupal by James Shields, 3 Dec 2015.

(function ($) {

  // This function is just used to display error messages on the page.
  // Assumes there's an element with an ID of "payment-errors".
  function reportError(msg) {
	  // Show the error in the form:
	  $('#payment-errors').text(msg).addClass('alert alert-error');
	  // re-enable the submit button:
	  $('#submitBtn').prop('disabled', false);
	  return false;
  }

	// Watch for a form submission:
	$("#simple-conreg-payment").submit(function(event) {

		// Flag variable:
		var error = false;

		// disable the submit button to prevent repeated clicks:
		$('#submitBtn').attr("disabled", "disabled");

		// Get the values:
		var ccNum = $('.card-number').val(),
		    cvcNum = $('.card-cvc').val(),
		    expMonth = $('.card-expiry-month').val(),
		    expYear = $('.card-expiry-year').val(),
		    cardholderName = $('.card-name').val(),
		    postcode = $('.postcode').val();

    // Set the Stripe public key, using setting received from Drupal.
    Stripe.setPublishableKey(drupalSettings.simple_conreg.payments.public_key);

		// Validate the number:
		if (!Stripe.card.validateCardNumber(ccNum)) {
			error = true;
			reportError('The credit card number appears to be invalid.');
		}

		// Validate the CVC:
		if (!Stripe.card.validateCVC(cvcNum)) {
			error = true;
			reportError('The CVC number appears to be invalid.');
		}

		// Validate the expiration:
		if (!Stripe.card.validateExpiry(expMonth, expYear)) {
			error = true;
			reportError('The expiration date '+expMonth+'/'+expYear+' appears to be invalid.');
		}

		// Validate other form elements, if needed!

		// Check for errors:
		if (!error) {

			// Get the Stripe token:
			Stripe.card.createToken({
				number: ccNum,
				cvc: cvcNum,
				exp_month: expMonth,
				exp_year: expYear,
				name: cardholderName,
				address_zip: postcode
			}, stripeResponseHandler);

		}

		// Prevent the form from submitting:
		return false;
	}); // Form submission

  // Function handles the Stripe response:
  function stripeResponseHandler(status, response) {

	  // Check for an error:
	  if (response.error) {
		  reportError(response.error.message);
	  } else { // No errors, submit the form:
	    var f = $("#simple-conreg-payment");
	    // Token contains id, last4, and card type:
	    var token = response['id'];
	    // Insert the token into the form so it gets submitted to the server
	    $("#stripeToken").val(token);
	    // Submit the form:
	    f.get(0).submit();
	  }

  } // End of stripeResponseHandler() function.

})(jQuery);
