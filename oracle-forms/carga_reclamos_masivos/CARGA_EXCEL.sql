-- WHEN-BUTTON-PRESSED: carga Excel a CARGA_RECLAMOS_MASIVOS
-- Fila 1 = encabezado. Datos desde fila 2.
-- A=ANO  B=COMPANIA  C=RAMO  D=SECUENCIAL

DECLARE
   l_application  CLIENT_OLE2.OBJ_TYPE;
   l_workbooks    CLIENT_OLE2.OBJ_TYPE;
   l_workbook     CLIENT_OLE2.OBJ_TYPE;
   l_worksheets   CLIENT_OLE2.OBJ_TYPE;
   l_worksheet    CLIENT_OLE2.OBJ_TYPE;
   l_used_range   CLIENT_OLE2.OBJ_TYPE;
   l_rows         CLIENT_OLE2.OBJ_TYPE;
   l_args         CLIENT_OLE2.LIST_TYPE;

   l_file         VARCHAR2(1024);
   l_ext          VARCHAR2(10);
   l_dot_pos      NUMBER;

   l_row          NUMBER;
   l_ultima_fila  NUMBER := 0;
   l_insertados   NUMBER := 0;
   l_omitidos     NUMBER := 0;
   l_first_row    NUMBER;
   l_row_count    NUMBER;

   l_ano          VARCHAR2(20);
   l_compania     VARCHAR2(20);
   l_ramo         VARCHAR2(20);
   l_secuencial   VARCHAR2(50);

   vLoteId        NUMBER;

   FUNCTION get_cell_value (
      p_worksheet IN CLIENT_OLE2.OBJ_TYPE,
      p_row       IN NUMBER,
      p_col       IN NUMBER
   ) RETURN VARCHAR2 IS
      l_c   CLIENT_OLE2.OBJ_TYPE;
      l_a   CLIENT_OLE2.LIST_TYPE;
      l_val VARCHAR2(4000);
   BEGIN
      l_a := CLIENT_OLE2.CREATE_ARGLIST;
      CLIENT_OLE2.ADD_ARG(l_a, p_row);
      CLIENT_OLE2.ADD_ARG(l_a, p_col);

      l_c := CLIENT_OLE2.GET_OBJ_PROPERTY(p_worksheet, 'Cells', l_a);
      CLIENT_OLE2.DESTROY_ARGLIST(l_a);

      l_val := CLIENT_OLE2.GET_CHAR_PROPERTY(l_c, 'Text');
      CLIENT_OLE2.RELEASE_OBJ(l_c);

      RETURN TRIM(l_val);
   EXCEPTION
      WHEN OTHERS THEN
         BEGIN CLIENT_OLE2.DESTROY_ARGLIST(l_a); EXCEPTION WHEN OTHERS THEN NULL; END;
         BEGIN CLIENT_OLE2.RELEASE_OBJ(l_c);     EXCEPTION WHEN OTHERS THEN NULL; END;
         RETURN NULL;
   END;

   PROCEDURE liberar_excel IS
   BEGIN
      BEGIN
         l_args := CLIENT_OLE2.CREATE_ARGLIST;
         CLIENT_OLE2.ADD_ARG(l_args, 0);
         CLIENT_OLE2.INVOKE(l_workbook, 'Close', l_args);
         CLIENT_OLE2.DESTROY_ARGLIST(l_args);
      EXCEPTION
         WHEN OTHERS THEN NULL;
      END;

      BEGIN
         CLIENT_OLE2.INVOKE(l_application, 'Quit');
      EXCEPTION
         WHEN OTHERS THEN NULL;
      END;

      BEGIN CLIENT_OLE2.RELEASE_OBJ(l_worksheet);   EXCEPTION WHEN OTHERS THEN NULL; END;
      BEGIN CLIENT_OLE2.RELEASE_OBJ(l_worksheets);  EXCEPTION WHEN OTHERS THEN NULL; END;
      BEGIN CLIENT_OLE2.RELEASE_OBJ(l_workbook);    EXCEPTION WHEN OTHERS THEN NULL; END;
      BEGIN CLIENT_OLE2.RELEASE_OBJ(l_workbooks);   EXCEPTION WHEN OTHERS THEN NULL; END;
      BEGIN CLIENT_OLE2.RELEASE_OBJ(l_application); EXCEPTION WHEN OTHERS THEN NULL; END;
   END;

BEGIN
   SELECT NVL(MAX(LOTE_ID), 0) + 1
     INTO vLoteId
     FROM CARGA_RECLAMOS_MASIVOS;

   l_file := CLIENT_GET_FILE_NAME(
                'C:\',
                '',
                'Archivos Excel (*.xls;*.xlsx)|*.xls;*.xlsx|',
                NULL,
                OPEN_FILE,
                TRUE);

   IF l_file IS NULL THEN
      MESSAGE('Carga cancelada.');
      SYNCHRONIZE;
      RETURN;
   END IF;

   l_dot_pos := INSTR(l_file, '.', -1);
   l_ext     := UPPER(SUBSTR(l_file, l_dot_pos + 1));

   IF l_ext NOT IN ('XLS', 'XLSX') THEN
      MESSAGE('El archivo debe ser Excel.');
      SYNCHRONIZE;
      RETURN;
   END IF;

   l_application := CLIENT_OLE2.CREATE_OBJ('Excel.Application');
   CLIENT_OLE2.SET_PROPERTY(l_application, 'Visible', 0);
   CLIENT_OLE2.SET_PROPERTY(l_application, 'DisplayAlerts', 0);

   l_workbooks := CLIENT_OLE2.GET_OBJ_PROPERTY(l_application, 'Workbooks');

   l_args := CLIENT_OLE2.CREATE_ARGLIST;
   CLIENT_OLE2.ADD_ARG(l_args, l_file);
   l_workbook := CLIENT_OLE2.INVOKE_OBJ(l_workbooks, 'Open', l_args);
   CLIENT_OLE2.DESTROY_ARGLIST(l_args);

   l_worksheets := CLIENT_OLE2.GET_OBJ_PROPERTY(l_workbook, 'Worksheets');

   l_args := CLIENT_OLE2.CREATE_ARGLIST;
   CLIENT_OLE2.ADD_ARG(l_args, 1);
   l_worksheet := CLIENT_OLE2.GET_OBJ_PROPERTY(l_worksheets, 'Item', l_args);
   CLIENT_OLE2.DESTROY_ARGLIST(l_args);

   l_used_range := CLIENT_OLE2.GET_OBJ_PROPERTY(l_worksheet, 'UsedRange');
   l_first_row  := CLIENT_OLE2.GET_NUM_PROPERTY(l_used_range, 'Row');
   l_rows       := CLIENT_OLE2.GET_OBJ_PROPERTY(l_used_range, 'Rows');
   l_row_count  := CLIENT_OLE2.GET_NUM_PROPERTY(l_rows, 'Count');
   l_ultima_fila := l_first_row + l_row_count - 1;

   CLIENT_OLE2.RELEASE_OBJ(l_rows);
   CLIENT_OLE2.RELEASE_OBJ(l_used_range);

   -- Siempre datos desde la fila 2 (fila 1 = encabezado)
   l_row := 2;

   WHILE l_row <= l_ultima_fila LOOP
      l_ano        := get_cell_value(l_worksheet, l_row, 1);
      l_compania   := get_cell_value(l_worksheet, l_row, 2);
      l_ramo       := get_cell_value(l_worksheet, l_row, 3);
      l_secuencial := get_cell_value(l_worksheet, l_row, 4);

      IF l_ano IS NULL
         AND l_compania IS NULL
         AND l_ramo IS NULL
         AND l_secuencial IS NULL
      THEN
         EXIT;
      END IF;

      IF l_ano IS NULL
         OR l_compania IS NULL
         OR l_ramo IS NULL
         OR l_secuencial IS NULL
      THEN
         l_omitidos := l_omitidos + 1;
      ELSE
         INSERT INTO CARGA_RECLAMOS_MASIVOS (
            ANO,
            COMPANIA,
            RAMO,
            SECUENCIAL,
            ESTATUS_REGISTRO,
            VER,
            LOTE_ID
         ) VALUES (
            l_ano,
            l_compania,
            l_ramo,
            l_secuencial,
            'PENDIENTE_A_PROCESAR',
            'S',
            vLoteId
         );
         l_insertados := l_insertados + 1;
      END IF;

      l_row := l_row + 1;
   END LOOP;

   liberar_excel;

   FORMS_DDL('COMMIT');

   GO_BLOCK('CARGA_RECLAMOS_MASIVOS');
   SET_BLOCK_PROPERTY('CARGA_RECLAMOS_MASIVOS', DEFAULT_WHERE, 'LOTE_ID = '||TO_CHAR(vLoteId));
   EXECUTE_QUERY;

   MESSAGE('Carga completada. Insertados: '||l_insertados||'. Omitidos: '||l_omitidos);
   SYNCHRONIZE;

EXCEPTION
   WHEN OTHERS THEN
      liberar_excel;
      MESSAGE('Error al cargar Excel: '||SQLERRM);
      SYNCHRONIZE;
END;
