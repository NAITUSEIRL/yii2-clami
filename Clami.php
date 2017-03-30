<?php

namespace jotapeserra\clami;

use yii\base\Component;
use yii\base\InvalidConfigException;
use yii\helpers\Json;

/**
 * Description of Clami
 *
 * @author Juan Pablo Sera
 */
class Clami extends Component {

  /**
   * @var array([
    'rutEmisor' => '76409739-4',
    'razSoc' => 'CTM Group SpA', //solo para identificarlo en la configuracion
    'token' => 'Token 654832f80841ded26f9386d6130a7dd9e1fd6429',
    'urlEnviarDte' => 'http://clami.cl:9000/v2/enviar/dte',
    'urlPortal' => 'http://clami.cl:9000/dte',
    'userPortal' => 'demo',
    'passPortal' => 'demo'
    'host' => 'http://clami.cl',
    'port' => 9000,
    'api_version' => 'v2',
    'enableTestData' => true,
    ],
   */
  public $perfiles = array();
  public $testData;
  public $jsonEncodeOption;
  

  public $perfilCargado = null;
  public $curl;
  public $jsonData;
  public $result_raw;
  public $result;
  public $result_ok;
  public $result_documento;
  public $result_info;
  public $codigo;
  public $estado;
  public $pdf;
  public $xml;
  public $folio;
  public $errors;

  public function init() {
    if (count($this->perfiles) == 0) {
      throw new InvalidConfigException('You must set at least 1 profile to Clami.');
    } else {
      //datos por defecto para cada perfil
      foreach ($this->perfiles as $perfil) {
	$mandatoryParams = [
	    'rutEmisor',
	    'rutEnvia',
	    'token',
	    'urlEnviarDte',
	    'enableTestData',
	];
	foreach ($mandatoryParams as $param) {
	  if (!array_key_exists($param, $perfil) || empty($perfil[$param])) {
	    throw new InvalidConfigException('You must set Mandatory parameter '.$param.' in perfil: '.print_r($perfil, true));
	  }
	  
	}
      }
    }
    if (empty($this->jsonEncodeOption)) {
      $this->jsonEncodeOption = JSON_UNESCAPED_UNICODE;
    }
  }

  private function getUrl($action = 'enviarDte') {
    if ($action == 'enviarDte') {
      return $this->perfilCargado['urlEnviarDte'];
    }
    throw new \Exception('Unknown action to get Url: '.$action);
  }

  private function setCurlOption($option, $value) {
    curl_setopt($this->curl, $option, $value);
  }

  private function prepareCurl($action = 'enviarDte') {
    if(is_null($this->perfilCargado)){
      throw new \Exception('no hay un perfil cargado para action: '.$action);
    }
    $headers = array(
	"Content-type: application/json",
	"Accept: application/json",
	"Authorization: " . $this->perfilCargado['token'],
    );
    $this->setCurlOption(CURLOPT_CUSTOMREQUEST, "POST");
    $this->setCurlOption(CURLOPT_HTTPHEADER, $headers);
    $this->setCurlOption(CURLOPT_CONNECTTIMEOUT, 500);
    $this->setCurlOption(CURLOPT_TIMEOUT, 1000);
    $this->setCurlOption(CURLOPT_RETURNTRANSFER, true);
  }

  private function resetValues() {
    //limpiar respuestas
    $this->result = null;
    $this->result_info = null;
    $this->result_ok = false;
    $this->codigo = null;
    $this->estado = null;
    $this->pdf = null;
    $this->xml = null;
    $this->pdf = null;
    $this->errors = [];
  }

  protected function loadPerfil($rutEmisor) {
    foreach ($this->perfiles as $perfil) {
      if ($perfil['rutEmisor'] == $rutEmisor) {
	$this->perfilCargado = $perfil;
	\Yii::trace('Clami - Perfil cargado Rut:' . $rutEmisor, 'Clami');
	return true;
      }
    }
    throw new InvalidConfigException('Couldnt fint rutEmisor ' . $rutEmisor . ' on the profiles settings.' . print_r($this->perfiles, true));
  }

  public function enviarDte($data, $format = 'json') {
    //limpiar respuestas
    $this->resetValues();


    //preparar datos
    if ($format != 'json') {
      $this->jsonData = Json::encode($data, $this->jsonEncodeOption);
    } else {
      $this->jsonData = $data;
    }
    \Yii::trace('Json de envio:' . $this->jsonData, 'Clami' . __METHOD__);

    //cargar el parfil de Clami segun rutEmisor
    $phpData = Json::decode($this->jsonData);
    $this->loadPerfil($phpData['Caratula']['RutEmisor']);
    $url = $this->getUrl('enviarDte');

    //enableTestData
    if ($this->perfilCargado['enableTestData']) {
      $phpData = Json::decode($this->jsonData);
      $phpData['Caratula'] = [
	  "RutEmisor" => $this->testData['rutEmisor'],
	  "TmstFirmaEnv" => $this->testData['TmstFirmaEnv'],
	  "RutReceptor" => $this->testData['RutReceptor'],
	  "RutEnvia" => $this->testData['rutEnvia'],
	  "NroResol" => $this->testData['NroResol'],
	  "FchResol" => $this->testData['FchResol'],
      ];
      $phpData['Documentos'][0]['Encabezado']['Emisor'] = [
	  "RUTEmisor" => $this->testData['rutEmisor'],
	  "CiudadOrigen" => $this->testData['rutEmisor'],
	  "Acteco" => $this->testData['Acteco'],
	  "GiroEmis" => $this->testData['GiroEmis'],
	  "CmnaOrigen" => $this->testData['CmnaOrigen'],
	  "RznSoc" => $this->testData['razSoc'],
	  "DirOrigen" => $this->testData['DirOrigen'],
	  'CdgVendedor' => $this->testData['CdgVendedor'],
      ];
      $url = $this->testData['urlEnviarDte'];

      $this->jsonData = Json::encode($phpData, $this->jsonEncodeOption);
      \Yii::trace('Json de envio final test:' . $this->jsonData, 'Clami' . __METHOD__);
    }

    //envio a Clami
    \Yii::trace('enviarDte to Clami API: token:' . $this->perfilCargado['token'] . ' url:' . $url, 'Clami' . __METHOD__);
    $this->curl = curl_init($url);
    $this->prepareCurl();
    $this->setCurlOption(CURLOPT_POSTFIELDS, $this->jsonData);

    //procesar
    $raw = curl_exec($this->curl);
    \Yii::trace('Info Respuesta Raw: ' . print_r($raw, true), __METHOD__);
    $this->result_raw = $raw;
    $this->result_info = curl_getinfo($this->curl);
    $this->parsearResultados($this->result_raw);
    curl_close($this->curl);

    return $this;
  }

  private function parsearResultados($result_raw) {
    try {
      $this->result = Json::decode($result_raw);
      \Yii::trace('Info Respuesta Curl: ' . print_r($this->result, true), __METHOD__);
      //campos devolucion
      if (!is_array($this->result)) {
	throw new \Exception('Formato Respuesta invalido');
      }
      if (array_key_exists('codigo', $this->result)) {
	$this->codigo = $this->result['codigo'];
	if (array_key_exists('estado', $this->result)) {
	  $this->estado = $this->result['estado'];
	}

	if ($this->codigo == 200 && $this->estado == 'OK') {
	  //resultado Ok, guardar pdf, folio, xml
	  try {
	    $this->result_documento = $this->result['documentos'][0];
	    $this->pdf = $this->result_documento['pdf'];
	    $this->xml = $this->result_documento['xml'];
	    $this->folio = $this->result_documento['folio'];
	    $validator = new \yii\validators\UrlValidator();
	    $validatorNum = new \yii\validators\NumberValidator();

	    if (!$validator->validate($this->pdf, $this->errors[])) {
	      throw new \Exception('error en valor del campo Pdf');
	    }
	    if (!$validator->validate($this->xml, $this->errors[])) {
	      throw new \Exception('error en valor del campo xml');
	    }
	    if (!$validatorNum->validate($this->folio, $this->errors[])) {
	      throw new \Exception('error en valor del campo Folio');
	    }
	    $this->result_ok = true;
	  } catch (\Exception $ex) {
	    $this->errors[] = 'Respuesta sistema Clami Ok pero no se pudo obtener los datos necesarios. ' . $ex->getMessage();
	  }
	} else {
	  if (strlen($this->estado) > 0) {
	    $this->errors[] = $this->estado;
	  }
	  //se recibe algun error
	  if (array_key_exists('detalle', $this->result)) {
	    if (is_array($this->result['detalle'])) {
	      foreach ($this->result['detalle'] as $detalle) {
		if (is_array($detalle)) {
		  foreach ($detalle as $deta) {
		    $this->errors[] = $deta;
		  }
		} else {
		  $this->errors[] = $detalle;
		}
	      }
	    } else {
	      $this->errors[] = $this->result['detalle'];
	    }
	  }
	  //se recibe algun error
	  if (array_key_exists('glosa', $this->result)) {
	    if (is_array($this->result['glosa'])) {
	      foreach ($this->result['glosa'] as $glosa) {
		if (is_array($glosa)) {
		  foreach ($glosa as $glo) {
		    $this->errors[] = $glo;
		  }
		} else {
		  $this->errors[] = $glosa;
		}
	      }
	    } else {
	      $this->errors[] = $this->result['glosa'];
	    }
	  }
	}
      } else {
	$this->errors[] = 'Resultado enviar DTE a Clami sin codigo de respuesta.';
      }
    } catch (\Exception $ex) {
      if ($this->result_info != null && $this->result_info['http_code'] == 0) {
	$this->errors[] = 'Servicio Clami no se encuentra activo. Codigo Http respuesta' . $this->result_info['http_code'];
      } else {
	$this->errors[] = 'Resultado enviar DTE a Clami no se pudo procesar como JSON. ' . $ex->getName();
      }
//			$this->result['estado'] = $ex->getName();
    }
  }

  public function resultOK() {
    if ($this->result_ok) {
      return true;
    }
    return false;
  }

  public function getError($full = false) {
    if ($this->resultOK())
      return false;
    $extra = $full ? '<br>Raw result:<br>' . $this->result_raw : '';
    $errorStr = '';
    foreach ($this->errors as $error) {
      $errorStr .= "\n" . $error;
    }
    return 'Clami ' . $this->estado . 'Código: ' . $this->codigo . '. Detalle:' . $errorStr . $extra;
  }

  public function getJson() {
    return Json::encode(Json::decode($this->jsonData), JSON_PRETTY_PRINT | $this->jsonEncodeOption);
  }

  public function getPdf() {
    if ($this->resultOK()) {
      $url = $this->pdf;
      $ch = curl_init();
      curl_setopt($ch, CURLOPT_AUTOREFERER, TRUE);
      curl_setopt($ch, CURLOPT_HEADER, 0);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
      curl_setopt($ch, CURLOPT_URL, $url);
      curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);

      $data = curl_exec($ch);
      curl_close($ch);

      return $data;
    }
    return false;
  }

  public function getXml() {
    if ($this->resultOK()) {
      $url = $this->xml;
      $ch = curl_init();
      curl_setopt($ch, CURLOPT_AUTOREFERER, TRUE);
      curl_setopt($ch, CURLOPT_HEADER, 0);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
      curl_setopt($ch, CURLOPT_URL, $url);
      curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);

      $data = curl_exec($ch);
      curl_close($ch);

      return $data;
    }
    return false;
  }

  public function getFolio() {
    if ($this->resultOK()) {
      return $this->folio;
    }
    return false;
  }

}
