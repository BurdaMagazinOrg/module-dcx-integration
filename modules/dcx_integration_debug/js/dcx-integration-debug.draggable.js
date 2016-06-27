/**
 * @file
 */

(function ($, Drupal, drupalSettings) {
  'use strict';

  Drupal.behaviors.dcxIntegrationDebugDraggable = {
    attach: function (context, settings) {
      var draggables = $('.draggable .item');

      draggables.prop('draggable', true);

      $('.draggable .single').on('dragstart', function (event) {
        var data = [{'documenttype-image': 'document/docABC'}];
        event.originalEvent.dataTransfer.setData('text/uri-list', JSON.stringify(data));
      });
      $('.draggable .list').on('dragstart', function (event) {
        var data = [{'documenttype-image': 'document/docABC'}, {'documenttype-image': 'document/doc123'}];
        event.originalEvent.dataTransfer.setData('text/uri-list', JSON.stringify(data));
      });
    }
  };

})(jQuery, Drupal, drupalSettings);
