# Stock Market Assessment — Admin Management Portal

A dedicated, secure, and standalone administrative portal for managing incoming stock market assessment leads.

---

## Key Features

1. **Secure Admin Authentication**:
   - Session-based authentication with bcrypt-hashed passwords.
   - Guarded routes (unauthorized users are immediately redirected to `/login.html`).
   - Default credentials: `admin` / `Stock@2026#Test` (configurable in `.env`).
2. **Date-wise Leads Directory**:
   - All leads are automatically sorted by creation timestamp (`ORDER BY created_at DESC`).
3. **100 Leads Per Page Pagination**:
   - Server-side pagination with default 100 leads per page.
   - Page controls (Previous, Next, Current Page indicator, Page size selector).
4. **Date Filtering**:
   - Filter by exact single date (`YYYY-MM-DD`).
   - Filter by date range (From Date & To Date).
5. **Live Search**:
   - Fast debounce search by Name, Primary Phone, WhatsApp Number, Reference ID, State, or Segment.
6. **Individual Lead Deletion**:
   - Permanent deletion with confirmation modal.
   - Automatically removes associated question & answer interaction logs from `assessment_interactions`.
7. **Lead Assessment Dossier & Click Audit Trail**:
   - Modal view showing complete lead details and chronological timeline of every question asked and option clicked.
8. **Export to CSV**:
   - Instant export of currently filtered leads as a UTF-8 encoded Excel-friendly CSV.

---

## Directory Structure

```
d:\Alpha-Admin-Portal\
├── .env                         # Database & Admin credentials
├── .env.example                 # Template for environment settings
├── .gitignore                   # Ignores .env and system files
├── cacert.pem                   # SSL CA certificate bundle
├── config/
│   ├── database.php             # PDO MySQL connection with SSL
│   └── auth.php                 # Admin session & authentication helpers
├── database/
│   └── admin_setup.php          # Table migration & seeder for admin_users
├── api/
│   ├── login.php                # Authentication endpoint
│   ├── logout.php               # Sign-out endpoint
│   ├── leads.php                # 100/page paginated leads with date filters
│   ├── lead-detail.php          # Full profile & click audit trail modal data
│   ├── delete-lead.php          # Individual lead deletion handler
│   └── export-csv.php           # Filtered CSV export
├── assets/
│   ├── css/
│   │   ├── auth.css             # Glassmorphic dark login theme
│   │   └── dashboard.css        # High-density admin dashboard styling
│   └── js/
│       ├── auth.js              # Login interaction script
│       └── dashboard.js         # Leads controller, filters, pagination, modal
├── login.html                   # Admin login screen
├── index.html                   # Admin dashboard UI
├── router.php                   # Built-in PHP server router
├── test_admin.php               # Automated test suite
└── README.md                    # Documentation
```

---

## Running the Admin Portal

### 1. Database Setup (Run Once)
```powershell
cd d:\Alpha-Admin-Portal
php database/admin_setup.php
```

### 2. Run Test Suite
```powershell
php test_admin.php
```

### 3. Start Development Server
```powershell
php -S 127.0.0.1:8080 router.php
```
Open **`http://localhost:8080`** in your browser.

* **Username**: `admin`
* **Password**: `Stock@2026#Test`
