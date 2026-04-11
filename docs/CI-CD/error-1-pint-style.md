# Error 1 - Pint: Problemas de estilo de codigo

## Flujo de trabajo afectado

- **Archivo:** `.github/workflows/php.yml`
- **Job:** `lint`
- **Paso fallido:** `Run Pint (style check)`

## Descripcion del error

El job `lint` del workflow **PHP** ejecuta `./vendor/bin/pint --test` para verificar que el codigo sigue el estandar de estilo de Laravel. El flag `--test` hace que Pint analice el codigo sin modificarlo y devuelva un error si encuentra violaciones.

La ejecucion fallo con la siguiente salida:

```
FAIL   239 files, 23 style issues

x app/Http/Controllers/Api/AppointmentController.php   unary_operator_spaces, braces_position, phpdoc_separation, no_unused_imports
x app/Http/Controllers/Api/AuthController.php          unary_operator_spaces, phpdoc_separation, not_operator_with_successor_space
x app/Http/Controllers/Api/CatalogController.php       single_blank_line_at_eof
x app/Http/Controllers/Api/ClientController.php        unary_operator_spaces, not_operator_with_successor_space, single_blank_line_at_eof
x app/Http/Controllers/Api/DashboardController.php     unary_operator_spaces, braces_position, not_operator_with_successor_space
x app/Http/Controllers/Api/InventoryController.php     unary_operator_spaces, braces_position, phpdoc_separation, not_operator_with_successor_space
x app/Http/Controllers/Api/PaymentController.php       unary_operator_spaces, braces_position, not_operator_with_successor_space
x app/Http/Controllers/Api/UserController.php          unary_operator_spaces, not_operator_with_successor_space, ordered_imports
x app/Http/Controllers/ChatbotController.php           unary_operator_spaces, braces_position, not_operator_with_successor_space
x app/Http/Middleware/AuthenticateMobileApiToken.php   unary_operator_spaces, not_operator_with_successor_space, single_blank_line_at_eof
x app/Models/MobileApiToken.php                        single_blank_line_at_eof
x app/Services/BusinessEventService.php                single_blank_line_at_eof
x app/Services/DashboardService.php                    fully_qualified_strict_types, unary_operator_spaces, not_operator_with_successor_space
x bootstrap/app.php                                    ordered_imports
x config/chatbot.php                                   single_blank_line_at_eof
x database/migrations/2026_04_01_000000_create_mobile_api_tokens_table.php   class_definition, braces_position, single_blank_line_at_eof
x database/seeders/TestUsersSeeder.php                 fully_qualified_strict_types, concat_space, trailing_comma_in_multiline
x routes/api.php                                       ordered_imports, single_blank_line_at_eof
x tests/Feature/Api/MobileApiAuthTest.php              single_blank_line_at_eof
x tests/Feature/Api/MobileApiBookingTest.php           single_blank_line_at_eof
x tests/Feature/Api/MobileApiParityAccessTest.php      no_unused_imports
x tests/Feature/Chatbot/ChatbotProductionHardeningTest.php   braces_position
x tests/Feature/Observability/BusinessEventLoggingTest.php   braces_position, no_unused_imports, ordered_imports

Process completed with exit code 1.
```

## Causa raiz

Los archivos listados fueron creados o modificados sin ejecutar el formateador de codigo `pint` antes de hacer commit. Pint aplica las reglas de estilo de Laravel (basadas en PSR-12 con algunas personalizaciones), y cualquier desviacion es reportada como error en el CI.

### Reglas violadas con mayor frecuencia

| Regla | Descripcion |
|---|---|
| `unary_operator_spaces` | Debe haber un espacio entre el operador unario (`!`, `-`, `++`, etc.) y el operando |
| `braces_position` | Las llaves de apertura de clases y funciones deben seguir la posicion correcta segun PSR-12 |
| `single_blank_line_at_eof` | Cada archivo debe terminar con exactamente una linea en blanco |
| `ordered_imports` | Las sentencias `use` deben estar ordenadas alfabeticamente |
| `no_unused_imports` | No deben existir sentencias `use` que no se utilicen en el archivo |
| `phpdoc_separation` | Los bloques PHPDoc deben estar separados del codigo correctamente |
| `fully_qualified_strict_types` | Los tipos en `declare(strict_types=1)` deben estar completamente cualificados |
| `not_operator_with_successor_space` | Debe haber un espacio despues del operador `!` |
| `concat_space` | Debe haber un espacio a ambos lados del operador de concatenacion `.` |
| `trailing_comma_in_multiline` | Los arrays y llamadas a funciones multilinea deben terminar con una coma |

## Solucion aplicada

Se ejecuto `pint` en modo de correccion automatica (sin `--test`) para que reformatee los 23 archivos afectados:

```bash
./vendor/bin/pint
```

Pint corrigio los 23 archivos automaticamente. La verificacion posterior confirmo el exito:

```
PASS   239 files
```

## Prevencion futura

Para evitar que este error vuelva a ocurrir, los desarrolladores deben ejecutar Pint localmente antes de hacer commit:

```bash
# Verificar sin modificar (igual que el CI)
./vendor/bin/pint --test

# Corregir automaticamente
./vendor/bin/pint
```

Se recomienda agregar Pint como hook de pre-commit o configurar el editor (VS Code, PhpStorm) para que lo ejecute automaticamente al guardar.

## Referencia al CI

- Workflow: [PHP](https://github.com/KikeGonRam/barber/actions/workflows/php.yml)
- Run de ejemplo con el error: [Run #24276032893](https://github.com/KikeGonRam/barber/actions/runs/24276032893/job/70890028852)
