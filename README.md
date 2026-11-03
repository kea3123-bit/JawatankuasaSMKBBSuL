# JawatankuasaSMKBBSuL

Sistem Jawatankuasa / MyPortfolio dan pemberatan tugas SMK Bandar Baru Sungai Lalang.

## Production architecture

### Current cut-over status

- Browser login is now direct JSON RPC to the eKeberadaan MySQL API.
- All `jt*` calls are direct JSON RPC to the Jawatankuasa PHP/MySQL API.
- The legacy Apps Script iframe/form bridge is no longer used by `index.html`.
- eKeberadaan remains the single authentication/session authority; Jawatankuasa stores no PIN/password.
- Jawatankuasa rejects eKeberadaan sessions that still require a temporary-PIN change.
- Full SQL data dumps and `config.local.php` stay off GitHub.



- Frontend: GitHub Pages — `https://kea3123-bit.github.io/JawatankuasaSMKBBSuL/`
- Jawatankuasa data: **MySQL** through `https://ppdkmy.edu.my/jawatankuasabbsul/api.php`
- Login / session: existing **eKeberadaan MySQL API** at `https://ppdkmy.edu.my/ekeberadaansmkbbsul/api.php`
- Google Sheets / Apps Script are no longer the production database or login transport.
- The old `apps-script/` directory remains historical migration source only.

The browser never receives MySQL credentials. The Jawatankuasa PHP API validates each eKeberadaan token server-to-server, then reads/writes the separate Jawatankuasa MySQL database.

## MySQL tables

The uploaded workbook is migrated into:

- `guru`
- `point_rules`
- `jawatankuasa`
- `pelantikan`
- `settings`
- `audit_log`

Use `php/schema.sql` only for an empty schema. For the initial production migration, import the generated full SQL dump from the latest Excel export so the existing rows are included.

## cPanel deployment

Target directory:

`public_html/jawatankuasabbsul/`

Required files:

- `api.php`
- `php/api.php`
- `php/_lib.php`
- `php/.htaccess`
- `php/config.local.php` — **server only; never commit**
- optional `php/schema.sql`

Create `php/config.local.php` from `php/config.local.example.php` and enter the real MySQL password on cPanel. For PHP and MySQL hosted in the same cPanel account, use `host => 'localhost'` unless the host specifically requires a remote address.

The database account needs SELECT / INSERT / UPDATE / DELETE privileges for the Jawatankuasa database.

## Initial data import

1. Create/confirm the MySQL database and user in cPanel.
2. Open phpMyAdmin and select the Jawatankuasa database.
3. Import the generated `JawatankuasaSMKBBSL_mysql.sql`.
4. Confirm the verification query at the bottom returns approximately:
   - 71 `guru`
   - 121 `point_rules`
   - 235 `jawatankuasa`
   - 1498 `pelantikan`
5. Upload the PHP backend files to `public_html/jawatankuasabbsul/`.
6. Create private `php/config.local.php`.
7. Test `https://ppdkmy.edu.my/jawatankuasabbsul/api.php` with JSON RPC `getApiStatus`.
8. Reload GitHub Pages and log in with the same eKeberadaan DELIMa email + PIN.

## Login integration

The frontend sends these functions directly to the eKeberadaan MySQL API:

- `checkDelimaAccount`
- `loginWithPin`
- `resumeSession`
- `resumeTrustedDevice`
- `logoutApp`

All `jt*` functions go to the Jawatankuasa MySQL API.

Therefore the former error **“Fungsi backend tidak dibenarkan”** from the Apps Script bridge is no longer part of the production login path.

## Admin model

Jawatankuasa admin access is granted when the authenticated eKeberadaan account is either:

- category `Pengurusan`, or
- `isAdmin = true` / Pentadbir Sistem.

Admin writes are recorded in `audit_log`.

## Roster sync

**Sync eKeberadaan** calls the MySQL eKeberadaan `getAdminData` endpoint and upserts active `Pengurusan` + `PPP` accounts into `guru`.

Because eKeberadaan protects `getAdminData` as a Pentadbir Sistem operation, manual full-roster sync requires an eKeberadaan system-admin session. Existing Jawatankuasa records remain available even when a normal Pengurusan user does not run sync.

## UI

### Senarai Jawatankuasa
Default for all signed-in users. Automatic search supports individual names, roles/jawatan and committee names/codes. No weighting/points are returned to non-admin users.

### Senarai Jawatankuasa (Pentadbir)
Pengurusan / Pentadbir Sistem only. Keeps the weighted teacher view and point totals.

### Pentadbiran
Lazy-loaded administration for point rules, teachers/assignments and committees.
