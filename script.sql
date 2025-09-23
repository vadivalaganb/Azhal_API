
/* Create new Database */
DROP DATABASE IF EXISTS azhal_it_solutions;
CREATE DATABASE azhal_it_solutions;
USE azhal_it_solutions;

/* User Pannel Tables Start Here */

/* Contacts Messages Table */
CREATE TABLE contact_messages (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(100) NOT NULL,
  subject VARCHAR(150) NOT NULL,
  message TEXT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

/* Student Register Table */
CREATE TABLE student_register (
    id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    contact VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    institution_name VARCHAR(150),
    academic_year VARCHAR(50),
    dob DATE,
    gender VARCHAR(10),
    address TEXT,
    department VARCHAR(100),
    course VARCHAR(100),
    profile_image VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

/* User Pannel Tables End Here */




/* Admin Pannel Tables Start Here */

/*Home Content Table*/
CREATE TABLE home_contents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    header_name VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    file_path VARCHAR(255),
    status BOOLEAN NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

/* Admin Pannel Tables End Here */