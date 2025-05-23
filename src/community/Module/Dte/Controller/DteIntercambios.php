<?php

/**
 * LibreDTE: Aplicación Web - Edición Comunidad.
 * Copyright (C) LibreDTE <https://www.libredte.cl>
 *
 * Este programa es software libre: usted puede redistribuirlo y/o
 * modificarlo bajo los términos de la Licencia Pública General Affero
 * de GNU publicada por la Fundación para el Software Libre, ya sea la
 * versión 3 de la Licencia, o (a su elección) cualquier versión
 * posterior de la misma.
 *
 * Este programa se distribuye con la esperanza de que sea útil, pero
 * SIN GARANTÍA ALGUNA; ni siquiera la garantía implícita
 * MERCANTIL o de APTITUD PARA UN PROPÓSITO DETERMINADO.
 * Consulte los detalles de la Licencia Pública General Affero de GNU
 * para obtener una información más detallada.
 *
 * Debería haber recibido una copia de la Licencia Pública General
 * Affero de GNU junto a este programa.
 * En caso contrario, consulte <http://www.gnu.org/licenses/agpl.html>.
 */

namespace website\Dte;

use sowerphp\core\Network_Request as Request;

/**
 * Controlador para intercambio entre contribuyentes.
 */
class Controller_DteIntercambios extends \sowerphp\autoload\Controller
{

    /**
     * Acción para mostrar la bandeja de intercambio de DTE.
     */
    public function listar(Request $request, $pagina = 1, $soloPendientes = false)
    {
        // Obtener contribuyente que se está utilizando en la sesión.
        try {
            $Emisor = libredte()->getSessionContribuyente();
        } catch (\Exception $e) {
            return libredte()->redirectContribuyenteSeleccionar($e);
        }
        // Validar $pagina.
        if (!is_numeric($pagina)) {
            return redirect('/dte/'.$this->request->getRouteConfig()['controller'].'/listar');
        }
        // Filtros.
        $filtros = [
            'soloPendientes' => $soloPendientes,
            'p' => $pagina,
        ];
        // Buscar documentos.
        if (isset($_GET['search'])) {
            foreach (explode(',', $_GET['search']) as $filtro) {
                list($var, $val) = explode(':', $filtro);
                $filtros[$var] = $val;
            }
        }
        $searchUrl = isset($_GET['search']) ? ('?search=' . $_GET['search']) : '';
        $paginas = 1;
        try {
            $documentos_total = $Emisor->countDocumentosIntercambios($filtros);
            if (!empty($pagina)) {
                $filtros['limit'] = config('app.ui.pagination.registers');
                $filtros['offset'] = ($pagina - 1) * $filtros['limit'];
                $paginas = $documentos_total ? ceil($documentos_total/$filtros['limit']) : 0;
                if ($pagina != 1 && $pagina > $paginas) {
                    return redirect('/dte/'.$this->request->getRouteConfig()['controller'].'/listar'.$searchUrl);
                }
            }
            $documentos = $Emisor->getDocumentosIntercambios($filtros);
        } catch (\Exception $e) {
            \sowerphp\core\Facade_Session_Message::error(
                'Error al recuperar los documentos:<br/>'.$e->getMessage()
            );
            $documentos_total = 0;
            $documentos = [];
        }
        $this->set([
            'Emisor' => $Emisor,
            'documentos' => $documentos,
            'documentos_total' => $documentos_total,
            'paginas' => ceil($documentos_total / config('app.ui.pagination.registers')),
            'pagina' => $pagina,
            'search' => $filtros,
            'soloPendientes' => $soloPendientes,
            'searchUrl' => $searchUrl,
            'ultimo_codigo' => (new Model_DteIntercambios())->setContribuyente($Emisor)->getUltimoCodigo(),
        ]);
    }

    /**
     * Acción que muestra la página de un intercambio.
     */
    public function ver(Request $request, $codigo)
    {
        $user = $request->user();
        // Obtener contribuyente que se está utilizando en la sesión.
        try {
            $Emisor = libredte()->getSessionContribuyente();
        } catch (\Exception $e) {
            return libredte()->redirectContribuyenteSeleccionar($e);
        }
        // obtener DTE intercambiado
        $DteIntercambio = new Model_DteIntercambio(
            $Emisor->rut, (int)$codigo, $Emisor->enCertificacion()
        );
        if (!$DteIntercambio->exists()) {
            return redirect('/dte/dte_intercambios/listar')
                ->withError(
                    __('No existe el intercambio solicitado.')
                );
        }
        // obtener firma
        $Firma = $Emisor->getFirma($user->id);
        if (!$Firma) {
            return redirect('/dte/admin/firma_electronicas/agregar')
                ->withError(
                    __('No existe una firma electrónica asociada a la empresa que se pueda utilizar para usar esta opción, ya que se requiere consultar el estado del DTE al SII para poder ver el intercambio. Antes de intentarlo nuevamente, debe [subir una firma electrónica vigente](%(url)s).',
                        [
                            'url' => url('/dte/admin/firma_electronicas/agregar')
                        ]
                    )
                );
        }
        // asignar variables para la vista
        $this->set([
            'Emisor' => $Emisor,
            'DteIntercambio' => $DteIntercambio,
            'email_asunto' => $DteIntercambio->getEmailAsunto(),
            'email_txt' => $DteIntercambio->getEmailTxt(),
            'email_html' => $DteIntercambio->getEmailHtml(),
            'EnvioDte' => $DteIntercambio->getEnvioDte(),
            'Documentos' => $DteIntercambio->getDocumentos(),
            'Firma' => $Firma,
            'test_xml' => $DteIntercambio->testXML(),
        ]);
    }

    /**
     * Acción que permite eliminar un intercambio desde la bandeja.
     */
    public function eliminar(Request $request, ...$pk)
    {
        list($codigo) = $pk;
        // Obtener contribuyente que se está utilizando en la sesión.
        try {
            $Emisor = libredte()->getSessionContribuyente();
        } catch (\Exception $e) {
            return libredte()->redirectContribuyenteSeleccionar($e);
        }
        // obtener DTE intercambiado
        $DteIntercambio = new Model_DteIntercambio(
            $Emisor->rut, (int)$codigo, $Emisor->enCertificacion()
        );
        if (!$DteIntercambio->exists()) {
            return redirect('/dte/dte_intercambios/listar')
                ->withError(
                    __('No existe el intercambio solicitado.')
                );
        }
        // verificar que el intercambio no esté en uso en los documentos recibidos
        if ($DteIntercambio->recibido()) {
            return redirect('/dte/dte_intercambios/ver/'.$codigo)
                ->withError(
                    __('El intercambio tiene a lo menos un DTE recibido asociado, no se puede eliminar.')
                );
        }
        // eliminar el intercambio y redireccionar
        $DteIntercambio->delete();
        return redirect('/dte/dte_intercambios/listar')
            ->withSuccess(
                __('Intercambio %(codigo)s eliminado.',
                    [
                        'codigo' => $codigo
                    ]
                )
            );
    }

    /**
     * Acción que muestra el mensaje del email de intercambio.
     */
    public function html($codigo)
    {
        // Obtener contribuyente que se está utilizando en la sesión.
        try {
            $Emisor = libredte()->getSessionContribuyente();
        } catch (\Exception $e) {
            return libredte()->redirectContribuyenteSeleccionar($e);
        }
        $DteIntercambio = new Model_DteIntercambio(
            $Emisor->rut, (int)$codigo, $Emisor->enCertificacion()
        );
        if (!$DteIntercambio->exists()) {
            return redirect('/dte/dte_intercambios/listar')
                ->withError(
                    __('No existe el intercambio solicitado.')
                );
        }
        $html = $DteIntercambio->getEmailHtml();
        return $this->render(null, [
            'html' => $html ? $html : 'No hay mensaje HTML.',
        ]);
    }

    /**
     * Acción para actualizar la bandeja de intercambio. Guarda los DTE
     * recibidos por intercambio y guarda los acuses de recibos de DTE
     * enviados por otros contribuyentes.
     */
    public function actualizar($dias = 7)
    {
        // Obtener contribuyente que se está utilizando en la sesión.
        try {
            $Emisor = libredte()->getSessionContribuyente();
        } catch (\Exception $e) {
            return libredte()->redirectContribuyenteSeleccionar($e);
        }
        // Actualizar bandeja de intercambio.
        try {
            $resultado = $Emisor->actualizarBandejaIntercambio($dias);
        } catch (\Exception $e) {
            if ($e->getCode() == 500) {
                return redirect('/dte/dte_intercambios/listar')
                    ->withError($e->getMessage())
                ;
            } else {
                return redirect('/dte/dte_intercambios/listar')
                    ->withInfo($e->getMessage())
                ;
            }
        }
        extract($resultado);
        if ($n_uids>1) {
            $encontrados = 'Se encontraron '.num($n_uids).' correos.';
        } else {
            $encontrados = 'Se encontró '.num($n_uids).' correo.';
        }
        if (!empty($errores)) {
            return redirect('/dte/dte_intercambios/listar')
                ->withWarning(
                    __('Se encontraron algunos problemas al procesar ciertos correos:<br/>- %(errors)s',
                        [
                            'errors' => implode('<br/>- ',$errores)
                        ]
                    )
                );
        }
        return redirect('/dte/dte_intercambios/listar')
            ->withSuccess(
                __('%(encontrados)s: EnvioDTE=%(envio_dte)s,  EnvioRecibos=%(envio_recibos)s, RecepcionEnvio=%(recepcion_envio)s, ResultadoDTE=%(resultado_dte)s y Omitidos=%(omitidos)s',
                    [
                        'encontrados' => $encontrados,
                        'envio_dte' => num($n_EnvioDTE),
                        'envio_recibos' => num($n_EnvioRecibos),
                        'recepcion_envio' => num($n_RecepcionEnvio),
                        'resultado_dte' => num($n_ResultadoDTE),
                        'omitidos' => num($omitidos)
                    ]
                )
            );
    }

    /**
     * Recurso para mostrar el PDF de un EnvioDTE de un intercambio de DTE.
     */
    public function _api_pdf_GET($codigo, $contribuyente, $cedible = false, $emisor = null, $dte = null, $folio = null)
    {
        // verificar si se pasaron credenciales de un usuario
        $User = $this->Api->getAuthUser();
        if (is_string($User)) {
            $this->Api->send($User, 401);
        }
        // crear contribuyente
        $Receptor = new Model_Contribuyente($contribuyente);
        if (!$Receptor->usuarioAutorizado($User, '/dte/dte_intercambios/pdf')) {
            return response()->json(
                __('No está autorizado a operar con la empresa solicitada.'),
                403
            );
        }
        // obtener DTE intercambiado
        $DteIntercambio = new Model_DteIntercambio($Receptor->rut, $codigo, $Receptor->enCertificacion());
        if (!$DteIntercambio->exists()) {
            return response()->json(
                __('No existe el intercambio solicitado.'),
                404
            );
        }
        // armar configuración del PDF
        extract($this->request->getValidatedData([
            'papelContinuo' => false,
        ]));
        $config = [
            'cedible' => $cedible,
            'documento' => [
                'emisor' => $emisor,
                'dte' => $dte,
                'folio' => $folio,
            ],
        ];
        if (!empty($papelContinuo)) {
            $config['formato'] = 'estandar';
            $config['papelContinuo'] = $papelContinuo;
            $config['extra'] = [
                'continuo' => [
                    'item' => [
                        'detalle' => 1,
                    ],
                ],
            ];
        }
        // obtener PDF
        try {
            $pdf = $DteIntercambio->getPDF($config);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), $e->getCode());
        }
        // entregar PDF
        $disposition = $Receptor->config_pdf_disposition ? 'inline' : 'attachement';
        $ext = ($DteIntercambio->documentos > 1 && empty($folio)) ? 'zip' : 'pdf';
        if ($emisor && $dte && $folio) {
            $file_name = 'LibreDTE_'.$emisor.'_T'.$dte.'F'.$folio.'.'.$ext;
        } else {
            $file_name = 'LibreDTE_'.$Receptor->rut.'_intercambio_'.$codigo.'.'.$ext;
        }
        $this->Api->response()->type('application/'.$ext);
        $this->Api->response()->header('Content-Disposition', $disposition.'; filename="'.$file_name.'"');
        $this->Api->response()->header('Content-Length', strlen($pdf));
        return response()->json($pdf);
    }

    /**
     * Acción para mostrar el PDF de un EnvioDTE de un intercambio de DTE.
     */
    public function pdf($codigo, $cedible = false, $emisor = null, $dte = null, $folio = null)
    {
        // Obtener contribuyente que se está utilizando en la sesión.
        try {
            $Receptor = libredte()->getSessionContribuyente();
        } catch (\Exception $e) {
            return libredte()->redirectContribuyenteSeleccionar($e);
        }
        // Consumir servicio de PDF.
        $get_query = http_build_query($this->request->getValidatedData([
            'papelContinuo' => false,
        ]));
        $url = '/api/dte/dte_intercambios/pdf/'.$codigo.'/'.$Receptor->rut.'/'.(int)$cedible.'/'.(int)$emisor.'/'.(int)$dte.'/'.(int)$folio.'?'.$get_query;
        $response = $this->consume($url);
        if ($response['status']['code'] != 200) {
            return redirect('/dte/dte_intercambios/listar')
                ->withError(
                    __('%(body)s',
                        [
                            'body' => $response['body']
                        ]
                    )
                );
        }
        // si dió código 200 se entrega la respuesta del servicio web
        $this->response->type('application/pdf');
        if (isset($response['header']['Content-Disposition'])) {
            $disposition = $Receptor->config_pdf_disposition ? 'inline' : 'attachement';
            $response['header']['Content-Disposition'] = str_replace(['attachement', 'inline'], $disposition, $response['header']['Content-Disposition']);
        }
        foreach (['Content-Disposition', 'Content-Length'] as $header) {
            if (isset($response['header'][$header])) {
                $this->response->header($header, $response['header'][$header]);
            }
        }
        $this->response->sendAndExit($response['body']);
    }

    /**
     * Recurso que descarga el XML del documento intercambiado.
     */
    public function _api_xml_GET($codigo, $contribuyente)
    {
        // verificar si se pasaron credenciales de un usuario
        $User = $this->Api->getAuthUser();
        if (is_string($User)) {
            $this->Api->send($User, 401);
        }
        // crear contribuyente
        $Receptor = new Model_Contribuyente($contribuyente);
        if (!$Receptor->usuarioAutorizado($User, '/dte/dte_intercambios/xml')) {
            return response()->json(
                __('No está autorizado a operar con la empresa solicitada.'),
                403
            );
        }
        // obtener DTE intercambio
        $DteIntercambio = new Model_DteIntercambio($Receptor->rut, $codigo, $Receptor->enCertificacion());
        if (!$DteIntercambio->exists()) {
            return response()->json(
                __('No existe el intercambio solicitado.'),
                404
            );
        }
        // entregar XML
        $xml = base64_decode($DteIntercambio->archivo_xml);
        $this->Api->response()->type('application/xml', 'ISO-8859-1');
        $this->Api->response()->header('Content-Length', strlen($xml));
        $this->Api->response()->header('Content-Disposition', 'attachement; filename="'.$DteIntercambio->archivo.'"');
        return response()->json($xml);
    }

    /**
     * Acción que descarga el XML del documento intercambiado.
     */
    public function xml($codigo)
    {
        // Obtener contribuyente que se está utilizando en la sesión.
        try {
            $Receptor = libredte()->getSessionContribuyente();
        } catch (\Exception $e) {
            return libredte()->redirectContribuyenteSeleccionar($e);
        }
        // Obtener XML.
        $response = $this->consume('/api/dte/dte_intercambios/xml/'.$codigo.'/'.$Receptor->rut);
        if ($response['status']['code'] != 200) {
            return redirect('/dte/dte_intercambios/listar')
                ->withError(
                    __('%(body)s',
                        [
                            'body' => $response['body']
                        ]
                    )
                );
        }
        // Si dió código 200 se entrega la respuesta del servicio web.
        $this->response->type('application/xml', 'ISO-8859-1');
        foreach (['Content-Disposition', 'Content-Length'] as $header) {
            if (isset($response['header'][$header])) {
                $this->response->header($header, $response['header'][$header]);
            }
        }
        $this->response->sendAndExit($response['body']);
    }

    /**
     * Recurso que entrega los XML del resultado de la revisión del intercambio.
     */
    public function _api_resultados_xml_GET($codigo, $contribuyente)
    {
        // verificar si se pasaron credenciales de un usuario
        $User = $this->Api->getAuthUser();
        if (is_string($User)) {
            $this->Api->send($User, 401);
        }
        // crear contribuyente
        $Emisor = new Model_Contribuyente($contribuyente);
        if (!$Emisor->usuarioAutorizado($User, '/dte/dte_intercambios/resultados_xml')) {
            return response()->json(
                __('No está autorizado a operar con la empresa solicitada.'),
                403
            );
        }
        // obtener DTE intercambio
        $DteIntercambio = new Model_DteIntercambio(
            $Emisor->rut, (int)$codigo, $Emisor->enCertificacion()
        );
        if (!$DteIntercambio->exists()) {
            return response()->json(
                __('No existe el intercambio solicitado.'),
                404
            );
        }
        // si no hay XML error
        if (
            !$DteIntercambio->recepcion_xml
            && !$DteIntercambio->recibos_xml
            && !$DteIntercambio->resultado_xml
        ) {
            return response()->json(
                __('No existen archivos de resultado generados, no se ha procesado aun el intercambio.'),
                400
            );
        }
        // agregar a archivo comprimido y entregar
        $dir = DIR_TMP.'/resultado_intercambio_'.$Emisor->rut.'-'.$Emisor->dv.'_'.$DteIntercambio->codigo;
        if (is_dir($dir)) {
            \sowerphp\general\Utility_File::rmdir($dir);
        }
        if (!mkdir($dir)) {
            return response()->json(
                __('No fue posible crear el directorio temporal para los XML.'),
                507
            );
        }
        if ($DteIntercambio->recepcion_xml) {
            file_put_contents($dir.'/RecepcionDTE.xml', base64_decode($DteIntercambio->recepcion_xml));
        }
        if ($DteIntercambio->recibos_xml) {
            file_put_contents($dir.'/EnvioRecibos.xml', base64_decode($DteIntercambio->recibos_xml));
        }
        if ($DteIntercambio->resultado_xml) {
            file_put_contents($dir.'/ResultadoDTE.xml', base64_decode($DteIntercambio->resultado_xml));
        }
        \sowerphp\general\Utility_File::compress($dir, ['format' => 'zip', 'delete' => true]);
        exit; // TODO: enviar usando $this->Api->send() / File::compress()
    }

    /**
     * Acción que entrega los XML del resultado de la revisión del intercambio.
     */
    public function resultados_xml($codigo)
    {
        // Obtener contribuyente que se está utilizando en la sesión.
        try {
            $Receptor = libredte()->getSessionContribuyente();
        } catch (\Exception $e) {
            return libredte()->redirectContribuyenteSeleccionar($e);
        }
        // Consumir servicio web de XML.
        $response = $this->consume('/api/dte/dte_intercambios/resultados_xml/'.$codigo.'/'.$Receptor->rut);
        if ($response['status']['code'] != 200) {
            if (in_array($response['status']['code'], [401, 403, 404])) {
                return redirect('/dte/dte_intercambios/listar')
                    ->withError(
                        __('%(body)s',
                            [
                                'body' => $response['body']
                            ]
                        )
                    );
            } else {
                return redirect(str_replace('resultados_xml', 'ver', $this->request->getRequestUriDecoded()))
                    ->withError(
                        __('%(body)s',
                            [
                                'body' => $response['body']
                            ]
                        )
                    );
            }
        }
        // si dió código 200 se entrega la respuesta del servicio web
        $this->response->type('application/zip');
        foreach (['Content-Disposition', 'Content-Length'] as $header) {
            if (isset($response['header'][$header])) {
                $this->response->header($header, $response['header'][$header]);
            }
        }
        $this->response->sendAndExit($response['body']);
    }

    /**
     * Acción que procesa y responde al intercambio recibido.
     */
    public function responder(Request $request, $codigo)
    {
        $user = $request->user();
        // Obtener contribuyente que se está utilizando en la sesión.
        try {
            $Receptor = libredte()->getSessionContribuyente();
        } catch (\Exception $e) {
            return libredte()->redirectContribuyenteSeleccionar($e);
        }
        // si no se viene por post error
        if (empty($_POST)) {
            return redirect(str_replace('responder', 'ver', $this->request->getRequestUriDecoded()))
                ->withError(
                    __('No puede acceder de forma directa a %(uri_decoded)s',
                        [
                            'uri_decoded' => $this->request->getRequestUriDecoded()
                        ]
                    )
                );
        }
        // obtener objeto de intercambio
        $DteIntercambio = new Model_DteIntercambio(
            $Receptor->rut,
            (int)$codigo,
            $Receptor->enCertificacion()
        );
        if (!$DteIntercambio->exists()) {
            return redirect('/dte/dte_intercambios/listar')
                ->withError(
                    __('No existe el intercambio solicitado.')
                );
        }
        // armar documentos con sus respuestas
        $documentos = [];
        $n_dtes = count($_POST['TipoDTE']);
        for ($i=0; $i<$n_dtes; $i++) {
            $documentos[] = [
                'TipoDTE' => $_POST['TipoDTE'][$i],
                'Folio' => $_POST['Folio'][$i],
                'FchEmis' => $_POST['FchEmis'][$i],
                'RUTEmisor' => $_POST['RUTEmisor'][$i],
                'RUTRecep' => $_POST['RUTRecep'][$i],
                'MntTotal' => $_POST['MntTotal'][$i],
                'EstadoRecepDTE' => $_POST['rcv_accion_codigo'][$i],
                'RecepDTEGlosa' => $_POST['rcv_accion_glosa'][$i],
            ];
        }
        // armar configuración extra para la respuesta
        $config = [
            'user_id' => $user->id,
            'NmbContacto' => $_POST['NmbContacto'],
            'MailContacto' => $_POST['MailContacto'],
            'sucursal' => $_POST['sucursal'],
            'Recinto' => $_POST['Recinto'],
            'responder_a' => $_POST['responder_a'],
            'periodo' => $_POST['periodo'],
        ];
        // generar respuesta
        try {
            $resultado = $DteIntercambio->responder($documentos, $config);
            if ($resultado['email'] === true) {
                $msg = __('Se procesaron DTE de intercambio y se envió la respuesta a: %(email_responder)s',
                    [
                        'email_responder' => $config['responder_a']
                    ]);
                if ($resultado['rc']['estado']) {
                    $msg .= '<br/><br/>- '.implode('<br/> -', $resultado['rc']['estado']);
                }
                return redirect(str_replace('responder', 'ver', $this->request->getRequestUriDecoded()))
                    ->withSuccess($msg);
            } else {
                $msg = __('Se procesaron DTE de intercambio, pero no fue posible enviar el email, por favor intente nuevamente.<br /><em>%(email_message)s</em>',
                    [
                        'email_message' => $resultado['email']['message']
                    ]);
                if ($resultado['rc']['estado']) {
                    $msg .= '<br/><br/>- '.implode('<br/> -', $resultado['rc']['estado']);
                }
                return redirect(str_replace('responder', 'ver', $this->request->getRequestUriDecoded()))
                    ->withWarning($msg);
            }
        } catch (\Exception $e) {
            return redirect(str_replace('responder', 'ver', $this->request->getRequestUriDecoded()))
                ->withError($e->getMessage())
            ;
        }
        // redireccionar
        return redirect(str_replace('responder', 'ver', $this->request->getRequestUriDecoded()));
    }

    /**
     * Acción que permite realizar una búsqueda avanzada dentro de los
     * documentos de intercambio.
     */
    public function buscar(Request $request)
    {
        $user = $request->user();
        // Obtener contribuyente que se está utilizando en la sesión.
        try {
            $Receptor = libredte()->getSessionContribuyente();
        } catch (\Exception $e) {
            return libredte()->redirectContribuyenteSeleccionar($e);
        }
        // Asignar variables para la vista.
        $usuarios = array_keys($Receptor->getUsuarios());
        $this->set([
            'Receptor' => $Receptor,
            'tipos_dte' => (new \website\Dte\Admin\Mantenedores\Model_DteTipos())->getList(),
            'usuarios' => array_combine($usuarios, $usuarios),
            'values_xml' => [],
        ]);
        // Procesar búsqueda.
        if (!empty($_POST)) {
            $_POST['xml'] = [];
            $values_xml = [];
            if (!empty($_POST['xml_nodo'])) {
                $n_xml = count($_POST['xml_nodo']);
                for ($i=0; $i<$n_xml; $i++) {
                    if (!empty($_POST['xml_nodo'][$i]) && !empty($_POST['xml_valor'][$i])) {
                        $_POST['xml'][$_POST['xml_nodo'][$i]] = $_POST['xml_valor'][$i];
                        $values_xml[] = [
                            'xml_nodo' => $_POST['xml_nodo'][$i],
                            'xml_valor' => $_POST['xml_valor'][$i],
                        ];
                    }
                    unset($_POST['xml_nodo'][$i], $_POST['xml_valor'][$i]);
                }
            }
            $this->set([
                'values_xml' => $values_xml,
            ]);
            $rest = new \sowerphp\core\Network_Http_Rest();
            $rest->setAuth($user->hash);
            $response = $rest->post(
                url('/api/dte/dte_intercambios/buscar/'.$Receptor->rut.'?_contribuyente_certificacion='.$Receptor->enCertificacion()),
                $_POST
            );
            if ($response === false) {
                \sowerphp\core\Facade_Session_Message::error(implode('<br/>', $rest->getErrors()));
            }
            else if ($response['status']['code'] != 200) {
                \sowerphp\core\Facade_Session_Message::error($response['body']);
            }
            else {
                $this->set([
                    'intercambios' => $response['body'],
                ]);
            }
        }
    }

    /**
     * Acción de la API que permite realizar una búsqueda avanzada dentro de los
     * documentos de intercambio.
     */
    public function _api_buscar_POST($receptor)
    {
        // verificar usuario autenticado
        $User = $this->Api->getAuthUser();
        if (is_string($User)) {
            $this->Api->send($User, 401);
        }
        // verificar permisos del usuario autenticado sobre el emisor del DTE
        $Receptor = new Model_Contribuyente($receptor);
        if (!$Receptor->exists()) {
            return response()->json(
                __('Emisor no existe.'),
                404
            );
        }
        if (!$Receptor->usuarioAutorizado($User, '/dte/dte_intercambios/buscar')) {
            return response()->json(
                __('No está autorizado a operar con la empresa solicitada.'),
                403
            );
        }
        // buscar documentos
        $intercambios = $Receptor->getDocumentosIntercambios((array)$this->Api->data);
        return response()->json($intercambios);
    }

    /**
     * Acción de la API que permite buscar dentro de la bandeja de intercambio.
     */
    public function _api_buscar_GET($receptor)
    {
        // crear receptor y verificar autorización
        $User = $this->Api->getAuthUser();
        if (is_string($User)) {
            $this->Api->send($User, 401);
        }
        $Receptor = new Model_Contribuyente($receptor);
        if (!$Receptor->exists()) {
            return response()->json(
                __('Receptor no existe.'),
                404
            );
        }
        if (!$Receptor->usuarioAutorizado($User, '/dte/dte_intercambios/listar')) {
            return response()->json(
                __('No está autorizado a operar con la empresa solicitada.'),
                403
            );
        }
        // buscar documentos
        $filtros = $this->request->getValidatedData([
            'soloPendientes' => true,
            'emisor' => null,
            'folio' => null,
            'recibido_desde' => date('Y-m-01'),
            'recibido_hasta' => date('Y-m-d'),
            'usuario' => null,
        ]);
        $intercambios = $Receptor->getDocumentosIntercambios((array)$this->Api->data);
        return response()->json($intercambios);
    }

    /**
     * Acción que permite cargar la respuesta recibida de un intercambio
     * Esta acción principalmente sirve para procesar y validar una respuesta
     * que no ha sido procesada de manera automática por la actualización
     * de la bandeja de intercambio.
     */
    public function probar_xml()
    {
        // Obtener contribuyente que se está utilizando en la sesión.
        try {
            $Receptor = libredte()->getSessionContribuyente();
        } catch (\Exception $e) {
            return libredte()->redirectContribuyenteSeleccionar($e);
        }
        // Asignar variables para la vista y procesar archivo.
        $this->set('Receptor', $Receptor);
        if (!empty($_FILES['archivo'])) {
            $n_archivos = count($_FILES['archivo']['name']);
            $archivos = [];
            for ($i = 0; $i<$n_archivos; $i++) {
                $file = [
                    'name' => $_FILES['archivo']['name'][$i],
                    'tmp_name' => $_FILES['archivo']['tmp_name'][$i],
                    'error' => $_FILES['archivo']['error'][$i],
                    'size' => $_FILES['archivo']['size'][$i],
                    'type' => $_FILES['archivo']['type'][$i],
                    'data' => file_get_contents($_FILES['archivo']['tmp_name'][$i]),
                ];
                if ($file['error'] || !$file['size'] || $file['type'] != 'text/xml') {
                    continue;
                }
                $archivo = [
                    'name' => $_FILES['archivo']['name'][$i],
                ];
                // tratar de procesar como EnvioDTE
                try {
                    $procesarEnvioDTE = (new Model_DteIntercambios())
                        ->setContribuyente($Receptor)
                        ->procesarEnvioDTE($file)
                    ;
                    if ($procesarEnvioDTE !== null) {
                        $archivo['estado'] = 'EnvioDTE: procesado y guardado.';
                        $archivos[] = $archivo;
                        continue;
                    }
                } catch (\Exception $e) {
                    $archivo['estado'] = 'EnvioDTE: '.$e->getMessage();
                    $archivos[] = $archivo;
                    continue;
                }
                // tratar de procesar como Recibo
                try {
                    $procesarRecibo = (new Model_DteIntercambioRecibo())
                        ->saveXML($Receptor, $file['data'])
                    ;
                    if ($procesarRecibo !== null) {
                        $archivo['estado'] = 'Recibo: procesado y guardado.';
                        $archivos[] = $archivo;
                        continue;
                    }
                } catch (\Exception $e) {
                    $archivo['estado'] = 'Recibo: '.$e->getMessage();
                    $archivos[] = $archivo;
                    continue;
                }
                // tratar de procesar como Recepción
                try {
                    $procesarRecepcion = (new Model_DteIntercambioRecepcion())
                        ->saveXML($Receptor, $file['data'])
                    ;
                    if ($procesarRecepcion !== null) {
                        $archivo['estado'] = 'Recepción: procesado y guardado.';
                        $archivos[] = $archivo;
                        continue;
                    }
                } catch (\Exception $e) {
                    $archivo['estado'] = 'Recepción: '.$e->getMessage();
                    $archivos[] = $archivo;
                    continue;
                }
                // tratar de procesar como Resultado
                try {
                    $procesarResultado = (new Model_DteIntercambioResultado())
                        ->saveXML($Receptor, $file['data'])
                    ;
                    if ($procesarResultado !== null) {
                        $archivo['estado'] = 'Resultado: procesado y guardado.';
                        $archivos[] = $archivo;
                        continue;
                    }
                } catch (\Exception $e) {
                    $archivo['estado'] = 'Resultado: '.$e->getMessage();
                    $archivos[] = $archivo;
                    continue;
                }
                // no se procesó
                $archivo['estado'] = 'No procesado. Es probable que no sea del ambiente actual o bien no sea un XML de los 4 casos esperados: EnvioDTE, Recibo, Recepción o Resultado.';
                $archivos[] = $archivo;
            }
            $this->set('archivos', $archivos);
        }
    }

}
