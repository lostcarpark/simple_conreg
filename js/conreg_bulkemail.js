(function (Drupal, once) {

  Drupal.behaviors.bulkemail = {
    attach (context) {
      once('init', '#edit-do-sending', context).forEach((bundle) => {
        document.querySelector("#upload").style.display = 'none';
        bundle.addEventListener('click', () => {
          document.querySelector("#upload").style.display = '';
          delay = document.querySelector('#edit-options-delay').value;
          // Start a timer running every 1s to upload the badges.
          bulkTimer = setInterval(() => {
            // Get the list of IDs.
            const idString = document.querySelector('#edit-sending-ids').value;
            // If no more IDs, stop timer.
            if (idString.length <= 0) {
              clearInterval(bulkTimer);
              document.querySelector("#upload").style.display = 'none';
              return;
            }
            const ids = idString.split(' ');
            // Get the first ID from the list.
            const first = ids.shift();
            // Upload the next email.
            fetch('/admin/members/bulksend/'+first);
            console.log(first);
            // Update the list of IDs in the textarea.
            document.querySelector('#edit-sending-ids').value = ids.join(' ');
          }, delay);
        });
      });
    }
  }
})(Drupal, once);
