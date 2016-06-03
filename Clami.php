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
    public $token;
    public $host;
    public $port;
    public $api_version;
	public $enviarDte = 'enviar/dte';
	public $curl;
	public $jsonData;
	public $result_raw;
	public $result;
	public $result_documento;
	public $result_info;
	public $testData;

	public function init() {
        if (empty($this->token)) {
            throw new InvalidConfigException('You must set token to authenticate in Clami.');
        }
        if (empty($this->host)) {
			$this->host = 'http://clami.cl';
        }
        if (empty($this->port)) {
			$this->port = 8000;
        }
        if (empty($this->api_version)) {
			$this->api_version = 'v2';
        }
        if (empty($this->enviarDte)) {
			$this->enviarDte = 'enviar/dte';
        }
        if (empty($this->testData)) {
			$this->testData = false;
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

	public function enviarDte($data, $format = 'json') {
		//limpiar respuestas
		$this->result = null;
		$this->result_info = null;

		//preparar datos
		if($format != 'json'){
			$this->jsonData = Json::encode($data, JSON_UNESCAPED_UNICODE);
		}else{
			$this->jsonData = $data;
		}

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
			$phpData['Documentos']['Encabezado']['Emisor'] = [
				"RUTEmisor" => "78961710-4",
				"CiudadOrigen" => "SANTIAGO",
				"Acteco" => "726000",
				"GiroEmis" => "SERVICIOS INTEGRALES DE INFORMATICA",
				"CmnaOrigen" => "SAN BERNARDO",
				"RznSoc" => "CONTACTO INFORMÁTICA LIMITADA",
				"DirOrigen" => "AV. ARGENTINA 515"
			];
			$this->jsonData = Json::encode($phpData, JSON_UNESCAPED_UNICODE);
		}


		//envio a Clami
		\Yii::trace('enviarDte to Clami API: token:'.$this->token.' url:' . $this->getUrl('enviarDte'), 'Clami'.__METHOD__);
		$this->curl = curl_init($this->getUrl('enviarDte'));
		$this->prepareCurl();
		$this->setCurlOption(CURLOPT_POSTFIELDS, $this->jsonData);

		//procesar
		$raw = curl_exec($this->curl);
		\Yii::trace('Info Respuesta Curl: ' . print_r($raw, true), __METHOD__);
		$this->result_raw = $raw;
		try{
			$this->result = Json::decode($raw);
		} catch (yii\base\InvalidParamException $ex) {
			$this->result['estado'] = 'InvalidResponse';
		}
		\Yii::trace('Info Respuesta Curl: ' . print_r($this->result, true), __METHOD__);
		$this->result_documento = $this->result['documentos'][0];
		$this->result_info = curl_getinfo($this->curl);
		curl_close($this->curl);

		return $this;
	}

	public function resultOK() {
		if($this->result != null && $this->result['estado'] == 'OK'){
			return true;
		}
		return false;
	}
	public function getError($full = false) {
		$extra = $full ? '<br>Raw result:<br>'.$this->result_raw : '';
		if($this->result == null){
			if($this->result_info != null){
				return 'Http response: '.$this->result_info['http_code'].$extra;
			}

		}else{
			return 'Clami response estado : '.$this->result['estado'].$extra;
		}
		return false;
	}
	public function getJson() {
		return Json::encode(Json::decode($this->jsonData), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
	}

	public function getPdf() {
		if($this->resultOK()){
			$url = $this->result_documento['pdf'];
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

}
