@props(['text'])

@php
    // El texto viene de la BD: se escapa PRIMERO (seguridad XSS) y despues se
    // resaltan solo patrones tecnicos curados envolviendolos en <strong>.
    // Mejora el escaneo visual rapido del cliente sin tocar el contenido guardado.
    $safe = e($text);

    // 1) Codigos de guia/maquina y sus secuencias: #4->#2->#1, #0.5-#1, #0.5 a #2, #0
    //    Se pintan en dorado: leen como "ficha tecnica" del corte.
    $safe = preg_replace_callback(
        '/#\d+(?:[.,]\d+)?(?:\s*(?:[–\-\x{2192}>]+|a)\s*#?\d+(?:[.,]\d+)?)*/u',
        fn ($m) => '<strong class="font-bold text-gold">'.$m[0].'</strong>',
        $safe
    );

    // 2) Terminos de tecnica/operacion (frase mas larga primero para no partirla).
    //    Se pintan en blanco intenso: destacan sobre el gris del cuerpo.
    $terms = '/navaja de precisi[oó]n|navaja recta|tijeras en movimiento cruzado|'
        .'movimiento cruzado|point-cutting|toalla caliente|alto mantenimiento|'
        .'m[ií]nimo mantenimiento|bajo mantenimiento|alisado t[eé]rmico|'
        .'digitopresi[oó]n|queratina l[ií]quida|taper suave|skin fade|degradado|'
        .'queratina|navaja/iu';
    $safe = preg_replace_callback(
        $terms,
        fn ($m) => '<strong class="font-semibold text-ink/90">'.$m[0].'</strong>',
        $safe
    );
@endphp

<p {{ $attributes }}>{!! $safe !!}</p>
