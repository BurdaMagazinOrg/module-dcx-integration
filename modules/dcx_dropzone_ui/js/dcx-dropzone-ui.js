/**
 * @file
 */

(function ($, Drupal, drupalSettings) {
  'use strict';

  Drupal.behaviors.dcxDropzoneUi = {
    attach: function (context, settings) {

      var dropzone_id = drupalSettings.dcx_dropzone.dropzone_id;
      var dropzone = $('#' + dropzone_id);
      var counterBox = dropzone.find('.box__uploading .counter');
      var counter;

      dropzone.on('dragover dragenter', function (event) {
        event.preventDefault();
        dropzone.addClass('is-dragover');
      });

      dropzone.on('dragleave dragend drop', function (event) {
        event.preventDefault();
        dropzone.removeClass('is-dragover');
      });

      dropzone.on('drop', function (event) {
        event.preventDefault();

        dropzone.trigger('dcxDropzone:dropped');

        if (dropzone.hasClass('is-uploading')) {
          return false;
        }

        dropzone.addClass('is-uploading').removeClass('is-error');

        var uris = decodeURIComponent(event.originalEvent.dataTransfer.getData('text/plain')).split('\n');

        counter = uris.length;
        decreaseAndUpdateCounter();

        for (var index = 0; index < uris.length; ++index) {

          var uri = uris[index];
          if (uri) {
            uri = uri.split('?')[0];

            $.ajax({
              url: '/dcx-migration/upload',
              method: 'POST',
              data: JSON.stringify([{'documenttype-image': uri.substr(uri.indexOf('document'))}])
            }).complete(function () {
              decreaseAndUpdateCounter();
              if (counter <= 0) {
                dropzone.removeClass('is-uploading');
              }
            }).success(function (data) {
              if (counter <= 0) {
                dropzone.addClass(!data.success ? 'is-error' : 'is-success');
              }
              dropzone.trigger('dcxDropzone:success');
            });
          }
        }

      });

      function decreaseAndUpdateCounter() {
        counter--;
        counterBox.html(' ' + counter + ' ');
      }
    }
  };

})(jQuery, Drupal, drupalSettings);
