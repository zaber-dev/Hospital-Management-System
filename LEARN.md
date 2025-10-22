# What You Can Learn From This Project

This project is a practical, hands-on example of how to build a full-stack, database-driven web application from the ground up. It follows a "database-first" methodology, where the relational database is designed first, and the application is then built to interact with it.

By exploring the code, the `Final Project Report.pdf`, and the `schema.sql` file, you can gain practical insights into the following areas:

## 1. Relational Database Design & SQL

This project is a case study in designing a normalized, efficient, and reliable database schema.

* **Entity-Relationship Diagram (ERD)**: See how a system's requirements are translated into a visual ERD, which models entities like `Patients`, `Doctors`, and `Appointments` and the relationships between them.
* **Normalization**: The schema is normalized (to at least 3NF) to reduce data redundancy and improve data integrity. For example, `Medications` are in their own table, separate from `Prescriptions`.
* **Referential Integrity**: Learn how `FOREIGN KEY` constraints are used to ensure that relationships between tables are always valid.
* **Data Integrity Rules**:
    * **`ON DELETE` Actions**: Understand the difference between `ON DELETE SET NULL`, `ON DELETE CASCADE`, and `ON DELETE RESTRICT` and why each is used (e.g., deleting a patient cascades to their appointments, but deleting a doctor only sets their records to `NULL`).
    * **`ENUM` Types**: See how `ENUM` is used to enforce controlled vocabularies for fields like `role`, `gender`, and `appointment_status`, preventing invalid data.
    * **`UNIQUE` Constraints**: Learn how `UNIQUE` keys (on fields like `email`, `username`, `room_number`) are used to prevent duplicate entries.
* **Practical SQL Queries**: The `8. Sample Queries` section of the report provides excellent examples of:
    * Creating tables (`CREATE TABLE`) with primary keys, foreign keys, and defaults.
    * Inserting data (`INSERT IGNORE INTO`).
    * Querying data with `JOIN`s to combine data from multiple tables.
    * Filtering data with `WHERE`, `DATE()`, `CURDATE()`, and `IS NULL`.
    * Counting results with `COUNT(*)`.

## 2. Backend Development (PHP)

The backend provides a clear example of secure, modern PHP practices.

* **Database-First Development**: See how to write PHP code that performs full CRUD (Create, Read, Update, Delete) operations against a pre-defined database schema.
* **PDO (PHP Data Objects)**: Learn how to use PHP's PDO extension to connect to a MySQL database.
* **Security (Prepared Statements)**: This project uses **prepared statements** with positional placeholders, which is the standard way to prevent SQL injection vulnerabilities.
* **Authentication & Session Management**: Study how `auth/login.php`, `auth/signup.php`, and `auth/logout.php` manage user sessions to keep users logged in and protect dashboard pages.

## 3. Web Application Security

The project implements several key security concepts right out of the box.

* **Role-Based Access Control (RBAC)**: See a simple and effective RBAC system. The application serves different dashboards and restricts actions based on the user's `role` (`admin`, `staff`, or `doctor`).
* **Data Scoping (Ownership)**: Learn how to enforce data ownership, where users (like `doctors`) are restricted to seeing only data relevant to them (e.g., *their* patients and *their* treatments).
* **CSRF Protection**: The application uses **CSRF tokens** on all POST forms, a critical security feature to prevent attackers from performing unwanted actions on behalf of a logged-in user.
* **Password Hashing**: The `users` table includes a `password_hash` field, demonstrating the correct practice of storing hashed passwords, not plaintext.

## 4. Frontend Development (Tailwind CSS)

The UI is built with modern, lightweight tools, making it easy to understand and modify.

* **Utility-First CSS**: See how to build a clean, responsive, and consistent UI rapidly using a utility-first framework like Tailwind CSS.
* **Component-Based UI**: The interface is broken down into manageable PHP files (e.g., `manage-patients.php`, `manage-rooms.php`), each handling a specific part of the application.
* **Lightweight Interactivity**: The project uses Alpine.js for simple interactivity (like dropdowns or modals), showing an alternative to heavier frameworks.

## How to Learn from This

1.  **Read the Report**: Start with the `Final Project Report.pdf`. Pay close attention to the **Requirements Analysis**, **ERD**, and **Database Schema** sections.
2.  **Run the Code**: Set up the project locally (see `README.md`) and interact with it.
3.  **Trace the Data**: Pick a feature (e.g., "Admit Patient").
    * Find the UI in the `dashboard/manage-rooms.php` file.
    * Find the corresponding `INSERT` logic in the PHP backend.
    * Look at the `admissions` table in the database to see the result.
4.  **Try to Extend It**: Look at the "Limitations and Future Works" section and try to implement one of the features, such as:
    * Adding server-side validation for email formats.
    * Implementing a "soft delete" feature.
    * Building a new report page (e.g., "Bed Occupancy Trends").