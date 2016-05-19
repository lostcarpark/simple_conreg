/**
 * @file
 * Simple Convention Registration.
 */

(function ($) {

  function load() {
    $(".edit-members-first-name,.edit-members-last-name").keyup(function(event) {
      var reg=/^([a-zA-Z0-9]+\-){3}/
      var base=reg.exec(event.target.id);
      var first = "#" + base[0] + "first-name";
      var last = "#" + base[0] + "last-name";
      var badge = "#" + base[0] + "badge-name";
      var name=$(first).val().concat(" ", $(last).val());
      $(badge).val(name);
    });
  }
  load();
  
  $(".edit-member-quantity").blur(function(event) {
    load();
  });

})(jQuery);
