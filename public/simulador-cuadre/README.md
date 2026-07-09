# Simulador de Cuadre

Programa frontend (HTML + CSS + JS) para simular y cuadrar movimientos entre:

| Tabla | Rol |
|-------|-----|
| **CXC_PES** | Factura / CxC (VAL_NET, impuestos, PTIMPORT2, BALANCE) |
| **TAJUSTE** | Cabecera del ajuste (PTIMPORT = Σ detalle) |
| **TAJUDET** | Líneas del ajuste (editables) |
| **Remesas** | Pago vinculado por NMCUOREC |

## Cómo abrirlo

Con el servidor Laravel:

```bash
php artisan serve --host=0.0.0.0 --port=8000
```

Luego: [http://127.0.0.1:8000/simulador-cuadre/](http://127.0.0.1:8000/simulador-cuadre/)

O abre `index.html` directamente en el navegador (no requiere backend).

## Reglas de cuadre

1. `Σ TAJUDET.PTIMPORT` → `TAJUSTE.PTIMPORT`
2. `TAJUSTE.PTIMPORT` → `CXC_PES.PTIMPORT2`
3. `FACTURA $ = VAL_NET − DES_NET + IMPT_NET`
4. `BALANCE = FACTURA $ − PTIMPORT2`
5. Remesa enlazada por `NMCUOREC = FACTURA`

En el Excel de referencia, `PTIMPORT2` va en negativo `(82,745.98)`, por eso el balance sube:
`22,099,978 − (−82,745.98) = 22,182,723.98`.

La suma de las líneas visibles de la captura da `81,123.51` (descuadre vs el total amarillo); usa **Auto-cuadrar** o el escenario «Cuadrar a (82,745.98)» para cerrarlo.

## Uso rápido

- Edita montos en **TAJUDET** (o VAL_NET / impuestos / remesa) y mira el impacto en cadena.
- **Auto-cuadrar**: elige objetivo (p. ej. `82,745.98`) y la línea a ajustar.
- Escenarios precargados a la izquierda (captura, cuadrado, sin ajuste, remesa, descuadre).
- Exporta / importa JSON para guardar un escenario.
