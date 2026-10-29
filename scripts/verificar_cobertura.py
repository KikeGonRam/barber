#!/usr/bin/env python3
"""Falla si la cobertura de sentencias del reporte clover baja del umbral (HT-09 / TT24-TT25).

Uso: python scripts/verificar_cobertura.py [coverage/coverage.xml]
El umbral se toma de la variable COVERAGE_MIN (porcentaje, 70 por defecto). Súbelo cuando se agreguen pruebas;
no lo bajes para que pase el CI.
"""
import os
import re
import sys

ruta = sys.argv[1] if len(sys.argv) > 1 else "coverage/coverage.xml"
minimo = float(os.environ.get("COVERAGE_MIN", "70"))

try:
    with open(ruta, encoding="utf-8") as f:
        xml = f.read()
except OSError as e:
    sys.exit(f"No se pudo leer {ruta}: {e}")

# El <metrics> del proyecto es el único que lleva el atributo files= (los de cada archivo no).
proyecto = [m for m in re.findall(r"<metrics ([^>]*?)/>", xml) if "files=" in m]
if not proyecto:
    sys.exit(f"{ruta} no tiene las métricas del proyecto: no se puede medir la cobertura.")

datos = dict(re.findall(r"(\w+)=\"(\d+)\"", proyecto[-1]))
total = int(datos.get("statements", 0))
cubiertas = int(datos.get("coveredstatements", 0))
if total == 0:
    sys.exit("El reporte no contiene sentencias: no se puede medir la cobertura.")

porcentaje = 100 * cubiertas / total
print(f"Cobertura de sentencias: {porcentaje:.1f} % ({cubiertas} de {total}). Umbral: {minimo:.1f} %.")
if porcentaje < minimo:
    sys.exit(f"La cobertura ({porcentaje:.1f} %) está por debajo del umbral ({minimo:.1f} %).")
