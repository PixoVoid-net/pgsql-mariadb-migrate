# PostgreSQL to MariaDB Migration Tool

A robust PHP-based tool designed to automate the migration of databases from PostgreSQL to MariaDB, ensuring data integrity, preservation of relationships, and structure.

---

## ⚠️ Warning

**This script is currently under development. Use it at your own risk.**

The author assumes no liability for any damages or data loss caused by the use of this script. Ensure that you create proper backups of your databases before running the migration.

Please read this entire `README.md` carefully before proceeding.

---

## 🚀 Features

- Automated schema and data migration.
- Foreign key and index preservation.
- Progress tracking and detailed logging.
- Batch processing for optimal performance.
- Enhanced error handling and logging.
- Connection pooling and prepared statements for improved performance.

---

## 🛠️ Installation

1. **Clone the repository**:
   ```bash
   git clone https://github.com/PixoVoid-net/pgsql-mariadb-migrate.git
   ```

2. **Navigate to the project directory**:
   ```bash
   cd pgsql-mariadb-migrate
   ```

3. **Ensure all required PHP extensions are installed**:
   - `ext-pdo`
   - `ext-pdo_pgsql`
   - `ext-pdo_mysql`

---

## 🔧 Configuration

1. **Copy the example environment file**:
   ```bash
   cp .env.example .env
   ```

2. **Update the `.env` file** with your database credentials and settings.

---

## 🚀 Usage

1. **Run the migration script**:
   ```bash
   php migration.php
   ```

2. **Monitor the migration process** through the console output and log files.

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

## 🤝 Contributing

Contributions are welcome! Please read the [CONTRIBUTING](CONTRIBUTING.md) file for details on our code of conduct and the process for submitting pull requests.

---

## 📞 Support

For support, please contact [contact@pixovoid.net](mailto:contact@pixovoid.net) or open an issue on GitHub.

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
