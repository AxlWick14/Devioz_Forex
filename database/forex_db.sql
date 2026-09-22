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
    open_price DECIMAL(24,10) NULL,
    high_price DECIMAL(24,10) NULL,
    low_price DECIMAL(24,10) NULL,
    previous_close DECIMAL(24,10) NULL,
    percent_change DECIMAL(12,6) NULL,
    fuente VARCHAR(60) NOT NULL,
    fecha_cotizacion DATETIME NOT NULL,
    fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_par_fecha (par, fecha_cotizacion),
    INDEX idx_fecha_cotizacion (fecha_cotizacion)
);

CREATE TABLE IF NOT EXISTS historico_cotizaciones (
    id_historico BIGINT AUTO_INCREMENT PRIMARY KEY,
    par VARCHAR(20) NOT NULL,
    fecha DATE NOT NULL,
    precio_apertura DECIMAL(24,10) NULL,
    precio_maximo DECIMAL(24,10) NULL,
    precio_minimo DECIMAL(24,10) NULL,
    precio_cierre DECIMAL(24,10) NOT NULL,
    cambio_porcentual DECIMAL(12,6) NULL,
    fuente VARCHAR(60) NOT NULL,
    fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_par_fecha (par, fecha),
    INDEX idx_par_fecha_historico (par, fecha)
);

CREATE TABLE IF NOT EXISTS velas_historicas (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    par VARCHAR(20) NOT NULL,
    intervalo VARCHAR(10) NOT NULL DEFAULT '1day',
    fecha_hora DATETIME NOT NULL,
    precio_apertura DECIMAL(24,10) NOT NULL,
    precio_maximo DECIMAL(24,10) NOT NULL,
    precio_minimo DECIMAL(24,10) NOT NULL,
    precio_cierre DECIMAL(24,10) NOT NULL,
    volumen DECIMAL(24,10) NULL,
    fuente VARCHAR(60) NOT NULL,
    fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_vela_par_intervalo_fecha (par, intervalo, fecha_hora),
    INDEX idx_vela_par_fecha (par, fecha_hora)
);

CREATE TABLE IF NOT EXISTS alertas (
    id_alerta BIGINT AUTO_INCREMENT PRIMARY KEY,
    par VARCHAR(20) NOT NULL,
    tipo_alerta ENUM('precio','variacion','tendencia') NOT NULL,
    condicion VARCHAR(50) NOT NULL,
    valor DECIMAL(24,10) NOT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_par_activa (par, activo)
);

INSERT IGNORE INTO divisas (codigo,nombre,simbolo,tipo) VALUES
('USD','Dólar estadounidense','$','FIAT'),
('PEN','Sol peruano','S/','FIAT'),
('EUR','Euro','€','FIAT'),
('GBP','Libra esterlina','£','FIAT'),
('BTC','Bitcoin','₿','CRYPTO'),
('ETH','Ethereum','ETH','CRYPTO'),
('SOL','Solana','SOL','CRYPTO'),
('XRP','XRP','XRP','CRYPTO');

INSERT IGNORE INTO cotizaciones (par, precio, open_price, high_price, low_price, previous_close, percent_change, fuente, fecha_cotizacion)
VALUES
('EUR/USD', 1.1000, 1.0980, 1.1020, 1.0970, 1.0990, 0.0910, 'Twelve Data', NOW()),
('GBP/USD', 1.2700, 1.2680, 1.2720, 1.2660, 1.2690, 0.0780, 'Twelve Data', NOW()),
('USD/PEN', 3.7800, 3.7750, 3.7850, 3.7730, 3.7790, 0.0260, 'Twelve Data', NOW());

INSERT IGNORE INTO historico_cotizaciones (par, fecha, precio_apertura, precio_maximo, precio_minimo, precio_cierre, cambio_porcentual, fuente)
VALUES
('EUR/USD', '2024-01-01', 1.0950, 1.1100, 1.0900, 1.1025, 0.6800, 'Twelve Data'),
('EUR/USD', '2024-01-02', 1.1025, 1.1180, 1.0980, 1.1100, 0.6800, 'Twelve Data'),
('EUR/USD', '2024-01-03', 1.1100, 1.1155, 1.1040, 1.1085, -0.1350, 'Twelve Data'),
('EUR/USD', '2024-01-04', 1.1085, 1.1200, 1.1050, 1.1160, 0.6750, 'Twelve Data'),
('GBP/USD', '2024-01-01', 1.2500, 1.2750, 1.2450, 1.2680, 1.4400, 'Twelve Data'),
('GBP/USD', '2024-01-02', 1.2680, 1.2810, 1.2600, 1.2765, 0.6700, 'Twelve Data'),
('GBP/USD', '2024-01-03', 1.2765, 1.2840, 1.2705, 1.2798, 0.2570, 'Twelve Data'),
('GBP/USD', '2024-01-04', 1.2798, 1.2875, 1.2740, 1.2817, 0.1480, 'Twelve Data'),
('USD/PEN', '2024-01-01', 3.7400, 3.7900, 3.7200, 3.7800, 1.0700, 'Twelve Data'),
('USD/PEN', '2024-01-02', 3.7800, 3.8000, 3.7650, 3.7900, 0.2650, 'Twelve Data'),
('USD/PEN', '2024-01-03', 3.7900, 3.8150, 3.7740, 3.7980, 0.2110, 'Twelve Data'),
('USD/PEN', '2024-01-04', 3.7980, 3.8220, 3.7860, 3.8100, 0.3160, 'Twelve Data');
