const ideaForm = document.getElementById('ideaForm');
const ideaInput = document.getElementById('ideaInput');
const submitBtn = document.getElementById('submitBtn');
const resultBox = document.getElementById('resultContainer');
const csrfTokenInput = document.getElementById('csrfToken') || document.querySelector('input[name="csrf_token"]');
const csrfToken = csrfTokenInput ? csrfTokenInput.value : '';

function debounce(fn, delay) {
  let timer = null;
  return function (...args) {
    clearTimeout(timer);
    timer = setTimeout(() => {
      fn.apply(this, args);
    }, delay);
  };
}

function sanitize(input) {
  const div = document.createElement('div');
  div.textContent = input;
  return div.innerHTML.trim();
}

function clearResultClasses() {
  resultBox.classList.remove('info', 'success', 'error', 'visible');
}

function validateInput(e) {
  let raw = e.target.value;
  const clean = sanitize(raw);
  if (raw !== clean) {
    e.target.value = clean;
    raw = clean;
  }
  if (clean.length < 3) {
    submitBtn.disabled = true;
    clearResultClasses();
    resultBox.classList.add('info', 'visible');
    resultBox.textContent = 'Please enter at least 3 characters.';
    if (window.AriaLiveAnnouncer) {
      window.AriaLiveAnnouncer.announcePolite(resultBox.textContent);
    }
  } else {
    submitBtn.disabled = false;
    clearResultClasses();
    resultBox.textContent = '';
    resultBox.classList.remove('visible');
  }
}

function showSpinner() {
  submitBtn.classList.add('loading');
}

function hideSpinner() {
  submitBtn.classList.remove('loading');
}

function showError(err) {
  clearResultClasses();
  resultBox.classList.add('error', 'visible');
  resultBox.textContent = typeof err === 'string' ? err : (err.message || 'An unexpected error occurred.');
  if (window.AriaLiveAnnouncer) {
    window.AriaLiveAnnouncer.announceAssertive(resultBox.textContent);
  }
}

function renderResult(data) {
  if (!data || typeof data.valid !== 'boolean') {
    showError('Invalid server response.');
    return;
  }
  clearResultClasses();
  if (data.valid) {
    resultBox.classList.add('success', 'visible');
    resultBox.textContent = data.feedback || 'Your idea looks good!';
    if (window.AriaLiveAnnouncer) {
      window.AriaLiveAnnouncer.announcePolite(resultBox.textContent);
    }
  } else {
    resultBox.classList.add('error', 'visible');
    resultBox.textContent = data.feedback || 'Sorry, that idea may not work.';
    if (window.AriaLiveAnnouncer) {
      window.AriaLiveAnnouncer.announceAssertive(resultBox.textContent);
    }
  }
}

function sendIdeaToServer(e) {
  if (e && e.preventDefault) e.preventDefault();
  const idea = sanitize(ideaInput.value);
  if (!idea) {
    showError('Idea cannot be empty.');
    return;
  }
  showSpinner();
  submitBtn.disabled = true;
  clearResultClasses();
  resultBox.textContent = '';
  resultBox.classList.remove('visible');
  const headers = {
    'Content-Type': 'application/json',
    'X-Requested-With': 'XMLHttpRequest'
  };
  if (csrfToken) headers['X-CSRF-Token'] = csrfToken;
  fetch('api/validate.php', {
    method: 'POST',
    headers: headers,
    body: JSON.stringify({ idea })
  })
  .then(response => {
    if (!response.ok) throw new Error(`Server error: ${response.status}`);
    return response.json();
  })
  .then(renderResult)
  .catch(showError)
  .finally(() => {
    hideSpinner();
    submitBtn.disabled = false;
  });
}

ideaInput.addEventListener('input', debounce(validateInput, 300));
ideaForm.addEventListener('submit', sendIdeaToServer);
