# Hospital Management System (HMS)

A comprehensive Hospital Management System built as a project for the Database Management System Lab (CSC 434) at IUBAT. This web application is designed to streamline key hospital operations through a unified, role-aware platform. It features a normalized relational database, a secure PHP backend, and a responsive user interface styled with Tailwind CSS.

This project was created by Md. Mahedi Zaman Zaber (zaber-dev) and submitted to Jubair Ahmed Nabin, Lecturer, Dept. of CSE, IUBAT.

## ✨ Key Features

The system supports multiple domains of hospital management:
* **Patient and Doctor Registries**: Maintain core records for patients, doctors, and departments.
* **Appointment Scheduling**: Create, update, and track appointments between patients and doctors.
* **Clinical Management**: Record patient treatments and issue prescriptions with specific medications and dosages.
* **Facility Management**: Manage room inventory and handle patient admissions and discharges.
* **Role-Based Access Control**: Three distinct user roles (Admin, Staff, Doctor) with tailored dashboards and permissions to ensure data security and integrity.
* **Secure Authentication**: Features session management and CSRF token protection on all POST forms.

## 🛠️ Technology Stack

* **Backend**: PHP with PDO (using positional placeholders for prepared statements).
* **Database**: MySQL/InnoDB.
* **Frontend**: Tailwind CSS (via CDN) and Alpine.js for lightweight interactivity.
* **Development Environment**: XAMPP.
* **Security**: Session management, CSRF tokens, role checks, and doctor ownership validation.

## 📊 Entity-Relationship Diagram (ERD)

The database design is based on the following ERD, which models the relationships between all major entities in the system.

![Hospital Management System ERD](Hospital%20Management%20System%20ERD.png)


## 🧑‍💻 User Roles & Permissions

The system implements three user roles with specific permissions:

* **Admin**: Has full access to the system. Can manage all master data including doctors, departments, medications, users, and hospital-wide records.
* **Staff**: Can manage patient records, appointments, and room admissions/discharges.
* **Doctor**: Has a restricted view. Doctors can only see and manage clinical data (treatments, prescriptions) for their own patients. User accounts with the 'doctor' role can be linked to a specific doctor profile.

## 🚀 Getting Started

To get a local copy up and running, follow these simple steps.

### Prerequisites

* **XAMPP**: The project is designed to run in a XAMPP environment. Make sure you have it installed with Apache and MySQL services running.
* **PHP**: The backend is written in PHP.
* **MySQL Database**: A MySQL server is required.

### Installation & Setup

1.  **Clone the repository:**
    ```sh
    git clone [https://github.com/zaber-dev/Hospital-Management-System.git](https://github.com/zaber-dev/hospital-management-system.git)
    ```
2.  **Move the project to `htdocs`:**
    Place the cloned project folder inside your XAMPP `htdocs` directory.

3.  **Database Setup:**
    * Open phpMyAdmin.
    * Create a new database named `hospital_management`.
    * Import the `schema.sql` file provided in the repository.

4.  **Database Configuration:**
    * You may need to update the database connection details (host, username, password) in the PHP configuration file to match your local setup.

5.  **Run the Application:**
    * Open your web browser and navigate to `http://localhost/[YOUR_PROJECT_FOLDER_NAME]`.

## 🚧 Limitations & Future Work

While the system is functional, there are several areas for improvement:

* **Conflict Detection**: The current scheduling system lacks strict conflict detection for overlapping appointments.
* **Auditing**: No audit trail is in place. Adding history tables or triggers would ensure traceability.
* **Soft Deletes**: Deletes are permanent. Implementing a "soft delete" feature would allow for data recovery.
* **Advanced Validation**: Server-side validation is minimal and could be extended to improve data integrity.
* **Reporting**: Building aggregate reports (e.g., bed occupancy trends, diagnoses statistics) would enhance decision-making.

## 👤 Author

* **Md. Mahedi Zaman Zaber**
* GitHub: [@zaber-dev](https://github.com/zaber-dev)

## 🤝 Contributing

Contributions, issues, and feature requests are welcome!

## 📄 License

This project is open-source. Feel free to use it for learning purposes.
