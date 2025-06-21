const ideaForm = document.getElementById('ideaForm');
const ideaInput = document.getElementById('ideaInput');
const submitBtn = document.getElementById('submitBtn');
const resultBox = document.getElementById('resultContainer');
const csrfTokenInput = document.getElementById('csrfToken') || document.querySelector('input[name="csrf_token"]');
const csrfToken = csrfTokenInput ? csrfTokenInput.value : '';

function debounce(fn, delay) {
  let timer = null;
  return function(...args) {
    if (!timer) {
      fn.apply(this, args);
    }
    clearTimeout(timer);
    timer = setTimeout(() => {
      timer = null;
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
  resultBox.classList.remove('info', 'success', 'error');
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
    resultBox.classList.add('info');
    resultBox.textContent = 'Please enter at least 3 characters.';
  } else {
    submitBtn.disabled = false;
    clearResultClasses();
    resultBox.textContent = '';
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
  resultBox.classList.add('error');
  resultBox.textContent = typeof err === 'string' ? err : (err.message || 'An unexpected error occurred.');
}

function renderResult(data) {
  if (!data || typeof data.valid !== 'boolean') {
    showError('Invalid server response.');
    return;
  }
  clearResultClasses();
  if (data.valid) {
    resultBox.classList.add('success');
    resultBox.textContent = data.feedback || 'Your idea looks good!';
  } else {
    resultBox.classList.add('error');
    resultBox.textContent = data.feedback || 'Sorry, that idea may not work.';
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
  const headers = {
    'Content-Type': 'application/json',
    'X-Requested-With': 'XMLHttpRequest'
  };
  if (csrfToken) headers['X-CSRF-Token'] = csrfToken;
  fetch('aivalidationhandler.php', {
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
