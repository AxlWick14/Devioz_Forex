CREATE DATABASE IF NOT EXISTS forex_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE forex_db;

CREATE TABLE IF NOT EXISTS divisas (
    id_divisa INT AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(10) NOT NULL UNIQUE,
    nombre VARCHAR(100) NOT NULL,
    simbolo VARCHAR(10),
    tipo ENUM('FIAT','CRYPTO') NOT NULL DEFAULT 'FIAT',
    estado TINYINT(1) NOT NULL DEFAULT 1
);

CREATE TABLE IF NOT EXISTS cotizaciones (
    id_cotizacion BIGINT AUTO_INCREMENT PRIMARY KEY,
    par VARCHAR(20) NOT NULL,
    precio DECIMAL(24,10) NOT NULL,
    fuente VARCHAR(60) NOT NULL,
    fecha_cotizacion DATETIME NOT NULL,
    fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_par_fecha (par, fecha_cotizacion)
);

INSERT IGNORE INTO divisas (codigo,nombre,simbolo,tipo) VALUES
('USD','Dólar estadounidense','$','FIAT'),
('PEN','Sol peruano','S/','FIAT'),
('EUR','Euro','€','FIAT'),
('GBP','Libra esterlina','£','FIAT'),
('JPY','Yen japonés','¥','FIAT'),
('BTC','Bitcoin','₿','CRYPTO'),
('ETH','Ethereum','ETH','CRYPTO'),
('SOL','Solana','SOL','CRYPTO'),
('XRP','XRP','XRP','CRYPTO');
