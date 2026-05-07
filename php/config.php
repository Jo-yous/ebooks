<?php
// ══════════════════════════════════════════════
//  NOT NOTHING — Server Configuration
//  Fill these values in using your AlwaysData
//  admin panel details.
// ══════════════════════════════════════════════

// ── Database (AlwaysData MySQL) ──────────────
define('DB_HOST', 'mysql-globalmedia.alwaysdata.net'); // AlwaysData MySQL host
define('DB_NAME', 'globalmedia_ebook');           // Your database name
define('DB_USER', 'globalmedia');                       // your AlwaysData username
define('DB_PASS', 'YOUR_DB_PASSWORD');

// ── Site & Book Info ─────────────────────────
define('SITE_URL',    'https://globalmedia.alwaysdata.net/ebook-store'); // your AlwaysData domain
define('BOOK_TITLE',  'Not Nothing: Small Designs That Save Lives');
define('SELLER_NAME', 'Your Name');                          // ← replace with your real name

// ── Email (who gets notified on each sale) ───
define('SELLER_EMAIL', 'knghenry3@gmail.com');   // ← your email address
define('FROM_EMAIL',   'orders@globalmedia.alwaysdata.net'); // AlwaysData email address

// ── PayPal ───────────────────────────────────
// Your PayPal Client ID from developer.paypal.com
// Go to: My Apps & Credentials → Create App → copy "Client ID"
define('PAYPAL_CLIENT_ID', '438XKRGBHHNH2');

// Set to false when you go live!
define('PAYPAL_SANDBOX', true);
