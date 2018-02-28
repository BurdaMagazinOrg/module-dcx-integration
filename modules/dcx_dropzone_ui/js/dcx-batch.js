/**
 * @file
 * Drupal's batch API.
 */

(function ($, Drupal) {

  'use strict';

  /**
   * Attaches the batch behavior to progress bars.
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.batch = {
    attach: function (context, settings) {
      var batch = settings.batch;
      var $progress = $('[data-drupal-progress]').once('batch');
      var progressBar;

      // Success: redirect to the summary.
      function updateCallback(progress, status, pb) {
        if (progress === '100') {
          pb.stopMonitoring();
          $.ajax({
            url: batch.uri + '&op=finished',
            success: function (data, success, response) {
              $progress.html(data['markup'] + $progress.parent().data('content'));
              $('.view-filters input[type=submit]').click();
            }
          });
        }
      }
      function errorCallback(pb) {
        $progress.prepend($('<p class="error"></p>').html(batch.errorMessage));
        $('#wait').hide();
      }

      if ($progress.length) {
        progressBar = new Drupal.ProgressBar('updateprogress', updateCallback, 'POST', errorCallback);
        progressBar.setProgress(-1, batch.initMessage);
        progressBar.startMonitoring(batch.uri + '&op=do', 10);
        // Remove HTML from no-js progress bar.
        $progress.empty();
        // Append the JS progressbar element.
        $progress.append(progressBar.element);
      }
    }
  };

})(jQuery, Drupal);
