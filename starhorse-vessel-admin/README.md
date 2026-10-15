# Starhorse Vessel Admin (CMS)

A custom admin panel for Starhorse Shipping Line, Inc. Today it fully manages
**Vessels** (fleet database + gallery images) and **User Management**
(staff/administrator accounts, roles, and login access). News, Schedules,
and Settings are scaffolded as placeholder modules ready for future
development — same folder pattern, just add a `functions/*.php`,
`modules/<name>/index.php`, and `modules/<name>/ajax/*.php`.

## ⚠️ If you already set up the database before July 2026

The very first version of this CMS created an `admin_users` table. It has
since been replaced by a richer `users` table that also powers the new User
Management module. If your database already has `admin_users`, run
`database/migration_002_users.sql` in phpMyAdmin (Import tab) **once** to
upgrade it in place — do not re-import `schema.sql` over an existing
database, or you'll get "table already exists" errors. Fresh installs should
just import `schema.sql` directly and can ignore this.

## 1. Requirements

- XAMPP (PHP 8.0+, MySQL/MariaDB) — `PDO` and `pdo_mysql` extensions enabled (on by default in XAMPP)
- Project placed at `C:\xampp\htdocs\starhorse-webbb\`

## 2. Database setup

1. Start Apache + MySQL in the XAMPP control panel.
2. Open **phpMyAdmin** → _Import_ → choose `database/schema.sql` (at the project
   root, `starhorse-webbb/database/schema.sql`) → _Go_.
   - This creates the `starhorse_cms` database with `users`, `vessels`,
     and `vessel_images` tables, one default administrator account, and one
     sample vessel record.

## 3. Configure the database connection

Edit `config/database.php` (project root) if your MySQL credentials differ
from the XAMPP defaults (`root` / empty password):

```php
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'starhorse_cms');
define('DB_USER', 'root');
define('DB_PASS', '');
```

## 4. Log in

Visit `http://localhost/starhorse-webbb/starhorse-vessel-admin/`

```
Username: admin
Password: Starhorse@Admin1
```

**Change this password immediately.** Easiest way: log in, go to **User
Management**, click the key icon on the `admin` row, and set a new password
there (Change Password modal). Alternatively, update it directly in the
database — generate a new bcrypt hash with a one-off PHP snippet:

```php
<?php echo password_hash('YourNewPassword', PASSWORD_DEFAULT);
```

...and paste the result into the `users.password` column for that row in
phpMyAdmin.

## 5. Using the User Management module

Sidebar → **User Management** (only visible to accounts with **Administrator**
access — a regular **User** account won't see this link, and hitting the URL
directly redirects them to a 403 Access Denied page; every AJAX endpoint
under `modules/users/ajax/` also checks this server-side).

- **+ Add User** opens a modal: profile photo (optional, drag-and-drop or
  browse), full name, contact number, email, username, password + confirm,
  user access (Administrator / User), and status (Active / Inactive).
- **Edit** (pencil icon) reuses the same modal but hides the password
  fields entirely — passwords are only changed via the dedicated **Change
  Password** modal (key icon).
- The status toggle icon activates/deactivates an account inline (with a
  confirmation prompt) — deactivated accounts can't log in and see:
  _"This account has been deactivated. Please contact the administrator."_
- **Delete** (trash icon) opens a confirmation modal.
- Safety rails: you can't deactivate or delete your own logged-in account,
  and the system always keeps at least one active Administrator — the last
  one is protected from being demoted, deactivated, or deleted.
- All actions run via AJAX with toast notifications in the bottom-right
  corner; the table never triggers a full page reload.

### Roles

|                           | Administrator | User                            |
| ------------------------- | ------------- | ------------------------------- |
| Dashboard                 | ✅            | ✅                              |
| Vessel Management         | ✅            | ✅                              |
| User Management           | ✅            | ❌ (hidden + 403 on direct URL) |
| Future modules / Settings | ✅            | ❌                              |

## 6. Using the Vessel module

- **Dashboard → Vessels** in the sidebar.
- **+ Add Vessel** opens a modal — fill in name, specs, optional
  description, drag-and-drop or browse for images, choose Published/Draft,
  save. No page reload.
- Table row actions: **view** (read-only), **edit** (same modal,
  pre-filled — you can remove existing images and/or add new ones),
  **delete** (confirmation modal).
- The table has built-in search, sorting, pagination, and an entries-per-page
  selector (DataTables).
- Only vessels with status **Published** appear on the public site's
  "Our Fleet" section (`sections/vessels.php`), which reads directly from
  the database — no manual HTML editing required.

## 7. Folder structure

```
starhorse-webbb/
├── config/database.php                shared PDO connection (public + admin)
├── database/
│   ├── schema.sql                     fresh installs: import this into phpMyAdmin
│   └── migration_002_users.sql        existing installs only (see warning above)
├── sections/vessels.php                public fleet section (reads DB)
└── starhorse-vessel-admin/
    ├── login.php / logout.php / index.php / dashboard.php / 403.php
    ├── includes/                      auth.php, header.php, sidebar.php, footer.php
    ├── functions/                     helpers.php, vessel_functions.php, user_functions.php
    ├── assets/css/admin.css           admin theme (brand-matched)
    ├── assets/js/admin.js             sidebar toggle, dropzone helper, toast notifications
    ├── uploads/vessels/                uploaded vessel images land here
    ├── uploads/users/                  uploaded profile photos land here
    └── modules/
        ├── vessels/                   fully functional
        │   ├── index.php              list page + Add/Edit/View/Delete modals
        │   ├── assets/vessels.js      DataTable + modal AJAX logic
        │   └── ajax/                  list.php, view.php, create.php, update.php, delete.php
        ├── users/                     fully functional, Administrator-only
        │   ├── index.php              list page + Add/Edit/Change Password/Delete modals
        │   ├── assets/users.js        DataTable + modal AJAX logic + toasts
        │   └── ajax/                  fetch.php, view.php, create.php, update.php,
        │                              change_password.php, activate.php, delete.php
        ├── news/       (placeholder)
        ├── schedules/  (placeholder)
        └── settings/   (placeholder)
```

## 8. Security notes

- All queries use PDO prepared statements.
- Every write endpoint checks a CSRF token and requires an active admin session.
- User Management endpoints additionally require Administrator access
  server-side (`require_admin()`), independent of what the sidebar shows.
- Uploaded images are validated by real MIME type + `getimagesize()` (not the
  client-supplied filename/type), then sent server-side to Cloudinary with a
  random public ID. The database stores the returned HTTPS URL; original
  filenames are never used as asset IDs. Resume documents remain local.
- Passwords are hashed with bcrypt (`password_hash` / `password_verify`);
  the minimum password length is 8 characters.
- Login blocks accounts with `status = 'inactive'` with a clear message.
- You can't deactivate/delete your own logged-in account, and the system
  always keeps at least one active Administrator.

## 9. Extending with a new module

Follow the Vessels or Users module as a template:

1. Add a table to `database/schema.sql`.
2. Add data-access functions in `functions/<name>_functions.php`.
3. Create `modules/<name>/index.php` (list UI + modals) and
   `modules/<name>/ajax/*.php` (fetch/create/update/delete/...).
4. Add the nav entry in `includes/sidebar.php` and drop the `'soon' => true`
   flag once it's live. Wrap it in `if (is_admin())` if it should be
   Administrator-only, and call `require_admin()` (instead of just
   `require_login()`) at the top of every page/endpoint in that module.

## Cloudinary image storage

- Set `CLOUDINARY_URL` in the server environment (or the ignored local
  `.env` file) using Cloudinary's API environment variable format:
  `cloudinary://<api_key>:<api_secret>@<cloud_name>`. Never commit this value.
- Run `composer install` for local PHP development. The Docker image installs
  locked dependencies during its build.
- On an existing PostgreSQL database, run
  `database/migration_005_cloudinary_image_urls.sql` once before deploying.
  Fresh PostgreSQL and complete-schema installs already use `TEXT` image fields.
- New image uploads are stored in Cloudinary and their HTTPS delivery URLs are
  saved in the existing image columns. Existing local filenames continue to
  render and can be removed normally; replacing them from the admin panel moves
  each asset to Cloudinary. Resume documents remain local and private.
