/**
 * @file
 * Simple Convention Registration.
 */

(function ($) {
  Drupal.behaviors.simple_conreg = {
    attach: function (context, settings) {
      $('.edit-members-first-name,.edit-members-last-name', context).on('input',function(event) {
        var reg=/^([a-zA-Z0-9]+\-){3}/
        var base=reg.exec(event.target.id);
        var max_length = drupalSettings.simple_conreg.badge_name_max;
        var first_name=$("#" + base[0] + "first-name").val().trim();
        var last_name=$("#" + base[0] + "last-name").val().trim();
        var name=first_name.concat(" ", last_name);
        var name_last=last_name.concat(", ", first_name);
        $("." + base[0] + "badge-name-option[value='N'] + label").text(name.substring(0, max_length));
        $("." + base[0] + "badge-name-option[value='F'] + label").text(first_name.substring(0, max_length));
        $("." + base[0] + "badge-name-option[value='L'] + label").text(name_last.substring(0, max_length));
      });
      
      // Loop through badge name option fields to determine whether to show "custom" badge name.
      $('.edit-members-badge-name-option').each(function(i, obj) {
        showBadgeNameOther(obj);
      });

      // If "other" badge name option selected, make custom badge name field visible, otherwise hide.
      $('.edit-members-badge-name-option').change(function(event) {
        showBadgeNameOther(event.currentTarget);
      });

      // If free amount entered, 
      $(".edit-free-amt", context).on('input',function(event) {
        const regex = /member[0-9]+/g
        const found = event.target.id.match(regex);
        if (found) {
          var memberTotal = Number($("#edit-"+found[0]+"-price-minus-free-amt").val());
          memberTotal += Number(event.target.value);
          $("#"+found[0]+"-value").text(memberTotal.toFixed(2));
        }
        var total = Number($("#edit-total-minus-free-amt").val());
        $(".edit-free-amt").each(function(index) {
          total += Number($(this).val());
        });
        $("#total-value").text(total.toFixed(2));
        if (total == 0) {
          $("#edit-payment-submit").val(drupalSettings.submit.free);
        }
        else {
          $("#edit-payment-submit").val(drupalSettings.submit.payment);
        }
      });
    }
  };
})(jQuery);

function showBadgeNameOther(obj)
{
  if (obj.checked) {
    var badgeOther = obj.parentNode.parentNode.parentNode.parentNode.parentNode.querySelector(".edit-members-badge-name-container")
    if (obj.value == 'O') {
      badgeOther.style.display = "block";
      badgeOther.querySelector(".edit-members-badge-name-other").required = true;
      badgeOther.querySelector("label").classList.add("form-required");
    }
    else {
      badgeOther.style.display = "none";
      badgeOther.querySelector(".edit-members-badge-name-other").required = false;
      badgeOther.querySelector("label").classList.remove("form-required");
    }
  }
}

