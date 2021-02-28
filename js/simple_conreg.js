/**
 * @file
 * Simple Convention Registration.
 */

(function ($) {

  function load() {
    $(".edit-members-first-name,.edit-members-last-name").on('input',function(event) {
      var reg=/^([a-zA-Z0-9]+\-){3}/
      var base=reg.exec(event.target.id);
      var max_length = drupalSettings.simple_conreg.badge_name_max;
      var first_name=$("#" + base[0] + "first-name").val().trim();
      var last_name=$("#" + base[0] + "last-name").val().trim();
      var name=first_name.concat(" ", last_name);
      var name_last=last_name.concat(", ", first_name);
      $("label[for='" + base[0] + "badge-name-option-n']").text(name.substring(0, max_length));
      $("label[for='" + base[0] + "badge-name-option-f']").text(first_name.substring(0, max_length));
      $("label[for='" + base[0] + "badge-name-option-l']").text(name_last.substring(0, max_length));
    });
  }
  load();
  
  $(".edit-member-quantity").blur(function(event) {
    load();
  });

})(jQuery);
