#!/usr/bin/env python3
"""Falla si la cobertura de sentencias del reporte clover baja del umbral (HT-09 / TT24-TT25).

Uso (desde la raíz del repositorio, después de generar coverage/coverage.xml): python scripts/verificar_cobertura.py
El umbral se toma de la variable COVERAGE_MIN (porcentaje, 70 por defecto). Súbelo cuando se agreguen pruebas;
no lo bajes para que pase el CI. La ruta del reporte es fija a propósito: no se acepta por argumento.

El reporte lo genera PHPUnit en el mismo job; se lee como texto (sin parser XML ni expresiones regulares) porque
solo hace falta un par de atributos.
"""
import os
import sys

REPORTE = "coverage/coverage.xml"
minimo = float(os.environ.get("COVERAGE_MIN", "70"))


def atributo(etiqueta: str, nombre: str) -> int:
    """Valor entero de nombre="..." dentro de una etiqueta <metrics ...>."""
    marca = f' {nombre}="'
    inicio = etiqueta.find(marca)
    if inicio < 0:
        return 0
    inicio += len(marca)
    return int(etiqueta[inicio:etiqueta.find('"', inicio)])


try:
    with open(REPORTE, encoding="utf-8") as f:
        texto = f.read()
except OSError as e:
    sys.exit(f"No se pudo leer {REPORTE}: {e}")

# El <metrics> del proyecto es el único que lleva el atributo files= (los de cada archivo no).
etiquetas = [t.split("/>", 1)[0] for t in texto.split("<metrics ")[1:]]
proyecto = [t for t in etiquetas if ' files="' in f" {t}"]
if not proyecto:
    sys.exit(f"{REPORTE} no tiene las métricas del proyecto: no se puede medir la cobertura.")

total = atributo(" " + proyecto[-1], "statements")
cubiertas = atributo(" " + proyecto[-1], "coveredstatements")
if total == 0:
    sys.exit("El reporte no contiene sentencias: no se puede medir la cobertura.")

porcentaje = 100 * cubiertas / total
print(f"Cobertura de sentencias: {porcentaje:.1f} % ({cubiertas} de {total}). Umbral: {minimo:.1f} %.")
if porcentaje < minimo:
    sys.exit(f"La cobertura ({porcentaje:.1f} %) está por debajo del umbral ({minimo:.1f} %).")
