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
