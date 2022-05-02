(function ($) {
  'use strict';

  Drupal.behaviors.disableOnClick = {
    attach: function (context, settings) {

      // Function to check "select all" if all checkboxes are selected.
      function checkAllSelected() {
        let allSelected = true;
        $('.checkbox-selectable').each(function(check) {
          if (!this.checked) allSelected = false;
        });
        $('.select-all').prop('checked', allSelected);
      }

      // Check initial state (if all checkboxes checked, check all should start checked).
      checkAllSelected();

      // Function to check all checkboxes when "select all" checked.
      $('.select-all', context).on('click', function(event) {
        $('.checkbox-selectable').prop('checked', event.target.checked);
      });

      // Function to update "select all" when checkboxes change. Set to checked if all checkboxes checked, otherwise unchecked.
      $('.checkbox-selectable').on('click', function() {
        checkAllSelected();
      });

    }
  };

}(jQuery));