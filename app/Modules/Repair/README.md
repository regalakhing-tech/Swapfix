# Repair Module

Browser workflows are implemented and registered in `routes/web.php` (the mail worker is in `routes/api.php`).

Files: TechnicianController.php, TechnicianRepository.php, RepairController.php, RepairRepository.php, RepairRules.php.

Routes: /repair, /technicians/{id}, /repair/request/{id}, /repairs, /technician/dashboard, /technician/services, /technician/profile.

Customers approve quotations and added costs before they change the agreed total. The assigned technician advances repair status in order. Private photos, history and chat are visible to participants. Reviews require the real customer and a completed repair.

All mutations call guarded Supabase RPCs; templates are under `resources/views/`. See [setup](../../../docs/SETUP.md) and [database](../../../supabase/README.md) for configuration and verification.
