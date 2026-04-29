# QPAY

Web banking wallet (PHP + MySQL) with iOS-like UI, PWA support, and admin controls.

## Main Features
- Multi-currency wallets: ILS, USD, JOD
- Transfers with commission logic (step or percentage)
- Financial history + PDF export + receipt image
- Notifications and account settings
- Admin dashboard with user/city/currency management
- Admin reports KPIs + CSV export (date-range)
- Advanced Multi-Step KYC registration

## KYC Flow
1. Personal data (age 18+ required)
2. Contact data (Palestinian mobile pattern 059/056)
3. Professional data
4. Security + uploads (ID + selfie)
5. Submit as `pending` for admin review

## Key Files
- `kyc_register.php`
- `api/register_step_val.php`
- `api/register_submit.php`
- `admin_dashboard.php`
- `dashboard.php`
- `SYSTEM_OVERVIEW.md`

## Run
Use a local PHP server + MySQL. Database tables auto-bootstrap from `includes/db.php`.
