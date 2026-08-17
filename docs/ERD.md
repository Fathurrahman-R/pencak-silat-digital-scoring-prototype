# Diagram Relasi Entitas (ERD)

> Dikelompokkan per domain supaya terbaca. Kolom yang ditulis hanya yang penting untuk memahami relasinya — lihat migrasi di `database/migrations/` untuk daftar kolom lengkap.

## Master turnamen & peserta

```mermaid
erDiagram
    tournaments ||--o{ arenas : punya
    tournaments ||--|| tournament_rule_settings : punya
    tournaments ||--o{ weight_classes : punya
    tournaments ||--o{ jurus_events : punya
    tournaments ||--o{ contingents : punya

    contingents ||--o{ athletes : punya
    contingents ||--o{ registrations : mendaftar

    registrations }o--o{ athletes : "registration_athlete (pivot, +position)"
    registrations }o--|| weight_classes : "ke kelas tanding (nullable)"
    registrations }o--|| jurus_events : "ke nomor jurus (nullable)"
    registrations ||--o{ weight_ins : ditimbang

    tournaments {
        bigint id PK
        string name
        date starts_on
        string status
    }
    tournament_rule_settings {
        bigint tournament_id FK
        int jumlah_juri_tanding
        int ambang_sepakat
        int window_konsensus_ms
        int jumlah_juri_jurus
    }
    weight_classes {
        bigint id PK
        bigint tournament_id FK
        string golongan_usia
        string jenis_kelamin
        string code
        decimal berat_min
        decimal berat_max
    }
    jurus_events {
        bigint id PK
        bigint tournament_id FK
        string jenis
        string golongan_usia
        string jenis_kelamin
        int waktu_acuan_ms "nullable"
    }
    contingents {
        bigint id PK
        bigint tournament_id FK
        string name
    }
    athletes {
        bigint id PK
        bigint contingent_id FK
        string name
        date date_of_birth
        decimal weight_claim
    }
    registrations {
        bigint id PK
        bigint contingent_id FK
        bigint weight_class_id FK "nullable"
        bigint jurus_event_id FK "nullable"
        string status
    }
    weight_ins {
        bigint id PK
        bigint registration_id FK
        decimal weight
        timestamp weighed_at
    }
```

## Keuangan

```mermaid
erDiagram
    tournaments ||--o{ fee_schedules : punya
    contingents ||--|| invoices : punya
    invoices ||--o{ invoice_items : rincian
    invoices ||--o{ manual_payments : "pembayaran manual"

    fee_schedules {
        bigint id PK
        bigint tournament_id FK
        string kategori
        string golongan_usia
        decimal amount
    }
    invoices {
        bigint id PK
        bigint contingent_id FK
        string status "draft|awaiting_payment|paid"
        decimal total_amount
        timestamp locked_at
        timestamp paid_at
    }
    invoice_items {
        bigint id PK
        bigint invoice_id FK
        bigint registration_id FK "nullable"
        string description
        decimal amount
    }
    manual_payments {
        bigint id PK
        bigint invoice_id FK
        decimal amount
        string proof_path
        bigint recorded_by FK
    }
```

payment_attempts dan payment_events (integrasi Midtrans) belum ada -- menyusul saat kredensial sandbox tersedia (lihat `docs/RENCANA.md` Fase 2b).

## Bagan & jadwal

```mermaid
erDiagram
    weight_classes ||--|| brackets : punya
    brackets ||--o{ matches : berisi
    matches ||--o{ match_officials : ditugaskan
    arenas ||--o{ matches : menampung

    brackets {
        bigint id PK
        bigint weight_class_id FK
        int size
        timestamp locked_at
    }
    matches {
        bigint id PK
        bigint bracket_id FK
        int round
        int position
        bigint red_registration_id FK "nullable"
        bigint blue_registration_id FK "nullable"
        bigint winner_registration_id FK "nullable"
        string win_reason
        string status
        bigint arena_id FK "nullable"
        int order_in_arena
        timestamp ratified_at
    }
    match_officials {
        bigint id PK
        bigint match_id FK
        bigint user_id FK
        string role "wasit|juri"
        int number "nullable, nomor urut juri"
    }
```

## Mesin scoring Tanding

```mermaid
erDiagram
    matches ||--o{ match_rounds : punya
    matches ||--o{ judge_inputs : "input mentah"
    matches ||--o{ score_events : "nilai sah"
    matches ||--o{ penalties : hukuman
    judge_inputs }o--o| score_events : "membentuk (score_event_id)"

    match_rounds {
        bigint id PK
        bigint match_id FK
        int round
        string status
        int duration_ms
        int accumulated_ms
        timestamp started_at
    }
    judge_inputs {
        bigint id PK
        bigint match_id FK
        int round
        bigint judge_user_id FK
        string corner
        string point_type
        timestamp server_ts "presisi milidetik"
        bigint score_event_id FK "nullable, terisi bila membentuk nilai"
        string rejected_reason "nullable"
    }
    score_events {
        bigint id PK
        bigint match_id FK
        int round
        string corner
        string point_type
        int value
        timestamp voided_at "nullable"
        string void_reason
    }
    penalties {
        bigint id PK
        bigint match_id FK
        int round
        string corner
        string tier "pembinaan|teguran|peringatan"
        int level
        int points "nullable untuk DQ"
        string violation_level
        timestamp voided_at "nullable"
    }
```

`judge_inputs` tidak pernah di-UPDATE atau DELETE (NFR-05). `score_events`/`penalties` dikoreksi lewat `voided_at`/`voided_by`/`void_reason`, tidak pernah disunting.

## Mesin scoring Jurus

```mermaid
erDiagram
    jurus_events ||--o{ jurus_performances : punya
    registrations ||--o{ jurus_performances : tampil
    jurus_performances ||--o{ jurus_scores : dinilai
    jurus_performances ||--o{ jurus_deductions : dikurangi

    jurus_performances {
        bigint id PK
        bigint jurus_event_id FK
        bigint registration_id FK
        string tahap "penyisihan|semifinal|final"
        string status
        timestamp started_at
        int duration_ms
        boolean didiskualifikasi
        timestamp ratified_at
    }
    jurus_scores {
        bigint id PK
        bigint performance_id FK
        bigint judge_user_id FK
        decimal value "9.00-10.00, UNIK per (performance, juri)"
    }
    jurus_deductions {
        bigint id PK
        bigint performance_id FK
        string tier "juri (0.01) | pengawas (0.50)"
        decimal jumlah
        timestamp voided_at "nullable"
    }
```

Beda dari `judge_inputs`: `jurus_scores` memakai UPSERT per juri (bukan log immutable) -- lihat penjelasan di README.

## VAR & Protes Manajer

```mermaid
erDiagram
    matches ||--o{ protest_cards : "jatah per sudut"
    protest_cards ||--o{ var_reviews : dipakai
    matches ||--o{ manager_protests : diajukan
    manager_protests ||--o| manager_protests : "banding (parent_id)"
    var_reviews }o--o| score_events : "sengketa nilai (nullable)"
    var_reviews }o--o| penalties : "sengketa hukuman (nullable)"

    protest_cards {
        bigint id PK
        bigint match_id FK
        string corner
        tinyint jumlah_dipakai
    }
    var_reviews {
        bigint id PK
        bigint match_id FK
        bigint protest_card_id FK
        string kejadian
        bigint score_event_id FK "nullable"
        bigint penalty_id FK "nullable"
        timestamp tenggat_at
        string keputusan "sah|tidak_sah"
    }
    manager_protests {
        bigint id PK
        bigint match_id FK
        string level "pertama|banding"
        bigint parent_id FK "nullable, self-reference"
        timestamp tenggat_keputusan_at
        string keputusan "diterima|ditolak"
    }
```

## Sistem (bawaan boilerplate)

`users`, `roles`, `permissions`, `role_has_permissions`, `resources`, `resource_permissions` -- lapisan resource key, lihat [`docs/BOILERPLATE-RESOURCE-KEYS.md`](BOILERPLATE-RESOURCE-KEYS.md). `audit_logs` mencatat tindakan yang mengubah skor, hukuman, atau hasil beserta pelakunya (FR-A-05).
