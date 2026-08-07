<?php

require_once __DIR__ . '/../models/Cotizacion.php';
require_once __DIR__ . '/../helpers/funciones.php';

/**
 * Controlador del modulo de cotizaciones.
 */
class CotizacionController
{
    /** Listado con buscador y filtros. */
    public function index(): void
    {
        $filtros = $this->filtrosDesdeGet();

        $this->render('cotizaciones/index', [
            'titulo'       => 'Cotizaciones',
            'cotizaciones' => Cotizacion::listar($filtros),
            'resumen'      => Cotizacion::resumen($filtros),
            'filtros'      => $filtros,
            // Si hay algo filtrado, el panel arranca abierto.
            'hayFiltros'   => $filtros['q'] !== '' || $filtros['fecha_desde'] !== ''
                              || $filtros['fecha_hasta'] !== '' || $filtros['estado'] !== ''
                              || $filtros['moneda'] !== '',
        ]);
    }

    /** Formulario de nueva cotizacion. */
    public function crear(): void
    {
        $this->render('cotizaciones/formulario', [
            'titulo'        => 'Nueva cotizacion',
            'cotizacion'    => null,
            'numero'        => Cotizacion::siguienteNumero(),
            'empresaConfig' => configEmpresa(),
            'ganancias'     => PricingCalculator::GANANCIAS_PERMITIDAS,
            'error'         => $_GET['error'] ?? null,
        ]);
    }

    /** Formulario de edicion, con la cotizacion precargada. */
    public function editar(): void
    {
        $cotizacion = Cotizacion::obtener((int) ($_GET['id'] ?? 0));

        if ($cotizacion === null) {
            http_response_code(404);
            exit('Cotizacion no encontrada.');
        }

        $this->render('cotizaciones/formulario', [
            'titulo'        => 'Editar cotizacion N° ' . $cotizacion['numero'],
            'cotizacion'    => $cotizacion,
            'numero'        => $cotizacion['numero'],
            'empresaConfig' => configEmpresa(),
            'ganancias'     => PricingCalculator::GANANCIAS_PERMITIDAS,
            'error'         => $_GET['error'] ?? null,
        ]);
    }

    /** Formulario de alta suelto, sin layout, para el modal del listado. */
    public function crearFragmento(): void
    {
        header('Content-Type: text/html; charset=UTF-8');

        $titulo        = '';
        $cotizacion    = null;
        $numero        = Cotizacion::siguienteNumero();
        $empresaConfig = configEmpresa();
        $ganancias     = PricingCalculator::GANANCIAS_PERMITIDAS;
        $error         = null;
        $enModal       = true;

        require __DIR__ . '/../views/cotizaciones/formulario.php';
    }

    /**
     * Formulario de edicion suelto, sin layout, para el modal del listado.
     *
     * Reusa la misma vista que la pantalla completa: si divergieran, un
     * campo agregado en una faltaria en la otra.
     */
    public function editarFragmento(): void
    {
        $cotizacion = Cotizacion::obtener((int) ($_GET['id'] ?? 0));

        if ($cotizacion === null) {
            http_response_code(404);
            exit('<p class="aviso aviso-error">Cotización no encontrada.</p>');
        }

        header('Content-Type: text/html; charset=UTF-8');

        $titulo        = '';
        $numero        = $cotizacion['numero'];
        $empresaConfig = configEmpresa();
        $ganancias     = PricingCalculator::GANANCIAS_PERMITIDAS;
        $error         = null;
        // Lo lee la vista: dentro del modal, Cancelar cierra en vez de navegar.
        $enModal       = true;

        require __DIR__ . '/../views/cotizaciones/formulario.php';
    }

    /** Procesa el POST de la edicion. */
    public function actualizar(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            redirigir(url());
        }

        validarCsrf();

        $id = (int) ($_POST['id'] ?? 0);

        if (Cotizacion::obtener($id) === null) {
            http_response_code(404);
            exit('Cotizacion no encontrada.');
        }

        $items = $this->itemsDesdePost($_POST['items'] ?? []);

        if ($items === []) {
            redirigir(url('editar', ['id' => $id, 'error' => 'Agrega al menos un item con descripcion.']));
        }

        if (trim((string) ($_POST['empresa'] ?? '')) === '') {
            redirigir(url('editar', ['id' => $id, 'error' => 'La empresa del cliente es obligatoria.']));
        }

        try {
            Cotizacion::actualizar($id, $_POST, $items);
        } catch (Throwable $e) {
            redirigir(url('editar', ['id' => $id, 'error' => 'No se pudo actualizar: ' . $e->getMessage()]));
        }

        redirigir(url('ver', ['id' => $id, 'ok' => 1]));
    }

    /** Procesa el POST del formulario. */
    public function guardar(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            redirigir(url('crear'));
        }

        validarCsrf();

        $items = $this->itemsDesdePost($_POST['items'] ?? []);

        if ($items === []) {
            redirigir(url('crear', ['error' => 'Agrega al menos un item con descripcion.']));
        }

        if (trim((string) ($_POST['empresa'] ?? '')) === '') {
            redirigir(url('crear', ['error' => 'La empresa del cliente es obligatoria.']));
        }

        try {
            $id = Cotizacion::crear($_POST, $items);
        } catch (Throwable $e) {
            redirigir(url('crear', ['error' => 'No se pudo guardar: ' . $e->getMessage()]));
        }

        redirigir(url('ver', ['id' => $id, 'ok' => 1]));
    }

    /**
     * Filtros de busqueda tal como llegan por la url.
     * Compartido por el listado y la exportacion, para que el CSV
     * contenga exactamente lo que se esta viendo en pantalla.
     */
    private function filtrosDesdeGet(): array
    {
        return [
            'q'           => trim((string) ($_GET['q'] ?? '')),
            'fecha_desde' => (string) ($_GET['fecha_desde'] ?? ''),
            'fecha_hasta' => (string) ($_GET['fecha_hasta'] ?? ''),
            'estado'      => (string) ($_GET['estado'] ?? ''),
            'moneda'      => (string) ($_GET['moneda'] ?? ''),
            'orden'       => (string) ($_GET['orden'] ?? ''),
            'dir'         => (string) ($_GET['dir'] ?? 'desc'),
        ];
    }

    /**
     * Descarga el listado en un CSV que Excel abre directamente.
     *
     * Respeta los filtros activos: lo que se ve en pantalla es lo que se
     * exporta. Se genera CSV y no xlsx a proposito: un xlsx real exige una
     * libreria pesada, y este archivo Excel lo abre igual.
     */
    public function exportar(): void
    {
        $filtros = $this->filtrosDesdeGet();
        $filas   = Cotizacion::listar($filtros);

        $nombre = 'cotizaciones-' . date('Y-m-d') . '.csv';

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $nombre . '"');
        header('Cache-Control: no-store');

        $salida = fopen('php://output', 'w');

        // BOM: sin esto Excel abre el archivo como ANSI y rompe las tildes.
        fwrite($salida, "\xEF\xBB\xBF");

        // Punto y coma: es el separador que espera Excel en configuracion
        // regional espanola. Con coma, todo cae en una sola columna.
        $sep = ';';

        fputcsv($salida, [
            'N°', 'Empresa', 'RUC', 'Dirección', 'Emisión', 'Válida hasta',
            'Forma de pago', 'Días crédito', 'Entrega (días)', 'Moneda', 'Ítems',
            'Subtotal', 'IGV', 'Total', 'Estado', 'Emisor', 'RUC emisor',
        ], $sep);

        $empresa = configEmpresa();

        foreach ($filas as $f) {
            $vence = date(
                'd/m/Y',
                strtotime((string) $f['fecha_emision'] . ' +' . (int) $f['validez_dias'] . ' days')
            );

            $formaPago = ucfirst((string) $f['forma_pago']);

            fputcsv($salida, [
                $f['numero'],
                $f['razon_social'],
                $f['ruc'] ?? '',
                $f['direccion'] ?? '',
                date('d/m/Y', strtotime((string) $f['fecha_emision'])),
                $vence,
                $formaPago,
                $f['forma_pago'] === 'credito' ? (int) $f['credito_dias'] : '',
                $f['tiempo_entrega_dias'] ? (int) $f['tiempo_entrega_dias'] : '',
                $f['moneda'],
                (int) $f['items'],
                $this->decimalExcel($f['cliente_subtotal']),
                $this->decimalExcel($f['cliente_igv']),
                $this->decimalExcel($f['cliente_total']),
                ucfirst((string) $f['estado']),
                $f['emisor_razon_social'] ?: $empresa['razon_social'],
                $f['emisor_ruc'] ?: $empresa['ruc'],
            ], $sep);
        }

        fclose($salida);
        exit;
    }

    /**
     * Numero con coma decimal y sin separador de miles.
     *
     * Excel en espanol espera la coma: con punto interpreta 9610.00 como
     * nueve millones y pico, o lo deja como texto.
     */
    private function decimalExcel($valor): string
    {
        return number_format((float) $valor, 2, ',', '');
    }

    /**
     * Fragmento HTML del detalle, para el modal del listado.
     *
     * Devuelve solo el trozo, sin layout: se carga por fetch cuando el
     * usuario abre el modal. Asi el listado no arrastra los items de todas
     * las cotizaciones en cada carga.
     */
    public function detalle(): void
    {
        $cotizacion = Cotizacion::obtener((int) ($_GET['id'] ?? 0));

        if ($cotizacion === null) {
            http_response_code(404);
            exit('<p class="aviso aviso-error">Cotización no encontrada.</p>');
        }

        header('Content-Type: text/html; charset=UTF-8');
        require __DIR__ . '/../views/cotizaciones/_detalle.php';
    }

    /** Detalle con el desglose completo. */
    public function ver(): void
    {
        $cotizacion = Cotizacion::obtener((int) ($_GET['id'] ?? 0));

        if ($cotizacion === null) {
            http_response_code(404);
            exit('Cotizacion no encontrada.');
        }

        $this->render('cotizaciones/ver', [
            'titulo'     => 'Cotizacion N° ' . $cotizacion['numero'],
            'cotizacion' => $cotizacion,
            'guardada'   => isset($_GET['ok']),
        ]);
    }

    /** Descarga del PDF. */
    public function pdf(): void
    {
        $cotizacion = Cotizacion::obtener((int) ($_GET['id'] ?? 0));

        if ($cotizacion === null) {
            http_response_code(404);
            exit('Cotizacion no encontrada.');
        }

        // generar_pdf.php se encarga de dompdf y del stream de salida.
        $descargar = isset($_GET['descargar']);
        require_once __DIR__ . '/../pdf/generar_pdf.php';
        generarPdfCotizacion($cotizacion, $descargar);
    }

    /** Baja. */
    public function eliminar(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            validarCsrf();
            Cotizacion::eliminar((int) ($_POST['id'] ?? 0));
        }

        redirigir(url());
    }

    /**
     * Normaliza los items que llegan del formulario.
     * Descarta filas totalmente vacias (el usuario pudo agregar y no llenar).
     */
    private function itemsDesdePost($crudos): array
    {
        if (!is_array($crudos)) {
            return [];
        }

        $items = [];

        foreach ($crudos as $fila) {
            if (!is_array($fila)) {
                continue;
            }

            $descripcion = trim((string) ($fila['descripcion'] ?? ''));

            // Sin descripcion la linea no significa nada: se ignora.
            if ($descripcion === '') {
                continue;
            }

            $items[] = [
                'codigo'              => $fila['codigo'] ?? null,
                'marca'               => $fila['marca'] ?? null,
                'descripcion'         => $descripcion,
                'cantidad'            => $fila['cantidad'] ?? 1,
                'precio'              => $fila['precio'] ?? 0,
                'licencia_so'         => $fila['licencia_so'] ?? 0,
                'delivery'            => $fila['delivery'] ?? 0,
                'embalaje'            => $fila['embalaje'] ?? 0,
                'envio'               => $fila['envio'] ?? 0,
                // Los checkbox no marcados simplemente no viajan en el POST.
                'aplica_detraccion'   => !empty($fila['aplica_detraccion']),
                'aplica_retencion'    => !empty($fila['aplica_retencion']),
                'porcentaje_ganancia' => $fila['porcentaje_ganancia'] ?? 0.10,
            ];
        }

        return $items;
    }

    /** Render de vista dentro del layout. */
    private function render(string $vista, array $datos = []): void
    {
        extract($datos, EXTR_SKIP);

        $rutaVista = __DIR__ . '/../views/' . $vista . '.php';

        ob_start();
        require $rutaVista;
        $contenido = ob_get_clean();

        require __DIR__ . '/../views/layout.php';
    }
}
