# Sistem Notifikasi HAS-ERP - Dokumentasi Teknis

## Overview
Sistem notifikasi terintegrasi penuh untuk menginformasikan user tentang events penting dalam aplikasi, terutama terkait Task Management.

## Arsitektur Sistem

### 1. Database Structure (`notifications` table)
```
- id: Primary key
- user_id: FK ke users (recipient)
- type: Tipe notifikasi (task_assigned, task_status_changed, task_activity, task_approval_required, task_approved)
- title: Judul notifikasi (ditampilkan di UI)
- body: Deskripsi notifikasi (detail)
- notifiable_type: Model class (polymorphic - usually Task)
- notifiable_id: ID dari model yang di-reference
- data: JSON field untuk menyimpan metadata (task_id, activity_id, approver, redirect_url, dll)
- read_at: Timestamp ketika notifikasi dibaca (NULL jika belum dibaca)
- created_at, updated_at: Timestamps
```

### 2. Notification Types

| Tipe | Trigger | Redirect Target |
|------|---------|-----------------|
| `task_assigned` | Task baru diassign ke user | Task detail page |
| `task_status_changed` | Status task berubah | Task detail page |
| `task_activity` | Ada komentar/aktivitas baru di task | Task detail page |
| `task_approval_required` | Assignee selesai, butuh approval | Task detail page |
| `task_approved` | Creator approve task | Task detail page |

## Bagaimana Notifikasi Dibuat

### A. TaskObserver (Task Status Changes)
```php
// File: app/Observers/TaskObserver.php
// Trigger: Ketika status task berubah
```

**Flow:**
1. Status task berubah (todo → in_progress → waiting_approval → done)
2. Observer menangkap `updated()` event
3. Notifikasi dikirim ke:
   - Task creator (jika status = waiting_approval)
   - Task assignees (jika status berubah)
   - Creator lagi (jika status = done dari waiting_approval)

**Data yang disimpan:**
```json
{
  "task_id": 123,
  "approver": "username_jika_diapprove"
}
```

### B. TaskActivityObserver (New Comments/Attachments)
```php
// File: app/Observers/TaskActivityObserver.php
// Trigger: Ketika ada aktivitas baru di task (comment, attachment)
```

**Flow:**
1. User menambah komentar atau attachment di task
2. Observer menangkap `created()` event
3. Notifikasi dikirim ke:
   - Task creator (jika bukan yang post)
   - Semua assignees (jika bukan yang post)

**Data yang disimpan:**
```json
{
  "task_id": 123,
  "activity_id": 456
}
```

### C. TaskPlannerController (New Task Assignment)
```php
// File: app/Http/Controllers/TaskPlannerController.php
// Trigger: Ketika task baru dibuat dengan assignees
```

**Flow:**
1. Task baru dibuat dengan assignees selain creator
2. Controller membuat notifikasi untuk setiap assignee

**Data yang disimpan:**
```json
{
  "task_id": 123,
  "creator": "nama_creator"
}
```

## Frontend Implementation

### JavaScript Functions

#### 1. `pollNotificationCount()` - Polling unread count
- Dipanggil setiap 30 detik (interval: 30000ms)
- Update badge di notification bell
- Endpoint: `GET /notifications/count`

#### 2. `loadNotifications()` - Fetch notifikasi list
- Dipanggil saat notification bell diklik
- Fetch max 50 notifikasi terbaru
- Endpoint: `GET /notifications`
- Parse data dan render ke UI dengan attributes:
  - `data-id`: Notification ID
  - `data-type`: Notification type
  - `data-task-id`: Task ID (dari data.task_id)
  - `data-redirect`: Custom redirect URL (dari data.redirect_url)

#### 3. `openNotif(el)` - Handle notifikasi click
- Mark notification as read
- Redirect ke halaman yang sesuai:
  - Jika ada `data-redirect`: Gunakan URL itu
  - Jika type include 'task' dan ada `task_id`: Redirect ke task detail
  - Jika tidak ada redirect data: Hanya mark as read

**Endpoint:** `POST /notifications/{id}/read`

#### 4. `markAllRead()` - Mark semua sebagai dibaca
- Mark ALL unread notifikasi jadi read
- Endpoint: `POST /notifications/read-all`

### HTML Structure
```html
<div class="notif-item unread" 
     data-id="123" 
     data-type="task_status_changed" 
     data-task-id="456"
     onclick="openNotif(this)">
  <span class="notif-dot"></span>
  <div class="notif-content">
    <div class="notif-title">Status: Task Title</div>
    <div class="notif-body">To Do → In Progress</div>
    <div class="notif-time">2 hours ago</div>
  </div>
</div>
```

## API Endpoints

### 1. GET `/notifications/count`
**Response:**
```json
{
  "count": 5
}
```

### 2. GET `/notifications`
**Response:**
```json
{
  "data": [
    {
      "id": 1,
      "type": "task_status_changed",
      "title": "Status: Task Title",
      "body": "To Do → In Progress",
      "data": {
        "task_id": 123
      },
      "read": false,
      "time": "2 hours ago"
    }
  ]
}
```

### 3. POST `/notifications/{id}/read`
**Request:** CSRF token via POST
**Response:**
```json
{
  "success": true
}
```

### 4. POST `/notifications/read-all`
**Request:** CSRF token via POST
**Response:**
```json
{
  "success": true
}
```

## Testing Sistem

### Test Manual via Route
Akses endpoint ini untuk membuat test notification:
```
GET /test-notification
```

**Response:**
```json
{
  "success": true,
  "message": "Test notification created for task: Task Title",
  "task_id": 123,
  "note": "Check notification bell to see the test notification"
}
```

**Cara Test:**
1. Login ke aplikasi
2. Pergi ke `/test-notification` di browser
3. Lihat notification bell di topbar
4. Klik notification bell
5. Verifikasi notifikasi muncul
6. Klik notifikasi
7. Verifikasi redirect ke task detail page

### Test Scenarios

#### Scenario 1: Task Assignment
1. User A membuat task baru
2. User A assign task ke User B
3. User B login
4. User B check notification bell
5. Verifikasi ada notifikasi "Tugas baru: [Task Title]"
6. Klik notifikasi
7. Verifikasi redirect ke task detail page

#### Scenario 2: Status Change
1. Task ada dengan status "To Do"
2. User (assignee) ubah status ke "In Progress"
3. Task creator check notification bell
4. Verifikasi ada notifikasi "Status: [Task Title]" dengan body "To Do → In Progress"
5. Klik notifikasi
6. Verifikasi redirect ke task detail page

#### Scenario 3: Approval Flow
1. Task ada dengan status "In Progress"
2. Assignee ubah status ke "Waiting Approval"
3. Creator check notification bell
4. Verifikasi ada notifikasi "Approval dibutuhkan: [Task Title]"
5. Creator klik approve di task detail
6. Assignee check notification bell
7. Verifikasi ada notifikasi "Tugas disetujui: [Task Title]"

#### Scenario 4: Activity/Comment
1. Task ada dengan comment section
2. User A add comment ke task
3. Task creator/assignees check notification bell
4. Verifikasi ada notifikasi "Aktivitas baru: [Task Title]" dengan preview comment
5. Klik notifikasi
6. Verifikasi redirect ke task detail page

## Troubleshooting

### Notifikasi tidak muncul
**Solusi:**
1. Check console browser (F12 → Console) untuk error
2. Verifikasi notification bell polling berjalan (F12 → Network → Filter XHR)
3. Check database jika notifikasi tersimpan: `SELECT * FROM notifications WHERE user_id = X`
4. Clear browser cache dan reload

### Redirect tidak bekerja
**Solusi:**
1. Verifikasi `task_id` ada di data notifikasi
2. Check bahwa task dengan ID tersebut exists
3. Check route `task-planner.show` terdaftar
4. Verifikasi user punya akses ke task detail page

### Badge tidak update
**Solusi:**
1. Verifikasi polling berjalan setiap 30 detik
2. Refresh page untuk reset polling
3. Check console untuk error di AJAX request

## Future Enhancements

1. **Real-time Notifications** - Gunakan WebSocket/Pusher daripada polling
2. **Email Notifications** - Kirim email untuk notifikasi penting
3. **Notification Preferences** - User bisa customize notification settings
4. **Notification History** - Archive notifikasi lama (read notifikasi keep selama 30 hari)
5. **In-app Sound** - Play sound saat ada notifikasi baru
6. **Custom Redirect** - Support notifikasi tipe lain (lead, contact, account)

## Code Flow Diagram

```
Event Trigger (Create/Update Task)
    ↓
Observer Detects Event
    ↓
Create Notification Record
    └─→ user_id, type, title, body, data (with task_id)
    ↓
Frontend: pollNotificationCount() [Every 30s]
    └─→ Update badge count
    ↓
User Clicks Notification Bell
    ↓
Frontend: loadNotifications()
    └─→ Fetch & render notifikasi list
    ↓
User Clicks Notifikasi Item
    ↓
Frontend: openNotif()
    ├─→ POST /notifications/{id}/read (mark as read)
    ├─→ Parse data & type
    └─→ Redirect ke target page (task detail)
```

## Database Queries Reference

```sql
-- Get unread count for user
SELECT COUNT(*) FROM notifications WHERE user_id = 1 AND read_at IS NULL;

-- Get notifikasi for user ordered by latest
SELECT * FROM notifications WHERE user_id = 1 ORDER BY created_at DESC LIMIT 50;

-- Mark all as read
UPDATE notifications SET read_at = NOW() WHERE user_id = 1 AND read_at IS NULL;

-- Delete old read notifications (older than 30 days)
DELETE FROM notifications WHERE user_id = 1 AND read_at IS NOT NULL AND read_at < DATE_SUB(NOW(), INTERVAL 30 DAY);
```

---

**Last Updated:** July 2, 2026
**Status:** ✅ Fully Implemented & Tested
