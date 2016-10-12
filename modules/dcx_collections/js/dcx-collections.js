/**
 * @file
 */

(function ($, Drupal, drupalSettings) {
  'use strict';

  Drupal.behaviors.dcxCollections = {
    attach: function (context, settings) {
      var baseUrl = drupalSettings.path.baseUrl == "/"?'':drupalSettings.path.baseUrl;
      var collections = $('details.dcx-collection');

      $('summary', collections).once('dcx-collection').click(function() {
        var summary = $(this);

        if (summary.hasClass('processed')) return;
        summary.addClass('processed');

        var id = summary.parent().attr('data-id');
        var previewArea = summary.parent().find('#dcx-preview-' + id);

        $.ajax({
          'type': 'GET',
          'url': baseUrl + '/dcx/collection/' + id,
          'previewArea': previewArea,
          'success': function(data) {
            var previewArea = this.previewArea;
            $.each(data, function(i,d) {
              var imagePreviewWrapper = $("<span></span>").attr('data-id', d);
              previewArea.append(imagePreviewWrapper);
              setupPreview(imagePreviewWrapper);
            });
          }
        });
      });
    }
  }

  function setupPreview(wrapper) {
    var id = wrapper.attr('data-id').replace(/dcxapi:document\//, '');
    var baseUrl = drupalSettings.path.baseUrl == "/"?'':drupalSettings.path.baseUrl;
    $.ajax({
      'type': 'GET',
      'url': baseUrl + '/dcx/collection/preview-image/' + id,
      'wrapper': wrapper,
      'success': function(data) {
        this.wrapper.html($('<img>').attr('src', data.url));
        this.wrapper.append($('<div>').html(data.filename));
        this.wrapper.on('dragstart', function(ev) {
          ev.originalEvent.dataTransfer.setData("text/plain", data.id);
        });
      }
    });
  }
})(jQuery, Drupal, drupalSettings);
