/**
 * @file
 */

(function ($, Drupal, drupalSettings) {
  'use strict';

  Drupal.behaviors.dcxDropzoneUi = {
    attach: function (context, settings) {

      var dropzone_id = drupalSettings.dcx_dropzone.dropzone_id;
      var dropzone = $('#' + dropzone_id);

      dropzone.on('dragover dragenter', function (event) {
        event.preventDefault();
        dropzone.parent().addClass('is-dragover');
      });

      dropzone.on('dragleave dragend drop', function (event) {
        event.preventDefault();
        dropzone.parent().removeClass('is-dragover');
      });

      dropzone.on('drop', function (event) {
        event.preventDefault();

        dropzone.trigger('dcxDropzone:dropped');

        if (dropzone.parent().hasClass('is-uploading')) {
          return false;
        }

        dropzone.parent().addClass('is-uploading').removeClass('is-error');

        var uris = decodeURIComponent(event.originalEvent.dataTransfer.getData('text/plain')).split('\n');

        var data = [];
        for (var index = 0; index < uris.length; ++index) {
          var uri = uris[index];
          if (uri) {
            uri = uri.split('?')[0];
            data.push({'documenttype-image': uri.substr(uri.indexOf('document'))});
          }
        }

        $.ajax({
          url: '/dcx-dropzone/upload',
          method: 'POST',
          data: JSON.stringify(data)
        }).complete(function () {
          dropzone.parent().removeClass('is-uploading');
          dropzone.trigger('dcxDropzone:success');
        }).success(function (data, success, response) {
          drupalSettings['batch'] = data['settings'];
          var html = dropzone.html();
          dropzone.filter(':not(.orig-processed)').addClass('orig-processed').each(function () {
            dropzone.data('content', html);
          });
          dropzone.html(data['markup']);
        });
      });


    }
  };

})(jQuery, Drupal, drupalSettings);
