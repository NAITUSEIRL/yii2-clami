<?php
namespace jotapeserra\clami;
use yii\base\Component;
use yii\base\InvalidConfigException;


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
	public $enviarDte = 'v2/enviar/dte';
	public $curl;

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
        if (empty($this->enviarDte)) {
			$this->enviarDte = 'v2/enviar/dte';
        }
    }

	public function enviarDte($data, $format = 'json') {
		if($format != 'json'){
			$jsonData = json_encode($data);
		}else{
			$jsonData = $data;
		}
		\Yii::trace('enviarDte to Clami API: token:'.$this->token.' url:' . $this->getUrl('enviarDte'), 'Clami'.__METHOD__);
            $this->curl = curl_init($this->getUrl('enviarDte'));
			$this->prepareCurl();
			$this->setCurlOption(CURLOPT_POSTFIELDS, $jsonData);

			$result = curl_exec($this->curl);
			$out = array_merge($result, curl_getinfo($this->curl) );

            \Yii::trace('Info Respuesta Curl: ' . print_r($out, true), __METHOD__);
            curl_close($this->curl);
			return $out;
//            $prodAPI = Json::decode($result);
//            $prod = new Productos();
//            $prod->idProductoOrigen = $prodAPI['idProducto'];
	}

	public function getUrl($action = 'enviarDte') {
		$url = $this->host.':'.$this->port;
		if($action == 'enviarDte'){
			$url .= '/'.$this->enviarDte;
		}
		return $url;
	}

	public function setCurlOption($option, $value) {
		curl_setopt($this->curl, $option, $value);
	}

	public function prepareCurl($action = 'enviarDte') {
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
}
