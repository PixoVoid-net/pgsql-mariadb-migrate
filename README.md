# PostgreSQL to MariaDB Migration Script

This PHP script helps migrate data from a PostgreSQL database to a MariaDB database. It handles the creation of tables in MariaDB based on PostgreSQL schemas and efficiently transfers data using batch processing to optimize performance.

## ⚠️ Warning

**This script is currently under development. Use at your own risk.**

The author **assumes no liability for any damages or data loss** caused by the use of this script. Before running this migration, make sure to create proper backups of your databases.

**Please read this entire README.md carefully before proceeding with the migration.**

## 🛠️ Requirements

- PHP 8.3 or higher
- PostgreSQL and MariaDB databases
- Database credentials with sufficient access
- A `.env` file to store configuration (see example below)

## 📝 Features

- Migrates PostgreSQL tables to MariaDB.
- Automatically converts data types from PostgreSQL to MariaDB.
- Uses batch processing to improve performance during data transfer.
- Supports configurable table engines (e.g., InnoDB).
- Logs progress and errors to a migration log file (`migration_log.txt`).

## 🔧 Installation & Setup

1. **Clone the repository:**

   ```bash
   git clone https://github.com/PixoVoid-net/pgsql-mariadb-migrate
   cd pgsql-mariadb-migrate
   ```

2. **Configure environment variables:**

   Create a `.env` file in the root directory with the following content, adjusting for your environment:

   ```plaintext
   # PostgreSQL Configuration
   PGSQL_HOST=your_pgsql_host
   PGSQL_PORT=5432
   PGSQL_DBNAME=your_pgsql_dbname
   PGSQL_USER=your_pgsql_user
   PGSQL_PASSWORD=your_pgsql_password

   # MariaDB Configuration
   MARIADB_HOST=your_mariadb_host
   MARIADB_PORT=3306
   MARIADB_DBNAME=your_mariadb_dbname
   MARIADB_USER=your_mariadb_user
   MARIADB_PASSWORD=your_mariadb_password

   # Optional: Table Engine (e.g., InnoDB, MyISAM)
   TABLE_ENGINE=InnoDB
   ```

3. **Run the migration script:**

   Execute the following command to start the migration process:

   ```bash
   php migration.php
   ```

   The script will display a warning message about usage at your own risk, and you will need to confirm by pressing Enter to proceed.

## 🏗️ How it Works

- **Step 1:** The script connects to both PostgreSQL and MariaDB using the credentials provided in the `.env` file.
- **Step 2:** It checks for all available tables in the PostgreSQL database.
- **Step 3:** For each table, it creates an equivalent table in MariaDB with the appropriate data types.
- **Step 4:** The script transfers the data from PostgreSQL to MariaDB in batches for efficiency.
- **Step 5:** Logs are generated for each operation, including success and failure messages.

## 📜 License

This project is licensed under the MIT License. See the [LICENSE](LICENSE) file for more details.

