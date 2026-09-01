-- Trigger: WHEN-BUTTON-PRESSED (cambio masivo forma de pago a CHEQUE)
--
-- FRM-41048 You cannot create records here:
--   1) NEXT_RECORD en la ULTIMA fila intenta INSERTAR una fila nueva.
--      Si el bloque tiene Insert Allowed = No, Forms lanza 41048.
--      Hay que salir con EXIT WHEN :SYSTEM.LAST_RECORD = 'TRUE'.
--   2) COMMIT_FORM intenta grabar bloques del form (incluye el LOG si tiene
--      una fila NEW). El INSERT del log ya se hizo por SQL; usar FORMS_DDL('COMMIT').
--   3) GO_BLOCK al log vacio (sin query) tambien intenta crear fila si
--      Insert Allowed = No. Ir al log y hacer EXECUTE_QUERY.

DECLARE
  v_coment VARCHAR2(2500) := 'CAMBIO MASIVO DE FORMA DE PAGO A CHEQUE '||TO_CHAR(SYSDATE, 'DD/MM/YYYY');
  v_n      NUMBER;
BEGIN
  GO_BLOCK('CARGA_RECLAMOS_MASIVOS');
  FIRST_RECORD;

  IF :SYSTEM.RECORD_STATUS = 'NEW' THEN
    MESSAGE('No hay registros de carga para procesar.');
    SYNCHRONIZE;
    RETURN;
  END IF;

  LOOP
    BEGIN
      IF :CARGA_RECLAMOS_MASIVOS.SECUENCIAL IS NOT NULL THEN
        UPDATE RECLAMACION
           SET FOR_PAG   = 'C',
               FEC_CIE   = SYSDATE,
               USA_U_ACT = USER
         WHERE ESTATUS IN (50, 51, 55, 536)
           AND ANO        = :CARGA_RECLAMOS_MASIVOS.ANO
           AND COMPANIA   = :CARGA_RECLAMOS_MASIVOS.COMPANIA
           AND RAMO       = :CARGA_RECLAMOS_MASIVOS.RAMO
           AND SECUENCIAL = :CARGA_RECLAMOS_MASIVOS.SECUENCIAL;

        v_n := SQL%ROWCOUNT;

        IF v_n > 0 THEN
          INSERT INTO AUTORIZACIONES_RECLAMOS_LOG (
            AUTORIZACION,
            DESCRIPCION,
            ESTATUS_DESC,
            USUARIO_CREACION,
            FECHA_CREACION
          ) VALUES (
            :CARGA_RECLAMOS_MASIVOS.ANO||'-'||:CARGA_RECLAMOS_MASIVOS.COMPANIA||'-'||:CARGA_RECLAMOS_MASIVOS.RAMO||'-'||:CARGA_RECLAMOS_MASIVOS.SECUENCIAL,
            v_coment,
            'EXITOSO',
            USER,
            SYSDATE
          );
        ELSE
          INSERT INTO AUTORIZACIONES_RECLAMOS_LOG (
            AUTORIZACION,
            DESCRIPCION,
            ESTATUS_DESC,
            USUARIO_CREACION,
            FECHA_CREACION
          ) VALUES (
            :CARGA_RECLAMOS_MASIVOS.ANO||'-'||:CARGA_RECLAMOS_MASIVOS.COMPANIA||'-'||:CARGA_RECLAMOS_MASIVOS.RAMO||'-'||:CARGA_RECLAMOS_MASIVOS.SECUENCIAL,
            'ESTATUS NO PERMITIDO PARA CAMBIO DE FORMA DE PAGO',
            'FALLO',
            USER,
            SYSDATE
          );
        END IF;
      END IF;
    EXCEPTION
      WHEN OTHERS THEN
        INSERT INTO AUTORIZACIONES_RECLAMOS_LOG (
          AUTORIZACION,
          DESCRIPCION,
          ESTATUS_DESC,
          USUARIO_CREACION,
          FECHA_CREACION
        ) VALUES (
          :CARGA_RECLAMOS_MASIVOS.ANO||'-'||:CARGA_RECLAMOS_MASIVOS.COMPANIA||'-'||:CARGA_RECLAMOS_MASIVOS.RAMO||'-'||:CARGA_RECLAMOS_MASIVOS.SECUENCIAL,
          'ERROR INTENTANDO CAMBIO A CHEQUE: '||SUBSTR(SQLERRM, 1, 200),
          'FALLO',
          USER,
          SYSDATE
        );
    END;

    EXIT WHEN :SYSTEM.LAST_RECORD = 'TRUE';
    NEXT_RECORD;
  END LOOP;

  FORMS_DDL('COMMIT');

  GO_BLOCK('AUTORIZACIONES_RECLAMOS_LOG');
  EXECUTE_QUERY;
  SYNCHRONIZE;
EXCEPTION
  WHEN OTHERS THEN
    MESSAGE('Error general: '||SQLERRM);
    SYNCHRONIZE;
END;
