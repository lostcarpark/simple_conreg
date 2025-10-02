(function (Drupal) {
  Drupal.behaviors.tablecopy = {
    attach: function (context) {
      const copyButtons = document.querySelectorAll('.table-copy');
      if (copyButtons) {
        copyButtons.forEach(element => {
          element.addEventListener("click", function() {
            const tableElements = document.querySelectorAll('table');
            if (tableElements) {
              tableElements.forEach(tableElement => {
                const range = document.createRange();
                range.selectNode(tableElement);
                window.getSelection().addRange(range);
              });
              document.execCommand("copy");
            }
            return false;
          });
        });
      }
    }
  }
})(Drupal);
