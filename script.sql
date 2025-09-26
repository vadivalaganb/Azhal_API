
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

/*Aout Content Table*/
CREATE TABLE about_sections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    section_key VARCHAR(50) UNIQUE NOT NULL,  -- "section1" or "section2"
    header_name VARCHAR(255) NOT NULL,        -- main title
    description TEXT NOT NULL,
    file_path VARCHAR(255),                   -- optional image/file for section1
    status BOOLEAN NOT NULL DEFAULT 1,        -- active/inactive
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);



CREATE TABLE about_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    section_id INT NOT NULL,                  -- links to about_sections
    icon VARCHAR(100) NOT NULL,               -- FontAwesome class
    subtitle VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    status BOOLEAN NOT NULL DEFAULT 1,        -- active/inactive
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (section_id) REFERENCES about_sections(id) ON DELETE CASCADE
);


CREATE TABLE services_content (
    id INT AUTO_INCREMENT PRIMARY KEY,
    icon VARCHAR(100) NOT NULL,               -- FontAwesome class
    header_name VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    status BOOLEAN NOT NULL DEFAULT 1,        -- active/inactive
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE employees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    gender ENUM('Male','Female','Other') NOT NULL,
    dob DATE NOT NULL,
    designation VARCHAR(100) NOT NULL,
    department VARCHAR(100) NOT NULL,
    joining_date DATE NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    phone VARCHAR(20) NOT NULL,
    location VARCHAR(100),
    manager VARCHAR(100),
    status TINYINT(1) DEFAULT 1,
    profile_image VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);


CREATE TABLE roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role_name VARCHAR(50) UNIQUE NOT NULL
);

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50),
    first_name VARCHAR(100),
    last_name VARCHAR(100),
    email VARCHAR(150) NOT NULL,
    password VARCHAR(255) NOT NULL,
    avatar_url VARCHAR(255),
    role_id INT NOT NULL,
    status TINYINT DEFAULT 1,
    is_email_verified TINYINT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(id)
);

/* Admin Pannel Tables End Here */