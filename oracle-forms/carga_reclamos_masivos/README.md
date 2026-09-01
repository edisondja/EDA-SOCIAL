# CARGA_RECLAMOS_MASIVOS — POST-QUERY

Oracle Forms: el `POST-QUERY` del bloque `CARGA_RECLAMOS_MASIVOS` se dispara
**al traer cada fila de la carga**, no cuando ya existe la reclamación.

Por eso `ESTATUS_RECLAMACION_DESC` y `FORMA_PAGO` no deben exigirse ahí.
Se consultan solo si ya hay llave completa, y la misma lógica se vuelve a
llamar **después** de insertar/cargar la reclamación.

## Dónde pegar cada pieza

| Pieza | Ubicación en el form |
|---|---|
| `PR_PINTAR_ESTATUS_REGISTRO` | Program Unit |
| `PR_CARGAR_INFO_RECLAMACION` | Program Unit |
| Trigger `POST-QUERY` | Block `CARGA_RECLAMOS_MASIVOS` (no PRE-QUERY, no form-level) |
| Llamada extra a `PR_CARGAR_INFO_RECLAMACION` | Al final del proceso que crea/carga la reclamación (después del `INSERT`/`COMMIT`) |

## Items de display

`ESTATUS_RECLAMACION_DESC` y `FORMA_PAGO` deben ser **Database Item = No**.
Si son items de tabla, asignarlos en POST-QUERY marca el registro como
cambiado y Forms pedirá guardar filas que no se editaron.

## FRM-41048 You cannot create records here

El loop del boton de cambio masivo no puede hacer `NEXT_RECORD` en la
ultima fila (eso intenta crear registro). Ver `CAMBIO_MASIVO_FORMA_PAGO.sql`.
Tampoco usar `COMMIT_FORM` ni `GO_BLOCK` al log vacio.

## FRM-41050 You cannot update this record

No usar `:BLOQUE.ITEM := valor` en POST-QUERY si la fila tiene
Update Allowed = No. Llenar con `COPY` y dejar el registro en
`QUERY_STATUS` (ver `POST_QUERY_INLINE.sql`).
