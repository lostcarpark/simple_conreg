var badgeTimer;

Drupal.behaviors.badges = {
  attach: function (context) {
    jQuery(".badge-name").textfit('bestfit');
    jQuery("#edit-upload-do-upload").click(function() {
      window.scrollTo(0, 0);

      // Make a list of IDs for all visible badges.      
      var ids="";
      jQuery(".badge").each(function(i, obj) {
        ids += obj.id + "\n";
      });
      // Put list of IDs in textarea (mainly for user to see what's happening).
      jQuery('#edit-upload-ids').val(ids);
      // Start a timer running every 1s to upload the badges.
      badgeTimer = setInterval(badgeTick, 1000);
    });
  },
  detach: function (context) {}
};

/*
 * Timer handler.
 */
function badgeTick() {
  // Get the list of IDs.
  var ids = jQuery('#edit-upload-ids').val();
  // If no more IDs, stop timer.
  if (ids.length <= 1) {
    clearInterval(badgeTimer);
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
  uploadBadge(first);
  // Update the list of IDs in the textarea.
  jQuery('#edit-upload-ids').val(rest);
}

/*
 * Function to upload badge image to server.
 */
function uploadBadge(selector) {
  // Remove previously displayed canvas.
  var cNode = jQuery('#canvas')[0];
  while (cNode.firstChild) {
    cNode.removeChild(cNode.lastChild);
  }
  // Turn the selected div into a Canvas.
  html2canvas(jQuery("#"+selector)[0]).then((canvas) => {
    // Display current canvas. Not really necessary, but gives the user sense of progress.
    cNode.appendChild(canvas);
    // Turn canvas into image.
    var data = selector + '|' + canvas.toDataURL('image/png');
    jQuery('#edit-upload-text').val(data);
    // Make Ajax call to upload image.
    jQuery.post('/members/badge/upload', {
      "data": data
    },
    function(data, status){});
  });
}
