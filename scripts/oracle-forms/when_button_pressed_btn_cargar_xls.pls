/*
================================================================================
  WHEN-BUTTON-PRESSED — ejemplo para botón BTN_CARGAR_XLS
  Pegar este código en el trigger del botón (después de crear los Program Units).
================================================================================
*/
BEGIN
  cargar_xls_mediprocesos_hn(TRUE); -- TRUE = el Excel tiene fila de encabezados
EXCEPTION
  WHEN OTHERS THEN
    MESSAGE('No se pudo iniciar la carga: ' || SQLERRM);
    SYNCHRONIZE;
END;
