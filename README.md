# PostgreSQL to MariaDB Migration Tool

A PHP-based tool to facilitate database migration from PostgreSQL to MariaDB, designed with a focus on structure preservation, relationship integrity, and compatibility.

---

## ⚠️ Disclaimer

**This script is no longer actively maintained.**  
Initially developed for a specific project, it has received minor updates but still requires further refinement. For instance, enhancements are needed for the migration of **foreign keys** and other aspects.

The author does not assume responsibility for data loss or damage caused by using this script. **Backup your databases** thoroughly before proceeding.

---

## 🚀 Key Features

- **Schema and Data Migration**: Transfers database schemas and data between PostgreSQL and MariaDB.  
- **Foreign Key Support**: Includes preliminary handling for foreign keys and indexes.  
- **Batch Processing**: Optimized for handling large datasets.  
- **Error Handling**: Logs issues encountered during migration for easier debugging.  
- **Progress Tracking**: Provides visual progress feedback.  
- **Prepared Statements**: Utilizes PDO for secure database interactions.

---

## 🛠️ Getting Started

1. **Clone the Repository**  
   ```bash
   git clone https://github.com/PixoVoid-net/pgsql-mariadb-migrate.git
   ```

2. **Navigate to the Project Directory**  
   ```bash
   cd pgsql-mariadb-migrate
   ```

3. **Check PHP Extensions**  
   Ensure the following extensions are installed:  
   - `ext-pdo`  
   - `ext-pdo_pgsql`  
   - `ext-pdo_mysql`  

---

## 🔧 Configuration Steps

1. **Copy the Example Environment File**  
   ```bash
   cp .env.example .env
   ```

2. **Edit the `.env` File**  
   Update database credentials and other settings based on your environment.

---

## 🚀 Running the Script

1. **Execute the Migration Tool**  
   ```bash
   php migration.php
   ```

2. **Monitor the Output**  
   The script will display progress in the console and generate detailed logs.

---

## 📋 Data Type Mapping

The following table outlines how PostgreSQL data types are mapped to MariaDB:

| PostgreSQL Type        | MariaDB Type        | Notes                               |
|------------------------|---------------------|-------------------------------------|
| `smallint`             | `INT`              |                                     |
| `integer`              | `INT`              |                                     |
| `bigint`               | `BIGINT`           |                                     |
| `boolean`              | `TINYINT(1)`       | `0` = false, `1` = true            |
| `character varying`    | `VARCHAR(255)`     |                                     |
| `text`                 | `TEXT`             |                                     |
| `timestamp`            | `DATETIME`         |                                     |
| `date`                 | `DATE`             |                                     |
| `numeric`              | `DECIMAL(20,6)`    |                                     |

---

## 🔍 Logging and Monitoring

- **Real-Time Feedback**: Console output shows progress and status updates.  
- **Log File**: Issues and migration events are logged in `migration.log`.  
- **Message Types**:  
  - 🟢 Success  
  - 🔴 Error  
  - 🟡 Warning  

---

## 🛠️ Known Issues and TODOs

- **Foreign Key Migration**: Needs significant improvement to handle complex relationships.  
- **Performance**: Optimization required for handling extremely large databases.  
- **Validation**: Additional checks for data integrity post-migration.

---

## 👨‍💻 About the Author

- **PixoVoid**  
  - Email: [contact@pixovoid.net](mailto:contact@pixovoid.net)  
  - Website: [pixovoid.net](https://pixovoid.net)  
  - GitHub: [PixoVoid-net](https://github.com/PixoVoid-net)

---

## 📜 License

This tool is distributed under the [MIT License](LICENSE). You are free to use, modify, and distribute it under these terms.

---

## 🤝 Contributions

Contributions are welcome! If you want to improve this tool, feel free to fork the repository and submit a pull request. See [CONTRIBUTING.md](CONTRIBUTING.md) for details.

---

## 📞 Support

For questions, open an issue on GitHub or contact [contact@pixovoid.net](mailto:contact@pixovoid.net).