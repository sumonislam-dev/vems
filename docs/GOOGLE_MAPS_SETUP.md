# Configuring Google Maps for the Stop Picker

The route/stop picker (`resources/js/components/MapStopPicker.tsx`, used from
`resources/js/pages/routes/create.tsx` and `edit.tsx`) supports two map
providers, switched entirely from `.env`:

| Provider | `VITE_MAP_PROVIDER` | Cost | Needs a key? |
|---|---|---|---|
| OpenStreetMap (Leaflet + Nominatim) | `osm` (default) | Free | No |
| Google Maps (Maps JavaScript API + Places) | `google` | Requires billing account, has a monthly free credit | Yes |

> This is a different integration from
> [`GOOGLE_GEOCODING_IMPLEMENTATION.md`](GOOGLE_GEOCODING_IMPLEMENTATION.md),
> which is a *server-side*, not-yet-implemented plan for reverse-geocoding
> attendance GPS points. This doc is about the *client-side* map you see when
> picking route stops, which is already implemented and just needs a key to
> switch on.

If `VITE_MAP_PROVIDER=google` is set but `VITE_GOOGLE_MAPS_API_KEY` is empty,
the app automatically falls back to the free OSM map (with a console warning)
instead of showing a broken map.

---

## 1. Create a Google Cloud project and API key

1. Go to the [Google Cloud Console](https://console.cloud.google.com/) and
   sign in with the account that should own billing for this project.
2. Create a new project (or pick an existing one) — top-left project
   dropdown → **New Project**.
3. Enable billing: **Billing → Link a billing account**. Google requires an
   active billing account even to stay within the free monthly usage
   credit — without it, map loads fail with `RefererNotAllowedMapError` /
   `ApiNotActivatedMapError` or a "For development purposes only" watermark.
4. Enable the required APIs — **APIs & Services → Library** — and enable
   both of these (the stop picker needs both):
   - **Maps JavaScript API** (renders the map itself)
   - **Places API (New)** — the search box uses the *New* Places API
     (`AutocompleteSuggestion`/`Place` classes), not the legacy "Places API".
     Google stopped letting new Cloud projects enable the legacy one in
     March 2025, so make sure you enable the one labeled **"Places API
     (New)"** in the API Library, not plain "Places API".
5. Create the key: **APIs & Services → Credentials → Create Credentials →
   API key**. Copy the generated key.

## 2. Restrict the key

This key ships to the browser (it's a `VITE_*` var, bundled into the
frontend JS), so restricting it is the only thing standing between it and
abuse — treat this step as required, not optional:

- **Application restriction:** *Websites* — add your app's domain(s), e.g.
  `https://your-domain.example/*` and, for local development,
  `http://localhost/*` / `http://127.0.0.1/*`.
- **API restriction:** *Restrict key* → select only **Maps JavaScript API**
  and **Places API (New)**. This means even a leaked key can't be used
  against other Google APIs on the billing account.
- Set a budget alert: **Billing → Budgets & alerts → Create budget**. Pick a
  small monthly threshold (e.g. $5) so you're emailed if usage spikes
  unexpectedly.

If you ever paste a key into a chat, ticket, screenshot, or commit by
mistake, treat it as compromised: rotate it (create a new key, delete the
old one) rather than just tightening restrictions after the fact.

## 3. Configure `.env`

```env
VITE_MAP_PROVIDER=google
VITE_GOOGLE_MAPS_API_KEY=your-key-here
```

The existing region-lock variables (`VITE_MAP_COUNTRY_CODE`,
`VITE_MAP_DEFAULT_LAT`/`LNG`/`ZOOM`, `VITE_MAP_MIN_ZOOM`,
`VITE_MAP_BOUNDS_*`) apply to **both** providers unchanged — they still
restrict the Google map's pan/zoom bounds and bias the Places search the
same way they do for OSM/Nominatim today.

Also copy the two new keys into `.env.example` if you haven't already (with
the value left blank), so other environments know the variables exist.

## 4. Restart the dev server

Vite only reads `.env` at server start, not on hot-reload:

```bash
# stop the running `npm run dev` (or `composer dev`), then:
npm run dev
```

## 5. Verify it worked

Open a page that uses the stop picker (e.g. **Routes → Create**, add a
stop, open the map), and check:

- The map renders actual Google Maps tiles (not a blank grey box).
- Typing in the search box returns Google Places suggestions.
- Zooming/panning is still constrained to the configured region.
- No `RefererNotAllowedMapError` / `ApiNotActivatedMapError` in the browser
  console (these mean the website restriction or an enabled API doesn't
  match what you configured in step 1–2).

## Switching back to OpenStreetMap

Set `VITE_MAP_PROVIDER=osm` (or delete the line — `osm` is the default) and
restart the dev server. No other code or config changes are needed.

## Troubleshooting

| Symptom | Likely cause |
|---|---|
| Map area shows a spinner forever | `VITE_GOOGLE_MAPS_API_KEY` is wrong/empty and `VITE_MAP_PROVIDER=google` — check the browser console for the exact Google error. |
| "For development purposes only" watermark | No billing account linked to the project. |
| `RefererNotAllowedMapError` | The site's URL isn't in the key's *Website restrictions* list. |
| `ApiNotActivatedMapError` | Maps JavaScript API or Places API (New) isn't enabled on the project. |
| Console error "You're calling a legacy API, which is not enabled for your project" | You (or the API restriction on the key) enabled "Places API" instead of **"Places API (New)"**. The stop picker's search box uses the new `AutocompleteSuggestion`/`Place` classes, which need the New one. |
| Search box returns nothing | Places API (New) isn't enabled, or the key's API restriction doesn't include it. |
| Map loads but ignores the Bangladesh (or configured) bounds | Check `VITE_MAP_BOUNDS_*`/`VITE_MAP_MIN_ZOOM` are set — same variables drive both providers. |
