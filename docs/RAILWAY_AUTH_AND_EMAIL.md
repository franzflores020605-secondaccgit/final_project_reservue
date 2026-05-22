# Railway: Google Sign-In, SMTP, and email

## Why Google Sign-In failed

Error: **Missing required parameter: client_id**

Railway does **not** read your local `.env.local`. `GOOGLE_CLIENT_ID` and `GOOGLE_CLIENT_SECRET` must be set as **Variables** on your **app** service (`successful-bravery`).

---

## Step 1 — Google Cloud Console

1. Open [Google Cloud Console](https://console.cloud.google.com/) → **APIs & Services** → **Credentials**.
2. Create or edit an **OAuth 2.0 Client ID** of type **Web application** (not Android/iOS for the website button).
3. **Authorized JavaScript origins** (add both if you use local + Railway):
   - `http://localhost:8000`
   - `https://YOUR-APP.up.railway.app`
4. **Authorized redirect URIs** (must match `GOOGLE_REDIRECT_URI` **exactly**):
   - `http://localhost:8000/connect/google/check`
   - `https://YOUR-APP.up.railway.app/connect/google/check`

If you see **Error 400: redirect_uri_mismatch**, the URI in Google Console does not match `GOOGLE_REDIRECT_URI` on Railway.
5. Copy **Client ID** and **Client secret**.

---

## Step 2 — Railway app variables

Railway → **successful-bravery** (app, not MySQL) → **Variables** → add:

| Variable | Value |
|----------|--------|
| `GOOGLE_CLIENT_ID` | From Google Console |
| `GOOGLE_CLIENT_SECRET` | From Google Console |
| `GOOGLE_REDIRECT_URI` | `https://YOUR-APP.up.railway.app/connect/google/check` |
| `DEFAULT_URI` | `https://YOUR-APP.up.railway.app` (no trailing slash) |
| `APP_SECRET` | Long random string |
| `DATABASE_URL` | Reference from MySQL service (if not already linked) |

Redeploy the app after saving variables.

---

## Step 3 — Email (Brevo)

Choose **one** option.

### Option A — Brevo API (contact form)

| Variable | Value |
|----------|--------|
| `BREVO_API_KEY` | From [Brevo API keys](https://app.brevo.com/settings/keys/api) |
| `BREVO_SENDER_EMAIL` | Verified sender in Brevo |
| `BREVO_SENDER_NAME` | `ReserVue` |
| `BREVO_NOTIFY_EMAIL` | Inbox that receives contact form messages |
| `BREVO_CONTACT_LIST_ID` | Optional list ID |

### Option B — Brevo SMTP

| Variable | Value |
|----------|--------|
| `MAILER_DSN` | `smtp://LOGIN:SMTP_KEY@smtp-relay.brevo.com:587` |
| `BREVO_SENDER_EMAIL` | Verified sender |
| `BREVO_NOTIFY_EMAIL` | Your inbox |

Leave `BREVO_API_KEY` empty if using SMTP only.

---

## Step 4 — Verify

1. **Deploy logs** — should NOT show `WARNING: GOOGLE_CLIENT_ID is not set`.
2. Open `https://YOUR-APP.up.railway.app/connect/google` — Google login page should appear (no `client_id` error).
3. Submit the contact form — check Brevo / inbox.

---

## Security

- Never commit real keys in `.env` pushed to GitHub.
- Rotate any credentials that were shared in screenshots or chat.
