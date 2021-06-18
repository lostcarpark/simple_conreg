/**
 * @file
 * JavaScript handlers for ConReg field options.
 */

(function ($) {
  Drupal.behaviors.conreg_fieldoptions = {
    attach: function (context, settings) {
      // Attach handler to field checkboxes with options.
      $(".field-has-options").change(function (event) {
        showOptions(event.currentTarget);
      });
      // Attach handler to fieldOption checkboxes.
      $(".field-option-has-detail").change(function (event) {
        showDetail(event.currentTarget);
      });
      // Loop through fields with options and hide the options where applicable.
      $(".field-has-options").each(function(i, obj) {
        showOptions(obj);
      });
      // Loop through fieldOption checkboxes and hide details where applicable.
      $(".field-option-has-detail").each(function(i, obj) {
        showDetail(obj);
      });
    }
  }
})(jQuery);

function showOptions(obj) {
  var show = obj.checked;
  var container = obj.parentNode.parentNode;
  var options = container.querySelector('fieldset');
  if (show) {
    options.style.display = "block";
  }
  else {
    options.style.display = "none";
  }
  options.querySelectorAll(".must-select").forEach(function(option) {
    option.required = show;
  });
}

function showDetail(obj) {
  var show = obj.checked;
  var container = obj.parentNode.parentNode;
  var detail = container.querySelector('.field-option-detail').parentNode;
  if (show) {
    detail.style.display = "block";
  }
  else {
    detail.style.display = "none";
  }
  container.querySelectorAll(".detail-required").forEach(function(textfield) {
    textfield.required = show;
  });
}

