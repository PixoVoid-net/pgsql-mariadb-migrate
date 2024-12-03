# PostgreSQL to MariaDB Migration Tool

A robust PHP-based tool designed to automate the migration of databases from PostgreSQL to MariaDB, ensuring data integrity, preservation of relationships, and structure.

---

## ⚠️ Warning

This script is currently under development. Use it at your own risk.

The author assumes no liability for any damages or data loss caused by the use of this script. Ensure that you create proper backups of your databases before running the migration.

Please read this entire `README.md` carefully before proceeding.

---

## 🚀 Features

- Automated schema and data migration.
- Foreign key and index preservation.
- Progress tracking and detailed logging.
- Batch processing for optimal performance.

---

## 👨‍💻 Author

- **PixoVoid**  
  - Email: [contact@pixovoid.net](mailto:contact@pixovoid.net)  
  - Website: [pixovoid.net](https://pixovoid.net)  
  - GitHub: [PixoVoid-net/pgsql-mariadb-migrate](https://github.com/PixoVoid-net/pgsql-mariadb-migrate)

---

## 📜 License

This project is licensed under the MIT License. See the [LICENSE](LICENSE) file for details.

---

## 🧩 Requirements

- PHP >= 8.3
- Required PHP Extensions:
  - `ext-pdo`
  - `ext-pdo_pgsql`
  - `ext-pdo_mysql`

---

## 🔧 Prerequisites

- PostgreSQL 9.4+
- MariaDB 10.3+

---

## 📥 Installation

1. Clone the repository:
   ```bash
   git clone https://github.com/PixoVoid-net/pgsql-mariadb-migrate.git
   cd pgsql-mariadb-migrate
   ```

2. Create a `.env` file in the project root directory and configure it with your database credentials:
   ```env
   PGSQL_HOST=localhost
   PGSQL_PORT=5432
   PGSQL_DBNAME=your_database
   PGSQL_USER=your_username
   PGSQL_PASSWORD=your_password

   MARIADB_HOST=localhost
   MARIADB_PORT=3306
   MARIADB_DBNAME=your_database
   MARIADB_USER=your_username
   MARIADB_PASSWORD=your_password
   ```

---

## ⚙️ Configuration

### Database Settings

The tool uses environment variables to manage database connections. All configurations are stored in the `.env` file.

### Migration Constants

Key configuration constants in the `DatabaseConfig` class:
- `BATCH_SIZE`: The number of records to process per batch (default: 100).
- `DEFAULT_ENGINE`: Default storage engine for tables (default: InnoDB).
- `CHARSET`: Default character set (default: utf8mb4).
- `COLLATION`: Default collation (default: utf8mb4_unicode_ci).

---

## 🏁 Usage

1. Ensure your `.env` file is configured correctly.
2. Run the migration script:
   ```bash
   php migration.php
   ```

The tool will:
1. Verify the required PHP extensions.
2. Connect to both PostgreSQL and MariaDB databases.
3. Create tables in the correct dependency order.
4. Migrate data with proper type conversion.
5. Establish foreign key constraints.
6. Add indexes.
7. Set up audit triggers.

---

## 📊 Data Type Mappings

| PostgreSQL Type        | MariaDB Type        | Notes                               |
|------------------------|---------------------|-------------------------------------|
| `smallint`             | `INT`               |                                     |
| `integer`              | `INT`               |                                     |
| `bigint`               | `BIGINT`            |                                     |
| `boolean`              | `TINYINT(1)`        | `0` = false, `1` = true             |
| `character varying`    | `VARCHAR(255)`      |                                     |
| `text`                 | `TEXT`              |                                     |
| `timestamp`            | `DATETIME`          |                                     |
| `date`                 | `DATE`              |                                     |
| `numeric`              | `DECIMAL(20,6)`     |                                     |

---

## 🔍 Logging and Monitoring

- Migration progress is displayed in real-time with a visual progress bar.
- All operations are logged to `migration.log`.
- Console output uses color coding for different message types:
  - 🟢 **Success** messages
  - 🔴 **Error** messages
  - 🟡 **Warning** messages
