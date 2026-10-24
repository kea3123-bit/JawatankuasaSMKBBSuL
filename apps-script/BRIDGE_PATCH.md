# Setup bridge untuk JawatankuasaSMKBBSuL

**Tidak perlu cari atau ubah `Bridges.gs`.** Projek eKeberadaan semasa menggunakan `Code.gs` dan tiada bridge sedia ada.

Cara yang betul ialah tambah **dua fail baharu** ke projek Apps Script eKeberadaan yang sama:

1. `CommitteeApi.gs` — API database jawatankuasa/pemberatan.
2. `PagesBridge.gs` — menerima POST daripada GitHub Pages dan memanggil fungsi login eKeberadaan + fungsi `jt*`.

Kedua-dua fail sudah tersedia dalam folder `apps-script/` repo ini.

## Langkah

1. Buka Google Sheet eKeberadaan.
2. Extensions → Apps Script.
3. Klik **+ → Script** dan cipta `CommitteeApi.gs`.
4. Salin seluruh kandungan `apps-script/CommitteeApi.gs` ke fail itu.
5. Klik **+ → Script** lagi dan cipta `PagesBridge.gs`.
6. Salin seluruh kandungan `apps-script/PagesBridge.gs`.
7. Simpan.
8. Deploy → Manage deployments → Edit deployment Web App semasa → **New version** → Deploy.
9. Jangan cipta deployment baharu jika mahu URL `/exec` sedia ada kekal.

## Fungsi yang dibenarkan oleh PagesBridge.gs

Login/session eKeberadaan:
- `checkDelimaAccount`
- `loginWithPin`
- `setFirstPin`
- `resumeSession`
- `resumeTrustedDevice`
- `logoutApp`

Jawatankuasa:
- `jtGetBootstrap`
- `jtGetTeacherDetail`
- `jtGetAdminData`
- `jtAdminUpdatePoint`
- `jtAdminUpdateTeacher`
- `jtAdminSaveAssignment`
- `jtAdminDeactivateAssignment`

Origin yang dibenarkan:
- `https://kea3123-bit.github.io`

## Admin

Admin penuh jika salah satu benar:
- kategori eKeberadaan = `Pengurusan`; atau
- tab `PENGGUNA` dalam database eKeberadaan mempunyai `Pentadbir = TRUE`.

AKP biasa tidak boleh login ke sistem Jawatankuasa. AKP dengan `Pentadbir = TRUE` dibenarkan sebagai Pentadbir Sistem.
