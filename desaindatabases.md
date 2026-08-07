kamu serorang senior developer yang akan membuat web aplikasi berbasis ERP Sebagai seorang senior developer yang akan membuat web aplikasi berbasis ERP.
hari ini kita kan focus pada pembuatan web aplikasi ERP yang dapat membantu perusahaan dalam mengelola berbagai aspek bisnis mereka, seperti manajemen inventaris, keuangan, sumber daya manusia, dan lain-lain.
kita kan membuat skema database yang efisien dan terstruktur untuk mendukung berbagai modul dalam aplikasi ERP ini. Selain itu, kita juga akan merancang antarmuka pengguna yang intuitif dan responsif agar pengguna dapat dengan mudah mengakses dan mengelola data mereka.
Langkah pertama dalam pembuatan web aplikasi ERP adalah merancang skema database yang mencakupkan tabel-tabel utama yang akan digunakan dalam aplikasi. Beberapa tabel yang perlu dipertimbangkan antara lain:




#   Database Schema untuk Aplikasi ERP

1. **Tabel Pengguna (users)**
   - id (Primary Key)
   - username (string 50, unique)
   - email (string, unique)
   - password (string)
   - phone_number (string 20, nullable)
   - division_id (Foreign Key ke tabel divisions, nullable)
   - task_role_id (Foreign Key ke tabel task_roles, nullable)
   - role (enum: Admin, Manager, Staff) // default: Staff
   - icon (string, nullable)
   - remember_token (string, nullable)
   - created_at
   - updated_at

2. **Tabel Modul (modules)**
   - id (Primary Key)
   - module_code (string 50, unique)             // Cth: "MOD_DIVISI"
   - module_name (string 100)                    // Cth: "Menu Divisi"
   - description (text, nullable)                // Cth: "Menu untuk mengelola data divisi"
   - route_name (string 100, nullable)           // Cth: "divisi.index"
   - icon (string 50, nullable)                  // Cth: "fa fa-gears"
   - group (string 50, nullable)                 // Cth: "Master Data"
   - created_at
   - updated_at

3. **Tabel User Access Control (user_access_controls)**
   - id (Primary Key)
   - user_id (Foreign Key ke tabel Users)
   - module_id (Foreign Key ke tabel Modules)
   - can_create (boolean, default false)
   - can_read (boolean, default false)
   - can_update (boolean, default false)
   - can_delete (boolean, default false)
   - can_approve (boolean, default false)
   - created_at
   - updated_at
   - **Unique:** (user_id, module_id)

4. **Tabel Divisi (divisions)**
   - id (Primary Key)
   - division_name (string 100)
   - description (text, nullable)
   - type (enum: Internal, External)  // default: Internal
   - status (enum: Active, Inactive)  // default: Active
   - created_at
   - updated_at

5. **Table Job Titles (job_titles)**
   - id (Primary Key)
   - title_name (string 100)
   - description (text, nullable)
   - status (enum: Active, Inactive)  // default: Active
   - created_at
   - updated_at

6. **Table Sources (sources)**
   - id (Primary Key)
   - source_name (string 100)
   - description (text, nullable)
   - status (enum: Active, Inactive)  // default: Active
   - created_at
   - updated_at

7. **Table Contact Methods (contact_methods)**
   - id (Primary Key)
   - method_name (string 100)
   - description (text, nullable)
   - status (enum: Active, Inactive)  // default: Active
   - created_at
   - updated_at

8. **Table Segmentation (segmentations)**
   - id (Primary Key)
   - segmentation_name (string 100)
   - description (text, nullable)
   - status (enum: Active, Inactive)  // default: Active
   - created_at
   - updated_at

9. **Table Business Entities (business_entities)**
   - id (Primary Key)
   - entity_name (string 100)
   - description (text, nullable)
   - status (enum: Active, Inactive)  // default: Active
   - created_at
   - updated_at

10. **Table Business Values (business_values)**
   - id (Primary Key)
   - value_name (string 100)
   - description (text, nullable)
   - status (enum: Active, Inactive)  // default: Active
   - created_at
   - updated_at

11. **Table Role In Projects (role_in_projects)**
   - id (Primary Key)
   - role_name (string 100)
   - description (text, nullable)
   - status (enum: Active, Inactive)  // default: Active
   - created_at
   - updated_at

12. **Table Interaction Levels (interaction_levels)**
   - id (Primary Key)
   - level_name (string 100)
   - description (text, nullable)
   - status (enum: Active, Inactive)  // default: Active
   - created_at
   - updated_at

13. **Table Types Accounts Company (types_accounts_companies)**
   - id (Primary Key)
   - type_name (string 100)          // Cth: "PT", "CV", "Koperasi"
   - description (text, nullable)
   - status (enum: Active, Inactive)  // default: Active
   - created_at
   - updated_at

14. **Table Account Types (account_types)**
   - id (Primary Key)
   - type_name (string 100)
   - description (text, nullable)
   - status (enum: Active, Inactive)  // default: Active
   - created_at
   - updated_at

15. **Table Account Company (account_companies)**
   - id (Primary Key)
   - account_name (string 150)
   - icon (string, nullable)
   - sources_id (Foreign Key ke tabel Sources, nullable)
   - types_accounts_companies_id (Foreign Key ke tabel Types Accounts Company, nullable)
   - website (string 200, nullable)
   - description (text, nullable)
   - segmentation_id (Foreign Key ke tabel Segmentations, nullable)
   - business_entities_id (Foreign Key ke tabel Business Entities, nullable)
   - business_values_id (Foreign Key ke tabel Business Values, nullable)
   - account_types_id (Foreign Key ke tabel Account Types, nullable)
   - end_user (integer, nullable)
   - parent_account_id (Foreign Key self-referencing ke account_companies, nullable)
   - phone (string 30, nullable)
   - interaction_levels_id (Foreign Key ke tabel Interaction Levels, nullable)
   - account_owner_id (Foreign Key ke tabel Users, nullable)
   - address_billing_street (string, nullable)
   - address_billing_city (string 100, nullable)
   - address_billing_province (string 100, nullable)
   - address_billing_postal_code (string 10, nullable)
   - address_billing_country (string 100, nullable)
   - address_shipping_street (string, nullable)
   - address_shipping_city (string 100, nullable)
   - address_shipping_province (string 100, nullable)
   - address_shipping_postal_code (string 10, nullable)
   - address_shipping_country (string 100, nullable)
   - status (enum: Active, Inactive)  // default: Active
   - created_at
   - updated_at
   - **Relasi:** Self-referencing (parent_account_id) untuk hierarki perusahaan
   - **Relasi:** Many-to-One ke tabel `users` (account_owner)

16. **Table Account Contacts (account_contacts)**
   - id (Primary Key)
   - account_companies_id (Foreign Key ke tabel Account Companies)
   - full_name (string 150)
   - icon (string, nullable)
   - salutation (enum: Ibu, Bapak, nullable)
   - email (string 100, nullable)
   - phone (string 30, nullable)
   - mobile (string 30, nullable)
   - job_titles_id (Foreign Key ke tabel Job Titles, nullable)
   - sources_id (Foreign Key ke tabel Sources, nullable)
   - role_in_projects_id (Foreign Key ke tabel Role In Projects, nullable)
   - contact_methods_id (Foreign Key ke tabel Contact Methods, nullable)
   - divisions_id (Foreign Key ke tabel Divisions, nullable)
   - contact_owner_id (Foreign Key ke tabel Users, nullable)
   - address_street (string, nullable)
   - address_city (string 100, nullable)
   - address_province (string 100, nullable)
   - address_postal_code (string 10, nullable)
   - address_country (string 100, nullable)
   - lead_status (enum: New, Contacted, Qualified, Unqualified, nullable)
   - status (enum: Active, Inactive)  // default: Active
   - created_at
   - updated_at
   - **Relasi:** Many-to-One ke tabel `account_companies`
   - **Relasi:** Many-to-One ke tabel `users` (contact_owner)

17. **Table Leads (leads)**
   - id (Primary Key)
   - lead_status (enum: New, Approach, Qualified, Unqualified)  // default: New
   - lead_title (string 500)
   - account_contacts_id (Foreign Key ke tabel Account Contacts)
   - account_companies_id (Foreign Key ke tabel Account Companies)
   - source_id (Foreign Key ke tabel Sources)
   - unqualified_reason (string, nullable)
   - closed_date (date, nullable)
   - all_filed_completed (boolean, default false)
   - lead_owner_id (Foreign Key ke tabel Users)
   - assigned_to (Foreign Key ke tabel Users, nullable)
   - lead_can_be_contacted (boolean, default false)
   - lead_follow_up_date (date, nullable)
   - lead_appoinment (boolean, default false)
   - identification (boolean, default false)
   - created_at
   - updated_at
   - **Relasi:** Many-to-One ke tabel `account_contacts`
   - **Relasi:** Many-to-One ke tabel `account_companies`
   - **Relasi:** Many-to-One ke tabel `sources`
   - **Relasi:** Many-to-One ke tabel `users` (lead_owner)
    - **Relasi:** Many-to-One ke tabel `users` (assigned_to)
    - **Relasi:** Polymorphic MorphMany ke tabel `logs` (via `App\Traits\Loggable`)

18. **Table WhatsApp Groups (whatsapp_groups)**
   - id (Primary Key)
   - division_id (Foreign Key ke tabel Divisions, unique)
   - group_name (string 100)
   - group_id (string 100, nullable)              // ID grup WhatsApp
   - description (text, nullable)
   - status (enum: Active, Inactive)  // default: Active
   - created_at
   - updated_at
   - **Relasi:** One-to-One ke tabel `divisions`

19. **Table Notifications (notifications)**
   - id (Primary Key)
   - user_id (Foreign Key ke tabel Users)
   - type (string 50)                             // Cth: "task_assigned"
   - title (string 200)
   - body (text, nullable)
   - notifiable_type (string 100)                 // Cth: "App\Models\Task"
   - notifiable_id (unsigned big integer)
   - data (json, nullable)
   - read_at (timestamp, nullable)
   - created_at
   - updated_at
   - **Index:** (user_id, read_at), (notifiable_type, notifiable_id)
   - **Relasi:** Many-to-One ke tabel `users`
   - **Relasi:** Polymorphic MorphTo ke Task / TaskActivity / Activity (Lead)

   **Grouping Notifications:**
   Notifikasi dikelompokkan (grouped) berdasarkan context asalnya — task atau lead.
   Group key diturunkan dari field `data` (JSON) melalui **Model Accessor** tanpa perlu kolom database tambahan:

   | Accessor | Turunan dari | Contoh |
   |----------|-------------|--------|
   | `group_key` | `data['task_id']` → `"task_123"`<br>`data['lead_id']` → `"lead_456"`<br>fallback → `"notifiable_type_notifiable_id"` | `"task_123"` |
   | `group_type` | `data['task_id']` → `"task"`<br>`data['lead_id']` → `"lead"`<br>lainnya → `"other"` | `"task"` |
   | `group_id` | `data['task_id']` atau `data['lead_id']` | `123` |

   **Struktur `data` JSON per notifiable_type:**

   | notifiable_type | data fields | Group ke |
   |---|---|---|
   | `Task` | `{ task_id, creator?, activity_id? }` | `task_{id}` |
    | `TaskActivity` | `{ task_id, activity_id, mentioned_by? }` | Parent `task_{id}` (resolve via task_id) |
    | `Activity` (Lead) | `{ lead_id, activity_id, mentioned_by? }` | `lead_{id}` |
    | `Activity` (Opportunity) | `{ opportunity_id, activity_id, mentioned_by? }` | `opportunity_{id}` |

   **Notification Types:**
   - `task_assigned` — User ditugaskan ke task baru
   - `task_activity` — Ada aktivitas/komentar baru pada task
   - `task_approval_required` — Task butuh approval creator
   - `task_approved` — Task telah disetujui
   - `task_status_changed` — Status task berubah
   - `mention` — User di-mention (@username) pada aktivitas lead atau task
   - `visit_recorded` — Visit location direkam pada task oleh user

   **Trait Notifiable (`app/Traits/Notifiable.php`):**
   Digunakan oleh model `Task` dan `TaskActivity` via polymorphic `MorphMany`.
   Method `notify($user, $type, $title, $body, $data)` membuat notifikasi untuk satu user.
   Notifikasi juga bisa dibuat langsung via `Notification::create([...])` (untuk mention, dll).

   **Display Behavior:**
   - **Dropdown (bell icon):** Notifikasi ditampilkan grouped, tiap grup punya header (nama task/lead, icon, badge unread count), child notifications expand default. Grup bisa di-toggle collapse/expand.
   - **Halaman `/notifications/all`:** Sama grouped, dengan pagination 20 notif per halaman.


20. **Table Task Roles (task_roles)**
   - id (Primary Key)
   - role_name (string 50)            // Nama role, cth: "Supervisor", "Staff"
   - hierarchy_level (unsigned integer) // Semakin tinggi angka, semakin tinggi hierarki
   - is_global_delegator (boolean, default false) // true = bisa lihat semua task
   - created_at
   - updated_at
   - **Relasi:** One-to-Many ke tabel `users` (via `task_role_id`)

21. **Table Task Categories (task_categories)**
   - id (Primary Key)
   - name (string 50)                 // Nama kategori, cth: "Operasional", "Proyek"
   - description (text, nullable)
   - division_id (Foreign Key ke tabel divisions, nullable)
   - created_at
   - updated_at
   - **Relasi:** One-to-Many ke tabel `tasks` (via `category_id`)
   - **Relasi:** Many-to-One ke tabel `divisions`

22. **Table Tasks (tasks)**
   - id (Primary Key)
   - lead_id (Foreign Key ke tabel leads, nullable) // Jika task terkait lead
   - opportunity_id (Foreign Key ke tabel opportunities, nullable) // Jika task terkait opportunity
   - creator_id (Foreign Key ke tabel users)          // Pembuat task
   - category_id (Foreign Key ke tabel task_categories)
   - whatsapp_group_id (Foreign Key ke tabel whatsapp_groups, nullable)
   - title (string 150)
   - description (text, nullable)
   - due_date (date)                                   // Tanggal deadline
   - time (time, nullable)                             // Waktu deadline
   - status (enum: todo, in_progress, waiting_approval, done) // default: todo
   - requires_approval (boolean, default false)        // Perlu approval creator
   - alert_type (enum: none, email, whatsapp, both)    // default: none
   - alert_target (enum: personal, group, both)        // default: personal
   - alert_time (datetime, nullable)                   // Waktu notifikasi
   - is_alert_sent (boolean, default false)
   - created_at
   - updated_at
   - **Index:** status, due_date, (is_alert_sent, alert_time)
   - **Relasi:** Many-to-One ke tabel `users` (creator)
   - **Relasi:** Many-to-One ke tabel `task_categories`
   - **Relasi:** Many-to-One ke tabel `opportunities`
   - **Relasi:** Many-to-One ke tabel `whatsapp_groups`
   - **Relasi:** Many-to-Many ke tabel `users` (via `task_assignees`)
   - **Relasi:** One-to-Many ke tabel `task_activities`
    - **Relasi:** One-to-Many ke tabel `task_visits`
    - **Relasi:** Polymorphic MorphMany ke tabel `logs` (via `App\Traits\Loggable`)

23. **Table Task Assignees (task_assignees)** [Pivot]
   - task_id (Foreign Key ke tabel tasks, Composite Primary Key)
   - user_id (Foreign Key ke tabel users, Composite Primary Key)
   - assigned_at (timestamp, default CURRENT_TIMESTAMP)
   - created_at
   - updated_at
   - **Relasi:** Many-to-Many penghubung antara `tasks` dan `users`

24. **Table Task Activities (task_activities)**
   - id (Primary Key)
   - task_id (Foreign Key ke tabel tasks)
   - user_id (Foreign Key ke tabel users)
   - content (text, nullable)                          // Isi komentar/aktivitas
   - reply_to_id (Foreign Key ke tabel task_activities, nullable) // Untuk reply thread
   - created_at
   - updated_at
   - **Index:** task_id, created_at
   - **Relasi:** Many-to-One ke tabel `tasks`
   - **Relasi:** Many-to-One ke tabel `users`
   - **Relasi:** Self-referencing (reply_to_id) untuk threaded replies
   - **Relasi:** One-to-Many ke tabel `task_activity_attachments`

25. **Table Task Activity Attachments (task_activity_attachments)**
   - id (Primary Key)
   - task_activity_id (Foreign Key ke tabel task_activities)
   - attachment_path (string)                          // Path file di storage
   - attachment_type (string)                          // Tipe file (image, pdf, dll)
   - attachment_name (string, nullable)                // Nama asli file
   - created_at
   - updated_at
   - **Index:** task_activity_id
   - **Relasi:** Many-to-One ke tabel `task_activities`
   

26. **Table Activity (activities)**
   - id (Primary Key)
   - lead_id (Foreign Key ke tabel leads, nullable) // Jika aktivitas terkait lead
   - opportunity_id (Foreign Key ke tabel opportunities, nullable) // Jika aktivitas terkait opportunity
   - task_id (Foreign Key ke tabel tasks, nullable) // Jika aktivitas terkait task
   - user_id (Foreign Key ke tabel users)          // Pengguna yang melakukan aktivitas
   - content (text, nullable)                          // Deskripsi aktivitas
   - reply_to_id (Foreign Key ke tabel activities, nullable) // Untuk reply thread
   - created_at
   - updated_at
    - **Index:** lead_id, opportunity_id, task_id, user_id, created_at
   - **Relasi:** Many-to-One ke tabel `leads`
   - **Relasi:** Many-to-One ke tabel `opportunities`
   - **Relasi:** Many-to-One ke tabel `tasks`
   - **Relasi:** Many-to-One ke tabel `users`
   - **Relasi:** Self-referencing (reply_to_id) untuk threaded replies
   - **Relasi:** One-to-Many ke tabel `activity_attachments`

27. **Table Activity Attachments (activity_attachments)**
   - id (Primary Key)
   - activity_id (Foreign Key ke tabel activities)
   - attachment_path (string)                          // Path file di storage
   - attachment_type (string)                          // Tipe file (image, pdf, dll)
   - attachment_name (string, nullable)                // Nama asli file
   - created_at
   - updated_at
   - **Index:** activity_id
   - **Relasi:** Many-to-One ke tabel `activities`

28. **Table Task Visits (task_visits)**
   - id (Primary Key)
   - task_id (Foreign Key ke tabel tasks)                 // Task yang dikunjungi
   - user_id (Foreign Key ke tabel users)                 // Pengguna yang melakukan kunjungan
   - latitude (decimal(10,7))                              // Latitude GPS
   - longitude (decimal(10,7))                             // Longitude GPS
   - address (string 255, nullable)                        // Alamat (reverse geocoding, opsional)
   - created_at
   - updated_at
   - **Index:** (task_id, created_at)
   - **Relasi:** Many-to-One ke tabel `tasks`
   - **Relasi:** Many-to-One ke tabel `users`

29. **Table Logs (logs)**
   - id (Primary Key)
   - module_id (Foreign Key ke tabel modules, nullable)   // Modul terkait aksi
   - user_id (Foreign Key ke tabel users, nullable)        // Pengguna yang melakukan aksi
   - action (string 100, nullable)                         // Cth: "create_task", "update_lead", "approve_task"
   - description (text, nullable)                          // Deskripsi aksi
   - loggable_type (string 100, nullable)                  // Polymorphic — cth: "App\Models\Task"
   - loggable_id (unsigned big integer, nullable)          // Polymorphic — ID entity terkait
   - created_at
   - updated_at
   - **Index:** (module_id, user_id, action, created_at)
   - **Index:** (loggable_type, loggable_id)
   - **Relasi:** Many-to-One ke tabel `modules`
   - **Relasi:** Many-to-One ke tabel `users`
   - **Relasi:** Polymorphic MorphTo `loggable` (Task, Lead, dll)

   **Log Types:**
   | Action | Module Code | Keterangan |
   |---|---|---|
   | `create_task` | `MOD_TASK_PLANNER` | Task dibuat |
   | `update_task` | `MOD_TASK_PLANNER` | Task diupdate |
   | `delete_task` | `MOD_TASK_PLANNER` | Task dihapus |
   | `transition_task` | `MOD_TASK_PLANNER` | Status task berubah |
   | `approve_task` | `MOD_TASK_PLANNER` | Task disetujui |
   | `reject_task` | `MOD_TASK_PLANNER` | Task ditolak |
   | `create_lead` | `MOD_LEADS_MANAGEMENT` | Lead dibuat |
   | `update_lead` | `MOD_LEADS_MANAGEMENT` | Lead diupdate |
   | `delete_lead` | `MOD_LEADS_MANAGEMENT` | Lead dihapus |
    | `qualified_lead` | `MOD_LEADS_MANAGEMENT` | Lead menjadi Qualified |
    | `unqualified_lead` | `MOD_LEADS_MANAGEMENT` | Lead menjadi Unqualified |
    | `create_opportunity` | `MOD_OPPORTUNITY_MANAGEMENT` | Opportunity dibuat |
    | `update_opportunity` | `MOD_OPPORTUNITY_MANAGEMENT` | Opportunity diupdate |
    | `delete_opportunity` | `MOD_OPPORTUNITY_MANAGEMENT` | Opportunity dihapus |

   **Helper `Log::record()`:**
   Static helper pada model `Log` untuk one-liner logging dari mana saja:
   ```php
   Log::record('create_task', "Task #{$task->id}: {$task->title}", 'MOD_TASK_PLANNER', $task);
   ```
   Parameter: `action`, `description`, `moduleCode` (nullable, resolve otomatis ke module_id), `loggable` (nullable polymorphic entity), `user` (nullable, default Auth::id()).

    **Trait Loggable (`app/Traits/Loggable.php`):**
    Digunakan oleh model `Task`, `Lead`, dan `Opportunity`. Menyediakan relationship `logs(): MorphMany`.
    ```php
    $task->logs()->with('user')->orderByDesc('created_at')->get();
    $lead->logs()->with('user')->orderByDesc('created_at')->get();
    $opportunity->logs()->with('user')->orderByDesc('created_at')->get();
    ```

    **Display Behavior:**
    - **Tab "Log" di halaman detail task:** Menampilkan daftar log terkait task tersebut (create, update, transition, approve, reject).
    - **Tab "Logs" di halaman detail lead:** Menampilkan daftar log terkait lead tersebut (create, update, qualified, unqualified, delete).
    - **Tab "Logs" di halaman detail opportunity:** Menampilkan daftar log terkait opportunity tersebut (create, update, delete).
    - Tidak ada action badge — tampilan minimal dengan avatar, username, timestamp, dan deskripsi.


30. **Table Forecasts (forecasts)**
   - id (Primary Key)
   - forecast_name (string 100)  // Cth: "Omitted", "Pipeline", "Best Case", "Commit", "Closed" 
   - description (text, nullable)
   - status (enum: Active, Inactive)  // default: Active
   - created_at
   - updated_at

31. **Table Loss Reasons (loss_reasons)**
   - id (Primary Key)
   - reason_name (string 100) Cth: "Lost to Competitor" "No Budget / Lost Funding" "No Decision / Non-Responsive" "Price" "Other"
   - description (text, nullable)
   - status (enum: Active, Inactive)  // default: Active
   - created_at
   - updated_at


32. **Table Stages (stages)**
   - id (Primary Key)
   - stage_name (string 100)  // Cth: New, Proposal & Quote, In Review, Negotiation, Closed Won, Closed Lost
   - description (text, nullable)
   - status (enum: Active, Inactive)  // default: Active
   - created_at
   - updated_at


33. **Table Opportunities (opportunities)**
   - id (Primary Key)
   - lead_id (Foreign Key ke tabel Leads, nullable)
   - stage_id (Foreign Key ke tabel Stages, nullable)
   - probability (unsigned integer, default 0) // Persentase peluang sukses
   - forecast_id (Foreign Key ke tabel Forecasts, nullable)
   - opportunity_name (string 150)
   - loss_reasons_id (Foreign Key ke tabel Loss Reasons, nullable)
   - quote_ready (boolean, default false)
   - division_id (Foreign Key ke tabel Divisions, nullable)
   - account_companies_id (Foreign Key ke tabel Account Companies)
   - account_contacts_id (Foreign Key ke tabel Account Contacts, nullable)
   - source_id (Foreign Key ke tabel Sources, nullable)
   - next_step (text, nullable)
   - end_user_id (Foreign Key ke tabel Account Companies, nullable)
   - budget (boolean, default false)
   - authorize (boolean, default false)
   - timeline (boolean, default false)
    - close_won_date (date, nullable)
    - description (text, nullable)
    - owner_id (Foreign Key ke tabel Users) // Pemilik opportunity
    - created_at
    - updated_at
    - **Relasi:** Many-to-One ke tabel `leads`
    - **Relasi:** Many-to-One ke tabel `stages`
    - **Relasi:** Many-to-One ke tabel `forecasts`
    - **Relasi:** Many-to-One ke tabel `loss_reasons`
    - **Relasi:** Many-to-One ke tabel `divisions`
    - **Relasi:** Many-to-One ke tabel `account_companies`
    - **Relasi:** Many-to-One ke tabel `account_contacts`
    - **Relasi:** Many-to-One ke tabel `sources`
    - **Relasi:** Many-to-One ke tabel `users` (owner)
    - **Relasi:** Many-to-One self-reference ke `account_companies` (end_user)
    - **Relasi:** One-to-Many ke tabel `activities` (via `opportunity_id`)
    - **Relasi:** One-to-Many ke tabel `tasks` (via `opportunity_id`)
    - **Relasi:** Polymorphic MorphMany ke tabel `logs` (via `App\Traits\Loggable`)

34. **Table Master Products (master_products)**
   - id (Primary Key)
   - name (string 150)
   - code (string 50, unique)
   - brand (string 100, nullable)
   - category (string 100, nullable)
   - division_id (Foreign Key ke tabel Divisions, nullable)
   - description (text, nullable)
   - price (decimal(15,2), default 0.00)
   - status (enum: Active, Inactive)  // default: Active
   - created_at
   - updated_at
   - **Relasi:** Many-to-One ke tabel `divisions`



