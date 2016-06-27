/**
 * @file
 */

(function ($, Drupal, drupalSettings) {
  'use strict';

  Drupal.behaviors.dcxEntityBrowser = {
    attach: function (context, settings) {

      var dropzoneWidget = $('.dcx-dropzone-widget');
      var dropArea = dropzoneWidget.find('.dcx-dropzone');

      dropzoneWidget.on('dragover dragenter', function (event) {
        event.preventDefault();
        dropArea.addClass('is-dragover-widget');
      });

      dropzoneWidget.on('dragleave dragend drop', function (event) {
        event.preventDefault();
        dropArea.removeClass('is-dragover-widget');
      });

      dropArea.on('dcxDropzone:success', function (e) {
        dropzoneWidget.find('div.view-filters input[type=submit]').click();
      });

      dropArea.on('dcxDropzone:dropped', function (e) {
        dropArea.removeClass('is-dragover-widget');
      });

    }
  };

})(jQuery, Drupal, drupalSettings);
