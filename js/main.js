// ══════════════════════════════════════════════
//  NOT NOTHING — Main JS
//  PayPal Smart Buttons + UI interactions
// ══════════════════════════════════════════════

// ── Pricing ───────────────────────────────────
const PRICES = { ebook: '9.99', paperback: '19.99', hardcover: '29.99' };
const FORMAT_LABELS = { ebook: 'eBook', paperback: 'Paperback', hardcover: 'Hardcover' };
let selectedFormat = 'ebook';

// ── Scroll fade-up observer ───────────────────
const fadeEls = document.querySelectorAll('.fade-up');
const observer = new IntersectionObserver((entries) => {
  entries.forEach(e => { if (e.isIntersecting) e.target.classList.add('visible'); });
}, { threshold: 0.12 });
fadeEls.forEach(el => observer.observe(el));

// ── Sticky nav ────────────────────────────────
const nav = document.querySelector('nav');
window.addEventListener('scroll', () => {
  nav.style.background = window.scrollY > 40
    ? 'rgba(13,13,20,0.95)' : 'rgba(13,13,20,0.7)';
});

// ── Hamburger menu ────────────────────────────
const hamburger = document.getElementById('hamburger');
const navLinks  = document.getElementById('nav-links');
hamburger?.addEventListener('click', () => navLinks.classList.toggle('open'));

// ── Format pills (hero/order section) ────────
document.querySelectorAll('.format-pill').forEach(pill => {
  pill.addEventListener('click', () => {
    document.querySelectorAll('.format-pill').forEach(p => p.classList.remove('active'));
    pill.classList.add('active');
    selectedFormat = pill.dataset.format;
    document.getElementById('price-display').textContent = '$' + PRICES[selectedFormat];
    // Sync modal pills
    syncModalPills(selectedFormat);
  });
});

// ── Modal ─────────────────────────────────────
const modal    = document.getElementById('order-modal');
const closeBtn = document.getElementById('modal-close');

document.querySelectorAll('[data-open-modal]').forEach(btn => {
  btn.addEventListener('click', () => openModal());
});
closeBtn?.addEventListener('click', () => closeModal());
modal?.addEventListener('click', e => { if (e.target === modal) closeModal(); });

function openModal() {
  syncModalPills(selectedFormat);
  updateModalPrice(selectedFormat);
  modal.classList.add('open');
}
function closeModal() {
  modal.classList.remove('open');
}

// ── Modal format pills ────────────────────────
document.querySelectorAll('.modal-format-pill').forEach(pill => {
  pill.addEventListener('click', () => {
    document.querySelectorAll('.modal-format-pill').forEach(p => p.classList.remove('active'));
    pill.classList.add('active');
    selectedFormat = pill.dataset.format;
    updateModalPrice(selectedFormat);
    // Sync main page pills too
    document.querySelectorAll('.format-pill').forEach(p => {
      p.classList.toggle('active', p.dataset.format === selectedFormat);
    });
    document.getElementById('price-display').textContent = '$' + PRICES[selectedFormat];
  });
});

function syncModalPills(format) {
  document.querySelectorAll('.modal-format-pill').forEach(p => {
    p.classList.toggle('active', p.dataset.format === format);
  });
}
function updateModalPrice(format) {
  const el = document.getElementById('modal-price');
  if (el) el.textContent = '$' + PRICES[format] + ' USD';
}

// ── Smooth scroll ─────────────────────────────
document.querySelectorAll('a[href^="#"]').forEach(a => {
  a.addEventListener('click', e => {
    const target = document.querySelector(a.getAttribute('href'));
    if (target) {
      e.preventDefault();
      target.scrollIntoView({ behavior: 'smooth' });
      navLinks?.classList.remove('open');
    }
  });
});

// ── PayPal Smart Buttons ──────────────────────
// Rendered once the SDK script loads (see index.html)
function initPayPal() {
  if (typeof paypal === 'undefined') return;

  paypal.Buttons({
    style: {
      layout: 'vertical',
      color:  'gold',
      shape:  'pill',
      label:  'buynow',
      height: 50,
    },

    // Called when buyer clicks PayPal button
    createOrder: (data, actions) => {
      const email = document.getElementById('modal-email')?.value?.trim();
      if (!email || !email.includes('@')) {
        document.getElementById('email-error').style.display = 'block';
        return Promise.reject(new Error('Email required'));
      }
      document.getElementById('email-error').style.display = 'none';

      return actions.order.create({
        purchase_units: [{
          description: `Not Nothing: Small Designs That Save Lives — ${FORMAT_LABELS[selectedFormat]}`,
          amount: {
            currency_code: 'USD',
            value: PRICES[selectedFormat],
          },
        }],
        application_context: {
          shipping_preference: selectedFormat === 'ebook' ? 'NO_SHIPPING' : 'GET_FROM_FILE',
        },
      });
    },

    // Called after buyer approves payment on PayPal
    onApprove: async (data, actions) => {
      const btn = document.getElementById('paypal-btn-wrap');
      btn.innerHTML = '<div class="paypal-processing">⏳ Confirming your payment…</div>';

      let order;
      try {
        order = await actions.order.capture();
      } catch (e) {
        btn.innerHTML = '<p class="paypal-error">❌ Payment capture failed. Please contact us.</p>';
        return;
      }

      // Notify PHP backend (non-blocking — payment is already done)
      const buyerEmail = document.getElementById('modal-email')?.value?.trim();
      fetch('php/order-complete.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          order_id:    order.id,
          payer_name:  (order.payer?.name?.given_name || '') + ' ' + (order.payer?.name?.surname || ''),
          payer_email: order.payer?.email_address || '',
          buyer_email: buyerEmail,
          format:      selectedFormat,
          amount:      PRICES[selectedFormat],
          status:      order.status,
        }),
      }).catch(err => console.warn('Order log failed (payment still completed):', err));

      // Redirect to thank-you page
      window.location.href = `thank-you.html?order=${order.id}&format=${selectedFormat}&amount=${PRICES[selectedFormat]}`;
    },

    onError: (err) => {
      console.error('PayPal error:', err);
      document.getElementById('paypal-btn-wrap').innerHTML =
        '<p class="paypal-error">❌ Something went wrong with PayPal. Please try again.</p>';
    },

    onCancel: () => {
      console.log('Payment cancelled.');
    },
  }).render('#paypal-button-container');
}

// ── Analytics ─────────────────────────────────
// Silently log the visit to the backend
window.addEventListener('DOMContentLoaded', () => {
  fetch('php/track.php').catch(err => console.warn('Analytics blocked', err));
});
