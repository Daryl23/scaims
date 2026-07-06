const resolveAppPath = typeof window.resolveAppPath === 'function'
  ? window.resolveAppPath
  : (path) => path;

function formatLandingNumber(value, decimals = 0) {
  return Number(value || 0).toLocaleString(undefined, {
    minimumFractionDigits: decimals,
    maximumFractionDigits: decimals
  });
}

function animateLandingNumber(element, target, decimals = 0, duration = 900) {
  if (!element) return;

  const start = performance.now();
  const finalValue = Number(target || 0);

  function step(timestamp) {
    const progress = Math.min((timestamp - start) / duration, 1);
    const value = finalValue * progress;
    element.textContent = formatLandingNumber(value, decimals);

    if (progress < 1) {
      requestAnimationFrame(step);
    } else {
      element.textContent = formatLandingNumber(finalValue, decimals);
    }
  }

  requestAnimationFrame(step);
}

function ensureInlineBadge(card, id, className) {
  let badge = card.querySelector(`#${id}`);
  if (!badge) {
    badge = document.createElement('p');
    badge.id = id;
    badge.className = className;
    const description = card.querySelector('p');
    if (description) {
      description.insertAdjacentElement('afterend', badge);
    } else {
      card.appendChild(badge);
    }
  }
  return badge;
}

function prepareSuggestionForm() {
  const form = document.querySelector('#suggestionModal form');
  if (!form) return null;

  form.removeAttribute('onsubmit');
  form.id = 'suggestionForm';

  const fields = form.querySelectorAll('input, textarea');
  if (fields[0]) fields[0].name = 'name';
  if (fields[1] && fields[1].type === 'email') {
    const emailBlock = fields[1].closest('div');
    if (emailBlock) emailBlock.remove();
  }
  const textarea = form.querySelector('textarea');
  if (textarea) textarea.name = 'suggestion';

  let feedback = document.getElementById('suggestionFeedback');
  if (!feedback) {
    feedback = document.createElement('div');
    feedback.id = 'suggestionFeedback';
    feedback.className = 'hidden mb-4 rounded-xl px-4 py-3 text-sm font-medium';
    form.insertAdjacentElement('beforebegin', feedback);
  }

  const submitButton = form.querySelector('button[type="submit"]');
  if (submitButton) submitButton.id = 'suggestionSubmitButton';

  return { form, feedback, submitButton };
}

async function loadLandingData() {
  const heroFarmers = document.getElementById('heroRegisteredFarmers');
  const heroHectares = document.getElementById('heroHectaresMonitored');
  const heroCropTypes = document.getElementById('heroCropTypesTracked');

  const serviceCards = document.querySelectorAll('#services .grid > div');
  const farmerCard = serviceCards[0];
  const newsCard = serviceCards[1];
  const suggestionCard = serviceCards[2];

  const snapshotSection = document.querySelectorAll('section.py-24.bg-olive-dark')[0];

  try {
    const response = await fetch(resolveAppPath('/landing_data.php'), { cache: 'no-store' });
    const payload = await response.json();

    if (!response.ok || !payload.success) {
      throw new Error(payload.error || 'Unable to load landing data.');
    }

    const stats = payload.stats || {};
    const latestPost = payload.latestPost;
    const totalReports = (stats.cropReports || 0) + (stats.damageReports || 0);
    const cropRatio = totalReports > 0 ? (stats.cropReports / totalReports) * 100 : 0;
    const damageRatio = totalReports > 0 ? (stats.damageReports / totalReports) * 100 : 0;

    animateLandingNumber(heroFarmers, stats.registeredFarmers || 0);
    animateLandingNumber(heroHectares, stats.hectaresMonitored || 0, 2);
    animateLandingNumber(heroCropTypes, stats.cropTypesTracked || 0);

    if (farmerCard) {
      const farmerBadge = ensureInlineBadge(
        farmerCard,
        'serviceAccountMeta',
        'mt-5 inline-flex items-center rounded-full bg-white/15 px-4 py-2 text-sm font-semibold text-gold-light'
      );
      farmerBadge.textContent = `${formatLandingNumber(stats.registeredFarmers)} farmers already registered`;

      const accountLink = farmerCard.querySelector('a[href="#"]');
      if (accountLink) {
        accountLink.href = resolveAppPath('/FARMERS%20UI/registration/rsbsa_check.html');
      }
    }

    if (newsCard) {
      let dateLine = newsCard.querySelector('#latestPostDate');
      if (!dateLine) {
        dateLine = document.createElement('p');
        dateLine.id = 'latestPostDate';
        dateLine.className = 'text-xs font-bold uppercase tracking-[0.18em] text-olive/45 mb-3';
        newsCard.insertBefore(dateLine, newsCard.querySelector('h3'));
      }

      const title = newsCard.querySelector('h3');
      const paragraphs = newsCard.querySelectorAll('p');
      const body = paragraphs[paragraphs.length - 1];
      const link = newsCard.querySelector('a');

      if (title) title.textContent = 'Latest Announcement';
      if (dateLine) dateLine.textContent = latestPost ? latestPost.formattedDate : 'No announcements yet';
      if (body) {
        body.textContent = latestPost
          ? latestPost.excerpt
          : 'Once the agriculture office publishes a post, it will appear here automatically.';
      }
      if (link) {
        link.href = resolveAppPath('/FARMERS%20UI/farmerLogin/farmerlogin.html');
        link.innerHTML = 'Farmer Login <span class="group-hover/link:translate-x-1 transition-transform">→</span>';
      }
    }

    if (suggestionCard) {
      const suggestionText = suggestionCard.querySelector('p');
      if (suggestionText) {
        suggestionText.textContent = `${formatLandingNumber(stats.suggestionsReceived)} suggestions have already been recorded from the community. Add yours to help shape the next improvements.`;
      }
    }

    if (snapshotSection) {
      const introLabel = snapshotSection.querySelector('span.text-gold-light');
      const heading = snapshotSection.querySelector('h2');
      const intro = snapshotSection.querySelector('p.text-white\\/80');
      const bullets = snapshotSection.querySelectorAll('ul li span:last-child');
      const statCards = snapshotSection.querySelectorAll('.grid.grid-cols-2 > div');

      if (introLabel) introLabel.textContent = 'Live System Snapshot';
      if (heading) heading.innerHTML = 'Your Agricultural Data,<br>Updated From The Database';
      if (intro) {
        intro.textContent = 'These figures come from your farmer, parcel, report, post, and suggestion records so the landing page reflects real system activity instead of static placeholders.';
      }

      if (bullets[0]) bullets[0].textContent = `${formatLandingNumber(stats.registeredFarmers)} farmer records are currently in the system.`;
      if (bullets[1]) bullets[1].textContent = `${formatLandingNumber(stats.totalParcels)} parcel records are already encoded for monitoring.`;
      if (bullets[2]) bullets[2].textContent = `${formatLandingNumber(stats.suggestionsReceived)} feedback submissions have been stored so far.`;

      if (statCards[0]) {
        statCards[0].innerHTML = `
          <div class="text-white/50 text-sm mb-2 uppercase tracking-wider">Crop Reports</div>
          <div class="text-3xl font-bold text-gold-light mb-3" id="snapshotCropReports">0</div>
          <div class="h-2 bg-white/10 rounded-full overflow-hidden">
            <div id="snapshotCropReportsBar" class="h-full bg-gold-light rounded-full transition-all duration-700" style="width:0%"></div>
          </div>
        `;
      }

      if (statCards[1]) {
        statCards[1].innerHTML = `
          <div class="text-white/50 text-sm mb-2 uppercase tracking-wider">Damage Reports</div>
          <div class="text-3xl font-bold text-harvest mb-3" id="snapshotDamageReports">0</div>
          <div class="h-2 bg-white/10 rounded-full overflow-hidden">
            <div id="snapshotDamageReportsBar" class="h-full bg-harvest rounded-full transition-all duration-700" style="width:0%"></div>
          </div>
        `;
      }

      animateLandingNumber(document.getElementById('snapshotCropReports'), stats.cropReports || 0);
      animateLandingNumber(document.getElementById('snapshotDamageReports'), stats.damageReports || 0);

      const cropBar = document.getElementById('snapshotCropReportsBar');
      const damageBar = document.getElementById('snapshotDamageReportsBar');
      if (cropBar) cropBar.style.width = `${cropRatio}%`;
      if (damageBar) damageBar.style.width = `${damageRatio}%`;
    }
  } catch (error) {
    console.error(error);
  }
}

async function submitSuggestion(event) {
  event.preventDefault();

  const form = document.getElementById('suggestionForm');
  const feedback = document.getElementById('suggestionFeedback');
  const submitButton = document.getElementById('suggestionSubmitButton');
  if (!form || !feedback || !submitButton) return;

  feedback.classList.add('hidden');
  feedback.classList.remove('modal-message-success', 'modal-message-error');
  submitButton.disabled = true;
  submitButton.textContent = 'Submitting...';

  try {
    const response = await fetch(resolveAppPath('/submit_suggestion.php'), {
      method: 'POST',
      body: new FormData(form)
    });
    const payload = await response.json();

    if (!response.ok || !payload.success) {
      throw new Error(payload.error || 'Unable to submit your suggestion.');
    }

    feedback.textContent = 'Your suggestion has been saved successfully.';
    feedback.classList.remove('hidden');
    feedback.classList.add('modal-message-success');
    form.reset();
    loadLandingData();

    setTimeout(() => {
      if (typeof closeModal === 'function') closeModal();
      feedback.classList.add('hidden');
      feedback.classList.remove('modal-message-success');
    }, 1200);
  } catch (error) {
    feedback.textContent = error.message;
    feedback.classList.remove('hidden');
    feedback.classList.add('modal-message-error');
  } finally {
    submitButton.disabled = false;
    submitButton.textContent = 'Submit';
  }
}

document.addEventListener('DOMContentLoaded', () => {
  const prepared = prepareSuggestionForm();
  if (prepared?.form) {
    prepared.form.addEventListener('submit', submitSuggestion);
  }
  loadLandingData();
});
