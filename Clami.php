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
class Clami extends Component{

	/**
     * @var string
     */
    protected $token;
    protected $host;
    protected $port;
    protected $api_version;
	protected $testData;


	/**
	 *
	 * @var array([
			'rutEmisor' => '76409739-4',
			'razSoc' => 'CTM Group SpA', //solo para identificarlo en la configuracion
			'token' => 'Token 654832f80841ded26f9386d6130a7dd9e1fd6429',
			'host' => 'http://clami.cl',
			'port' => 9000,
			'api_version' => 'v2',
			'testData' => true,
		],
	 */
	public $perfiles = array();

	public $enviarDte = 'enviar/dte';
	public $curl;
	public $jsonData;
	public $result_raw;
	public $result;
	public $result_ok;
	public $result_documento;
	public $result_info;
	public $jsonEncodeOption;


	public $codigo;
	public $estado;
	public $pdf;
	public $xml;
	public $folio;
	public $errors;


	public function init() {
        if(count($this->perfiles) == 0 ){
            throw new InvalidConfigException('You must set at least 1 profile to Clami.');
		}else{
			//datos por defecto para cada perfil
			foreach ($this->perfiles as $perfil) {
				if (empty($perfil['rutEmisor'])) {
					throw new InvalidConfigException('You must set rutEmisor to identify a profile with Clami.');
				}
				if (empty($perfil['token'])) {
					throw new InvalidConfigException('You must set token to authenticate in Clami.');
				}
				if (empty($perfil['host'])) {
					$perfil['host'] = 'http://clami.cl';
				}
				if (empty($perfil['port'])) {
					$perfil['port'] = 8000;
				}
				if (empty($perfil['api_version'])) {
					$perfil['api_version'] = 'v2';
				}
				if (empty($perfil['testData'])) {
					$perfil['testData'] = false;
				}
			}
		}
        if (empty($this->enviarDte)) {
			$this->enviarDte = 'enviar/dte';
        }
        if (empty($this->jsonEncodeOption)) {
			$this->jsonEncodeOption = JSON_UNESCAPED_UNICODE;
        }
    }

	private function getUrl($action = 'enviarDte') {
		$url = $this->host.':'.$this->port;
		if($action == 'enviarDte'){
			$url .= '/'.$this->api_version.'/'.$this->enviarDte;
		}
		return $url;
	}

	private function setCurlOption($option, $value) {
		curl_setopt($this->curl, $option, $value);
	}

	private function prepareCurl($action = 'enviarDte') {
		$headers = array(
            "Content-type: application/json",
            "Accept: application/json",
            "Authorization: ".$this->token,
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
			if($perfil['rutEmisor'] === $rutEmisor){
				$this->token = $perfil['token'];
				$this->host = $perfil['host'];
				$this->port = $perfil['port'];
				$this->api_version = $perfil['api_version'];
				$this->testData = $perfil['testData'];
				\Yii::trace('Clami - Perfil cargado Rut:'.$rutEmisor,'Clami');
			}
		}
		throw new InvalidConfigException('Couldnt fint rutEmisor '.$rutEmisor.' on the profiles settings.'.print_r( $this->perfiles, true));
		);
	}


	public function enviarDte($data, $format = 'json') {
		//limpiar respuestas
		$this->resetValues();


		//preparar datos
		if($format != 'json'){
			$this->jsonData = Json::encode($data, $this->jsonEncodeOption);
		}else{
			$this->jsonData = $data;
		}
		\Yii::trace('Json de envio:'.$this->jsonData, 'Clami'.__METHOD__);

		//cargar el parfil de Clami segun rutEmisor
		$phpData = Json::decode($this->jsonData);
		$this->loadPerfil($phpData['Caratula']['RutEmisor']);


		//testData
		if($this->testData){
			$phpData = Json::decode($this->jsonData);
			$phpData['Caratula'] = [
				"RutEmisor" => "78961710-4",
				"TmstFirmaEnv" => "R",
				"RutReceptor" => "60803000-K",
				"RutEnvia" => "8033340-4",
				"NroResol" => "0",
				"FchResol" => "2014-03-04"
			];
			$phpData['Documentos'][0]['Encabezado']['Emisor'] = [
				"RUTEmisor" => "78961710-4",
				"CiudadOrigen" => "SANTIAGO",
				"Acteco" => "726000",
				"GiroEmis" => "SERVICIOS INTEGRALES DE INFORMATICA",
				"CmnaOrigen" => "SAN BERNARDO",
				"RznSoc" => "CONTACTO INFORMÁTICA LIMITADA",
				"DirOrigen" => "AV. ARGENTINA 515",
				'CdgVendedor' => "Vendedor datos de prueba",

			];
			$this->jsonData = Json::encode($phpData, $this->jsonEncodeOption);
			\Yii::trace('Json de envio final test:'.$this->jsonData, 'Clami'.__METHOD__);
		}

		//envio a Clami
		\Yii::trace('enviarDte to Clami API: token:'.$this->token.' url:' . $this->getUrl('enviarDte'), 'Clami'.__METHOD__);
		$this->curl = curl_init($this->getUrl('enviarDte'));
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
		try{
			$this->result = Json::decode($result_raw);
			\Yii::trace('Info Respuesta Curl: ' . print_r($this->result, true), __METHOD__);
			//campos devolucion
			if(!is_array($this->result)){
				throw new \Exception('Formato Respuesta invalido');
			}
			if(array_key_exists('codigo', $this->result)){
				$this->codigo = $this->result['codigo'];
				if( array_key_exists('estado', $this->result)){
					$this->estado = $this->result['estado'];
				}

				if($this->codigo == 200 && $this->estado == 'OK'){
					//resultado Ok, guardar pdf, folio, xml
					try{
						$this->result_documento = $this->result['documentos'][0];
						$this->pdf = $this->result_documento['pdf'];
						$this->xml = $this->result_documento['xml'];
						$this->folio = $this->result_documento['folio'];
						$validator = new \yii\validators\UrlValidator();
						$validatorNum = new \yii\validators\NumberValidator();

						if(!$validator->validate($this->pdf, $this->errors[])){
							throw new \Exception('error en valor del campo Pdf');
						}
						if(!$validator->validate($this->xml, $this->errors[])){
							throw new \Exception('error en valor del campo xml');
						}
						if(!$validatorNum->validate($this->folio, $this->errors[])){
							throw new \Exception('error en valor del campo Folio');
						}
						$this->result_ok = true;
					} catch (\Exception $ex) {
						$this->errors[] ='Respuesta sistema Clami Ok pero no se pudo obtener los datos necesarios. '.$ex->getMessage();
					}
				}else{
					if(strlen($this->estado) >0 ){
						$this->errors[] =$this->estado;
					}
					//se recibe algun error
					if(array_key_exists('detalle',$this->result) ){
						if(is_array($this->result['detalle'])){
							foreach ($this->result['detalle'] as $detalle) {
								if(is_array($detalle)){
									foreach ($detalle as $deta) {
										$this->errors[] = $deta;
									}
								}else{
									$this->errors[] = $detalle;
								}
							}
						}else{
							$this->errors[] = $this->result['detalle'];
						}

					}
					//se recibe algun error
					if(array_key_exists('glosa',$this->result)){
						if(is_array($this->result['glosa'])){
							foreach ($this->result['glosa'] as $glosa) {
								if(is_array($glosa)){
									foreach ($glosa as $glo) {
										$this->errors[] = $glo;
									}
								}else{
									$this->errors[] = $glosa;
								}
							}
						}else{
							$this->errors[] = $this->result['glosa'];
						}
					}
				}

			}else{
				$this->errors[] ='Resultado enviar DTE a Clami sin codigo de respuesta.';

			}
		} catch (\Exception $ex) {
			if($this->result_info != null && $this->result_info['http_code'] == 0){
					$this->errors[] = 'Servicio Clami no se encuentra activo. Codigo Http respuesta'.$this->result_info['http_code'];
			}else{
				$this->errors[] ='Resultado enviar DTE a Clami no se pudo procesar como JSON. '.$ex->getName();
			}
//			$this->result['estado'] = $ex->getName();
		}
	}

	public function resultOK() {
		if($this->result_ok){
			return true;
		}
		return false;
	}
	public function getError($full = false) {
		if($this->resultOK()) return false;
		$extra = $full ? '<br>Raw result:<br>'.$this->result_raw : '';
		$errorStr = '';
		foreach ($this->errors as $error) {
			$errorStr .= "\n".$error;
		}
		return 'Clami '.$this->estado.'Código: '.$this->codigo.'. Detalle:'.$errorStr.$extra;
	}
	public function getJson() {
		return Json::encode(Json::decode($this->jsonData), JSON_PRETTY_PRINT | $this->jsonEncodeOption);
	}

	public function getPdf() {
		if($this->resultOK() ){
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
		if($this->resultOK() ){
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
		if($this->resultOK()){
			return $this->folio;
		}
		return false;
	}

}
