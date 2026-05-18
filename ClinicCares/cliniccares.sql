-- ClinicCare Database Schema
-- MySQL / MariaDB

CREATE DATABASE IF NOT EXISTS cliniccares CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE cliniccares;

-- Users table (base auth)
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin','doctor','patient') NOT NULL DEFAULT 'patient',
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    avatar VARCHAR(255) DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 0,
    email_verified TINYINT(1) DEFAULT 0,
    verification_token VARCHAR(255) DEFAULT NULL,
    reset_token VARCHAR(255) DEFAULT NULL,
    reset_expires DATETIME DEFAULT NULL,
    totp_secret VARCHAR(100) DEFAULT NULL,
    two_factor_enabled TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Doctors table
CREATE TABLE doctors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    specialization VARCHAR(150) NOT NULL,
    license_number VARCHAR(100) UNIQUE NOT NULL,
    department VARCHAR(100),
    bio TEXT,
    consultation_fee DECIMAL(10,2) DEFAULT 500.00,
    available_days VARCHAR(100) DEFAULT 'Monday,Tuesday,Wednesday,Thursday,Friday',
    start_time TIME DEFAULT '08:00:00',
    end_time TIME DEFAULT '17:00:00',
    slot_duration INT DEFAULT 30,
    google_calendar_id VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Patients table
CREATE TABLE patients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    date_of_birth DATE,
    gender ENUM('male','female','other'),
    blood_type ENUM('A+','A-','B+','B-','AB+','AB-','O+','O-') DEFAULT NULL,
    address TEXT,
    city VARCHAR(100),
    emergency_contact_name VARCHAR(150),
    emergency_contact_phone VARCHAR(20),
    allergies TEXT,
    insurance_provider VARCHAR(150),
    insurance_number VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Appointments table
CREATE TABLE appointments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    doctor_id INT NOT NULL,
    appointment_date DATE NOT NULL,
    appointment_time TIME NOT NULL,
    end_time TIME NOT NULL,
    reason TEXT,
    status ENUM('pending','confirmed','cancelled','completed','no_show') DEFAULT 'pending',
    type ENUM('consultation','follow_up','emergency','check_up') DEFAULT 'consultation',
    notes TEXT,
    google_event_id VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE
);

-- Medical Records table
CREATE TABLE medical_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    doctor_id INT NOT NULL,
    appointment_id INT DEFAULT NULL,
    diagnosis TEXT NOT NULL,
    symptoms TEXT,
    treatment TEXT,
    notes TEXT,
    vital_bp VARCHAR(20),
    vital_temp VARCHAR(10),
    vital_pulse VARCHAR(10),
    vital_weight VARCHAR(10),
    vital_height VARCHAR(10),
    follow_up_date DATE DEFAULT NULL,
    record_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE,
    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE SET NULL
);

-- Prescriptions table
CREATE TABLE prescriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    doctor_id INT NOT NULL,
    medical_record_id INT DEFAULT NULL,
    appointment_id INT DEFAULT NULL,
    prescription_number VARCHAR(50) UNIQUE NOT NULL,
    issue_date DATE NOT NULL,
    valid_until DATE,
    notes TEXT,
    status ENUM('active','completed','cancelled') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE,
    FOREIGN KEY (medical_record_id) REFERENCES medical_records(id) ON DELETE SET NULL,
    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE SET NULL
);

-- Prescription Items
CREATE TABLE prescription_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    prescription_id INT NOT NULL,
    medication_name VARCHAR(255) NOT NULL,
    dosage VARCHAR(100),
    frequency VARCHAR(100),
    duration VARCHAR(100),
    instructions TEXT,
    quantity INT DEFAULT 1,
    FOREIGN KEY (prescription_id) REFERENCES prescriptions(id) ON DELETE CASCADE
);

-- Billing / Payments
CREATE TABLE billing (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_number VARCHAR(50) UNIQUE NOT NULL,
    patient_id INT NOT NULL,
    appointment_id INT DEFAULT NULL,
    doctor_id INT DEFAULT NULL,
    subtotal DECIMAL(10,2) NOT NULL DEFAULT 0,
    discount DECIMAL(10,2) DEFAULT 0,
    tax DECIMAL(10,2) DEFAULT 0,
    total DECIMAL(10,2) NOT NULL DEFAULT 0,
    amount_paid DECIMAL(10,2) DEFAULT 0,
    balance DECIMAL(10,2) DEFAULT 0,
    status ENUM('pending','partial','paid','cancelled','refunded') DEFAULT 'pending',
    payment_method ENUM('cash','gcash','paymaya','card','insurance','bank_transfer') DEFAULT NULL,
    payment_reference VARCHAR(255) DEFAULT NULL,
    payment_date DATETIME DEFAULT NULL,
    due_date DATE,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE SET NULL,
    FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE SET NULL
);

-- Billing Items
CREATE TABLE billing_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    billing_id INT NOT NULL,
    description VARCHAR(255) NOT NULL,
    quantity INT DEFAULT 1,
    unit_price DECIMAL(10,2) NOT NULL,
    total DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (billing_id) REFERENCES billing(id) ON DELETE CASCADE
);

-- Notifications
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('appointment','billing','prescription','system','reminder') DEFAULT 'system',
    is_read TINYINT(1) DEFAULT 0,
    link VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Activity Logs
CREATE TABLE activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(255) NOT NULL,
    description TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Doctor Schedule Overrides (days off, special hours)
CREATE TABLE schedule_overrides (
    id INT AUTO_INCREMENT PRIMARY KEY,
    doctor_id INT NOT NULL,
    override_date DATE NOT NULL,
    is_available TINYINT(1) DEFAULT 0,
    start_time TIME DEFAULT NULL,
    end_time TIME DEFAULT NULL,
    reason VARCHAR(255),
    FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE
);

-- ====================
-- SEED DATA
-- ====================

-- Admin user (password: Admin@123)
INSERT INTO users (email, password, role, first_name, last_name, phone, is_active, email_verified) VALUES
('admin@cliniccare.com', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'System', 'Administrator', '09171234567', 1, 1);

-- Doctor users (password: Doctor@123)
INSERT INTO users (email, password, role, first_name, last_name, phone, is_active, email_verified) VALUES
('dr.reyes@cliniccare.com', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'doctor', 'Maria', 'Reyes', '09181234567', 1, 1),
('dr.santos@cliniccare.com', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'doctor', 'Jose', 'Santos', '09191234567', 1, 1),
('dr.garcia@cliniccare.com', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'doctor', 'Ana', 'Garcia', '09201234567', 1, 1);

-- Insert doctors profile
INSERT INTO doctors (user_id, specialization, license_number, department, bio, consultation_fee) VALUES
(2, 'Internal Medicine', 'LIC-2024-001', 'Internal Medicine', 'Board-certified internist with 10 years of experience.', 800.00),
(3, 'Pediatrics', 'LIC-2024-002', 'Pediatrics', 'Dedicated pediatrician specializing in child health.', 600.00),
(4, 'Cardiology', 'LIC-2024-003', 'Cardiology', 'Expert cardiologist with advanced training in heart disease.', 1200.00);

-- Patient users (password: Patient@123)
INSERT INTO users (email, password, role, first_name, last_name, phone, is_active, email_verified) VALUES
('juan.dela.cruz@email.com', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'patient', 'Juan', 'Dela Cruz', '09211234567', 1, 1),
('maria.santos@email.com', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'patient', 'Maria', 'Santos', '09221234567', 1, 1),
('pedro.reyes@email.com', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'patient', 'Pedro', 'Reyes', '09231234567', 1, 1);

INSERT INTO patients (user_id, date_of_birth, gender, blood_type, address, city, emergency_contact_name, emergency_contact_phone) VALUES
(5, '1990-05-15', 'male', 'O+', '123 Rizal Street', 'Manila', 'Ana Dela Cruz', '09241234567'),
(6, '1985-08-22', 'female', 'A+', '456 Bonifacio Ave', 'Quezon City', 'Roberto Santos', '09251234567'),
(7, '1978-12-10', 'male', 'B+', '789 Luna Street', 'Makati', 'Luisa Reyes', '09261234567');

-- Sample appointments
INSERT INTO appointments (patient_id, doctor_id, appointment_date, appointment_time, end_time, reason, status, type) VALUES
(1, 1, DATE_ADD(CURDATE(), INTERVAL 1 DAY), '09:00:00', '09:30:00', 'Regular check-up', 'confirmed', 'check_up'),
(1, 2, DATE_ADD(CURDATE(), INTERVAL 3 DAY), '10:00:00', '10:30:00', 'Follow-up consultation', 'pending', 'follow_up'),
(2, 1, DATE_ADD(CURDATE(), INTERVAL 2 DAY), '11:00:00', '11:30:00', 'Fever and cough', 'confirmed', 'consultation'),
(3, 3, DATE_ADD(CURDATE(), INTERVAL 5 DAY), '14:00:00', '14:30:00', 'Heart palpitations', 'pending', 'consultation'),
(1, 1, DATE_SUB(CURDATE(), INTERVAL 7 DAY), '09:00:00', '09:30:00', 'Blood pressure check', 'completed', 'check_up'),
(2, 2, DATE_SUB(CURDATE(), INTERVAL 14 DAY), '10:00:00', '10:30:00', 'Annual physical', 'completed', 'check_up');

-- Sample medical records
INSERT INTO medical_records (patient_id, doctor_id, appointment_id, diagnosis, symptoms, treatment, vital_bp, vital_temp, vital_pulse, vital_weight, vital_height, record_date) VALUES
(1, 1, 5, 'Hypertension Stage 1', 'Headache, dizziness, elevated BP', 'Lifestyle modification and medication', '140/90', '36.8', '82', '75kg', '170cm', DATE_SUB(CURDATE(), INTERVAL 7 DAY)),
(2, 2, 6, 'Upper Respiratory Tract Infection', 'Cough, fever, runny nose', 'Antibiotics and rest', '110/70', '37.9', '88', '58kg', '160cm', DATE_SUB(CURDATE(), INTERVAL 14 DAY));

-- Sample prescriptions
INSERT INTO prescriptions (patient_id, doctor_id, medical_record_id, appointment_id, prescription_number, issue_date, valid_until, status) VALUES
(1, 1, 1, 5, 'RX-2024-0001', DATE_SUB(CURDATE(), INTERVAL 7 DAY), DATE_ADD(CURDATE(), INTERVAL 23 DAY), 'active'),
(2, 2, 2, 6, 'RX-2024-0002', DATE_SUB(CURDATE(), INTERVAL 14 DAY), DATE_SUB(CURDATE(), INTERVAL 7 DAY), 'completed');

INSERT INTO prescription_items (prescription_id, medication_name, dosage, frequency, duration, instructions, quantity) VALUES
(1, 'Amlodipine', '5mg', 'Once daily', '30 days', 'Take in the morning with or without food', 30),
(1, 'Losartan', '50mg', 'Once daily', '30 days', 'Take at the same time each day', 30),
(2, 'Amoxicillin', '500mg', 'Three times daily', '7 days', 'Take with food to avoid stomach upset', 21),
(2, 'Paracetamol', '500mg', 'As needed', '7 days', 'Take for fever above 38°C', 14);

-- Sample billing
INSERT INTO billing (invoice_number, patient_id, appointment_id, doctor_id, subtotal, tax, total, amount_paid, balance, status, payment_method, payment_date, due_date) VALUES
('INV-2024-0001', 1, 5, 1, 800.00, 0, 800.00, 800.00, 0.00, 'paid', 'gcash', DATE_SUB(CURDATE(), INTERVAL 7 DAY), DATE_SUB(CURDATE(), INTERVAL 7 DAY)),
('INV-2024-0002', 2, 6, 2, 600.00, 0, 600.00, 0.00, 600.00, 'pending', NULL, NULL, DATE_ADD(CURDATE(), INTERVAL 7 DAY));

INSERT INTO billing_items (billing_id, description, quantity, unit_price, total) VALUES
(1, 'Consultation Fee - Dr. Maria Reyes', 1, 800.00, 800.00),
(2, 'Consultation Fee - Dr. Jose Santos', 1, 600.00, 600.00);


-- ClinicCare: Find Nearest Clinic / Doctor Feature
-- Run this AFTER the main cliniccare.sql import

USE cliniccares;

-- Add location fields to doctors table
ALTER TABLE doctors
    ADD COLUMN IF NOT EXISTS clinic_name      VARCHAR(200)   DEFAULT NULL AFTER bio,
    ADD COLUMN IF NOT EXISTS clinic_address   VARCHAR(300)   DEFAULT NULL AFTER clinic_name,
    ADD COLUMN IF NOT EXISTS clinic_city      VARCHAR(100)   DEFAULT NULL AFTER clinic_address,
    ADD COLUMN IF NOT EXISTS clinic_lat       DECIMAL(10,7)  DEFAULT NULL AFTER clinic_city,
    ADD COLUMN IF NOT EXISTS clinic_lng       DECIMAL(10,7)  DEFAULT NULL AFTER clinic_lat,
    ADD COLUMN IF NOT EXISTS clinic_phone     VARCHAR(30)    DEFAULT NULL AFTER clinic_lng,
    ADD COLUMN IF NOT EXISTS clinic_hours     VARCHAR(200)   DEFAULT 'Mon-Fri 8:00 AM – 5:00 PM' AFTER clinic_phone,
    ADD COLUMN IF NOT EXISTS accepts_walkin   TINYINT(1)     DEFAULT 1 AFTER clinic_hours,
    ADD COLUMN IF NOT EXISTS telemedicine     TINYINT(1)     DEFAULT 0 AFTER accepts_walkin;

-- Standalone clinics table (clinics not tied to a single doctor)
CREATE TABLE IF NOT EXISTS clinics (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(200) NOT NULL,
    type            ENUM('hospital','clinic','specialty','pharmacy','diagnostic') DEFAULT 'clinic',
    address         VARCHAR(300),
    city            VARCHAR(100),
    lat             DECIMAL(10,7) NOT NULL,
    lng             DECIMAL(10,7) NOT NULL,
    phone           VARCHAR(30),
    email           VARCHAR(150),
    website         VARCHAR(200),
    hours           VARCHAR(200) DEFAULT 'Mon-Fri 8:00 AM – 5:00 PM',
    accepts_walkin  TINYINT(1) DEFAULT 1,
    telemedicine    TINYINT(1) DEFAULT 0,
    emergency       TINYINT(1) DEFAULT 0,
    rating          DECIMAL(2,1) DEFAULT 4.0,
    description     TEXT,
    specializations VARCHAR(500),
    is_active       TINYINT(1) DEFAULT 1,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Seed doctor locations (Manila area, Philippines)
UPDATE doctors SET
    clinic_name    = 'ClinicCare – Makati Medical Center',
    clinic_address = 'Amorsolo St, Legazpi Village, Makati',
    clinic_city    = 'Makati City',
    clinic_lat     = 14.5547,
    clinic_lng     = 121.0244,
    clinic_phone   = '+63 2 8888 8999',
    clinic_hours   = 'Mon–Fri 8:00 AM – 6:00 PM, Sat 8:00 AM – 12:00 PM',
    accepts_walkin = 1,
    telemedicine   = 1
WHERE id = 1;

UPDATE doctors SET
    clinic_name    = 'ClinicCare – Quezon City Pediatric Center',
    clinic_address = 'Elliptical Rd, Diliman, Quezon City',
    clinic_city    = 'Quezon City',
    clinic_lat     = 14.6524,
    clinic_lng     = 121.0374,
    clinic_phone   = '+63 2 8929 7777',
    clinic_hours   = 'Mon–Sat 9:00 AM – 5:00 PM',
    accepts_walkin = 1,
    telemedicine   = 0
WHERE id = 2;

UPDATE doctors SET
    clinic_name    = 'ClinicCare – BGC Heart & Vascular Institute',
    clinic_address = '5th Ave corner 39th St, Bonifacio Global City',
    clinic_city    = 'Taguig City',
    clinic_lat     = 14.5490,
    clinic_lng     = 121.0490,
    clinic_phone   = '+63 2 8789 7700',
    clinic_hours   = 'Mon–Fri 7:00 AM – 7:00 PM',
    accepts_walkin = 0,
    telemedicine   = 1
WHERE id = 3;

-- Seed standalone clinics (Metro Manila & surroundings)
INSERT INTO clinics (name, type, address, city, lat, lng, phone, hours, accepts_walkin, emergency, rating, specializations, description) VALUES

('Philippine General Hospital',
 'hospital',
 'Taft Ave, Ermita, Manila',
 'Manila',
 14.5653, 120.9925,
 '+63 2 8554 8400',
 'Open 24 Hours',
 1, 1, 4.3,
 'Emergency,Internal Medicine,Surgery,Pediatrics,OB-GYN',
 'The largest government hospital in the Philippines, providing tertiary-level care.'),

('St. Luke''s Medical Center – Global City',
 'hospital',
 '32nd St corner 5th Ave, BGC, Taguig',
 'Taguig',
 14.5477, 121.0483,
 '+63 2 8789 7700',
 'Open 24 Hours',
 1, 1, 4.8,
 'Cardiology,Oncology,Neurology,Orthopedics,Internal Medicine',
 'Premier private hospital with world-class facilities and specialist doctors.'),

('Makati Medical Center',
 'hospital',
 '2 Amorsolo St, Legaspi Village, Makati',
 'Makati',
 14.5547, 121.0244,
 '+63 2 8888 8999',
 'Open 24 Hours',
 1, 1, 4.7,
 'Cardiology,Oncology,Orthopedics,Neurology,Internal Medicine',
 'One of Metro Manila''s top private hospitals offering comprehensive medical services.'),

('The Medical City',
 'hospital',
 'Ortigas Ave, Pasig',
 'Pasig',
 14.5876, 121.0701,
 '+63 2 8988 1000',
 'Open 24 Hours',
 1, 1, 4.6,
 'Oncology,Cardiology,Pediatrics,Internal Medicine,Dermatology',
 'A leading private hospital known for its cancer center and cardiac services.'),

('Ospital ng Maynila Medical Center',
 'hospital',
 'Quirino Ave, Malate, Manila',
 'Manila',
 14.5685, 120.9897,
 '+63 2 8524 6061',
 'Open 24 Hours',
 1, 1, 3.9,
 'Emergency,Surgery,Internal Medicine,Pediatrics',
 'City government hospital serving the people of Manila with affordable care.'),

('Asian Hospital & Medical Center',
 'hospital',
 '2205 Civic Dr, Filinvest Corporate City, Muntinlupa',
 'Muntinlupa',
 14.4189, 121.0392,
 '+63 2 8771 9000',
 'Open 24 Hours',
 1, 1, 4.7,
 'Cardiology,Neurology,Orthopedics,Oncology,Gastroenterology',
 'JCI-accredited hospital in the south offering top-tier medical services.'),

('Quirino Memorial Medical Center',
 'hospital',
 'Quirino Ave, Project 4, Quezon City',
 'Quezon City',
 14.6180, 121.0640,
 '+63 2 8913 4561',
 'Open 24 Hours',
 1, 1, 3.8,
 'Emergency,Internal Medicine,Pediatrics,OB-GYN,Surgery',
 'Government hospital in Quezon City providing accessible healthcare.'),

('Hi-Precision Diagnostics',
 'diagnostic',
 'G/F WalterMart Makati, Makati Ave',
 'Makati',
 14.5580, 121.0190,
 '+63 2 8888 4747',
 'Mon–Sat 7:00 AM – 7:00 PM, Sun 8:00 AM – 5:00 PM',
 1, 0, 4.5,
 'Laboratory,X-Ray,Ultrasound,ECG,MRI',
 'Trusted diagnostic center with fast, accurate results across multiple branches.'),

('Lung Center of the Philippines',
 'specialty',
 'Quezon Ave, Quezon City',
 'Quezon City',
 14.6526, 121.0337,
 '+63 2 8924 6101',
 'Mon–Fri 7:00 AM – 5:00 PM',
 1, 1, 4.4,
 'Pulmonology,Thoracic Surgery,Internal Medicine,Allergy',
 'National specialty center for lung and respiratory diseases.'),

('National Kidney & Transplant Institute',
 'specialty',
 'East Ave, Diliman, Quezon City',
 'Quezon City',
 14.6501, 121.0484,
 '+63 2 8981 0300',
 'Open 24 Hours',
 1, 1, 4.6,
 'Nephrology,Urology,Transplant Surgery,Internal Medicine',
 'The country''s leading center for kidney diseases and transplant services.'),

('Capitol Medical Center',
 'hospital',
 'Scout Magbanua St, Quezon City',
 'Quezon City',
 14.6329, 121.0146,
 '+63 2 8372 7777',
 'Open 24 Hours',
 1, 1, 4.3,
 'Internal Medicine,Surgery,Cardiology,OB-GYN,Pediatrics',
 'A well-established private hospital in the heart of Quezon City.'),

('HealthNow Digital Clinic',
 'clinic',
 'Net One Center, BGC, Taguig',
 'Taguig',
 14.5512, 121.0476,
 '+63 917 888 9000',
 'Mon–Fri 9:00 AM – 6:00 PM',
 1, 0, 4.2,
 'General Practice,Telemedicine,Wellness',
 'Modern digital-first clinic offering both in-person and telemedicine consultations.'),

('Family Doctors Clinic – Alabang',
 'clinic',
 'Festival Mall Medical Strip, Alabang, Muntinlupa',
 'Muntinlupa',
 14.4217, 121.0373,
 '+63 2 8850 1234',
 'Mon–Sat 8:00 AM – 8:00 PM, Sun 10:00 AM – 6:00 PM',
 1, 0, 4.4,
 'General Practice,Pediatrics,Internal Medicine,OB-GYN',
 'Friendly neighborhood clinic with complete family health services.'),

('Philippine Heart Center',
 'specialty',
 'East Ave, Quezon City',
 'Quezon City',
 14.6494, 121.0493,
 '+63 2 8925 2401',
 'Open 24 Hours',
 1, 1, 4.7,
 'Cardiology,Cardiac Surgery,Vascular Surgery,Echocardiography',
 'The national center for cardiovascular diseases and cardiac surgery.'),

('Pasay City General Hospital',
 'hospital',
 'Padre Zamora St, Pasay',
 'Pasay',
 14.5379, 120.9982,
 '+63 2 8833 9999',
 'Open 24 Hours',
 1, 1, 3.7,
 'Emergency,Internal Medicine,Surgery,Pediatrics',
 'City government hospital serving Pasay and surrounding communities.');

-- --------------------------------------------------------
-- Backup logs table (for ClinicCares backup system)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS backup_logs (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    filename      VARCHAR(255)                          NOT NULL DEFAULT '',
    file_size     BIGINT                                NOT NULL DEFAULT 0,
    triggered_by  ENUM('manual','scheduled','restore')  NOT NULL DEFAULT 'manual',
    status        ENUM('success','failed')              NOT NULL DEFAULT 'success',
    notes         TEXT                                  DEFAULT NULL,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
