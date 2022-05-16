function clearValidity (element) {
  element.setCustomValidity('')
}

function setValidity (element, message) {
  element.setCustomValidity(message)
  element.reportValidity()
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

function submitForm (event) {
  event.preventDefault()

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

  const submitButton = $('#submit-button')

  const oldValue = submitButton.text()

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

  console.log($.param(params))

  fetch('vote.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json'
    },
    body: JSON.stringify(params)
  }).then(response => {
    response.text().then(
      response => {
        // error messages
        const success = 0
        const invalid_argument = 1
        const voted_already = 2
        const invalid_credentials = 3
        const session_timeout = 4
        const internal_server_error = 5

        console.log(response)
      }
    )
  })

  // if we passed the "check" stage, hide our previous elements, change color of the button to green
  // present the user with a few options to vote for
  // if we failed, change the button to a red outline with clear background with "try again" written inside
  // and display the according error message

  return false
}

$(() => {
  $('#id-number').on('input', function () {
    clearValidity(this)
  })

  $('#birth-year').on('input', function () {
    clearValidity(this)
  })

  $('#vote-form-element').submit(submitForm)

  fetch('/provinces.json').then(
    value => {
      value.json().then(
        provincesJson => {
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
        }
      )
    }
  )
})
