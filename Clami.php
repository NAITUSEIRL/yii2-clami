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

	public function init() {
        if (empty($this->token)) {
            throw new InvalidConfigException('You must set token to authenticate.');
        }return true;
    }
}
