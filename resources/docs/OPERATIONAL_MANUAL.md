# Panduan Operasional Harian ERPC

> Buku panduan untuk pengguna non-teknis: tim internal, portal buyer, dan portal supplier.  
> Teks penjelasan dalam Bahasa Indonesia. Nama menu/tombol mengikuti tampilan sistem (bahasa Inggris).

## Daftar Isi

1. [Pengantar](#0-pengantar)
2. [Operasional Internal](#1-operasional-internal)
3. [Portal Buyer](#2-portal-buyer)
4. [Portal Supplier](#3-portal-supplier)
5. [Lampiran](#4-lampiran)

---

## 0. Pengantar

### Apa itu ERPC?

ERPC adalah sistem operasional pengadaan dan trading B2B. Tim menggunakan ERPC setiap hari untuk request, tender, quote, approval, order, pengiriman, dan invoice — sebelum atau bersamaan dengan pencatatan di sistem keuangan perusahaan.

### Siapa memakai apa?

| Anda adalah… | Masuk ke… | Bagian panduan |
|--------------|-----------|----------------|
| Staf internal (CP, Key Account, Finance, approver) | Aplikasi internal (panel tim) | Bagian 1 |
| Pengguna buyer | **Buyer Portal** | Bagian 2 |
| Pengguna supplier | **Supplier Portal** | Bagian 3 |

Jika staf internal memiliki akses ke lebih dari satu team/tenant, pilih team yang sesuai sebelum mulai bekerja. Data Request, approval, master data, dan pengaturan mengikuti team yang sedang aktif.

### Cara memakai panduan ini

1. Cari bagian sesuai peran Anda.
2. Baca ringkasan singkat, lalu ikuti langkah **Bagaimana caranya…**
3. Lihat **Lampiran** untuk istilah, stage, dan siapa yang menyetujui apa.

---

## 1. Operasional Internal

### Ruang kerja harian

Pusat pekerjaan harian adalah **Request View**. Satu Request menyatukan proses dari inquiry sampai penutupan melalui delapan tab: **Requested Items** · **Supplier Quotes** · **Buyer Quotes** · **Supplier Orders** · **Goods Receive** · **Invoices** · **Fulfillment** · **Completion Report**. Tab akan tersedia mengikuti stage dan approval yang sudah terpenuhi. Di bagian bawah Request View, widget panduan menampilkan langkah yang perlu dilakukan untuk tab aktif.

### Dashboard

Gunakan Dashboard untuk menentukan prioritas kerja sebelum membuka Request:

- **Active Requests** merangkum Request aktif berdasarkan fase.
- **Quotes Expiring Soon** menunjukkan buyer quote yang mendekati masa berlaku.
- **Awaiting Payment** menampilkan invoice buyer yang belum dibayar, termasuk yang terlambat.
- **Requires Attention** menggabungkan pekerjaan yang perlu segera ditindaklanjuti.
- **Pipeline by Stage** memperlihatkan jumlah dan nilai Request pada setiap stage.
- **Current Month Revenue**, **Invoices Paid**, **Pending Revenue**, dan **Previous Month** merangkum pendapatan dari invoice buyer.

### Master Data

**Buyers** menyimpan data pelanggan, Key Account, pengguna portal, dan batas kredit yang dipakai dalam Request serta transaksi buyer.

**Suppliers** menyimpan data vendor, status PKP, ketentuan pengiriman, pengguna portal, dan hubungan supplier dengan Article.

**Articles** adalah daftar barang atau jasa yang dipakai untuk mencocokkan item Request. Satu Article dapat dihubungkan ke beberapa supplier agar auto tender dapat membuat penawaran ke vendor yang sesuai.

**Categories** mengelompokkan buyer, supplier, dan Article untuk memudahkan pencarian, penyaringan, serta navigasi katalog.

**People** menyimpan kontak buyer atau supplier. Kontak ini juga dapat dipilih sebagai PIC dalam proses pengiriman.

**Projects** mengelompokkan beberapa Request dalam satu pekerjaan besar atau bertahap sehingga pembelian, pendapatan, dan margin dapat dipantau bersama.

### Workflow

- **Workflow → Requests** adalah daftar seluruh pekerjaan. Buat Request baru, cari berdasarkan nomor atau buyer, pantau stage, lalu buka Request View untuk menjalankan proses harian.
- **Workflow → Projects** dipakai ketika beberapa Request perlu dikelompokkan berdasarkan buyer, inisiatif, atau proyek yang sama.

### Approval

- **Approval → Registrations** untuk meninjau pendaftaran pengguna buyer dari katalog publik.
- **Approval → Credit Limit Requests** untuk persetujuan perubahan batas kredit buyer oleh Finance.
- **Approval → Credit Limit Acceptances** untuk penerimaan dokumen QE, PNL, PO, dan dokumen pembayaran sesuai peran approver.
- **Approval → Goods Receive** untuk memeriksa dokumen penerimaan barang.
- **Approval → Quotation Evaluations** untuk meninjau perbandingan dan pemilihan supplier.
- **Approval → Profit & Loss** untuk memeriksa biaya, harga jual, dan margin.
- **Approval → Supplier Orders** untuk dual approval PO sebelum dikirim kepada supplier.

### Finance

Menu Finance memberi daftar lintas-Request: **Buyer Quotes** untuk penawaran kepada buyer, **Supplier Quotes** untuk respons vendor, **Buyer Orders** untuk pesanan buyer, **Supplier Orders** untuk PO supplier, dan **Credit Limits** untuk memantau batas, pemakaian, serta sisa kredit setiap buyer.

### Settings operasional

Pastikan **Settings → Currencies**, **Exchange Rates**, **Tax Codes**, dan **Unit Of Measures** terpelihara agar mata uang, konversi, pajak, dan satuan pada dokumen konsisten. **Email Settings** untuk SMTP dan **Email Templates** biasanya disiapkan saat setup awal; tanyakan kepada Administrator jika email tidak terkirim atau template perlu diubah.

### Bagaimana caranya: Buat Request, tambah item, dan match Article

**Siapa:** CP  
**Kapan:** Ada inquiry baru dari buyer  
**Hasil:** Request berstatus Draft dengan semua item matched

1. Buka **Workflow → Requests**, lalu pilih **New request**.
2. Pilih **Buyer**, tentukan type goods atau service, isi priority, dan lengkapi informasi inquiry.
3. Simpan Request, lalu buka tab **Requested Items**.
4. Tambahkan satu baris untuk setiap barang atau jasa yang diminta buyer beserta quantity dan satuannya.
5. Match setiap baris ke **Article** yang sesuai. Buat atau perbarui Article lebih dahulu bila belum tersedia.
6. Periksa kembali deskripsi dan quantity, lalu pastikan semua item sudah matched sebelum melanjutkan ke tender.

### Bagaimana caranya: Jalankan auto tender dan kelola Supplier Quotes

**Siapa:** CP  
**Kapan:** Semua item Request sudah matched  
**Hasil:** Quote per supplier aktif tersedia untuk Article terkait

1. Majukan stage Request dari Draft ke Awaiting Supplier Response untuk memicu auto tender.
2. Pastikan sistem satu kali membuat quote untuk setiap supplier aktif yang terhubung dengan Article terkait; membuka tab **Supplier Quotes** saja tidak memicu auto tender.
3. Periksa item pada setiap quote, lalu kirim email permintaan penawaran kepada supplier.
4. Saat respons diterima, isi atau perbarui harga, currency, payment terms, dan validity.
5. Bandingkan respons dan tindak lanjuti quote yang masih berstatus pending.
6. Ubah status penawaran yang dipilih menjadi selected dan yang tidak dipakai menjadi rejected.

### Bagaimana caranya: Siapkan dan approve Quotation Evaluation

**Siapa:** CP menyiapkan; Senior dan KA menyetujui  
**Kapan:** Supplier quotes sudah dibandingkan  
**Hasil:** Quotation Evaluation (QE) berstatus Approved

1. Dari Request, buat QE berdasarkan supplier quotes yang akan dibandingkan.
2. Periksa harga, lead time, status PKP, dan payment terms setiap supplier.
3. Pilih penawaran yang sesuai dan pastikan personel approval sudah benar.
4. Unduh PDF QE untuk pemeriksaan dan penandatanganan.
5. Unggah dokumen QE yang sudah disiapkan sebagai bukti resmi.
6. Senior meninjau melalui **Approval → Quotation Evaluations**.
7. KA membuka dokumen pending melalui **Approval → Credit Limit Acceptances**, memilih **View Document**, lalu **Approve** bila isinya benar.
8. Pastikan status QE menjadi Approved sebelum menyiapkan buyer quote.

### Bagaimana caranya: Buat dan kirim Buyer Quote

**Siapa:** CP dan KA  
**Kapan:** QE sudah Approved atau aturan bypass sudah terpenuhi  
**Hasil:** Quote terkirim; berstatus Accepted dan buyer PO terunggah bila diterima

1. Buka tab **Buyer Quotes** pada Request.
2. Copy item dan harga dari supplier quote yang dipilih atau buat buyer quote secara manual.
3. Tentukan margin, currency, validity, dan payment terms. Pastikan seluruh persentase payment terms berjumlah 100%.
4. Periksa harga jual, pajak, dan informasi buyer.
5. Kirim quote melalui email dan lampirkan atau unduh PDF bila diperlukan.
6. Pantau respons buyer, lalu ubah status menjadi Accepted atau Rejected sesuai keputusan.
7. Saat quote Accepted, unggah buyer PO pada quote tersebut sebelum melanjutkan ke order.

### Bagaimana caranya: Siapkan dan approve PNL

**Siapa:** CP menyiapkan; Senior dan KA menyetujui  
**Kapan:** Buyer sudah menerima buyer quote  
**Hasil:** PNL Approved sebelum pembelian dilakukan

1. Buat PNL dari Request setelah buyer quote berstatus Accepted.
2. Tinjau cost, sell, dan margin untuk setiap kelompok supplier.
3. Pastikan nilai pada PNL sesuai dengan supplier quote terpilih dan buyer quote yang diterima.
4. Unduh PDF PNL untuk pemeriksaan dan penandatanganan.
5. Unggah dokumen PNL yang sudah disiapkan.
6. Senior meninjau PNL melalui **Approval → Profit & Loss**.
7. KA membuka dokumen melalui **Approval → Credit Limit Acceptances** dan memilih **Approve** bila isinya benar.
8. Pastikan PNL berstatus Approved sebelum membuat komitmen pembelian.

### Bagaimana caranya: Buat Buyer Order, terbitkan invoice, dan catat pembayaran

**Siapa:** CP; Finance memantau  
**Kapan:** PNL Approved, buyer quote berstatus Accepted, dan tidak ada buyer quote yang masih Sent tanpa PO

**Hasil:** Order confirmed, invoice terbit, dan pembayaran tercatat

1. Buka tab **Invoices** pada Request. Tab ini menampilkan Buyer Orders.
2. Buat Buyer Order dari buyer quote yang sudah Accepted.
3. Periksa item, nilai, dan payment terms, lalu lakukan **Confirm**. Saat order dikonfirmasi, kredit buyer akan dicadangkan.
4. Pada baris order yang sudah confirmed, pilih **Issue Invoice** untuk menerbitkan dan mengirim invoice kepada buyer.
5. Saat pembayaran diterima, pilih **Record Payment**.
6. Isi method, date, reference, nilai pembayaran, dan unggah proof.
7. Simpan pembayaran, lalu pastikan status invoice diperbarui. Kredit buyer akan dilepas sesuai pembayaran yang sudah dikonfirmasi.

### Bagaimana caranya: Buat, approve, dan kirim Supplier Order

**Siapa:** CP membuat; dua Senior yang berbeda menyetujui  
**Kapan:** PNL Approved dan jalur order sudah siap  
**Hasil:** PO berstatus Approved lalu Sent kepada supplier

1. Buka tab **Supplier Orders** pada Request.
2. Pilih **Create from Accepted Quote**, lalu buat satu PO untuk setiap supplier yang dipilih berdasarkan item pada accepted buyer quote.
3. Periksa item, quantity, harga, currency, pajak, dan supplier pada setiap PO.
4. Pilih **Confirm** untuk mengirim PO ke antrean approval.
5. Dua Senior yang berbeda membuka **Approval → Supplier Orders** dan masing-masing memberikan approval.
6. Pastikan PO berstatus Approved setelah approval kedua. Jangan kirim PO yang belum mendapat dua approval.
7. Setelah Approved, kirim PO melalui email kepada supplier dan sertakan PDF.
8. Jika dokumen PO perlu penerimaan KA, unggah dokumen lalu proses melalui **Approval → Credit Limit Acceptances**.

Tab **Invoices** dan **Supplier Orders** terbuka bersamaan setelah gate terpenuhi; pembuatan Supplier Order tidak perlu menunggu Buyer Order dibuat.

### Bagaimana caranya: Terima barang dan approve dokumen Goods Receive

**Siapa:** CP atau tim warehouse; approver sesuai kebijakan  
**Kapan:** Barang dari supplier sudah datang  
**Hasil:** Dokumen Goods Receive Approved dan action **Create Shipment** dapat digunakan untuk Request goods

1. Buka Request, lalu pilih tab **Goods Receive**.
2. Pilih supplier order yang barangnya sudah diterima.
3. Unggah dokumen penerimaan sebagai satu batch untuk supplier order tersebut.
4. Periksa kembali supplier order dan dokumen yang terlampir, lalu simpan batch.
5. Approver membuka **Approval → Goods Receive**, meninjau batch, lalu memilih **Approve** bila dokumen sesuai.
6. Pastikan semua dokumen berstatus Approved sebelum menggunakan **Create Shipment** pada tab **Fulfillment**.

### Bagaimana caranya: Kelola Fulfillment barang atau jasa

**Siapa:** CP atau tim logistics  
**Kapan:** Goods Receive sudah Approved untuk goods, atau pekerjaan jasa siap diserahterimakan  
**Hasil:** Delivery Order terkirim untuk goods atau Acceptance Report tersimpan untuk jasa

1. Buka Request, lalu pilih tab **Fulfillment**.
2. Untuk Request goods, buka **Shipments**, lalu pilih **Create Shipment** untuk membuat shipment berdasarkan supplier order.
3. Pilih PIC buyer dari data **People** agar nama dan nomor telepon tercantum pada Delivery Order.
4. Lengkapi status dan informasi pengiriman, lalu generate PDF Delivery Order (DO).
5. Periksa PDF, kemudian kirim shipment atau DO kepada buyer melalui email.
6. Untuk Request service, buka **Acceptance Reports** pada tab yang sama, lalu unggah laporan penerimaan pekerjaan.
7. Untuk Request mixed, lengkapi **Shipments** dan **Acceptance Reports** sebelum menutup pekerjaan.

### Bagaimana caranya: Lengkapi Completion Report

**Siapa:** CP menyiapkan; Finance menyetujui dokumen pembayaran  
**Kapan:** Pengiriman atau pekerjaan selesai dan Request memasuki proses closing  
**Hasil:** Dokumen completion dan dokumen pembayaran tersimpan serta Approved

1. Buka Request, lalu pilih tab **Completion Report**.
2. Unggah dokumen yang membuktikan pekerjaan atau pengiriman sudah selesai.
3. Jika dokumen menjadi bukti pembayaran, tandai sebagai payment document dan pilih payment term yang sesuai.
4. Simpan dokumen, lalu pastikan informasi dan lampirannya dapat dibuka.
5. Finance membuka **Approval → Credit Limit Acceptances**, memilih **View Document**, lalu **Approve** bila dokumen pembayaran sesuai.
6. Pastikan status dokumen sudah Approved dan matriks pembayaran pada Request telah diperbarui sebelum closing.

### Bagaimana caranya: Ajukan kenaikan batas kredit buyer

**Siapa:** Pemohon; dua Finance approver yang berbeda  
**Kapan:** Batas kredit aktif buyer tidak cukup untuk transaksi berikutnya  
**Hasil:** Batas kredit aktif meningkat setelah dua approval Finance

1. Buka **Approval → Credit Limit Requests**, lalu pilih header action **Request Credit Limit**.
2. Pilih buyer, periksa batas kredit aktif saat ini, masukkan batas baru yang lebih tinggi, lalu kirim permintaan.
3. Finance approver pertama membuka **Approval → Credit Limit Requests**, meninjau buyer, batas saat ini, dan batas yang diminta, lalu memilih **Approve**.
4. Finance approver kedua meninjau permintaan yang sama dan memberikan approval kedua.
5. Pastikan status permintaan menjadi Approved. Batas kredit aktif baru hanya berubah setelah dua Finance approver yang berbeda menyetujuinya.
6. Jika permintaan ditolak, baca alasan penolakan dan ajukan ulang dengan nilai atau informasi yang sudah diperbaiki.

### Bagaimana caranya: Undang Portal Users dan proses Registrations

**Siapa:** CP, KA, atau Admin  
**Kapan:** Buyer atau supplier membutuhkan akses portal, atau ada signup dari katalog publik  
**Hasil:** User portal aktif atau registration ditolak dengan keputusan yang tercatat

1. Untuk undangan langsung, buka **Master Data → Buyers** atau **Master Data → Suppliers**, lalu pilih perusahaan terkait.
2. Pada halaman view buyer atau supplier, pilih header action **Invite Portal User**, masukkan alamat email pengguna, lalu kirim invitation.
3. Gunakan tab **Portal Users** untuk melihat status pengguna serta melakukan resend, revoke, atau deactivate bila diperlukan.
4. Pastikan pengguna menerima undangan dan menyelesaikan aktivasi akun portal.
5. Untuk signup dari katalog publik, buka **Approval → Registrations**.
6. Periksa identitas dan informasi perusahaan pada registration yang masih pending.
7. Pilih **Approve** untuk memberikan akses buyer portal atau **Reject** bila pendaftaran tidak dapat diterima.

### Bagaimana caranya: Gunakan Activity Timeline dan Request Notes

**Siapa:** CP dan KA; buyer atau supplier melalui portal  
**Kapan:** Kapan saja selama Request berjalan  
**Hasil:** Jejak aktivitas tersimpan dan percakapan Request tercatat sesuai audience

1. Buka Request, lalu scroll ke widget **Activities** di bagian bawah halaman.
2. Baca urutan perubahan, upload, pergerakan kredit, dan milestone; buka detail aktivitas bila perlu.
3. Pada bagian catatan di bawah timeline, tulis pesan dan tambahkan lampiran bila diperlukan.
4. Untuk staf internal, pilih visibility **Internal only**, **Share with buyer**, atau **Share with supplier**. Jika memilih supplier, tentukan supplier tujuan.
5. Buyer atau supplier dapat menulis catatan dari portal; catatan akan terlihat hanya pada audience yang sesuai.
6. Gunakan balasan pada thread yang sama agar konteks keputusan tidak terpisah.

### Bagaimana caranya: Import dan export data

**Siapa:** Admin atau CP  
**Kapan:** Migrasi data, pembaruan massal, atau penyusunan laporan  
**Hasil:** File CSV atau Excel berhasil diunggah atau diunduh

1. Buka halaman list **People**, **Buyers**, **Suppliers**, atau **Articles**.
2. Pilih **Import**, unduh atau ikuti format kolom yang tersedia, lalu siapkan file CSV atau Excel.
3. Unggah file, periksa hasil validasi, dan perbaiki baris yang gagal sebelum mengulang import.
4. Gunakan **Export** pada list tersebut untuk mengunduh data master yang dibutuhkan.
5. Untuk laporan transaksi, gunakan **Export** pada list Quotation Evaluations (QE), Profit & Loss (PNL), Buyer Quotes, Supplier Quotes, Buyer Orders, atau Supplier Orders.
6. Jika export diproses di background, tunggu notifikasi selesai lalu gunakan link download yang tersedia.

### Goods, service, atau mixed?

Pilih alur sesuai isi Request:

- **Goods:** unggah dan approve dokumen pada **Goods Receive**, lalu proses pengiriman melalui **Fulfillment → Shipments** dan generate DO.
- **Service:** catat serah terima pekerjaan melalui **Fulfillment → Acceptance Reports**; Goods Receive bersifat opsional atau lebih ringan sesuai kebutuhan.
- **Mixed:** selesaikan kedua jalur, yaitu **Shipments** untuk barang dan **Acceptance Reports** untuk jasa.
- Approval QE, PNL, dan supplier PO mengikuti pola yang sama untuk goods maupun service.

---

## 2. Portal Buyer

### Ruang kerja Buyer

Masuk ke **Buyer Portal** menggunakan akun yang sudah diundang atau disetujui. Portal hanya menampilkan Request milik perusahaan Anda. Informasi supplier, harga supplier, margin, dan catatan internal tidak ditampilkan.

Pada daftar **Requests**, buka satu Request untuk melihat:

- **Request Progress** — milestone yang aman untuk buyer, dari permintaan awal sampai invoice, pembayaran, dan pengiriman.
- **Quotes** — penawaran yang sudah dikirim kepada Anda.
- **Invoices** — invoice yang sudah diterbitkan. Status internal **Sent** ditampilkan sebagai **Received**.
- **Payments** — jadwal pembayaran per termin dan tindakan untuk mengirim bukti pembayaran.
- **Shipments** — status dan informasi pengiriman barang.
- **Activities** dan catatan — aktivitas yang dibagikan kepada buyer serta percakapan terkait Request.

### Bagaimana caranya: Lihat daftar dan detail Request

**Siapa:** Pengguna buyer

**Kapan:** Ingin memeriksa permintaan atau progres pekerjaan

**Hasil:** Detail Request dan milestone terbaru terlihat

1. Masuk ke **Buyer Portal**, lalu buka **Requests**.
2. Cari Request berdasarkan nomor atau informasi yang tersedia pada daftar.
3. Pilih Request untuk membuka halaman detail.
4. Baca **Request Progress** untuk mengetahui posisi proses saat ini.
5. Buka bagian **Quotes**, **Invoices**, **Payments**, **Shipments**, atau **Activities** sesuai informasi yang dibutuhkan.
6. Hubungi tim internal melalui catatan bila ada informasi yang perlu dikonfirmasi.

### Bagaimana caranya: Terima quote, unggah PO, atau tolak quote

**Siapa:** Pengguna buyer yang berwenang mengambil keputusan

**Kapan:** Quote baru sudah tersedia pada Request

**Hasil:** Quote diterima beserta PO buyer, atau tercatat sebagai ditolak

1. Buka **Requests**, lalu pilih Request terkait.
2. Di panel **Quote awaiting your confirmation** dekat bagian atas halaman Request, periksa item, quantity, harga, validity, dan payment terms. Gunakan **PDF** bila perlu meninjau dokumen lengkap.
3. Jika penawaran sesuai, pilih **Accept**, lalu pilih **Confirm** pada dialog konfirmasi.
4. Setelah konfirmasi, modal **Upload Purchase Order** terbuka otomatis. Unggah **PO File** yang benar, lalu pilih **Upload** untuk menyelesaikan acceptance.
5. Jika penawaran tidak sesuai, pilih **Reject** pada panel yang sama dan konfirmasi keputusan; sampaikan alasannya melalui catatan bila diperlukan.
6. Bagian **Quotes** di bawah hanya menampilkan riwayat/reference quote secara read-only dengan action **PDF**.
7. Periksa kembali status quote dan pastikan PO sudah terunggah untuk quote yang diterima.

### Bagaimana caranya: Unduh PDF quote atau invoice

**Siapa:** Pengguna buyer

**Kapan:** Membutuhkan dokumen untuk pemeriksaan, persetujuan, atau arsip

**Hasil:** PDF quote atau invoice tersimpan di perangkat

1. Buka Request terkait.
2. Untuk penawaran, buka **Quotes**, pilih quote, lalu pilih **PDF**.
3. Untuk tagihan, buka **Invoices**, pilih invoice yang sudah diterbitkan, lalu gunakan tindakan unduh PDF.
4. Buka file hasil unduhan dan periksa nomor dokumen, perusahaan, item, serta nilainya.
5. Jika data tidak sesuai, kirim catatan pada Request sebelum melakukan pembayaran atau keputusan lain.

### Bagaimana caranya: Kirim bukti pembayaran

**Siapa:** Pengguna buyer yang menangani pembayaran

**Kapan:** Pembayaran invoice sudah dilakukan

**Hasil:** Bukti pembayaran terkirim dengan status **Pending**

1. Buka Request terkait, lalu buka bagian **Payments**.
2. Cari termin pembayaran yang sudah dibayar.
3. Pada termin tersebut, pilih **Record payment**.
4. Isi informasi pembayaran yang diminta dan unggah bukti pembayaran yang jelas.
5. Kirim pembayaran.
6. Pastikan pembayaran muncul dengan status **Pending**. Status ini berarti bukti masih menunggu konfirmasi staf.
7. Pantau kembali termin pembayaran sampai dikonfirmasi; jangan mengirim bukti yang sama berulang kali kecuali diminta.

### Bagaimana caranya: Lacak Shipments

**Siapa:** Pengguna buyer

**Kapan:** Barang sedang diproses atau sudah dikirim

**Hasil:** Status dan informasi pengiriman terbaru diketahui

1. Buka Request terkait.
2. Pilih **Shipments**.
3. Buka shipment yang ingin diperiksa.
4. Periksa status, informasi tujuan, dan detail pengiriman yang tersedia.
5. Cocokkan perubahan status dengan **Request Progress** pada Request.
6. Bila barang belum diterima atau informasi tidak sesuai, kirim catatan pada Request agar tim dapat menindaklanjuti.

### Bagaimana caranya: Baca timeline dan kirim catatan

**Siapa:** Pengguna buyer

**Kapan:** Membutuhkan riwayat progres atau ingin berkomunikasi dengan tim

**Hasil:** Informasi buyer-visible terbaca dan catatan tersimpan pada Request

1. Buka Request terkait, lalu lihat **Activities** atau timeline.
2. Baca aktivitas dari yang paling relevan untuk mengetahui quote, invoice, pembayaran, atau shipment terbaru.
3. Gunakan bagian catatan di bawah timeline untuk menulis pertanyaan atau informasi.
4. Tambahkan lampiran bila diperlukan.
5. Kirim catatan, lalu lanjutkan balasan pada percakapan yang sama agar konteks tetap utuh.
6. Ingat bahwa portal hanya menampilkan aktivitas dan catatan yang memang dibagikan kepada buyer.

### Bagaimana caranya: Registrasi dari katalog publik

**Siapa:** Calon pengguna buyer yang belum memiliki akun

**Kapan:** Ingin mengirim permintaan dari katalog publik dan mengakses Buyer Portal

**Hasil:** Registrasi terkirim dan menunggu persetujuan

1. Buka katalog publik dan lakukan registrasi melalui formulir yang tersedia.
2. Lengkapi identitas, informasi perusahaan, dan alamat email yang aktif.
3. Kirim registrasi dan tunggu pemeriksaan oleh tim internal melalui **Approval → Registrations**.
4. Setelah disetujui, ikuti petunjuk aktivasi atau login yang dikirimkan.
5. Jika belum dapat masuk, hubungi tim internal untuk memeriksa status registrasi; jangan membuat registrasi berulang dengan email yang sama.

---

## 3. Portal Supplier

### Ruang kerja Supplier

Masuk ke **Supplier Portal** menggunakan akun yang sudah diundang. Data dibatasi untuk perusahaan supplier Anda; supplier lain dan informasi komersial internal tidak ditampilkan.

Menu utama:

- **Requests** — daftar permintaan atau quote yang ditujukan kepada supplier Anda.
- **My Articles** — daftar Article yang terhubung dengan supplier Anda beserta harga penawaran yang dapat dipelihara.

### Bagaimana caranya: Buka request atau quote yang menunggu respons

**Siapa:** Pengguna supplier

**Kapan:** Menerima permintaan penawaran baru atau perlu memeriksa pekerjaan terbuka

**Hasil:** Request dan item yang perlu direspons terlihat

1. Masuk ke **Supplier Portal**, lalu buka **Requests**.
2. Cari Request yang masih menunggu respons.
3. Pilih Request untuk melihat quote dan item yang diminta.
4. Periksa deskripsi, quantity, satuan, dan informasi kebutuhan yang dibagikan.
5. Baca timeline atau catatan supplier untuk melihat penjelasan tambahan.
6. Siapkan harga dan ketentuan sebelum mengirim respons.

### Bagaimana caranya: Isi dan submit quote

**Siapa:** Pengguna supplier yang berwenang memberikan penawaran

**Kapan:** Request sudah diperiksa dan harga siap diberikan

**Hasil:** Quote dengan harga dan ketentuan terkirim kepada tim internal

1. Buka **Requests**, lalu pilih Request dan quote yang menunggu respons.
2. Pada bagian **Your Prices**, isi unit price untuk setiap main item. Semua harga wajib diisi; jika tidak dapat memberikan penawaran, gunakan action **Decline**.
3. Pada bagian **Quote Details**, pilih **Currency** dan periksa quantity agar harga tidak salah dasar perhitungan.
4. Isi **Quote Valid Until** sesuai masa berlaku penawaran.
5. Isi **Notes** bila ada syarat atau penjelasan khusus.
6. Unggah **Quotation Document** bila dokumen penawaran tersedia.
7. Periksa kembali seluruh harga dan detail quote, lalu pilih **Submit Quote**.
8. Pastikan respons sudah tersimpan atau terkirim; gunakan catatan supplier pada Request untuk klarifikasi lanjutan.

### Bagaimana caranya: Update harga di My Articles

**Siapa:** Pengguna supplier yang mengelola katalog harga

**Kapan:** Harga penawaran Article berubah atau perlu diperbarui

**Hasil:** Harga Article supplier tersimpan dengan informasi terbaru

1. Buka **My Articles**.
2. Cari Article berdasarkan nama atau informasi yang tersedia.
3. Buka Article yang akan diperbarui.
4. Ubah harga penawaran dan informasi harga lain yang tersedia.
5. Periksa currency dan nilai sebelum menyimpan.
6. Simpan perubahan, lalu pastikan harga terbaru tampil pada Article.
7. Perbarui harga secara berkala agar permintaan penawaran berikutnya menggunakan referensi yang relevan.

### Bagaimana caranya: Baca dan kirim catatan supplier

**Siapa:** Pengguna supplier

**Kapan:** Membutuhkan klarifikasi atau ingin menyampaikan informasi terkait Request

**Hasil:** Percakapan supplier tersimpan dan hanya tersedia untuk audience yang sesuai

1. Buka **Requests**, lalu pilih Request terkait.
2. Baca timeline dan catatan yang dibagikan kepada supplier Anda.
3. Tulis pertanyaan, klarifikasi ketersediaan, lead time, atau syarat khusus pada bagian catatan.
4. Tambahkan lampiran bila diperlukan.
5. Kirim catatan dan gunakan thread yang sama untuk balasan berikutnya.
6. Catatan supplier dibatasi untuk supplier Anda dan tim internal; supplier lain tidak dapat melihatnya.

### Bagaimana caranya: Pantau hasil quote dan Supplier Order

**Siapa:** Pengguna supplier

**Kapan:** Quote sudah disubmit atau tim internal sudah memilih penawaran

**Hasil:** Supplier mengetahui tindak lanjut yang dibagikan tanpa melihat informasi rahasia

1. Buka **Requests** dan pilih Request yang sebelumnya sudah direspons.
2. Periksa status quote untuk mengetahui apakah masih ditinjau atau sudah ada tindak lanjut.
3. Jika quote dipilih dan Supplier Order (PO) sudah dikirim, buka informasi atau dokumen PO yang tersedia.
4. Periksa item, quantity, harga, alamat, dan ketentuan yang tercantum pada PO.
5. Gunakan catatan supplier untuk meminta koreksi atau klarifikasi sebelum pemenuhan.
6. Supplier hanya melihat data miliknya dan informasi yang dibagikan untuk menjalankan pesanan. Harga supplier lain, perbandingan penawaran, harga jual buyer, margin, PNL, approval internal, dan catatan internal tidak ditampilkan.

---

## 4. Lampiran

### Glosarium

| Istilah | Arti |
|---------|------|
| Request | Catatan utama untuk satu pekerjaan atau transaksi dari permintaan awal hingga selesai. |
| Article | Barang atau jasa yang dipakai untuk mencocokkan item dalam Request. |
| Supplier Quote | Penawaran harga dan ketentuan dari supplier. |
| Quotation Evaluation (QE) | Dokumen perbandingan penawaran supplier sebelum penawaran kepada buyer disiapkan. |
| Buyer Quote | Penawaran harga dan ketentuan yang dikirim kepada buyer. |
| Profit & Loss (PNL) | Dokumen pemeriksaan biaya, harga jual, dan margin sebelum pembelian. |
| Buyer Order / tab Invoices | Pesanan buyer yang dikelola pada tab **Invoices**; invoice buyer diterbitkan dari pesanan ini. |
| Supplier Order (PO) | Pesanan pembelian kepada supplier yang memerlukan approval sebelum dikirim. |
| Goods Receive | Dokumen penerimaan barang dari supplier. |
| Fulfillment | Tahap pemenuhan berupa pengiriman barang atau laporan penerimaan jasa. |
| Shipment / DO | Catatan pengiriman dan **Delivery Order** untuk barang. |
| Acceptance Report | Laporan penerimaan untuk pekerjaan atau Request jasa. |
| Completion Report | Dokumen penyelesaian pekerjaan dan bukti terkait pembayaran. |
| Credit Limit Acceptances | Menu approval untuk dokumen QE, PNL, PO, dan dokumen pembayaran. |
| Credit Limit Request | Permintaan perubahan batas kredit buyer yang memerlukan approval Finance. |

### Stage dan tab Request View

Badge stage pada daftar dan Request View memakai nama tab beserta posisinya, misalnya **Invoices (6/8)**, sedangkan filter stage memakai nama stage lengkap seperti **Awaiting Buyer Confirmation**. Tabel berikut menunjukkan tab yang relevan; pada stage **Awaiting Shipment**, badge **Shipments (7/8)** merujuk bagian Shipments di tab **Fulfillment**.

| Stage | Tab Request View |
|-------|------------------|
| Draft | Requested Items |
| Awaiting Supplier Response | Supplier Quotes |
| Preparing Buyer Quote | Buyer Quotes |
| Awaiting Buyer Confirmation | Invoices |
| Preparing Supplier Order | Supplier Orders |
| Goods Receive | Goods Receive |
| Awaiting Shipment | Fulfillment (Shipments) |
| Shipped / Delivered | Completion Report |
| Invoiced / Paid / Completed | Completion Report |
| Cancelled | — |

### Matriks approval

| Dokumen | Siapa | Menu |
|---------|-------|------|
| Quotation Evaluation | CP siapkan; Senior; KA via dokumen | Approval → Quotation Evaluations · Credit Limit Acceptances |
| Profit & Loss | CP siapkan; Senior + KA | Approval → Profit & Loss · Credit Limit Acceptances |
| Supplier Order | Senior (min. 2 orang berbeda) lalu kirim | Approval → Supplier Orders |
| Goods Receive | Approver sesuai kebijakan | Approval → Goods Receive |
| Completion / payment docs | Finance | Credit Limit Acceptances |
| Credit Limit Request | Finance (×2) | Approval → Credit Limit Requests |
| Portal registration | CP / Admin | Approval → Registrations |

### FAQ

#### Tab Request terkunci / tidak bisa dibuka
Tab pada **Request View** terbuka mengikuti gate proses. Untuk melewati gate QE, pastikan **Quotation Evaluation (QE)** sudah Approved atau supplier quote sudah obtained dan selected. Pastikan juga **Profit & Loss (PNL)** sudah Approved, buyer quote sudah Accepted dengan buyer PO terunggah, dan tidak ada buyer quote yang masih Sent. Untuk Request barang, Supplier Order harus sudah dibuat, Approved, dan Sent sebelum tab **Goods Receive** terbuka; setelah dokumen Goods Receive diunggah, semua dokumen harus Approved sebelum action **Create Shipment** dapat digunakan pada tab **Fulfillment**. Jika semua syarat terpenuhi tetapi tab masih terkunci, periksa stage dan status dokumen terkait bersama CP atau approver.

#### Buyer quote sudah expired
Sistem mengirim reminder kepada pembuat quote pada 7, 3, dan 1 hari sebelum expiry. Setelah quote expired, buyer dan Key Account menerima notifikasi. Buyer dapat melihat status Expired, tetapi tidak dapat menerima quote tersebut. Hubungi tim internal agar CP atau Key Account memperpanjang masa berlaku atau membuat revisi quote, lalu tunggu quote aktif dikirim kembali.

#### Credit buyer kurang
Ajukan kenaikan melalui **Approval → Credit Limit Requests** dengan action **Request Credit Limit**. Permintaan harus disetujui oleh dua Finance approver yang berbeda sebelum batas aktif berubah. Konfirmasi Buyer Order memakai kredit buyer, sehingga order baru dapat dilanjutkan setelah available credit mencukupi.

#### Belum bisa login portal
Akun portal harus lebih dahulu diundang melalui header action **Invite Portal User** pada halaman view Buyer atau Supplier, atau berasal dari registration katalog publik yang sudah disetujui melalui **Approval → Registrations**. Tab **Portal Users** dipakai untuk melihat status serta melakukan resend, revoke, deactivate, atau reactivate. Periksa email undangan, termasuk folder spam, lalu gunakan alamat email yang diundang. Jika belum ada email atau registration masih pending, hubungi tim internal.

### Batasan yang perlu diketahui
- Invoice buyer biasanya diterbitkan dari **Buyer Order** (tab **Invoices** pada Request).
- Credit note belum tersedia di layar panel.
- Pelacakan invoice/pembayaran supplier masih terbatas — gunakan PO + dokumen Completion untuk saat ini.
