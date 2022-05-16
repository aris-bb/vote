function clearValidity (element) {
  element.setCustomValidity('')
}

function setValidity (element, message) {
  element.setCustomValidity(message)
  element.reportValidity()
}

function onError (error) {
  const errorMessage = $('#error-message')
  errorMessage.text(error.message)
  errorMessage.show()
}

function validateIdNumber (idNumber) {
  const erroneous = new Set(['11111111110', '22222222220', '33333333330', '44444444440', '55555555550', '66666666660', '77777777770', '88888888880', '99999999990'])

  if (erroneous.has(idNumber)) {
    return false
  }

  idNumber = Array.from(idNumber, Number)

  if (idNumber[0] === 0) {
    return false
  }

  let sum = 0
  for (let i = 0; i < 10; i++) {
    sum += idNumber[i]
  }

  if (sum % 10 !== idNumber[10]) {
    return false
  }

  return true
}

function makeApiCall (params, callback) {
  fetch('vote.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json'
    },
    body: JSON.stringify(params)
  }).then(response => {
    response.text().then(
      response => {
        callback(response)
      }
    )
  }).catch(onError)
}

function submitForm (event) {
  event.preventDefault()

  // error values
  const success = '0'
  const invalidArgument = '1'
  const votedAlready = '2'
  const invalidCredentials = '3'
  const sessionTimeout = '4'
  const internalServerError = '5'

  const errorMessages = {
    [invalidArgument]: 'Geçersiz parametre.',
    [votedAlready]: 'Zaten oy vermişsiniz.',
    [invalidCredentials]: 'Geçersiz kimlik bilgileri.',
    [sessionTimeout]: 'Lütfen sayfayı yenileyin.',
    [internalServerError]: 'Sunucu hatası.'
  }

  const submitButton = $('#submit-button')

  const errorMessage = $('#error-message')
  errorMessage.hide()

  if (!submitButton.hasClass('vote-button')) {
    // checking part, do the initial validation

    const idNumber = $('#id-number')
    clearValidity(idNumber.get(0))

    const birthYear = $('#birth-year')
    clearValidity(birthYear.get(0))

    if (idNumber.val().toString().length !== 11) {
      setValidity(idNumber.get(0), 'Kimlik numarası 11 haneli olmalıdır.')
      return false
    }

    if (!validateIdNumber(idNumber.val())) {
      setValidity(idNumber.get(0), 'Geçersiz kimlik numarası.')
      return false
    }

    if (birthYear.val().toString().length !== 4) {
      setValidity(birthYear.get(0), 'Doğum yılı 4 haneli olmalıdır.')
      return false
    }

    if (birthYear.val() > 2022 || birthYear.val() < 1800) {
      setValidity(birthYear.get(0), 'Geçersiz doğum yılı.')
      return false
    }

    submitButton.text(' Kontrol ediliyor...')
    submitButton.prepend($('<i/>', { class: 'fas fa-cog fa-spin' }))

    const params = {
      action: 'check',
      id_number: idNumber.val(),
      birth_year: birthYear.val(),
      name: $('#name').val(),
      surname: $('#surname').val(),
      province_id: $('#province').val(),
      terminal_id: $('#terminal-id').val()
    }

    makeApiCall(params, response => {
      if (response === success) {
        $('#main-title').text('Lütfen bir seçenek seçin')

        $('#part-1').hide()
        $('#internal-data').find('*').prop('disabled', true)

        $('#vote-options').show()

        submitButton.text('Oy ver')
        submitButton.addClass('vote-button')

        $('.vote-radio').prop('required', true)
      } else {
        submitButton.text('Tekrar dene')
        if (response in errorMessages) {
          errorMessage.text(errorMessages[response])
        } else {
          errorMessage.text(errorMessages[internalServerError])
        }
        errorMessage.show()
      }
    })
  } else {
    submitButton.text(' Oy veriliyor...')
    submitButton.prepend($('<i/>', { class: 'fas fa-cog fa-spin' }))

    const params = {
      action: 'vote',
      voted_for: $('input[name="flex-radio"]:checked', '#vote-form-element').val()
    }

    makeApiCall(params, response => {
      if (response === success) {
        $('#main-title').text('Oyunuz kaydedilmiştir.')

        $('#internal-data').hide()
        $('#vote-options').hide()

        submitButton.text('Oy ver')
        submitButton.prop('disabled', true)
      } else {
        submitButton.text('Tekrar dene')
        if (response in errorMessages) {
          errorMessage.text(errorMessages[response])
        } else {
          errorMessage.text(errorMessages[internalServerError])
        }
        errorMessage.show()
      }
    })
  }

  return false
}

$(() => {
  $('#vote-options').hide()

  $('#id-number').on('input', function () {
    clearValidity(this)
  })

  $('#birth-year').on('input', function () {
    clearValidity(this)
  })

  $('#vote-form-element').submit(submitForm)

  fetch('/vote/www/provinces.json').then(
    response => {
      if (response.ok) {
        return response.json()
      }
      throw new Error('Error while retrieving provinces.json')
    }
  ).then(provincesJson => {
    const sortedProvinces = Object.entries(provincesJson).map(([id, provinceName]) => ({ [provinceName]: id }))

    sortedProvinces.sort((l, r) => {
      const [leftProvinceName] = Object.keys(l)
      const [rightProvinceName] = Object.keys(r)

      return leftProvinceName.localeCompare(rightProvinceName)
    })

    $.each(sortedProvinces, (_, provincePair) => {
      $('#province').append($('<option/>', {
        value: Object.values(provincePair),
        text: Object.keys(provincePair)
      }))
    })
  }).catch(onError)

  fetch('/vote/www/vote_options.json').then(
    response => {
      if (response.ok) {
        return response.json()
      }
      throw new Error('Error while retrieving vote_options.json')
    }
  ).then(voteOptions => {
    $.each(voteOptions, (key, value) => {
      $('#vote-options').append(
        $('<div/>', {
          class: 'form-check'
        }).append([
          $('<input/>', {
            class: 'form-check-input vote-radio',
            type: 'radio',
            id: value,
            name: 'flex-radio',
            value: key
          }),
          $('<label/>', {
            class: 'form-check-label',
            for: value,
            text: value
          })
        ])
      )
    })
  }).catch(onError)
})
