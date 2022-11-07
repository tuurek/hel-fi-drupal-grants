(function (Drupal, drupalSettings) {
  Drupal.behaviors.GrantsHandlerApplicationStatusCheck = {
    attach: function (context, settings) {
      var pollFrequency = 2000
      var maxFrequency = 180000
      var timerInterval = null
      var applicationNumber = null
      var requestUrl = null
      var currentStatus = null
      var statusTagElement = null

      function changeValue() {
        stop()
        pollFrequency = Math.min(maxFrequency, pollFrequency * 1.5)
        const xhttp = new XMLHttpRequest()
        if (statusTagElement !== null) {
          statusTagElement.classList.remove("hide-spinner")
        }
        var dataJson = currentStatus;
        xhttp.onload = function() {
          data = this.responseText
          try {
            dataJson = JSON.parse(data).data.value
          } catch (e) {
            statusTagElement.classList.add("show-error")
          }
          if (dataJson !== currentStatus) {
            location.reload()
          }
          if (statusTagElement !== null) {
            statusTagElement.classList.add("hide-spinner")
          }
          start(pollFrequency)
        }
        xhttp.open("GET", requestUrl)
        xhttp.send()

      }

      function start(timeoutValue) {
        stop() // stoping the previous counting (if any)
        timerInterval = setInterval(changeValue, timeoutValue)
      }
      var stop = function() {
        clearInterval(timerInterval)
      }
      var onlyOneCheckable = document.getElementsByClassName('applicationStatusCheckable')

      if (onlyOneCheckable.length == 1) {
        applicationNumber = onlyOneCheckable[0].getAttribute('data-application-number')
        requestUrl = drupalSettings.grants_handler.site_url + '/grants-metadata/status-check/' + applicationNumber
        currentStatus = onlyOneCheckable[0].getAttribute('data-status')
        statusTagElement = onlyOneCheckable[0];
        start(pollFrequency)
      }
    }
  };
})(Drupal, drupalSettings);
