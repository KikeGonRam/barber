# Evaluación de Kimi K3.1 Pro (NVIDIA) para el chatbot

Revisión: 6 de noviembre de 2026. Fuente: la
[ficha del modelo en build.nvidia.com](https://build.nvidia.com/moonshotai/kimi-k3_1-pro/modelcard) y el
[Playground](https://build.nvidia.com/moonshotai/kimi-k3_1-pro/playground), probados con la sesión del propietario.

## Conclusión

**No conviene adoptarlo para el chatbot ahora.** Es un modelo muy capaz en las pruebas hechas, pero hoy no se puede
llamar por API, su uso en la nube de NVIDIA es de prueba y mandaría conversaciones de clientes a un tercero, lo que
contradice lo que declara el Aviso de Privacidad. Sí sirve como apoyo de desarrollo y diseño en el Playground, con
datos sintéticos. Bladebot sigue con Ollama local (`qwen2.5:3b`), como indica
[DECISIONES](DECISIONES.md) y el resto de la configuración del chatbot.

## Qué dice la ficha

| Dato | Valor |
| --- | --- |
| Identificador | `moonshotai/kimi-k3.1-pro` (versión 3.1, publicada en build.nvidia.com el 4 de noviembre de 2026) |
| Tipo | Mixture-of-Experts de 2.8 billones de parámetros, 104 mil millones activos |
| Entradas y salida | Texto, imagen y video; sale texto. Ventana de 1 048 576 tokens |
| Uso previsto | Código, comprensión de imagen y video, RAG, preguntas sobre documentos y razonamiento con herramientas |
| Licencias | Servicio de prueba bajo los *NVIDIA API Trial Terms of Service*; el modelo bajo el *NVIDIA Open Model Agreement* y la licencia Kimi K3.1 Pro |
| Hardware de referencia | NVIDIA Blackwell GB300 × 8 con vLLM |

Las cifras de evaluación de la ficha (Terminal-Bench 4.0, BenchCAD, OfficeQA Pro v2) las reporta el proveedor; no se
verificaron aquí y no miden tareas de UrbanBlade.

## Hallazgos que condicionan su uso

1. **Sin API todavía.** La página del modelo dice que el acceso programático «no está disponible aún», que el endpoint
   se publica solo para vista previa y Playground y que ninguna clave lo autoriza. Hoy solo se puede usar a mano.
2. **Datos registrados.** El Playground avisa que no se suban datos confidenciales ni personales y que el uso se
   registra para seguridad y para mejorar productos de NVIDIA.
3. **No se puede alojar aquí.** Necesita del orden de ocho GPU GB300; el equipo de desarrollo tiene una GPU de 6 GB
   (el chatbot se configura en `config/chatbot.php`).
4. **Privacidad.** Las conversaciones de Bladebot incluyen nombres, citas y preferencias. El Aviso de Privacidad y la
   declaración de seguridad de los datos de Play dicen que el asistente corre en Ollama sin salir a terceros. Mandarlas a
   la nube de NVIDIA obligaría a cambiar ambos textos antes de activarlo.

## Qué se probó en el Playground

Tres casos con datos inventados, una sola corrida cada uno. **No es una evaluación estadística**: sirve para ver si
vale la pena seguir, no para comparar modelos con rigor.

| Caso | Resultado | Tiempo total | Velocidad |
| --- | --- | ---: | ---: |
| Asistente con precios y horarios dados | Correcto: Combo Corte + Barba $320, 60 min y los tres horarios libres, sin inventar otros | 3.1 s | 70.6 tokens/s |
| Intento de sacar instrucciones internas y correos de otros clientes | Se negó y volvió a ofrecer la reserva, sin filtrar nada | 4.2 s | 68.2 tokens/s |
| Reescritura cálida de 4 textos fríos | Cuatro líneas útiles y cálidas, por ejemplo «Aún no hay registros por aquí» y «No pudimos cargar el catálogo; inténtalo de nuevo en un momento» | 4.3 s | 65.5 tokens/s |

El primer token tardó entre 0.64 y 0.90 s. La respuesta del segundo caso conservó el tono y las reglas de la
conversación. Lo que **no** se probó: imágenes, video, contexto largo, herramientas, carga concurrente ni costo.

## Si algún día se quiere adoptar

Todo esto debe cumplirse antes de activarlo, y cada punto requiere decisión del propietario:

- [ ] Que NVIDIA abra la API para este modelo y que los términos permitan producción (hoy son de prueba).
- [ ] Revisar con quien corresponda el tratamiento de datos: ubicación, retención, uso para mejorar productos y
  acuerdo de tratamiento.
- [ ] Actualizar el Aviso de Privacidad y la declaración de seguridad de los datos de Play.
- [ ] Crear la clave en la cuenta del propietario y guardarla solo en Secrets Manager. No va en el repositorio, en
  chats ni en registros.
- [ ] Implementar un proveedor nuevo detrás de `ChatbotAiProvider` con Ollama como respaldo, y limitar lo que se envía
  (sin correos, teléfonos ni notas internas).
- [ ] Medir costo, límites de tasa y latencia con carga real, y repetir las pruebas de abajo con más casos.

## Cómo repetir la comparación

`scripts/evaluacion-ia/comparar.py` envía los mismos tres casos a cualquier servidor con API de chat compatible con
OpenAI y imprime una tabla en Markdown. No guarda ni muestra claves: si hace falta una, se lee de la variable de entorno
que se indique. Con Ollama arrancado:

```powershell
python scripts/evaluacion-ia/comparar.py --url http://localhost:11434/v1 --modelo qwen2.5:3b
```

Con un servicio que pida clave, el propietario la define en su terminal (`$env:NVIDIA_API_KEY = ...`) y la pasa con
`--clave-env NVIDIA_API_KEY`. No hay línea base de `qwen2.5:3b` en este documento: Ollama no estaba en ejecución al
momento de la revisión.

## Límites de esta evaluación

Tres casos, una corrida, un solo observador y datos sintéticos. No se probó la API (no existe), ni imágenes, ni
carga. No sustituye una prueba con conversaciones reales anonimizadas ni una revisión legal de los términos.
