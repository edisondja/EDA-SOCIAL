/*
================================================================================
  Oracle Forms 12c — Carga Excel (.xls / .xlsx) → bloque MEDIPROCESOS_HN
================================================================================
  Requisitos:
    - WebUtil (WEBUTIL.PLL / webutil.olb) adjunto al form
    - Microsoft Excel en la PC del cliente (CLIENT_OLE2)
    - Bloque: MEDIPROCESOS_HN

  Mapeo de columnas (fila 1 = encabezados; datos desde fila 2):

    A  SINIESTRO_EXPEDIENT
    B  ANO
    C  COMPANIA
    D  RAMO
    E  SECUENCIAL
    F  VALOR_A_PAGAR
    G  DEDUCIBLE
    H  TOTAL_PRESTADO
    I  NO_CUBIERTO
    J  ESTATUS_REG
    K  COMENTARIO

  Instalación:
    1. Crear Program Unit FUNCTION  get_cell_value  (código abajo)
    2. Crear Program Unit PROCEDURE cargar_xls_mediprocesos_hn (código abajo)
    3. En un botón, trigger WHEN-BUTTON-PRESSED:
         BEGIN
           cargar_xls_mediprocesos_hn(TRUE);
         END;
================================================================================
*/


/*==============================================================================
  Program Unit name : GET_CELL_VALUE
  Type              : Function
  Return            : VARCHAR2
==============================================================================*/
FUNCTION get_cell_value (
  p_worksheet IN CLIENT_OLE2.OBJ_TYPE,
  p_row       IN NUMBER,
  p_col       IN NUMBER
) RETURN VARCHAR2 IS
  l_cell  CLIENT_OLE2.OBJ_TYPE;
  l_args  CLIENT_OLE2.LIST_TYPE;
  l_value VARCHAR2(4000);
BEGIN
  l_args := CLIENT_OLE2.CREATE_ARGLIST;
  CLIENT_OLE2.ADD_ARG(l_args, p_row);
  CLIENT_OLE2.ADD_ARG(l_args, p_col);
  l_cell  := CLIENT_OLE2.GET_OBJ_PROPERTY(p_worksheet, 'Cells', l_args);
  CLIENT_OLE2.DESTROY_ARGLIST(l_args);

  l_value := CLIENT_OLE2.GET_CHAR_PROPERTY(l_cell, 'Text');
  CLIENT_OLE2.RELEASE_OBJ(l_cell);

  RETURN TRIM(l_value);
EXCEPTION
  WHEN OTHERS THEN
    BEGIN
      CLIENT_OLE2.DESTROY_ARGLIST(l_args);
    EXCEPTION
      WHEN OTHERS THEN NULL;
    END;
    BEGIN
      CLIENT_OLE2.RELEASE_OBJ(l_cell);
    EXCEPTION
      WHEN OTHERS THEN NULL;
    END;
    RETURN NULL;
END;


/*==============================================================================
  Program Unit name : CARGAR_XLS_MEDIPROCESOS_HN
  Type              : Procedure
  Parámetro         : p_tiene_encabezado (TRUE = salta fila 1)
==============================================================================*/
PROCEDURE cargar_xls_mediprocesos_hn (
  p_tiene_encabezado IN BOOLEAN DEFAULT TRUE
) IS
  l_application  CLIENT_OLE2.OBJ_TYPE;
  l_workbooks    CLIENT_OLE2.OBJ_TYPE;
  l_workbook     CLIENT_OLE2.OBJ_TYPE;
  l_worksheets   CLIENT_OLE2.OBJ_TYPE;
  l_worksheet    CLIENT_OLE2.OBJ_TYPE;
  l_used_range   CLIENT_OLE2.OBJ_TYPE;
  l_rows         CLIENT_OLE2.OBJ_TYPE;
  l_args         CLIENT_OLE2.LIST_TYPE;

  l_file         VARCHAR2(1024);
  l_dot_pos      NUMBER;
  l_ext          VARCHAR2(10);

  l_row          NUMBER;
  l_primera_fila NUMBER;
  l_ultima_fila  NUMBER := 0;
  l_insertados   NUMBER := 0;
  l_omitidos     NUMBER := 0;
  l_hay_datos_bloque BOOLEAN;

  l_siniestro    VARCHAR2(4000);
  l_ano          VARCHAR2(4000);
  l_compania     VARCHAR2(4000);
  l_ramo         VARCHAR2(4000);
  l_secuencial   VARCHAR2(4000);
  l_valor_pagar  VARCHAR2(4000);
  l_deducible    VARCHAR2(4000);
  l_total_prest  VARCHAR2(4000);
  l_no_cubierto  VARCHAR2(4000);
  l_estatus      VARCHAR2(4000);
  l_comentario   VARCHAR2(4000);

  l_fila_vacia   BOOLEAN;

  PROCEDURE liberar_ole IS
  BEGIN
    BEGIN
      CLIENT_OLE2.INVOKE(l_workbook, 'Close');
    EXCEPTION
      WHEN OTHERS THEN NULL;
    END;
    BEGIN
      CLIENT_OLE2.INVOKE(l_application, 'Quit');
    EXCEPTION
      WHEN OTHERS THEN NULL;
    END;
    BEGIN CLIENT_OLE2.RELEASE_OBJ(l_rows);        EXCEPTION WHEN OTHERS THEN NULL; END;
    BEGIN CLIENT_OLE2.RELEASE_OBJ(l_used_range);  EXCEPTION WHEN OTHERS THEN NULL; END;
    BEGIN CLIENT_OLE2.RELEASE_OBJ(l_worksheet);   EXCEPTION WHEN OTHERS THEN NULL; END;
    BEGIN CLIENT_OLE2.RELEASE_OBJ(l_worksheets);  EXCEPTION WHEN OTHERS THEN NULL; END;
    BEGIN CLIENT_OLE2.RELEASE_OBJ(l_workbook);    EXCEPTION WHEN OTHERS THEN NULL; END;
    BEGIN CLIENT_OLE2.RELEASE_OBJ(l_workbooks);   EXCEPTION WHEN OTHERS THEN NULL; END;
    BEGIN CLIENT_OLE2.RELEASE_OBJ(l_application); EXCEPTION WHEN OTHERS THEN NULL; END;
  END;

  FUNCTION to_num_safe (p_txt IN VARCHAR2) RETURN NUMBER IS
    l_txt VARCHAR2(100);
  BEGIN
    IF p_txt IS NULL THEN
      RETURN NULL;
    END IF;
    l_txt := REPLACE(REPLACE(TRIM(p_txt), ',', '.'), ' ', '');
    RETURN TO_NUMBER(l_txt);
  EXCEPTION
    WHEN OTHERS THEN
      RETURN NULL;
  END;

BEGIN
  /* 1) Diálogo de archivo en el cliente */
  l_file := CLIENT_GET_FILE_NAME(
              'C:\',
              '',
              'Archivos Excel (*.xls;*.xlsx)|*.xls;*.xlsx|Todos (*.*)|*.*|',
              NULL,
              OPEN_FILE,
              TRUE
            );

  IF l_file IS NULL THEN
    MESSAGE('Carga cancelada: no se seleccionó archivo.');
    SYNCHRONIZE;
    RETURN;
  END IF;

  l_dot_pos := INSTR(l_file, '.', -1);
  IF l_dot_pos = 0 THEN
    MESSAGE('Nombre de archivo sin extensión.');
    SYNCHRONIZE;
    RETURN;
  END IF;

  l_ext := UPPER(SUBSTR(l_file, l_dot_pos + 1));
  IF l_ext NOT IN ('XLS', 'XLSX') THEN
    MESSAGE('El archivo debe ser .xls o .xlsx');
    SYNCHRONIZE;
    RETURN;
  END IF;

  /* 2) Abrir Excel */
  l_application := CLIENT_OLE2.CREATE_OBJ('Excel.Application');
  CLIENT_OLE2.SET_PROPERTY(l_application, 'Visible', FALSE);
  CLIENT_OLE2.SET_PROPERTY(l_application, 'DisplayAlerts', FALSE);

  l_workbooks := CLIENT_OLE2.GET_OBJ_PROPERTY(l_application, 'Workbooks');

  l_args := CLIENT_OLE2.CREATE_ARGLIST;
  CLIENT_OLE2.ADD_ARG(l_args, l_file);
  l_workbook := CLIENT_OLE2.INVOKE_OBJ(l_workbooks, 'Open', l_args);
  CLIENT_OLE2.DESTROY_ARGLIST(l_args);

  l_worksheets := CLIENT_OLE2.GET_OBJ_PROPERTY(l_workbook, 'Worksheets');

  l_args := CLIENT_OLE2.CREATE_ARGLIST;
  CLIENT_OLE2.ADD_ARG(l_args, 1); /* primera hoja */
  l_worksheet := CLIENT_OLE2.GET_OBJ_PROPERTY(l_worksheets, 'Item', l_args);
  CLIENT_OLE2.DESTROY_ARGLIST(l_args);

  /* 3) Última fila absoluta = UsedRange.Row + Rows.Count - 1 */
  DECLARE
    l_first_row NUMBER;
    l_row_count NUMBER;
  BEGIN
    l_used_range := CLIENT_OLE2.GET_OBJ_PROPERTY(l_worksheet, 'UsedRange');
    l_first_row  := CLIENT_OLE2.GET_NUM_PROPERTY(l_used_range, 'Row');
    l_rows       := CLIENT_OLE2.GET_OBJ_PROPERTY(l_used_range, 'Rows');
    l_row_count  := CLIENT_OLE2.GET_NUM_PROPERTY(l_rows, 'Count');
    l_ultima_fila := NVL(l_first_row, 1) + NVL(l_row_count, 0) - 1;
    CLIENT_OLE2.RELEASE_OBJ(l_rows);
    CLIENT_OLE2.RELEASE_OBJ(l_used_range);
  EXCEPTION
    WHEN OTHERS THEN
      l_ultima_fila := 5000;
  END;

  IF p_tiene_encabezado THEN
    l_primera_fila := 2;
  ELSE
    l_primera_fila := 1;
  END IF;

  IF NVL(l_ultima_fila, 0) < l_primera_fila THEN
    MESSAGE('El Excel no contiene filas de datos.');
    SYNCHRONIZE;
    liberar_ole;
    RETURN;
  END IF;

  /* 4) Insertar en el bloque */
  GO_BLOCK('MEDIPROCESOS_HN');
  FIRST_RECORD;

  l_row := l_primera_fila;
  WHILE l_row <= l_ultima_fila LOOP
    l_siniestro   := get_cell_value(l_worksheet, l_row, 1);
    l_ano         := get_cell_value(l_worksheet, l_row, 2);
    l_compania    := get_cell_value(l_worksheet, l_row, 3);
    l_ramo        := get_cell_value(l_worksheet, l_row, 4);
    l_secuencial  := get_cell_value(l_worksheet, l_row, 5);
    l_valor_pagar := get_cell_value(l_worksheet, l_row, 6);
    l_deducible   := get_cell_value(l_worksheet, l_row, 7);
    l_total_prest := get_cell_value(l_worksheet, l_row, 8);
    l_no_cubierto := get_cell_value(l_worksheet, l_row, 9);
    l_estatus     := get_cell_value(l_worksheet, l_row, 10);
    l_comentario  := get_cell_value(l_worksheet, l_row, 11);

    l_fila_vacia := (l_siniestro IS NULL
                 AND l_ano IS NULL
                 AND l_compania IS NULL
                 AND l_ramo IS NULL
                 AND l_secuencial IS NULL
                 AND l_valor_pagar IS NULL
                 AND l_deducible IS NULL
                 AND l_total_prest IS NULL
                 AND l_no_cubierto IS NULL
                 AND l_estatus IS NULL
                 AND l_comentario IS NULL);

    IF l_fila_vacia THEN
      IF l_insertados > 0 THEN
        /* fin de datos */
        EXIT;
      ELSE
        l_omitidos := l_omitidos + 1;
        l_row := l_row + 1;
      END IF;
    ELSE
      /* ¿El registro actual del bloque ya tiene datos? */
      l_hay_datos_bloque := NOT (
           :MEDIPROCESOS_HN.SINIESTRO_EXPEDIENT IS NULL
       AND :MEDIPROCESOS_HN.ANO IS NULL
       AND :MEDIPROCESOS_HN.COMPANIA IS NULL
       AND :MEDIPROCESOS_HN.RAMO IS NULL
       AND :MEDIPROCESOS_HN.SECUENCIAL IS NULL
      );

      IF l_insertados > 0 OR l_hay_datos_bloque THEN
        CREATE_RECORD;
      END IF;

      :MEDIPROCESOS_HN.SINIESTRO_EXPEDIENT := l_siniestro;
      :MEDIPROCESOS_HN.ANO                 := l_ano;
      :MEDIPROCESOS_HN.COMPANIA            := l_compania;
      :MEDIPROCESOS_HN.RAMO                := l_ramo;
      :MEDIPROCESOS_HN.SECUENCIAL          := l_secuencial;
      :MEDIPROCESOS_HN.VALOR_A_PAGAR       := to_num_safe(l_valor_pagar);
      :MEDIPROCESOS_HN.DEDUCIBLE           := to_num_safe(l_deducible);
      :MEDIPROCESOS_HN.TOTAL_PRESTADO      := to_num_safe(l_total_prest);
      :MEDIPROCESOS_HN.NO_CUBIERTO         := to_num_safe(l_no_cubierto);
      :MEDIPROCESOS_HN.ESTATUS_REG         := l_estatus;
      :MEDIPROCESOS_HN.COMENTARIO          := l_comentario;

      l_insertados := l_insertados + 1;
      l_row := l_row + 1;
    END IF;
  END LOOP;

  MESSAGE('Carga finalizada. Insertados: ' || TO_CHAR(l_insertados));
  SYNCHRONIZE;

  liberar_ole;

EXCEPTION
  WHEN OTHERS THEN
    MESSAGE('Error al cargar Excel: ' || SQLERRM);
    SYNCHRONIZE;
    liberar_ole;
END;
