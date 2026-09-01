-- Pegar al FINAL del proceso que inserta/carga la reclamacion
-- (WHEN-BUTTON-PRESSED de procesar, o el trigger que hace el INSERT),
-- DESPUES de que el registro ya este en la tabla RECLAMACION.
--
-- Si el INSERT se commitea al final del lote, llama esto despues del COMMIT
-- recorriendo las filas PROCESADO, o vuelve a consultar el bloque:
--   GO_BLOCK('CARGA_RECLAMOS_MASIVOS');
--   EXECUTE_QUERY;

BEGIN
  -- Opcion A: la fila actual de carga ya tiene ANO/COMPANIA/RAMO/SECUENCIAL
  PR_CARGAR_INFO_RECLAMACION;
  PR_PINTAR_ESTATUS_REGISTRO;

  -- Opcion B: si procesaste un lote y quieres refrescar todas las filas
  -- visibles, descomenta:
  --
  -- GO_BLOCK('CARGA_RECLAMOS_MASIVOS');
  -- EXECUTE_QUERY;
END;
