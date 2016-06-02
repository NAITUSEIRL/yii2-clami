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
	public $result;
	public $result_documento;
	public $result_info;

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
			$jsonData = json_encode($data);
		}else{
			$jsonData = $data;
		}


		//envio a Clami
		\Yii::trace('enviarDte to Clami API: token:'.$this->token.' url:' . $this->getUrl('enviarDte'), 'Clami'.__METHOD__);
		$this->curl = curl_init($this->getUrl('enviarDte'));
		$this->prepareCurl();
		$this->setCurlOption(CURLOPT_POSTFIELDS, $jsonData);

		//procesar
		$this->result = json_decode(curl_exec($this->curl));
		\Yii::trace('Info Respuesta Curl: ' . print_r($this->result, true), __METHOD__);
		$this->result_documento = $this->result['documento'][0];
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
	public function getError() {
		if($this->result == null){
			if($this->result_info != null){
				return 'Http response: '.$this->result_info['http_code'];
			}

		}else{
			return 'Clami response estado : '.$this->result['estado'];
		}
		return false;
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
