/**
 * @file
 */

(function ($, Drupal, drupalSettings) {
  'use strict';

  Drupal.behaviors.dcxCollections = {
    attach: function (context, settings) {
      var docs = $('#edit-collections .dcx-preview');
      docs.each(function(i, d) {
        var doc = $(d);
        var id = doc.attr('data-id').replace(/dcxapi:document\//, '');
        var baseUrl = drupalSettings.path.baseUrl == "/"?'':drupalSettings.path.baseUrl;
        $.ajax({
          'type': 'GET',
          'url': baseUrl + '/dcx/preview/' + id,
          'doc': doc,
          'success': function(data) {
            this.doc.html($('<img>').attr('src', data.url));
            this.doc.append($('<div>').html(data.filename));
            this.doc.on('dragstart', function(ev) {
              ev.originalEvent.dataTransfer.setData("text/plain", data.id);
            });
          }
        });
      });
    }
  };
})(jQuery, Drupal, drupalSettings);
