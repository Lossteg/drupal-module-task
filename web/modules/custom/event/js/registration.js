(function ($, Drupal, once) {
  'use strict';

  Drupal.behaviors.eventRegistration = {
    attach: function (context, settings) {
      once('event-registration', '.register-event-button', context).forEach(function (button) {
        $(button).on('click', function(e) {
          e.preventDefault();

          const $button = $(this);
          const $message = $('.registration-message');

          $.ajax({
            url: this.href,
            method: 'POST',
            success: function(response) {
              if (response.status) {
                $button.remove();
                $message.html(response.message).
                removeClass('error').
                addClass('success').
                show();
              } else {
                $message.html(response.message).removeClass('success').addClass('error');
              }
            },
            error: function(xhr, status, errorMessage) {
              $message.html(
                'An error occurred during registration: ' + errorMessage
              ).removeClass('success').addClass('error');
            }
          });
        });
      });
    }
  };
})(jQuery, Drupal, once);
