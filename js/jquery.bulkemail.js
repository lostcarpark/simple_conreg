var bulkTimer;

Drupal.behaviors.bulkemail = {
  attach: function (context) {
    //jQuery("#sending").hide();
    jQuery("#edit-do-sending").click(function() {
      //jQuery("#sending").show();
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
  var ids = jQuery('#edit-sending-ids').val();
  // If no more IDs, stop timer.
  if (ids.length <= 1) {
    clearInterval(bulkTimer);
    //jQuery("#sending").hide();
    return;
  }
  // Get the first ID from the list.
  var index = ids.indexOf("\n");
  var first = ids.substring(0,index);
  var rest = ids.substring(index+1);
  // If no newlines, only one entry left.
  if (index === -1) {
    first = ids;
    rest = "";
  }
  // Upload the next badge.
  sendMessage(first);
  // Update the list of IDs in the textarea.
  jQuery('#edit-sending-ids').val(rest);
}

/*
 * Function to upload badge image to server.
 */
function sendMessage(mid) {
  // Make Ajax call to send email.
  jQuery.get('/admin/members/bulksend/1/'+mid, function(data, status){});
}
