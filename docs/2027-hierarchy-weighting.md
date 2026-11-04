# Reka Bentuk Sesi 2027: Hierarki Jawatankuasa & Pemberatan Tugas

## Prinsip

Sistem memisahkan tiga perkara yang sebelum ini bercampur:

1. **Jawatan dalam jawatankuasa** — contoh: Pengerusi, Setiausaha, Setiausaha EMIS/APDM.
2. **Kategori skor** — contoh: AJK, Setiausaha, GURU DATA, SU KURIKULUM.
3. **Asas portfolio** — contoh: Pengetua, Guru Data, Ketua Panitia, SU Kurikulum.

Ini membolehkan Manual Pengurusan dikekalkan secara tepat tanpa memerlukan satu kod mata baharu untuk setiap variasi nama jawatan.

## Sumber utama

### Manual Pengurusan SMKBBSL 2026

Manual tempatan kekal sebagai sumber operasi utama untuk pelantikan sekolah.

Hierarki utama:

- **JM01** — Jawatankuasa Induk Pengurusan dan Pentadbiran Sekolah
  - JM01/1 hingga JM01/12 sebagai sub-jawatankuasa.
- **JM02–JM09** — jawatankuasa pengurusan khusus.
- **JM21** — Jawatankuasa Induk Kurikulum Sekolah.
  - JM21/1 Jadual Waktu.
  - JM22 Panitia Mata Pelajaran dan bidang.
  - JM22/1, JM22/2, JM23 dan PSS.
- **JM25** — Jawatankuasa Induk HEM.
  - JM25/1–JM25/10, JM26/1–JM26/4, JM27–JM29.
- **JM30** — Jawatankuasa Induk Kokurikulum.
  - JM30(E2), JM31, JM32 dan unit kelab/persatuan/sukan/badan beruniform.
- Lampiran bilik khas disusun di bawah Jawatankuasa Pengurusan Bilik Khas.

Beberapa kod dalam kandungan manual berulang (contohnya JM22/0/2, JM23 dan JM28). Sistem menyimpan rujukan asal dan menandakan variasi, bukannya menukar dokumen sumber secara senyap.

## Rujukan luar KPM

### MySG

SPI KPM Bil. 4 Tahun 2021 ialah rujukan tadbir urus sekolah:
https://www.moe.gov.my/index.php/spi-kpm-bil-4-tahun-2021-garis-panduan-tadbir-urus

Ia digunakan sebagai kerangka semakan struktur, tetapi Manual Pengurusan SMKBBSL kekal sumber operasi pelantikan tempatan.

### Panitia Mata Pelajaran

SPI KPM Bil. 2 Tahun 2023:
https://www.moe.gov.my/index.php/spi-kpm-bil2-tahun-2023-garis-panduan-pengurusan-p

Perkara yang digunakan dalam sistem:
- semua guru yang mengajar mata pelajaran sama ialah ahli panitia;
- PGB melantik Ketua Panitia;
- semua Ketua Panitia secara automatik menjadi ahli Jawatankuasa Pengurusan Kurikulum.

Ini menyokong hubungan **JM22 Panitia → JM21 Induk Kurikulum** dan asas portfolio Ketua Panitia.

### Guru Data

SPI KPM Bil. 1 Tahun 2022:
https://www.moe.gov.my/spi-kpm-bil1-tahun-2022-garis-panduan-pelantikan-d

Bukti beban khusus:
- Guru Data diperuntukkan **7 jam seminggu** untuk pengurusan data;
- waktu mengajar tidak melebihi **8 jam seminggu**;
- Guru Data berperanan sebagai setiausaha Jawatankuasa Pengurusan Maklumat Sekolah (JPMS).

Atas sebab ini, `GURU DATA` dikategorikan sebagai portfolio kritikal dan cadangan 5 mata dikekalkan.

### Pengurusan Bencana

SPI KPM Bil. 6 Tahun 2021 menetapkan struktur peringkat sekolah:
- Pengerusi: Pengetua/Guru Besar;
- Timbalan Pengerusi: GPK Pentadbiran;
- Setiausaha: GPK HEM;
- AJK merangkumi GPK, GKMP, Ketua Pembantu Tadbir/Pembantu Tadbir, GBK, Guru Data dan portfolio berkaitan.

Manual SMKBBSL 2026 mempunyai susunan JM07 yang sedikit berbeza. Sistem **tidak menimpa manual sekolah**; perbezaan direkodkan sebagai item semakan 2027.

## Model mata 2027

> Nilai mata bukan markah rasmi KPM. Ia ialah instrumen dalaman sekolah untuk membandingkan beban tugas secara konsisten.

| Kategori | Cadangan asas | Rasional |
|---|---:|---|
| Pengerusi/Naib Pengerusi ex-officio Pengetua/PK | 0 | Tanggungjawab hakiki jawatan pengurusan; kekal dalam hierarchy tetapi tidak menduplikasi pemberatan |
| Portfolio kritikal dengan beban masa khusus (cth. Guru Data) | 5 | Beban khusus disokong pekeliling |
| SU Kurikulum / SU HEM / SU Sukan / Guru Kelas / portfolio teras setara | 5 | Operasi berterusan, pelaporan dan koordinasi |
| Ketua Panitia / Ketua Guru Penasihat utama | 4 | Memimpin unit dan menyelaras ahli/program |
| Setiausaha / Penyelaras / Bendahari | 3 | Tanggungjawab operasi dan dokumentasi utama |
| Penolong Setiausaha / Penolong Penyelaras / Penolong Bendahari | 2 | Tanggungjawab sokongan khusus |
| AJK | 1 | Keahlian aktif biasa |
| Keahlian automatik/ex-officio tanpa tugasan operasi tambahan | 0–1 | Elak inflasi mata daripada keahlian struktur semata-mata |

Setiap `committee_role_template` boleh mempunyai `points_override` sendiri. Oleh itu jawatan bernama sama boleh mempunyai bobot berbeza jika skop tugas jawatankuasa berbeza.

## Workflow sesi

- **2026 — PUBLISHED/ARCHIVED:** rekod sejarah, read-only.
- **2027 — DRAFT:** bebas rearrange, bulk move, padam dan ubah struktur.
- **2027 — REVIEW:** masih boleh pembetulan, tetapi dianggap dalam semakan pentadbir.
- **2027 — PUBLISHED:** dikunci; sesi published sebelumnya menjadi ARCHIVED.

Mata disimpan sebagai **snapshot pada pelantikan setiap sesi**. Mengubah point rule 2027 tidak mengubah keputusan 2026.

## Audit automatik 2027

Dashboard semakan menyediakan:

- guru >120% purata mata;
- guru <80% purata mata;
- guru tanpa pelantikan;
- jawatan wajib yang masih kosong;
- role melebihi kapasiti;
- duplicate pelantikan;
- duplicate nama jawatankuasa;
- kadar jawatankuasa yang sudah dipetakan kepada kod Manual;
- perbandingan mata dan bilangan tugas dengan sesi sebelumnya.

## Strategi migrasi

1. Jalankan migration sessions/hierarchy.
2. Pastikan 2026 ditanda PUBLISHED.
3. Sistem membina template jawatan awal untuk semua jawatankuasa daripada pelantikan aktif 2026.
4. Struktur manual utama diperkemaskan dengan `required_role`, `min_count`, `max_count`, hierarchy dan sumber.
5. Clone 2026 → 2027 melalui UI.
6. Rearrange 2027 sahaja.
7. Semak dashboard konflik/kekosongan.
8. REVIEW → PUBLISHED selepas pengesahan pengurusan.
