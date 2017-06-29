/**
 * @file
 */

(function ($, Drupal, drupalSettings) {
  'use strict';

  Drupal.behaviors.dcxArticleImportUi = {
    attach: function (context, settings) {
      var submitButton = $('#edit-submit');
      var textfield = $('#edit-dcx-id');

      var dropzone = $('<div></div>').addClass('dcx-dropzone');
      var message = $('<div>' + Drupal.t('Drop DC-X story link here!') + '</div>').addClass('message').appendTo(dropzone);
      dropzone.insertBefore(textfield);

      dropzone.on('dragover dragenter', function (event) {
        event.preventDefault();
      });

      dropzone.on('dragleave dragend drop', function (event) {
        event.preventDefault();
      });

      dropzone.on('drop', function (event) {
        event.preventDefault();

        var uris = decodeURIComponent(event.originalEvent.dataTransfer.getData('text/plain')).split('\n');
        if (uris.length !== 1) {
          dropzone.css('backgroundColor', '#FFC0CB');
          message.html(Drupal.t('Please provide exactly one link!'));
          return;
        }
        var match = uris[0].match(/(document\/(doc|dc5burda-)[\w]+).*x-doctype=documenttype-story/);

        if (!match) {
          dropzone.css('backgroundColor', '#FFC0CB');
          message.html(Drupal.t('Please provide a valid story link!'));
        }

        var id = match[1];

        textfield.val(id);
        submitButton.click();
      });
    }
  };

})(jQuery, Drupal, drupalSettings);
