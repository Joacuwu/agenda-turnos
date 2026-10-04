-- Instalación en MySQL 5.6+ / MariaDB. Después crear un usuario con sql/crear_usuario.php.
CREATE TABLE IF NOT EXISTS usuarios (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  usuario VARCHAR(50) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  nombre VARCHAR(100) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uk_usuarios_usuario (usuario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS profesionales (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre VARCHAR(60) NOT NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS servicios (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre VARCHAR(60) NOT NULL,
  duracion SMALLINT UNSIGNED NOT NULL COMMENT 'minutos',
  precio DECIMAL(10,2) NOT NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bloques de atención semanales. dia_semana: 1 = lunes ... 7 = domingo. Puede haber varios bloques por día (ej. mañana y tarde).
CREATE TABLE IF NOT EXISTS horarios (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  profesional_id INT UNSIGNED NOT NULL,
  dia_semana TINYINT UNSIGNED NOT NULL,
  desde CHAR(5) NOT NULL,
  hasta CHAR(5) NOT NULL,
  PRIMARY KEY (id),
  KEY idx_horarios_profesional_dia (profesional_id, dia_semana),
  CONSTRAINT fk_horarios_profesional FOREIGN KEY (profesional_id) REFERENCES profesionales (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS turnos (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  codigo CHAR(6) NOT NULL,
  servicio_id INT UNSIGNED NOT NULL,
  profesional_id INT UNSIGNED NOT NULL,
  inicio DATETIME NOT NULL,
  fin DATETIME NOT NULL,
  cliente VARCHAR(80) NOT NULL,
  telefono VARCHAR(20) NOT NULL,
  email VARCHAR(120) NULL,
  estado ENUM('reservado', 'asistio', 'ausente', 'cancelado') NOT NULL DEFAULT 'reservado',
  creado_en DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uk_turnos_codigo (codigo),
  KEY idx_turnos_agenda (profesional_id, inicio),
  KEY idx_turnos_inicio (inicio),
  CONSTRAINT fk_turnos_servicio FOREIGN KEY (servicio_id) REFERENCES servicios (id),
  CONSTRAINT fk_turnos_profesional FOREIGN KEY (profesional_id) REFERENCES profesionales (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Datos iniciales de ejemplo (cambiarlos por los del negocio).
INSERT INTO profesionales (nombre) VALUES ('Lucas'), ('Martina');
INSERT INTO servicios (nombre, duracion, precio) VALUES
  ('Corte', 30, 9000), ('Corte y barba', 45, 13500), ('Barba', 30, 6000), ('Color', 90, 25000);
INSERT INTO horarios (profesional_id, dia_semana, desde, hasta) VALUES
  (1, 1, '09:00', '13:00'), (1, 1, '15:00', '19:00'),
  (1, 2, '09:00', '13:00'), (1, 2, '15:00', '19:00'),
  (1, 3, '09:00', '13:00'), (1, 3, '15:00', '19:00'),
  (1, 4, '09:00', '13:00'), (1, 4, '15:00', '19:00'),
  (1, 5, '09:00', '13:00'), (1, 5, '15:00', '19:00'),
  (1, 6, '09:00', '14:00'),
  (2, 2, '10:00', '18:00'), (2, 3, '10:00', '18:00'), (2, 4, '10:00', '18:00'),
  (2, 5, '10:00', '18:00'), (2, 6, '10:00', '18:00');
