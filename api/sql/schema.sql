-- Alumni Career Map - schema
-- Clean MySQL/MariaDB schema (extracted from original RTF, utf8mb4).

CREATE DATABASE IF NOT EXISTS alumni_ds_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE alumni_ds_db;

CREATE TABLE IF NOT EXISTS alumni (id INT AUTO_INCREMENT PRIMARY KEY,first_name VARCHAR(50) NOT NULL,last_name VARCHAR(50) NOT NULL,email VARCHAR(100) UNIQUE NOT NULL,enrollment_year INT NOT NULL,graduation_year INT NOT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP);

CREATE TABLE IF NOT EXISTS jobs (id INT AUTO_INCREMENT PRIMARY KEY,alumnus_id INT NOT NULL,company_name VARCHAR(100) NOT NULL,job_title VARCHAR(100) NOT NULL,country VARCHAR(50) NOT NULL,city VARCHAR(50) NOT NULL,latitude DECIMAL(10, 8) NOT NULL,  -- Απαραίτητο για τους χάρτες (Leaflet/OpenMaps)longitude DECIMAL(11, 8) NOT NULL, -- Απαραίτητο για τους χάρτεςis_current BOOLEAN DEFAULT TRUE,   -- Για να ξέρουμε ποια είναι η τρέχουσα αν υπάρχουν πολλέςstart_date DATE NOT NULL, FOREIGN KEY (alumnus_id) REFERENCES alumni(id) ON DELETE CASCADE);
