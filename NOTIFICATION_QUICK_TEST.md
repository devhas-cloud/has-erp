# 🔔 Quick Start Guide - Testing Sistem Notifikasi

## 5 Menit Setup & Test

### Step 1: Verify Routes & Database
```bash
# Verifikasi migrations sudah run
php artisan migrate:status | grep notifications

# Output harus ada baris:
# [x]  2026_07_01_000009_create_notifications_table
```

### Step 2: Test Endpoint (Dev Only)
Buka browser dan akses:
```
http://your-app/test-notification
```

Expected response:
```json
{
  "success": true,
  "message": "Test notification created for task: [Task Title]",
  "task_id": 123,
  "note": "Check notification bell to see the test notification"
}
```

### Step 3: Check Notification Bell
1. Reload halaman aplikasi
2. Lihat topbar kanan - ada notification bell icon 🔔
3. Verifikasi ada **badge dengan angka** (jumlah notifikasi unread)

### Step 4: Click Notification Bell
1. Klik notification bell
2. Dropdown muncul dengan daftar notifikasi
3. Verifikasi notifikasi dari Step 2 ada di list

### Step 5: Click Notification Item
1. Klik on notifikasi item di dropdown
2. Verifikasi **redirect ke task detail page** (not 404)
3. Verifikasi notifikasi hilang dari dropdown (sudah di-mark as read)
4. Badge count berkurang

**✅ Jika semua step berhasil, sistem notifikasi sudah berfungsi!**

---

## Testing Real Scenarios

### Scenario A: Create & Assign Task
**Objective:** Test task assignment notification

**Setup (2 users needed):**
- User A (creator)
- User B (assignee)

**Steps:**
1. **[User A]** Login & create new task
2. **[User A]** Assign task to User B
3. **[User A]** Save task
4. **[User B]** Login (buka tab/browser baru)
5. **[User B]** Verify notification bell shows badge with "1"
6. **[User B]** Click notification bell
7. **[User B]** Should see "Tugas baru: [Task Title]"
8. **[User B]** Click notification → redirect to task detail
9. **[User B]** Verify task details show correctly

**✅ Pass if:** Notification muncul dan redirect bekerja

---

### Scenario B: Task Status Change
**Objective:** Test status change notification

**Setup:**
- Existing task dengan creator = A, assignee = B

**Steps:**
1. **[User B]** Login to task detail
2. **[User B]** Change status from "To Do" → "In Progress"
3. **[User A]** (In different window) Verify notification bell badge ↑
4. **[User A]** Click notification bell
5. **[User A]** Should see "Status: [Task Title]" with "To Do → In Progress"
6. **[User A]** Click notification → redirect to task
7. **[User A]** Verify task status is now "In Progress"

**✅ Pass if:** Status change notifikasi terkirim & redirect bekerja

---

### Scenario C: Approval Flow
**Objective:** Test approval notification flow

**Setup:**
- Task dengan requires_approval = true

**Steps:**
1. **[User B - Assignee]** Change status → "Waiting Approval"
2. **[User A - Creator]** Check notification: "Approval dibutuhkan"
3. **[User A - Creator]** Click notification → go to task
4. **[User A - Creator]** Click "Approve" button
5. **[User B - Assignee]** Check notification: "Tugas disetujui"
6. **[User B - Assignee]** Click notification → verify task status = "Done"

**✅ Pass if:** Approval flow notifications all sent correctly

---

### Scenario D: Mark All Read
**Objective:** Test bulk mark read

**Steps:**
1. Open notification dropdown
2. Verify multiple notifikasi ada (if not, create test notifications)
3. Click "Tandai semua dibaca" button
4. Verify all notifikasi dots disappear (no longer show as unread)
5. Verify badge count goes to 0 (or hidden)

**✅ Pass if:** All notifications marked as read

---

## Debugging Checklist

If something not working, check these:

- [ ] Notification bell badge not showing?
  - Check browser console (F12) for JavaScript errors
  - Verify `/notifications/count` endpoint returns correct number
  
- [ ] Notification list not loading?
  - Check Network tab (F12) → XHR
  - Verify `/notifications` returns valid JSON with `data` array
  
- [ ] Redirect not working?
  - Check if task_id correct in notification data
  - Verify route `task-planner.show` exists: `php artisan route:list | grep task-planner`
  - Check if task exists: `SELECT * FROM tasks WHERE id = X`
  - Check user permissions for task access
  
- [ ] No notifications being created?
  - Check database: `SELECT * FROM notifications ORDER BY created_at DESC LIMIT 10`
  - Verify user_id correct in database
  - Check task observer is registered in service provider

---

## Database Check Commands

```bash
# Check if notifications exist
mysql -u root -p your_db -e "SELECT * FROM notifications LIMIT 5;"

# Check for specific user
mysql -u root -p your_db -e "SELECT id, user_id, type, title, read_at FROM notifications WHERE user_id = 1 ORDER BY created_at DESC LIMIT 10;"

# Count unread for user
mysql -u root -p your_db -e "SELECT COUNT(*) as unread_count FROM notifications WHERE user_id = 1 AND read_at IS NULL;"
```

---

## Performance Tips

- Notifikasi poll interval: **30 detik** (optimal balance)
- Max notifikasi loaded: **50** (dapat diubah di controller)
- Old notifications dapat di-delete setelah 30 hari (database maintenance)

---

## Key Files Changed

1. **app/resources/views/layouts/app.blade.php**
   - Line ~1055: loadNotifications() function
   - Line ~1080: openNotif() function

2. **app/Observers/TaskObserver.php**
   - Fixed notification creation untuk consistency

3. **routes/web.php**
   - Added /test-notification endpoint

4. **NOTIFICATION_SYSTEM.md**
   - Complete technical documentation

---

## Still Having Issues?

1. Check NOTIFICATION_SYSTEM.md for detailed troubleshooting
2. Verify all files modified correctly
3. Run `php artisan migrate` if notifications table missing
4. Check Laravel logs: `storage/logs/laravel.log`
5. Check browser console: F12 → Console

**Last Updated:** July 2, 2026
