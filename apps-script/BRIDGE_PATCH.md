# Patch eKeberadaan modular untuk JawatankuasaSMKBBSuL

Baseline yang disokong ialah projek eKeberadaan modular yang mempunyai fail `Bridges.gs`, `00_Core.gs`, `10_Auth.gs`, dan fail-fail modul lain.

## Jangan tambah PagesBridge.gs

`Bridges.gs` sedia ada sudah mempunyai `doPost()` dan transport GitHub Pages. Menambah `PagesBridge.gs` akan mewujudkan duplicate `doPost()` dan boleh merosakkan deployment.

## Perubahan yang diperlukan

### 1. Tambah `CommitteeApi.gs`

Copy seluruh `apps-script/CommitteeApi.gs` ke projek Apps Script eKeberadaan yang sama.

Fail ini:
- guna `requireSessionUser_(token)` yang sedia ada;
- guna `user.category` dan `user.isAdmin` daripada PENGGUNA;
- tidak membaca/menulis PENGGUNA secara berasingan;
- membuka database Jawatankuasa sahaja.

Database Jawatankuasa:
`1T4FRkd5IqnNw6PoDYFnXZmX8wjDUF_m2-7dQ2BxaBig`

Admin penuh jika:
- `Kategori = Pengurusan`; atau
- `PENGGUNA.Pentadbir = TRUE`.

AKP biasa tidak boleh login ke sistem Jawatankuasa. AKP dengan `Pentadbir = TRUE` boleh login sebagai Pentadbir Sistem.

### 2. Patch `Bridges.gs`

Tambahkan nama berikut ke array dalam `pagesBridgeMethodAllowed_()`:

```js
'jtGetBootstrap',
'jtGetTeacherDetail',
'jtGetAdminRules',
'jtGetAdminTeachers',
'jtGetAdminCommittees',
'jtGetAdminData',
'jtAdminUpdatePoint',
'jtAdminUpdateTeacher',
'jtAdminSaveAssignment',
'jtAdminDeactivateAssignment',
'jtAdminSaveCommittee',
```

Tambahkan kes berikut di dalam `invokePagesBridgeMethod_()`:

```js
case 'jtGetBootstrap': return jtGetBootstrap.apply(null, args);
case 'jtGetTeacherDetail': return jtGetTeacherDetail.apply(null, args);
case 'jtGetAdminRules': return jtGetAdminRules.apply(null, args);
case 'jtGetAdminTeachers': return jtGetAdminTeachers.apply(null, args);
case 'jtGetAdminCommittees': return jtGetAdminCommittees.apply(null, args);
case 'jtGetAdminData': return jtGetAdminData.apply(null, args);
case 'jtAdminUpdatePoint': return jtAdminUpdatePoint.apply(null, args);
case 'jtAdminUpdateTeacher': return jtAdminUpdateTeacher.apply(null, args);
case 'jtAdminSaveAssignment': return jtAdminSaveAssignment.apply(null, args);
case 'jtAdminDeactivateAssignment': return jtAdminDeactivateAssignment.apply(null, args);
case 'jtAdminSaveCommittee': return jtAdminSaveCommittee.apply(null, args);
```

Jangan ubah `doPost()`, `getPagesWebOrigins_()`, login, session, attendance atau fungsi eKeberadaan lain.

## Permission Google Sheet

Akaun yang menjalankan/deploy Web App eKeberadaan mesti mempunyai akses Editor kepada database Jawatankuasa kerana API menggunakan `SpreadsheetApp.openById()`.

## Deploy

Selepas simpan:
Deploy -> Manage deployments -> Edit deployment Web App sedia ada -> New version -> Deploy.

Kekalkan deployment yang sama supaya URL `/exec` tidak berubah.
