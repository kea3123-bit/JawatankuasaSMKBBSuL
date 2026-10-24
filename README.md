# JawatankuasaSMKBBSuL

Sistem pemberatan tugas guru SMK Bandar Baru Sungai Lalang.

## Architecture

- Frontend: GitHub Pages (`index.html`)
- Database: Google Sheets
- Authentication: existing eKeberadaan DELIMa + 6-digit PIN
- API: `apps-script/CommitteeApi.gs` added to the existing eKeberadaan Apps Script project
- Admin role: eKeberadaan category **Pengurusan**
- Login access: **Guru sahaja**. AKP records remain in the database for reporting/reference.

## Google Sheet database

Spreadsheet ID:

`1Rt8U0xvd3B1sXIG_KSXWWWlezSct5cpOXRP2xOZrEYU`

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

1. Open the Apps Script project behind the deployment above.
2. Add a script file named `CommitteeApi.gs`.
3. Paste the contents of `apps-script/CommitteeApi.gs`.
4. Apply the bridge additions in `apps-script/BRIDGE_PATCH.md` to `Bridges.gs`.
5. Deploy a **new version** of the existing Web App deployment so the same `/exec` URL remains valid.

The supplied eKeberadaan bridge already allows `https://kea3123-bit.github.io` by default.

## Security model

Every committee API call first runs the existing eKeberadaan `requireSessionUser_(token)`.

- Only an active eKeberadaan account whose normalized name matches an active row in `GURU` with `Kumpulan=Guru` may enter.
- On first successful match, the DELIMa email is bound to that teacher row.
- After binding, a different email cannot claim the same teacher row.
- Admin methods additionally require `user.category === 'Pengurusan'`.
- Admin writes are recorded in `AUDIT_LOG`.
- The Google Sheet does not need to be public.

## Admin features

- Edit points for every score code.
- Edit a teacher's jawatan hakiki.
- Add/edit/deactivate committee assignments for each teacher.
- Select the score code used for every assignment.
- Changing a score rule recalculates all displayed totals.

## User features

- Search and sort all teachers by total points.
- See total assignments and total weighted score.
- Click a teacher to see every active committee/task, role, score code and points.
- Existing eKeberadaan session/trusted-device credentials are reused where available.
