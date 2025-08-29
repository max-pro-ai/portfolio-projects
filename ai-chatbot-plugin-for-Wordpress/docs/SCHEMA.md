# Architecture and Flow

This plugin separates the chat UI from server-side delivery to guarantee reliability.

- Frontend (chat widget): collects messages for the user session and stores a short view cache.
- Backend (WordPress): stores conversations to DB and enqueues delivery jobs per session.
- Queue files: each session’s history is saved with a scheduled_time and picked up by cron.
- Delivery: email via PHP mail(); optional Telegram; retries on failure; deletes queue only after success.

## Data Flow
1) User sends message.
2) Server saves conversation (DB) and enqueues a delivery file with scheduled_time.
3) Minutely cron scans queue dir and sends due histories (email + Telegram).
4) On success: remove the queue file. On failure: reschedule retry.

## WP‑Cron requirement
To deliver on time without traffic, configure a real cron or external pinger:
- System cron (preferred): php /path/to/wordpress/wp-cron.php every minute.
- Or external pinger: https://YOUR_SITE/wp-cron.php?doing_wp_cron every minute.

If WP‑Cron is inactive, deliveries may wait until the next site visit.
