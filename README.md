# JawatankuasaSMKBBSuL

Sistem pemberatan tugas guru SMK Bandar Baru Sungai Lalang.

## Architecture

- Frontend: GitHub Pages (`index.html`)
- Database: Google Sheets
- Authentication: existing eKeberadaan DELIMa + 6-digit PIN
- API: `apps-script/CommitteeApi.gs` added to the existing eKeberadaan Apps Script project
- Admin role: eKeberadaan category **Pengurusan** OR `PENGGUNA.Pentadbir = TRUE` in the eKeberadaan database.
- Login access: normal users are **Guru sahaja**. An AKP account may enter only when it is marked `Pentadbir = TRUE` (Pentadbir Sistem).

## Google Sheet database

Spreadsheet ID:

`1T4FRkd5IqnNw6PoDYFnXZmX8wjDUF_m2-7dQ2BxaBig`

Tabs:
- `GURU` — 59 Guru + 6 AKP, login binding, total points
- `POINT_RULES` — editable weight/point rules from the reference Excel
- `JAWATANKUASA` — committee/task catalogue
- `PELANTIKAN` — all teacher/AKP assignments
- `SETTINGS`
- `AUDIT_LOG`

## Connect to eKeberadaan

Existing backend deployment:

`https://script.google.com/macros/s/AKfycbyUv2F6Fl3drRR-TTTJkefu73TwbuQk5GVf36z87WLYIy40SSqZOfKBCeSIy7xje_wq/exec`

1. Open the modular eKeberadaan Apps Script project that contains `Bridges.gs`.
2. Add a new script file named `CommitteeApi.gs` and paste `apps-script/CommitteeApi.gs`.
3. Patch the existing `Bridges.gs` exactly as documented in `apps-script/BRIDGE_PATCH.md`: add the 11 `jt*` names to `pagesBridgeMethodAllowed_()` and the matching 11 cases to `invokePagesBridgeMethod_()`.
4. Do **not** add another `doPost()` or `PagesBridge.gs`; the existing `Bridges.gs` already provides the GitHub Pages transport.
5. Save the project.
6. Deploy a **new version of the existing Web App deployment** so the same `/exec` URL remains valid.

The existing bridge already allows `https://kea3123-bit.github.io` by default.

## Security model

Every committee API call first runs the existing eKeberadaan `requireSessionUser_(token)`.

- The committee API reuses the already-validated eKeberadaan session through `requireSessionUser_(token)`; it does not open the eKeberadaan spreadsheet separately.
- Normal access requires an active eKeberadaan account matched to an active `GURU` row with `Kumpulan=Guru`.
- If the logged-in eKeberadaan user has `user.isAdmin = true` (the existing `PENGGUNA.Pentadbir` checkbox), the account is treated as **Pentadbir Sistem** and may also match an AKP row.
- On first successful match, the DELIMa email is bound to that `GURU`/AKP row.
- After binding, a different email cannot claim the same person row.
- Admin methods require either `user.category === 'Pengurusan'` or `PENGGUNA.Pentadbir = TRUE`.
- Admin writes are recorded in `AUDIT_LOG`.
- The Google Sheet does not need to be public.

## Admin features

- Edit/rename every jawatan / nilai mata with impact confirmation before cascading to active assignments.
- Edit a teacher's jawatan hakiki.
- Add/edit/deactivate committee assignments for each teacher, with Undo after deactivation.
- Assignment committee name and Bidang are always derived from the master `JAWATANKUASA` row by `CommitteeID`.
- Assignment `Peranan` and `KodSkor` are always stored with the same canonical jawatan name.
- Changing a score rule recalculates displayed totals and refreshes the teacher list without reloading the whole app.
- Admin settings are split into lazy-loaded pages for Nilai Mata, Guru & Pelantikan, and Jawatankuasa.

## Teacher roster sync

The eKeberadaan `PENGGUNA` sheet is the master teacher roster.

- Active `Pengurusan` and `PPP` users are automatically synchronized into the Jawatankuasa `GURU` sheet.
- A newly added active teacher appears even with zero committee assignments and therefore starts with 0 points / 0 tasks.
- Name matching normalizes common `BT` / `BINTI` variants to avoid duplicate people.
- Existing committee assignments remain linked by `PersonID`.
- Teacher rows no longer active in eKeberadaan are preserved for history but set `Aktif=FALSE`.
- AKP are not included in the teacher ranking; an AKP with eKeberadaan `Pentadbir=TRUE` may still use the admin interface.
- Existing `JawatanHakiki` values are preserved; eKeberadaan job title is used when creating a new teacher or when the Jawatankuasa job title is blank.
- Roster sync is cached for 10 minutes during normal API use. Admins can explicitly run **Sync eKeberadaan** from Guru & Pelantikan when an immediate refresh is needed.

## Jawatankuasa & Jawatan reference tab

A separate **Jawatankuasa & Jawatan** tab reproduces the old MyPortfolio-style reference experience using live Sheet data. It supports Automatik / Jawatankuasa / Individu modes, universal search by name/committee/role, Bidang filtering, expandable cards, and copy-all output. It intentionally does not show ranking, total points, or any weighting values.

## Universal search

The main Guru & Mata page supports three search modes: **Automatik**, **Jawatankuasa**, and **Individu**. The search field matches teacher names, jawatan hakiki, committee names, Bidang, and assignment roles. Search data is loaded lazily on first use so initial page loading stays light. In Automatik mode, a committee-title match switches to a full committee view with all active members; name/role searches remain in individual view.

## User features

- Search and sort all teachers by total points.
- See total assignments and total weighted score.
- Click a teacher to see every active committee/task, Bidang, role and points.
- Existing eKeberadaan session/trusted-device credentials are reused where available.
