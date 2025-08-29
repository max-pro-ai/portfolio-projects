# AI ChatBot Assistant (WordPress Plugin)

AI ChatBot Assistant is a WordPress plugin that adds an on-site chat widget powered by OpenAI. It stores user–bot conversations and, after a configurable inactivity timeout, delivers the chat history to email and optionally Telegram.

Important: For reliable, on-time delivery of histories, ensure WP‑Cron is running (via system cron or external pinger). The plugin enqueues server-side jobs independent of the browser.

## Features
- Chat widget with customizable UI (size, colors, fonts, texts)
- OpenAI integration with model selection (uses modern defaults)
- Conversation storage in DB; client-side recent view cache for UX
- Server-side queueing of chat histories after inactivity timeout
- Email delivery via PHP mail() and optional Telegram delivery
- Resilient retries; queue is deleted only after successful delivery

## How it works
1) User sends message → plugin saves the conversation to DB.
2) Server enqueues a delivery job with scheduled_time = last_user_message_time + inactivity_timeout.
3) A minutely cron task processes due histories and sends them to email (+ Telegram if enabled).
4) After successful delivery to all channels, the queued file is removed. Failed deliveries are retried.

Note: The UI keeps a short local view of messages, but the source of truth for delivery is the server queue.

## Requirements
- WordPress 5.8+
- PHP 7.4+
- Outbound email enabled for PHP mail()
- (Optional) Telegram Bot Token and Chat ID
- WP‑Cron active (system cron or external pinger recommended)

## Installation
1) Upload the `ai-chatbot-plugin` directory to `wp-content/plugins/`.
2) Activate “AI ChatBot Assistant” in the WordPress Plugins screen.
3) Go to Settings → AI ChatBot to configure:
   - OpenAI API key and model
   - Widget appearance and text
   - Email recipient
   - Inactivity timeout (ms)
   - Telegram (optional): bot token and chat ID

## Cron setup (recommended)
- System cron (preferred):
  - Every minute: php /path/to/wordpress/wp-cron.php >/dev/null 2>&1
- Or HTTP pinger (e.g., UptimeRobot):
  - Every minute: https://YOUR_SITE/wp-cron.php?doing_wp_cron
- Ensure DISABLE_WP_CRON is not set to true in wp-config.php.

## Troubleshooting
- Histories are delayed until I visit the site:
  - WP‑Cron likely isn’t running. Configure a system cron or external pinger.
- Email doesn’t arrive:
  - Verify PHP mail() is permitted on hosting; check spam; confirm Settings → Email recipient; see wp-content/debug.log.
- Telegram doesn’t arrive:
  - Verify Bot Token and Chat ID; test from plugin settings if available; check debug log.

## Documentation
- Russian README: README-ru.md
- Ukrainian README: README-uk.md
- Architecture and flow diagram: docs/SCHEMA.md

## License
This project is provided as-is by the site owner; add a license file if you plan to distribute publicly.
