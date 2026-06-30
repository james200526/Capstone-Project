-- ================================================================
--  CyberClinic Secure System | Database v4.0
-- ================================================================
CREATE DATABASE IF NOT EXISTS cyberclinic_secure
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE cyberclinic_secure;

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(150) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name_enc BLOB NOT NULL,
    phone_enc BLOB, birthdate_enc BLOB, sex_enc BLOB,
    address_enc BLOB, emergency_contact_enc BLOB,
    blood_type_enc BLOB, allergies_enc BLOB,
    age TINYINT UNSIGNED,
    is_active TINYINT(1) DEFAULT 1,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    mfa_secret VARCHAR(255),
    mfa_enabled TINYINT(1) DEFAULT 0,
    mfa_backup_codes TEXT,
    failed_login_count TINYINT UNSIGNED DEFAULT 0,
    locked_until TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS admins (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    mfa_secret VARCHAR(255),
    mfa_enabled TINYINT(1) DEFAULT 0,
    mfa_backup_codes TEXT,
    failed_login_count TINYINT UNSIGNED DEFAULT 0,
    locked_until TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS doctors (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    specialty VARCHAR(100) NOT NULL,
    bio TEXT, email VARCHAR(150) UNIQUE NOT NULL,
    phone VARCHAR(30), initials CHAR(3),
    license_number VARCHAR(50),
    availability VARCHAR(255) DEFAULT 'Mon-Fri',
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS appointments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    doctor_id INT UNSIGNED NOT NULL,
    appointment_date DATE NOT NULL,
    appointment_time TIME NOT NULL,
    status ENUM('pending','approved','completed','cancelled') DEFAULT 'pending',
    reason_enc BLOB, notes_enc BLOB,
    follow_up_date DATE NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS medical_records (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    doctor_id INT UNSIGNED NOT NULL,
    appointment_id INT UNSIGNED,
    diagnosis_enc BLOB NOT NULL,
    treatment_enc BLOB, notes_enc BLOB,
    record_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE,
    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS prescriptions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    doctor_id INT UNSIGNED NOT NULL,
    appointment_id INT UNSIGNED,
    medication_enc BLOB NOT NULL,
    dosage_enc BLOB, instructions_enc BLOB,
    prescribed_date DATE NOT NULL,
    valid_until DATE,
    status ENUM('active','completed','cancelled') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE,
    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS notifications (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    type VARCHAR(50) NOT NULL,
    title VARCHAR(150) NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS audit_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    actor_type ENUM('patient','admin','system') NOT NULL,
    actor_id INT UNSIGNED,
    action VARCHAR(100) NOT NULL,
    target_type VARCHAR(50), target_id INT UNSIGNED,
    detail TEXT, ip_address VARCHAR(45), user_agent VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS backup_log (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    filename VARCHAR(255) NOT NULL,
    file_size BIGINT UNSIGNED,
    backup_type ENUM('manual','scheduled','pre_update') DEFAULT 'manual',
    status ENUM('success','failed') DEFAULT 'success',
    created_by VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS rate_limit (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    identifier VARCHAR(100) NOT NULL UNIQUE,
    attempts TINYINT UNSIGNED DEFAULT 1,
    window_start TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_appt_user    ON appointments(user_id);
CREATE INDEX idx_appt_doctor  ON appointments(doctor_id);
CREATE INDEX idx_appt_date    ON appointments(appointment_date);
CREATE INDEX idx_appt_status  ON appointments(status);
CREATE INDEX idx_appt_created ON appointments(created_at);
CREATE INDEX idx_records_user ON medical_records(user_id);
CREATE INDEX idx_rx_user      ON prescriptions(user_id);
CREATE INDEX idx_notif_user   ON notifications(user_id, is_read);
CREATE INDEX idx_audit_actor  ON audit_log(actor_type, actor_id);
CREATE INDEX idx_audit_created ON audit_log(created_at);

-- Admin password: Admin@CyberClinic1
INSERT INTO admins (full_name, email, password, mfa_enabled) VALUES
('System Administrator', 'admin@cyberclinic.com',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 0);

INSERT INTO doctors (full_name, specialty, bio, email, phone, initials, license_number, availability) VALUES
('Dr. Sarah Chen',     'Cardiology',       'Board-certified cardiologist with 15 years in interventional cardiology.',        'sarah.chen@cyberclinic.com',    '+63 917 001 0001','SC','PRC-2001-001','Mon,Wed,Fri'),
('Dr. James Miller',   'Dermatology',      'Specialising in medical and cosmetic dermatology with focus on skin cancer.',      'james.miller@cyberclinic.com',  '+63 917 001 0002','JM','PRC-2001-002','Tue,Thu,Sat'),
('Dr. Priya Patel',    'Pediatrics',       'Dedicated pediatrician focused on preventive care and childhood development.',     'priya.patel@cyberclinic.com',   '+63 917 001 0003','PP','PRC-2001-003','Mon-Fri'),
('Dr. Michael Torres', 'Orthopedics',      'Expert in sports medicine and joint replacement surgery, 20 years experience.',    'michael.torres@cyberclinic.com','+63 917 001 0004','MT','PRC-2001-004','Mon,Wed,Fri'),
('Dr. Emily Watson',   'General Medicine', 'Family medicine practitioner providing comprehensive primary care for all ages.',   'emily.watson@cyberclinic.com',  '+63 917 001 0005','EW','PRC-2001-005','Mon-Sat'),
('Dr. Ramon Santos',   'Neurology',        'Neurologist specialising in stroke prevention and headache management.',            'ramon.santos@cyberclinic.com',  '+63 917 001 0006','RS','PRC-2001-006','Tue,Thu'),
('Dr. Ana Reyes',      'OB-GYN',           'Obstetrician-gynecologist providing comprehensive women''s health services.',       'ana.reyes@cyberclinic.com',     '+63 917 001 0007','AR','PRC-2001-007','Mon,Wed,Fri'),
('Dr. Kevin Lim',      'Ophthalmology',    'Eye specialist with expertise in cataract surgery and vision correction.',          'kevin.lim@cyberclinic.com',     '+63 917 001 0008','KL','PRC-2001-008','Tue,Thu,Sat');

ALTER TABLE users  MODIFY COLUMN mfa_secret VARCHAR(255);
ALTER TABLE admins MODIFY COLUMN mfa_secret VARCHAR(255);
