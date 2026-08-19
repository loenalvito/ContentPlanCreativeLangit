-- Run once as a MySQL/MariaDB administrator. Replace the sample password first.
-- Laravel migrations remain the authoritative application schema.

CREATE DATABASE IF NOT EXISTS `kolabo_creative`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

CREATE USER IF NOT EXISTS 'kolabo_app'@'localhost'
    IDENTIFIED BY 'REPLACE_WITH_A_LONG_RANDOM_PASSWORD';

GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, DROP, REFERENCES, SHOW VIEW, TRIGGER
    ON `kolabo_creative`.* TO 'kolabo_app'@'localhost';

FLUSH PRIVILEGES;
