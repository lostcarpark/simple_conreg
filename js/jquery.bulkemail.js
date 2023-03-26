var bulkTimer;

Drupal.behaviors.bulkemail = {
  attach: function (context) {
    //jQuery("#sending").hide();
    jQuery("#edit-do-sending").click(function() {
      jQuery("#sending").show();
      delay = jQuery('#edit-options-delay').val();
      // Start a timer running every 1s to upload the badges.
      bulkTimer = setInterval(sendTick, delay);
    });
  },
  detach: function (context) {}
};

/*
 * Timer handler.
 */
function sendTick() {
  // Get the list of IDs.
  const idString = jQuery('#edit-sending-ids').val();
  // If no more IDs, stop timer.
  if (idString.length <= 0) {
    clearInterval(bulkTimer);
    jQuery("#sending").hide();
    return;
  }
  const ids = idString.split(' ');
  // Get the first ID from the list.
  const first = ids.shift();
  // Upload the next email.
  sendMessage(first);
  // Update the list of IDs in the textarea.
  jQuery('#edit-sending-ids').val(ids.join(' '));
}

/*
 * Function to upload badge image to server.
 */
function sendMessage(mid) {
  // Make Ajax call to send email.
  jQuery.get('/admin/members/bulksend/'+mid, function(data, status){});
}
