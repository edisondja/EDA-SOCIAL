-- Trigger: POST-QUERY  (version todo-en-uno, si no quieres Program Units)
-- Block:   CARGA_RECLAMOS_MASIVOS
--
-- Pegar esto en el POST-QUERY del bloque. NO va en PRE-QUERY.
-- PRE-QUERY corre antes de que existan filas; ahi este codigo falla.
--
-- Importante: ESTATUS_RECLAMACION_DESC y FORMA_PAGO = Database Item No.

DECLARE
  V_ESTATUS  NUMBER;
  V_FORMA_P  VARCHAR2(50);
  V_DESC     VARCHAR2(200);
BEGIN
  -------------------------------------------------------------------------
  -- 1) Color del estatus de CARGA (siempre, si hay valor)
  -------------------------------------------------------------------------
  IF :CARGA_RECLAMOS_MASIVOS.ESTATUS_REGISTRO = 'PROCESADO' THEN
    SET_ITEM_INSTANCE_PROPERTY('CARGA_RECLAMOS_MASIVOS.ESTATUS_REGISTRO',
                               CURRENT_RECORD,
                               VISUAL_ATTRIBUTE,
                               'ESTATUS_COLOR_EXITO');
  ELSIF :CARGA_RECLAMOS_MASIVOS.ESTATUS_REGISTRO IS NOT NULL THEN
    SET_ITEM_INSTANCE_PROPERTY('CARGA_RECLAMOS_MASIVOS.ESTATUS_REGISTRO',
                               CURRENT_RECORD,
                               VISUAL_ATTRIBUTE,
                               'ESTATUS_COLOR_ERROR');
  END IF;

  :CARGA_RECLAMOS_MASIVOS.ESTATUS_RECLAMACION_DESC := NULL;
  :CARGA_RECLAMOS_MASIVOS.FORMA_PAGO               := NULL;

  -------------------------------------------------------------------------
  -- 2) Reclamacion: solo si la fila ya tiene llave.
  --    Si todavia no existe en BD, NO se aborta el query (NO_DATA_FOUND).
  -------------------------------------------------------------------------
  IF :CARGA_RECLAMOS_MASIVOS.ANO        IS NULL
  OR :CARGA_RECLAMOS_MASIVOS.COMPANIA   IS NULL
  OR :CARGA_RECLAMOS_MASIVOS.RAMO       IS NULL
  OR :CARGA_RECLAMOS_MASIVOS.SECUENCIAL IS NULL THEN
    RETURN;
  END IF;

  BEGIN
    SELECT R.ESTATUS,
           R.FOR_PAG
      INTO V_ESTATUS,
           V_FORMA_P
      FROM RECLAMACION R
     WHERE R.ANO        = :CARGA_RECLAMOS_MASIVOS.ANO
       AND R.COMPANIA   = :CARGA_RECLAMOS_MASIVOS.COMPANIA
       AND R.RAMO       = :CARGA_RECLAMOS_MASIVOS.RAMO
       AND R.SECUENCIAL = :CARGA_RECLAMOS_MASIVOS.SECUENCIAL;
  EXCEPTION
    WHEN NO_DATA_FOUND THEN
      IF :CARGA_RECLAMOS_MASIVOS.ESTATUS_REGISTRO = 'PROCESADO' THEN
        :CARGA_RECLAMOS_MASIVOS.ESTATUS_RECLAMACION_DESC := 'NO EXISTE RECLAMACION!';
      END IF;
      RETURN;
    WHEN TOO_MANY_ROWS THEN
      :CARGA_RECLAMOS_MASIVOS.ESTATUS_RECLAMACION_DESC := 'RECLAMACION DUPLICADA!';
      RETURN;
  END;

  BEGIN
    SELECT E.DESCRIPCION
      INTO V_DESC
      FROM ESTATUS E
     WHERE E.TIPO   = 'RECLAMACION'
       AND E.CODIGO = V_ESTATUS;

    :CARGA_RECLAMOS_MASIVOS.ESTATUS_RECLAMACION_DESC := V_DESC;
  EXCEPTION
    WHEN NO_DATA_FOUND THEN
      :CARGA_RECLAMOS_MASIVOS.ESTATUS_RECLAMACION_DESC := 'ESTATUS ' || NVL(TO_CHAR(V_ESTATUS), '?');
    WHEN TOO_MANY_ROWS THEN
      :CARGA_RECLAMOS_MASIVOS.ESTATUS_RECLAMACION_DESC := 'ESTATUS ' || TO_CHAR(V_ESTATUS);
  END;

  IF V_FORMA_P IS NOT NULL THEN
    IF V_FORMA_P = 'C' THEN
      :CARGA_RECLAMOS_MASIVOS.FORMA_PAGO := 'CHEQUE';
    ELSE
      :CARGA_RECLAMOS_MASIVOS.FORMA_PAGO := 'TRANSFERENCIA';
    END IF;
  END IF;
END;
