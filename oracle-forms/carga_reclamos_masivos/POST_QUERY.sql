-- Trigger: POST-QUERY
-- Block:   CARGA_RECLAMOS_MASIVOS
--
-- Este trigger corre al hacer EXECUTE_QUERY de la CARGA, fila por fila.
-- En ese momento la reclamacion PUEDE NO EXISTIR todavia.
-- Por eso aqui solo se pinta el estatus de carga, y se intenta la
-- reclamacion sin abortar si no esta. La llamada definitiva es
-- PR_CARGAR_INFO_RECLAMACION despues de crear/cargar la reclamacion.

BEGIN
  PR_PINTAR_ESTATUS_REGISTRO;

  -- POST-QUERY no aplica a registros NEW; igual se protege.
  IF :SYSTEM.RECORD_STATUS = 'NEW' THEN
    RETURN;
  END IF;

  PR_CARGAR_INFO_RECLAMACION;
END;
