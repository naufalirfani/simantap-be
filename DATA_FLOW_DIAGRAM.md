# Data Flow Diagram (DFD) - SIMANTAP-BE

## Sistem Informasi Manajemen Talenta dan Penilaian (SIMANTAP)

---

## 1. OVERVIEW SISTEM

Sistem SIMANTAP adalah aplikasi backend untuk mengelola data pegawai, penilaian kompetensi, dan perencanaan suksesi jabatan. Sistem ini mengintegrasikan data dari API eksternal dan menyediakan analisis berbasis kriteria penilaian yang terstruktur.

---

## 2. ENTITAS EKSTERNAL (EXTERNAL ENTITIES)

### 2.1 External API (Sumber Data Pegawai & Peta Jabatan)
- Menyediakan data pegawai dan struktur organisasi
- Data diambil melalui proses sinkronisasi

### 2.2 Admin/User Sistem
- Melakukan konfigurasi penilaian
- Upload data penilaian bulk
- Mengelola standar kompetensi
- Mengakses statistik dan rekomendasi

### 2.3 Client Application (Frontend)
- Mengonsumsi API untuk menampilkan data
- Melakukan pencarian dan filtering
- Menampilkan visualisasi data

---

## 3. STRUKTUR DATABASE

### 3.1 Tabel Master Data

#### **users**
- `id` (Primary Key)
- `name`, `email`, `password`
- Pengguna sistem dengan autentikasi

#### **jenis_jabatan**
- `id` (UUID, Primary Key)
- `name` (Unique)
- Master jenis jabatan (Struktural, Fungsional, Pelaksana)

#### **peta_jabatan** (Struktur Organisasi)
- `id` (UUID, Primary Key)
- `parent_id` (UUID, Self-referencing)
- `nama_jabatan`, `unit_kerja`, `slug`
- `level`, `order_index`
- `bezetting`, `kebutuhan_pegawai`
- `is_pusat`, `jenis_jabatan`, `kelas_jabatan`
- `jabatan_id`, `nama_pejabat` (JSON)
- Hierarki struktur organisasi

#### **pegawai** (Data Pegawai)
- `id` (UUID, Primary Key)
- `pegawai_id` (Unique), `nip` (Indexed)
- `name`, `email`
- `unit_organisasi_name`, `jabatan_name`
- `jenis_jabatan`, `golongan`
- `json` (JSONB - data lengkap)
- `avatar` (Base64)
- `peta_jabatan_id` → FK ke peta_jabatan
- `jenis_jabatan_id` → FK ke jenis_jabatan

---

### 3.2 Tabel Kriteria Penilaian (Hierarki 3 Level)

#### **indikators** (Level 1)
- `id` (UUID, Primary Key)
- `indikator` (Nama indikator)
- `bobot` (Decimal 5,2) - Auto-calculated
- `penilaian` (Kategori penilaian)

#### **subindikators** (Level 2)
- `id` (UUID, Primary Key)
- `subindikator` (Nama sub-indikator)
- `bobot` (Decimal 5,2)
- `isactive` (Boolean)
- `indikator_id` → FK ke indikators (CASCADE)

#### **instrumens** (Level 3 - Kriteria Detail)
- `id` (UUID, Primary Key)
- `instrumen` (Deskripsi kriteria)
- `bobot` (Decimal 5,2) - renamed dari 'skor'
- `subindikator_id` → FK ke subindikators (CASCADE)

---

### 3.3 Tabel Standar & Penilaian

#### **standar_kompetensi_msk**
- `id` (UUID, Primary Key)
- `jenis_jabatan_id` → FK ke jenis_jabatan (CASCADE)
- `subindikator_id` → FK ke subindikators (CASCADE)
- `standar` (TINYINT - nilai standar minimal)
- Definisi standar kompetensi per jenis jabatan

#### **penilaians**
- `id` (BigInt, Auto-increment)
- `pegawai_id` → FK ke pegawai (CASCADE)
- `penilaian` (JSON - data penilaian lengkap)
- Menyimpan nilai penilaian pegawai

#### **syarat_suksesi**
- `id` (BigInt, Auto-increment)
- `jabatan_id` → FK ke peta_jabatan (CASCADE)
- `syarat` (JSON - persyaratan)
- Kriteria suksesi untuk setiap jabatan

---

### 3.4 Tabel Konfigurasi & Statistik

#### **daftar_kotak** (Box Chart Configuration)
- `id` (Primary Key)
- `intervals` (JSON - interval nilai)
- `kotak` (JSON - kategori box)
- Konfigurasi untuk kategorisasi penilaian

#### **statistik** (Materialized View)
- `key` (Statistik key)
- `value` (Bigint - nilai statistik)
- View aggregate dari data pegawai:
  - Total pegawai (by jenis jabatan, gender, eselon, dll)
  - Statistik jabatan fungsional (Utama, Madya, Muda, dll)

---

## 4. DATA FLOW DIAGRAM

### 4.1 Level 0 - Context Diagram

```
┌─────────────────────┐
│   External API      │
│  (Data Pegawai &    │
│  Peta Jabatan)      │
└──────────┬──────────┘
           │
           │ Sync Data Pegawai
           │ Sync Peta Jabatan
           ↓
┌──────────────────────────────────────────────────────┐
│                                                      │
│           SISTEM SIMANTAP BACKEND                    │
│    (Manajemen Talenta & Penilaian Pegawai)          │
│                                                      │
└───────┬─────────────────────────────────┬────────────┘
        │                                 │
        │ Request API                     │ Data Pegawai
        │ (CRUD, Statistik, Rekomendasi)  │ Penilaian
        ↓                                 │ Statistik
┌───────────────────┐                    │
│  Admin/User       │                    │
│  - Konfigurasi    │←───────────────────┘
│  - Upload Data    │
│  - View Reports   │
└───────────────────┘
        ↑
        │ UI Display
        │
┌───────────────────┐
│  Client App       │
│  (Frontend)       │
└───────────────────┘
```

---

### 4.2 Level 1 - Process Decomposition

```
┌─────────────────────────────────────────────────────────────────────────┐
│                         SISTEM SIMANTAP                                 │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│  ┌──────────────────────────────────────────────────────────────┐     │
│  │  P1: SINKRONISASI DATA EKSTERNAL                            │     │
│  │  - Sync Pegawai dari External API                           │     │
│  │  - Sync Peta Jabatan dari External API                      │     │
│  │  - Update Statistik (Materialized View)                     │     │
│  └───────────────┬──────────────────────────────────────────────┘     │
│                  │                                                     │
│                  ↓ Store Data                                          │
│  ┌──────────────────────────────────────────────────────────────┐     │
│  │  D1: Database Tables                                        │     │
│  │  - pegawai, peta_jabatan, jenis_jabatan                     │     │
│  └───────────────┬──────────────────────────────────────────────┘     │
│                  │                                                     │
│                  ↓ Read/Write                                          │
│  ┌──────────────────────────────────────────────────────────────┐     │
│  │  P2: MANAJEMEN KRITERIA PENILAIAN                           │     │
│  │  - CRUD Indikator                                           │     │
│  │  - CRUD Sub-Indikator                                       │     │
│  │  - CRUD Instrumen                                           │     │
│  │  - Auto-calculate bobot hierarki                            │     │
│  └───────────────┬──────────────────────────────────────────────┘     │
│                  │                                                     │
│                  ↓ Store Criteria                                      │
│  ┌──────────────────────────────────────────────────────────────┐     │
│  │  D2: Criteria Tables                                        │     │
│  │  - indikators, subindikators, instrumens                    │     │
│  └───────────────┬──────────────────────────────────────────────┘     │
│                  │                                                     │
│                  ↓ Read Criteria                                       │
│  ┌──────────────────────────────────────────────────────────────┐     │
│  │  P3: MANAJEMEN PENILAIAN                                    │     │
│  │  - Input/Generate Penilaian Pegawai                         │     │
│  │  - Bulk Upload (Excel/CSV)                                  │     │
│  │  - Calculate nilai berdasarkan instrumen                    │     │
│  │  - Sync/recalculate penilaian                               │     │
│  └───────────────┬──────────────────────────────────────────────┘     │
│                  │                                                     │
│                  ↓ Store Assessment                                    │
│  ┌──────────────────────────────────────────────────────────────┐     │
│  │  D3: Assessment Tables                                      │     │
│  │  - penilaians                                               │     │
│  └───────────────┬──────────────────────────────────────────────┘     │
│                  │                                                     │
│                  ↓ Read Assessment + Pegawai                           │
│  ┌──────────────────────────────────────────────────────────────┐     │
│  │  P4: STANDAR KOMPETENSI & SUKSESI                           │     │
│  │  - CRUD Standar Kompetensi MSK                              │     │
│  │  - CRUD Syarat Suksesi per Jabatan                          │     │
│  │  - Compare pegawai vs standar                               │     │
│  └───────────────┬──────────────────────────────────────────────┘     │
│                  │                                                     │
│                  ↓ Store Standards                                     │
│  ┌──────────────────────────────────────────────────────────────┐     │
│  │  D4: Standards Tables                                       │     │
│  │  - standar_kompetensi_msk, syarat_suksesi                   │     │
│  └───────────────┬──────────────────────────────────────────────┘     │
│                  │                                                     │
│                  ↓ Aggregate & Analyze                                 │
│  ┌──────────────────────────────────────────────────────────────┐     │
│  │  P5: REKOMENDASI & ANALISIS                                 │     │
│  │  - Recommend Pegawai untuk Jabatan                          │     │
│  │  - Calculate Gap Analysis                                   │     │
│  │  - Generate Box Chart (9-Box)                               │     │
│  │  - Statistik Dashboard                                      │     │
│  └───────────────┬──────────────────────────────────────────────┘     │
│                  │                                                     │
│                  ↓ Read Statistics                                     │
│  ┌──────────────────────────────────────────────────────────────┐     │
│  │  D5: Analytics                                              │     │
│  │  - statistik (Materialized View)                            │     │
│  │  - daftar_kotak                                             │     │
│  └──────────────────────────────────────────────────────────────┘     │
│                                                                         │
└─────────────────────────────────────────────────────────────────────────┘
```

---

## 5. DETAIL DATA FLOW BY PROCESS

### 5.1 P1: SINKRONISASI DATA EKSTERNAL

**Input:**
- External API endpoint untuk pegawai
- External API endpoint untuk peta jabatan
- Authentication token

**Process:**
1. **Sync Pegawai** (`POST /api/pegawai/sync`)
   - Fetch data pegawai dari External API
   - Parse dan normalize data
   - Insert/Update tabel `pegawai`
   - Update relasi ke `peta_jabatan` dan `jenis_jabatan`
   - Return: summary (Inserted/Updated/Errors)

2. **Sync Peta Jabatan** (`POST /api/peta-jabatan/sync`)
   - Fetch struktur organisasi dari External API
   - Build hierarki jabatan (parent-child)
   - Insert/Update tabel `peta_jabatan`
   - Update level dan order_index
   - Return: summary hasil sync

3. **Update Statistik** (`POST /api/statistik/sync`)
   - Refresh materialized view `statistik`
   - Aggregate data pegawai by kategori
   - Update counts (jenis jabatan, gender, eselon, dll)

**Output:**
- Data pegawai tersimpan di DB
- Struktur organisasi tersimpan di DB
- Statistik ter-update

**Data Stores:**
- D1.1: `pegawai`
- D1.2: `peta_jabatan`
- D1.3: `jenis_jabatan`
- D5.1: `statistik`

---

### 5.2 P2: MANAJEMEN KRITERIA PENILAIAN

**Input:**
- Request CRUD dari Admin/User

**Process:**

1. **Indikator Management** (`/api/indikators`)
   - GET: List all indikators dengan bobot
   - POST: Create new indikator
   - PUT: Update indikator
   - DELETE: Delete indikator (cascade ke sub-indikator & instrumen)
   - Auto-calculate bobot dari sum sub-indikator

2. **Sub-Indikator Management** (`/api/subindikators`)
   - GET: List sub-indikators by indikator_id
   - POST: Create new sub-indikator
   - PUT: Update sub-indikator
   - DELETE: Delete sub-indikator (cascade ke instrumen)
   - `POST /api/subindikators/bulk-bobot`: Bulk update bobot
     - Update multiple sub-indikator bobot
     - Auto-update parent indikator bobot

3. **Instrumen Management** (`/api/instrumens`)
   - GET: List instrumens by subindikator_id
   - POST: Create new instrumen dengan skor
   - PUT: Update instrumen
   - DELETE: Delete instrumen

**Output:**
- Hierarki kriteria penilaian tersimpan
- Bobot otomatis ter-kalkulasi

**Data Stores:**
- D2.1: `indikators`
- D2.2: `subindikators`
- D2.3: `instrumens`

**Business Rules:**
- Bobot indikator = SUM(bobot sub-indikator)
- Cascade delete: Indikator → Sub-Indikator → Instrumen
- Sub-indikator bisa di-nonaktifkan (isactive = false)

---

### 5.3 P3: MANAJEMEN PENILAIAN

**Input:**
- Request penilaian dari Admin
- File Excel/CSV untuk bulk upload
- Data pegawai dan kriteria

**Process:**

1. **Create/Update Penilaian** (`POST/PUT /api/penilaians`)
   - Input: pegawai_id, penilaian (JSON)
   - Validate pegawai exists
   - Store penilaian data
   - Return: penilaian record

2. **Bulk Upload Penilaian** (`POST /api/penilaians/bulk`)
   - Upload Excel/CSV file
   - Parse file dengan PhpSpreadsheet
   - Validate:
     - NIP exists in pegawai table
     - Required columns present
   - Loop through rows:
     - Match NIP to pegawai_id
     - Extract penilaian values
     - Generate nilai for special cases:
       - **Masa Kerja**: Auto-calculate dari tmtCpns
       - **Other**: Parse dari column values
     - Insert/Update penilaian record
   - Return: summary (success/failed records)

3. **Sync/Recalculate Penilaian** (`POST /api/penilaians/sync`)
   - Fetch all pegawai
   - For each pegawai:
     - Load existing penilaian or create new
     - Recalculate nilai based on current instrumen rules
     - Update penilaian record
   - Used when instrumen rules change

4. **Get Penilaian** (`GET /api/penilaians`)
   - Filter by pegawai_id
   - Pagination support
   - Return: penilaian list with pegawai data

**Output:**
- Penilaian tersimpan per pegawai
- Nilai otomatis ter-generate

**Data Stores:**
- D3.1: `penilaians`
- D1.1: `pegawai` (read)
- D2.3: `instrumens` (read for calculation)

**Calculation Logic:**
```
Masa Kerja Calculation:
- Extract tmtCpns from pegawai JSON
- Calculate years = now - tmtCpns
- Match years to instrumen rules:
  - "X tahun keatas" → years >= X
  - ">X s.d Y tahun" → X < years <= Y
  - "X s.d Y tahun" → X <= years <= Y
- Return matched skor
```

---

### 5.4 P4: STANDAR KOMPETENSI & SUKSESI

**Input:**
- Request CRUD dari Admin

**Process:**

1. **Standar Kompetensi MSK** (`/api/standar-kompetensi-msk`)
   - GET: List standar by jenis_jabatan_id
   - POST: Create standar kompetensi
     - Input: jenis_jabatan_id, subindikator_id, standar
     - Define minimum standard for job type
   - PUT: Update standar
   - DELETE: Delete standar
   - `POST /api/standar-kompetensi-msk/bulk`: Bulk update
     - Update multiple standards at once

2. **Syarat Suksesi** (`/api/syarat-suksesi`)
   - GET: List by jabatan_id
   - POST: Create syarat suksesi
     - Input: jabatan_id (peta_jabatan), syarat (JSON)
     - Define succession requirements for position
   - PUT: Update syarat
   - DELETE: Delete syarat

**Output:**
- Standar kompetensi per jenis jabatan
- Kriteria suksesi per jabatan

**Data Stores:**
- D4.1: `standar_kompetensi_msk`
- D4.2: `syarat_suksesi`
- D1.3: `jenis_jabatan` (read)
- D1.2: `peta_jabatan` (read)
- D2.2: `subindikators` (read)

**Relationships:**
```
standar_kompetensi_msk:
- jenis_jabatan (Struktural, Fungsional, Pelaksana)
- subindikator (specific competency area)
- standar (minimum score required)

syarat_suksesi:
- jabatan (specific position in org)
- syarat (JSON with custom requirements)
```

---

### 5.5 P5: REKOMENDASI & ANALISIS

**Input:**
- Request analisis dari User
- Data pegawai + penilaian + standar

**Process:**

1. **Rekomendasi Pegawai** (`GET /api/pegawai/rekomendasi/{peta_jabatan_id}`)
   - Input: peta_jabatan_id (target position)
   - Fetch target jabatan details
   - Fetch jenis_jabatan of target position
   - Load standar_kompetensi_msk for that jenis_jabatan
   - Load all pegawai with penilaian
   - For each pegawai:
     - Calculate gap: penilaian vs standar
     - Calculate total score/fit percentage
     - Check syarat_suksesi compliance
   - Sort by fit score (highest first)
   - Return: ranked list of recommended pegawai

2. **Peta Jabatan Tree with Recommendations**
   - `GET /api/peta-jabatan/tree`: Full hierarchical tree
   - `GET /api/peta-jabatan/tree-by-unit-kerja`: Tree filtered by unit
   - For each position node:
     - Show bezetting (current staff count)
     - Show kebutuhan_pegawai (required staff count)
     - Show nama_pejabat (current office holder)
     - Calculate: gap = kebutuhan - bezetting
     - Optionally include recommended candidates

3. **Statistik Dashboard** (`GET /api/statistik`)
   - Read from materialized view
   - Return key-value pairs:
     - Total pegawai (overall, by jenis jabatan)
     - Gender distribution
     - Eselon level distribution
     - Functional position levels
   - Used for dashboard metrics

4. **Box Chart (9-Box Grid)** (`/api/daftar-kotak`)
   - GET: Retrieve intervals and kotak configuration
   - POST: Update configuration
   - Used to categorize pegawai into performance/potential grid
   - Configuration defines:
     - `intervals`: score ranges for each axis
     - `kotak`: labels/categories for each box

5. **Pegawai List with Analysis** (`GET /api/pegawai`)
   - Support `?with_penilaian=true` to include assessment data
   - Filters: q (search), unit, jabatan, jenis_jabatan, golongan
   - Join with peta_jabatan and jenis_jabatan
   - Pagination & sorting
   - Return: enriched pegawai data for analysis

**Output:**
- Ranked recommendations for succession
- Gap analysis per pegawai
- Organizational statistics
- Categorized talent grid

**Data Stores:**
- D1 (ALL): `pegawai`, `peta_jabatan`, `jenis_jabatan`
- D2 (ALL): `indikators`, `subindikators`, `instrumens`
- D3.1: `penilaians`
- D4 (ALL): `standar_kompetensi_msk`, `syarat_suksesi`
- D5 (ALL): `statistik`, `daftar_kotak`

**Algorithms:**
```
Gap Analysis:
- For each subindikator in standar_kompetensi_msk:
  - actual_score = penilaian[subindikator_id]
  - required_score = standar_kompetensi_msk.standar
  - gap = required_score - actual_score
  - compliance = (actual_score >= required_score)

Fit Score:
- total_fit = SUM(penilaian scores where meets standard) / total_standards
- OR: weighted average based on subindikator bobot

Recommendation Ranking:
- Sort by: fit_score DESC, total_score DESC
- Apply additional filters: unit_match, golongan_eligibility, etc.
```

---

## 6. RELASI ANTAR TABEL

### 6.1 Entity Relationship

```
┌─────────────────┐
│  users          │
│  (autentikasi)  │
└─────────────────┘

┌─────────────────┐         ┌─────────────────────┐
│ jenis_jabatan   │◄────────│  peta_jabatan       │
│ - id            │  1:N    │  - id               │
│ - name          │         │  - parent_id (self) │
└────────┬────────┘         │  - nama_jabatan     │
         │                  │  - jenis_jabatan    │
         │ 1:N              │  - unit_kerja       │
         │                  └──────────┬──────────┘
         │                             │
         │                             │ 1:N
         │                             ↓
         │                  ┌──────────────────────┐
         │                  │  pegawai             │
         │                  │  - id                │
         │                  │  - nip               │
         │                  │  - name              │
         ↓                  │  - peta_jabatan_id   │
┌────────────────────┐     │  - jenis_jabatan_id  │
│standar_kompetensi  │     └──────────┬───────────┘
│   _msk             │                │
│- jenis_jabatan_id  │                │ 1:N
│- subindikator_id   │                ↓
│- standar           │     ┌──────────────────────┐
└──────┬─────────────┘     │  penilaians          │
       │                   │  - id                │
       │                   │  - pegawai_id        │
       │                   │  - penilaian (JSON)  │
       │                   └──────────────────────┘
       │
       │         ┌─────────────────────┐
       │         │  syarat_suksesi     │
       │         │  - id               │
       │         │  - jabatan_id       │
       │         │  - syarat (JSON)    │
       │         └─────────────────────┘
       │
       ↓
┌─────────────────┐
│  indikators     │
│  - id           │
│  - indikator    │
│  - bobot        │
└────────┬────────┘
         │ 1:N
         ↓
┌─────────────────┐
│  subindikators  │
│  - id           │
│  - subindikator │
│  - bobot        │
│  - indikator_id │
└────────┬────────┘
         │ 1:N
         ↓
┌─────────────────┐
│  instrumens     │
│  - id           │
│  - instrumen    │
│  - bobot/skor   │
│  - subindikator │
│    _id          │
└─────────────────┘

┌─────────────────────┐
│  statistik          │
│  (Materialized View)│
│  - key              │
│  - value            │
└─────────────────────┘

┌─────────────────┐
│  daftar_kotak   │
│  - intervals    │
│  - kotak        │
└─────────────────┘
```

### 6.2 Cascade Rules

1. **indikators → subindikators → instrumens**
   - DELETE CASCADE
   - Menghapus indikator akan menghapus semua sub-indikator dan instrumen

2. **pegawai → penilaians**
   - DELETE CASCADE
   - Menghapus pegawai akan menghapus semua penilaian

3. **peta_jabatan → syarat_suksesi**
   - DELETE CASCADE
   - Menghapus jabatan akan menghapus syarat suksesi

4. **jenis_jabatan → standar_kompetensi_msk**
   - DELETE CASCADE
   - Menghapus jenis jabatan akan menghapus standar kompetensi

5. **subindikators → standar_kompetensi_msk**
   - DELETE CASCADE
   - Menghapus sub-indikator akan menghapus standar kompetensi

---

## 7. DATA FLOW PATTERNS

### 7.1 Master Data Sync Flow
```
External API
     │
     ↓ HTTP Request
┌────────────────┐
│ Sync Command   │
│ (Artisan)      │
└────────┬───────┘
         │
         ↓ Parse & Normalize
┌────────────────┐
│ Insert/Update  │
│ Database       │
└────────┬───────┘
         │
         ↓ Refresh
┌────────────────┐
│ Statistik View │
└────────────────┘
```

### 7.2 Assessment Creation Flow
```
Admin Upload Excel
     │
     ↓ POST /api/penilaians/bulk
┌────────────────┐
│ Parse Excel    │
│ (PhpSpreadsheet│
└────────┬───────┘
         │
         ↓ For each row
┌────────────────┐
│ Match NIP      │
│ to Pegawai     │
└────────┬───────┘
         │
         ↓ Extract values
┌────────────────┐
│ Calculate Nilai│
│ (Masa Kerja,   │
│  etc.)         │
└────────┬───────┘
         │
         ↓ Store
┌────────────────┐
│ Penilaians     │
│ Table          │
└────────────────┘
```

### 7.3 Recommendation Flow
```
User Request
     │
     ↓ GET /api/pegawai/rekomendasi/{jabatan_id}
┌────────────────┐
│ Load Jabatan   │
│ Details        │
└────────┬───────┘
         │
         ↓ Get jenis_jabatan
┌────────────────┐
│ Load Standar   │
│ Kompetensi MSK │
└────────┬───────┘
         │
         ↓ Fetch all pegawai
┌────────────────┐
│ Load Penilaian │
│ for Each       │
└────────┬───────┘
         │
         ↓ Calculate
┌────────────────┐
│ Gap Analysis   │
│ Fit Score      │
└────────┬───────┘
         │
         ↓ Rank
┌────────────────┐
│ Sort by Score  │
│ Return Top N   │
└────────────────┘
```

### 7.4 Bobot Calculation Flow (Auto-update)
```
User Update Sub-Indikator Bobot
     │
     ↓ POST /api/subindikators/bulk-bobot
┌────────────────────┐
│ Update Each        │
│ Sub-Indikator      │
└────────┬───────────┘
         │
         ↓ For each parent indikator
┌────────────────────┐
│ SUM(subindikator   │
│     .bobot)        │
└────────┬───────────┘
         │
         ↓ Update
┌────────────────────┐
│ Indikator.bobot    │
└────────────────────┘
```

---

## 8. API ENDPOINTS SUMMARY

### 8.1 Master Data
| Endpoint | Method | Purpose | Data Flow |
|----------|--------|---------|-----------|
| `/api/pegawai` | GET | List pegawai with filters | D1 → User |
| `/api/pegawai/{nip}` | GET | Get pegawai detail | D1 → User |
| `/api/pegawai/sync` | POST | Sync from external API | External → P1 → D1, D5 |
| `/api/pegawai/rekomendasi/{id}` | GET | Get recommendations | D1,D3,D4 → P5 → User |
| `/api/peta-jabatan` | GET | List positions | D1 → User |
| `/api/peta-jabatan/tree` | GET | Hierarchical tree | D1 → P5 → User |
| `/api/peta-jabatan/tree-by-unit-kerja` | GET | Tree by unit | D1 → P5 → User |
| `/api/peta-jabatan/sync` | POST | Sync from external | External → P1 → D1 |

### 8.2 Criteria Management
| Endpoint | Method | Purpose | Data Flow |
|----------|--------|---------|-----------|
| `/api/indikators` | GET/POST/PUT/DELETE | CRUD Indikators | User ↔ P2 ↔ D2 |
| `/api/subindikators` | GET/POST/PUT/DELETE | CRUD Sub-Indikators | User ↔ P2 ↔ D2 |
| `/api/subindikators/bulk-bobot` | POST | Bulk update bobot | User → P2 → D2 |
| `/api/instrumens` | GET/POST/PUT/DELETE | CRUD Instrumens | User ↔ P2 ↔ D2 |

### 8.3 Assessment
| Endpoint | Method | Purpose | Data Flow |
|----------|--------|---------|-----------|
| `/api/penilaians` | GET/POST/PUT/DELETE | CRUD Penilaian | User ↔ P3 ↔ D3 |
| `/api/penilaians/bulk` | POST | Bulk upload Excel | User → P3 → D3 |
| `/api/penilaians/sync` | POST | Recalculate all | P3 → D3 (read D2) |

### 8.4 Standards
| Endpoint | Method | Purpose | Data Flow |
|----------|--------|---------|-----------|
| `/api/standar-kompetensi-msk` | GET/POST/PUT/DELETE | CRUD Standards | User ↔ P4 ↔ D4 |
| `/api/standar-kompetensi-msk/bulk` | POST | Bulk update | User → P4 → D4 |
| `/api/syarat-suksesi` | GET/POST/PUT/DELETE | CRUD Requirements | User ↔ P4 ↔ D4 |

### 8.5 Analytics
| Endpoint | Method | Purpose | Data Flow |
|----------|--------|---------|-----------|
| `/api/statistik` | GET | Get statistics | D5 → P5 → User |
| `/api/statistik/sync` | POST | Refresh stats view | P1 → D5 |
| `/api/daftar-kotak` | GET/POST | Box chart config | User ↔ P5 ↔ D5 |

---

## 9. SECURITY & MIDDLEWARE

### 9.1 Middleware Stack (semua API routes)
```
Request
   ↓
┌──────────────────┐
│ log.api.requests │ → Logging setiap request
└────────┬─────────┘
         ↓
┌──────────────────┐
│ verify.api.token │ → Validasi API token
└────────┬─────────┘
         ↓
┌──────────────────┐
│ whitelist.ip     │ → Filter IP address
└────────┬─────────┘
         ↓
    Controller
```

### 9.2 Data Protection
- **Cascade Delete**: Prevent orphaned records
- **Foreign Key Constraints**: Data integrity
- **UUID Primary Keys**: Security & distribution
- **JSON Validation**: Ensure data structure
- **Index Strategy**: Performance optimization

---

## 10. PERFORMANCE CONSIDERATIONS

### 10.1 Optimizations
1. **Materialized View (statistik)**
   - Pre-aggregated data
   - Refresh on demand (sync endpoint)
   - Fast dashboard queries

2. **Indexes**
   - `pegawai.nip` (indexed)
   - `pegawai.pegawai_id` (unique)
   - `penilaians.pegawai_id` (indexed)
   - `standar_kompetensi_msk.jenis_jabatan_id` (indexed)
   - `standar_kompetensi_msk.subindikator_id` (indexed)

3. **Pagination**
   - All list endpoints support `per_page`
   - Max 100 items per page
   - Default 15 items

4. **Lazy Loading**
   - Penilaian only loaded when `with_penilaian=true`
   - Reduces payload size

5. **Timeout Handling**
   - Sync operations: 10 minutes timeout
   - Prevents server overload

---

## 11. CRITICAL BUSINESS LOGIC

### 11.1 Masa Kerja Auto-Calculation
```php
// Extract tmtCpns from pegawai JSON
$years = Carbon::parse($pegawai->json['tmtCpns'])->diffInYears(now());

// Match to instrumen rules
foreach ($instrumens as $ins) {
    // "10 tahun keatas" → years >= 10
    // ">5 s.d 10 tahun" → 5 < years <= 10
    // "0 s.d 5 tahun" → 0 <= years <= 5
    if (matchRule($ins->instrumen, $years)) {
        return $ins->skor;
    }
}
```

### 11.2 Gap Analysis
```php
// For each subindikator in standard
$gap = [];
foreach ($standar as $s) {
    $actual = $penilaian[$s->subindikator_id] ?? 0;
    $required = $s->standar;
    $gap[] = [
        'subindikator' => $s->subindikator->subindikator,
        'actual' => $actual,
        'required' => $required,
        'gap' => $required - $actual,
        'status' => $actual >= $required ? 'MEET' : 'GAP'
    ];
}
```

### 11.3 Fit Score Calculation
```php
$total_standards = count($standar);
$meet_count = array_filter($gap, fn($g) => $g['status'] === 'MEET');
$fit_percentage = ($meet_count / $total_standards) * 100;
```

---

## 12. JSON DATA STRUCTURES

### 12.1 pegawai.json (JSONB)
```json
{
  "pegawaiId": "string",
  "nip": "string",
  "name": "string",
  "jenisKelamin": "M|F",
  "tmtCpns": "date",
  "tmt_cpns": "date",
  "eselonLevel": "1|2|3|4",
  "unitOrganisasiName": "string",
  "jabatanName": "string",
  "golongan": "string",
  // ... other fields
}
```

### 12.2 penilaians.penilaian (JSON)
```json
{
  "subindikator_id_1": 85.5,
  "subindikator_id_2": 92.0,
  // ... nilai per subindikator
}
```

### 12.3 syarat_suksesi.syarat (JSON)
```json
{
  "min_golongan": "III/c",
  "min_pendidikan": "S1",
  "required_certifications": ["A", "B"],
  "min_experience_years": 5,
  // ... custom requirements
}
```

### 12.4 daftar_kotak.intervals (JSON)
```json
{
  "performance": [0, 33, 66, 100],
  "potential": [0, 33, 66, 100]
}
```

### 12.5 daftar_kotak.kotak (JSON)
```json
[
  {"x": 1, "y": 1, "label": "Low Performer", "color": "red"},
  {"x": 2, "y": 2, "label": "Core Performer", "color": "yellow"},
  {"x": 3, "y": 3, "label": "High Performer", "color": "green"},
  // ... 9 boxes total
]
```

---

## 13. CONCLUSION

Sistem SIMANTAP mengimplementasikan data flow yang terstruktur dengan:

1. **Separation of Concerns**: 5 process groups yang jelas
2. **Data Integrity**: Foreign keys, cascade, validations
3. **Automation**: Auto-sync, auto-calculate bobot, auto-generate nilai
4. **Analytics**: Gap analysis, recommendations, statistics
5. **Scalability**: Pagination, indexing, materialized views
6. **Security**: Middleware stack, token auth, IP whitelist

**Key Flows:**
- **External API → Sync → Database**: Master data management
- **Admin → Criteria → Database**: Assessment framework setup
- **Admin → Upload → Calculate → Database**: Bulk assessment
- **User → Request → Analyze → Recommendation**: Decision support

**Critical Features:**
- Hierarchical criteria (Indikator → SubIndikator → Instrumen)
- Auto-calculated weights and scores
- Dynamic gap analysis
- Succession planning recommendations
- Real-time statistics dashboard

---

## LEGEND

- **P**: Process
- **D**: Data Store
- **→**: Data Flow Direction
- **↔**: Bidirectional Data Flow
- **FK**: Foreign Key
- **1:N**: One-to-Many Relationship
- **CASCADE**: Auto-delete related records

---

**Document Version:** 1.0  
**Last Updated:** February 2, 2026  
**System:** SIMANTAP Backend API  
**Framework:** Laravel 11  
**Database:** PostgreSQL
