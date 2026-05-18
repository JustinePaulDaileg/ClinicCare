# ClinicCares - Clinic Management System

A web-based clinic management system developed for ITEL 203 – Web Systems and Technologies.

## Project Description

ClinicCares is a fully functional web-based clinic management system that streamlines clinic operations including patient management, appointment booking, doctor scheduling, billing, prescriptions, and medical records. The system supports role-based access for Admins, Doctors, and Patients.

## Members

- Justine Paul H. Daileg
- Kervin Jetcel E. Jaraplasan
- John Mark M. Magsino

BS Information Technology 2A

## Technologies Used

- **Language:** PHP
- **Database:** MySQL
- **Frontend:** HTML, CSS, JavaScript, Bootstrap
- **Libraries/APIs:**
  - PHPMailer (email notifications)
  - PayMongo API (payment gateway)
  - Google Authenticator / PHPGangsta (2FA verification)
  - Chart.js (analytics/reports)
- **Version Control:** GitHub
- **Local Development:** XAMPP

## Features

- Role-based access (Admin, Doctor, Patient)
- Patient registration and management
- Appointment booking and scheduling
- Billing and invoice generation
- Medical records and prescriptions
- Two-factor authentication (2FA)
- Email notifications
- Online payment via PayMongo
- Clinic finder
- Backup and restore system
- Dashboard with analytics

## Installation Instructions

1. Clone the repository:
   ```
   git clone https://github.com/yourusername/ClinicCares.git
   ```
2. Move the project folder to your XAMPP `htdocs` directory.
3. Import the database:
   - Open phpMyAdmin
   - Create a new database named `cliniccares`
   - Import `cliniccares.sql`
4. Configure the database connection:
   - Copy `includes/config.example.php` to `includes/config.php`
   - Update the database credentials
5. Start Apache and MySQL in XAMPP.
6. Open your browser and go to `http://localhost/ClinicCares`

## Deployment Link

[Live System](https://your-deployment-link-here.com)

## License

This project was created for academic purposes only.
