jQuery(function ($) {
  var show_modal_wait = false
  $('.briox.sync').on('click', function (e) {
    e.preventDefault()
    var button = $(this)
    button.prop('disabled', true)
    button.html('...')

    var orderId = button.data('order-id')
    var data = {
      action: 'briox_sync',
      order_id: orderId,
      nonce: briox.nonce
    }

    jQuery.post(ajaxurl, data, function (response) {
      console.log(response)
      button.prop('disabled', false)
      button.html('Synced')
      window.location.reload(false)
    })
  })

  $('.briox-close').on('click', function (e) {
    var modal = document.getElementById('briox-modal-id')
    if (modal) { modal.style.display = 'none' }
    show_modal_wait = false
  })

  $('.briox.update_product').on('click', function (e) {
    e.preventDefault()
    var button = $(this)
    button.prop('disabled', true)
    button.html('...')

    var product_id = button.data('product-id')
    var data = {
      action: 'briox_update_article',
      product_id: product_id,
      nonce: briox.nonce
    }

    jQuery.post(ajaxurl, data, function (response) {
      console.log(response)
      button.prop('disabled', false)
      button.html('Update')
      window.location.reload(false)
    })
  })

  $('.briox_connection').on('click', function (e) {
    e.preventDefault()
    var authentication_token = $('#briox_authentication_token');
    var client_identifier = $('#briox_client_identifier')
    var user_email = $('#briox_user_email')
    $.post(ajaxurl, { action: 'briox_connection', nonce: briox.nonce, authentication_token: authentication_token.val(), client_identifier: client_identifier.val(), user_email: user_email.val(), id: e.target.id }, function (response) {
      if ('briox_connect' == e.target.id && response.result == 'success') {
        show_modal_wait = true
        waitForConfirmation()
      } else if (response.result == 'success') {
        var message = $('<div id="message" class="updated"><p>' + response.message + '</p></div>')
        message.hide()
        message.insertBefore($('#briox_titledesc_connect'))
        message.fadeIn('fast', function () {
          setTimeout(function () {
            message.fadeOut('fast', function () {
              message.remove()
              window.location.reload()
            })
          }, 5000)
        })
      } else if (response.result == 'error') {
        alert(response.message)
      }
    })
  })

  $('#briox_sync_all').on('click', function (e) {
    e.preventDefault()
    jQuery.post(ajaxurl, { action: 'briox_sync_all', nonce: briox.nonce }, function (response) {
      if (response.result == 'success' || response.result == 'error') {
        var message = jQuery('<div id="message" class="updated"><p>' + response.message + '</p></div>')
        message.hide()
        message.insertBefore(jQuery('#briox_titledesc_sync_all'))
        message.fadeIn('fast', function () {
          setTimeout(function () {
            message.fadeOut('fast', function () {
              message.remove()
            })
          }, 10000)
        })
      }
    })
  })

  $('#briox_sync_wc_products').on('click', function (e) {
    e.preventDefault()
    jQuery.post(ajaxurl, { action: 'briox_sync_wc_products', nonce: briox.nonce }, function (response) {
      if (response.result == 'success' || response.result == 'error') {
        var message = jQuery('<div id="message" class="updated"><p>' + response.message + '</p></div>')
        message.hide()
        message.insertBefore(jQuery('#briox_titledesc_sync_wc_products'))
        message.fadeIn('fast', function () {
          setTimeout(function () {
            message.fadeOut('fast', function () {
              message.remove()
            })
          }, 10000)
        })
      }
    })
  })

  $('#briox_sync_stripe').on('click', function (e) {
    e.preventDefault()
    var sync_days = prompt(briox.sync_message, '1')
    if (sync_days != null && sync_days != '' && !isNaN(sync_days)) {
      jQuery.post(ajaxurl, { action: 'briox_sync_stripe', sync_days: sync_days, nonce: briox.nonce }, function (response) {
        if (response.result == 'success' || response.result == 'error') {
          var message = jQuery('<div id="message" class="updated"><p>' + response.message + '</p></div>')
          message.hide()
          message.insertBefore(jQuery('#briox_titledesc_sync_stripe'))
          message.fadeIn('fast', function () {
            setTimeout(function () {
              message.fadeOut('fast', function () {
                message.remove()
              })
            }, 10000)
          })
        }
      })
    } else if (sync_days != null && isNaN(sync_days)) {
      alert(briox.sync_warning)
    }
  })

  $('#briox_sync_paypal').on('click', function (e) {
    e.preventDefault()
    var sync_days = prompt(briox.sync_message, '1')
    if (sync_days != null && sync_days != '' && !isNaN(sync_days)) {
      jQuery.post(ajaxurl, { action: 'briox_sync_paypal', sync_days: sync_days, nonce: briox.nonce }, function (response) {
        if (response.result == 'success' || response.result == 'error') {
          var message = jQuery('<div id="message" class="updated"><p>' + response.message + '</p></div>')
          message.hide()
          message.insertBefore(jQuery('#briox_titledesc_sync_paypal'))
          message.fadeIn('fast', function () {
            setTimeout(function () {
              message.fadeOut('fast', function () {
                message.remove()
              })
            }, 10000)
          })
        }
      })
    } else if (sync_days != null && isNaN(sync_days)) {
      alert(briox.sync_warning)
    }
  })

  $('#briox_sync_izettle').on('click', function (e) {
    e.preventDefault()
    var sync_days = prompt(briox.sync_message, '1')
    if (sync_days != null && sync_days != '' && !isNaN(sync_days)) {
      jQuery.post(ajaxurl, { action: 'briox_sync_izettle', sync_days: sync_days, nonce: briox.nonce }, function (response) {
        if (response.result == 'success' || response.result == 'error') {
          var message = jQuery('<div id="message" class="updated"><p>' + response.message + '</p></div>')
          message.hide()
          message.insertBefore(jQuery('#briox_titledesc_sync_izettle'))
          message.fadeIn('fast', function () {
            setTimeout(function () {
              message.fadeOut('fast', function () {
                message.remove()
              })
            }, 10000)
          })
        }
      })
    } else if (sync_days != null && isNaN(sync_days)) {
      alert(briox.sync_warning)
    }
  })

  $('#briox_sync_klarna').on('click', function (e) {
    e.preventDefault()
    var sync_days = prompt('Number of days to sync', '1')
    if (sync_days != null && sync_days != '' && !isNaN(sync_days)) {
      jQuery.post(ajaxurl, { action: 'briox_sync_klarna', sync_days: sync_days, nonce: briox.nonce }, function (response) {
        if (response.result == 'success' || response.result == 'error') {
          var message = jQuery('<div id="message" class="updated"><p>' + response.message + '</p></div>')
          message.hide()
          message.insertBefore(jQuery('#briox_titledesc_sync_klarna'))
          message.fadeIn('fast', function () {
            setTimeout(function () {
              message.fadeOut('fast', function () {
                message.remove()
              })
            }, 10000)
          })
        }
      })
    } else if (sync_days != null && isNaN(sync_days)) {
      alert(briox.sync_warning)
    }
  })

  $('#briox_clear_cache').on('click', function (e) {
    e.preventDefault()
    console.log(briox)
    jQuery.post(ajaxurl, { action: 'briox_clear_cache', nonce: briox.nonce }, function (response) {
      if (response.result == 'success' || response.result == 'error') {
        var message = jQuery('<div id="message" class="updated"><p>' + response.message + '</p></div>')
        message.hide()
        message.insertBefore(jQuery('#briox_titledesc_clear_cache'))
        message.fadeIn('fast', function () {
          setTimeout(function () {
            message.fadeOut('fast', function () {
              message.remove()
            })
          }, 10000)
        })
      }
    })
  })

  $('.notice-dismiss').on('click', function (e) {
    var is_bi_notice = jQuery(e.target).parents('div').hasClass('bi_notice');
    if (is_bi_notice) {
      var parents = jQuery(e.target).parent().prop('className');
      jQuery.post(ajaxurl, { action: 'briox_clear_notice', nonce: briox.nonce , parents: parents }, function (response) { })
    }
  });

  function waitForConfirmation() {
    if (show_modal_wait) {
      var modal = document.getElementById('briox-modal-id')
      if (modal) { modal.style.display = 'block' }

      jQuery.post(ajaxurl, { 'action': 'briox_check_activation', 'nonce': briox.nonce }, function (response) {
        var message = response.message
        document.getElementById('briox-status').innerHTML = message

        if (response.status == 'success') {
          var modal = document.getElementById('briox-modal-id')
          if (modal) { modal.style.display = 'none' }
          show_modal_wait = false
          window.location.reload()
          return
        } else if (response.status == 'failure') {
          return
        } else {
          setTimeout(function () { waitForConfirmation() }, 1000)
        }
      })
    }
  }
})
