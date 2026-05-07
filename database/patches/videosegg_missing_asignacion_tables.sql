-- Parche para dumps videosegg donde faltó el DDL de estas tablas (solo aparecían
-- comentarios + LOCK TABLES → error #1146 "Tabla no existe").
-- Insertar este bloque ANTES del primer bloque `-- Table structure for table `asignacion_t``
-- o importar este archivo primero sobre la base `videosegg` y luego el dump completo.
-- Carpeta típica del dump: ~/Documents/videosegg/

DROP TABLE IF EXISTS `asignacion_t`;
CREATE TABLE `asignacion_t` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `asignar_video_a_t`;
CREATE TABLE `asignar_video_a_t` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
