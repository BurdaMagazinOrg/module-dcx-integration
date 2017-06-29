/**
 * @file
 */

(function ($, Drupal, drupalSettings) {
  'use strict';

  Drupal.behaviors.dcxEntityBrowser = {
    attach: function (context, settings) {

      var dropzoneWidget = $('.dcx-dropzone-widget');
      var dropArea = dropzoneWidget.find('.dcx-dropzone');
      var timer;

      dropzoneWidget.on('dragover dragenter', function (event) {
        event.preventDefault();
        dropArea.addClass('is-dragover-widget');
      });

      dropzoneWidget.on('dragleave dragend drop', function (event) {
        event.preventDefault();
        dropArea.removeClass('is-dragover-widget');
      });

      dropArea.on('dcxDropzone:success', function (event) {
        clearInterval(timer);
        updateView();
      });

      dropArea.on('dcxDropzone:dropped', function (event) {

        dropArea.removeClass('is-dragover-widget');
        if (!timer) {
          timer = setInterval(updateView, 1500);
        }
      });

      function updateView() {
        dropzoneWidget.find('div.view-filters input[type=submit]').click();
      }
    }
  };
})(jQuery, Drupal, drupalSettings);
