<?php

namespace Umbrella\Ya\RemessaBoleto\Validator;

use Symfony\Component\Yaml\Yaml;
use Umbrella\Ya\RemessaBoleto\Enum\BancoEnum;
use \Exception;

class Validator
{
    /**
     * arquivo que contem os dados da geracao do boleto
     */
    const CONFIG_FILE = "/../config/validator.yml";

    /**
     * @var array
     */
    private $dataValidator;


    /**
     * @var array
     */
    private $emptyFields = [];

    /**
     * @param int $bancoIdentificador
     */
    public function __construct(int $bancoIdentificador)
    {
        $fileconfig = dirname(__FILE__) . self::CONFIG_FILE;

        if (!file_exists($fileconfig)) {
            throw new \Exception("Arquivo de configuração nao localizado: {$fileconfig}");
        }

        $this->loadDataValidator($bancoIdentificador);
    }

    /**
     * Carrega os dados de acordo com banco passado no parametro.
     * @param  int    $bancoIdentificador
     * @return void
     */
    private function loadDataValidator(int $bancoIdentificador)
    {
        switch ($bancoIdentificador) {
            case BancoEnum::BRADESCO:
                $this->dataValidator = Yaml::parseFile(dirname(__FILE__) . self::CONFIG_FILE)['bradesco'];
                break;

            case BancoEnum::SICOOB:
                $this->dataValidator = Yaml::parseFile(dirname(__FILE__) . self::CONFIG_FILE)['sicoob'];
                break;

            default:
                throw new \Exception("Objeto de validação do banco não localizado {self::CONFIG_FILE}");
                break;
        }
    }

    /**
     * compare validator
     * @param  array       dados do arquivo de remessa a ser criado
     */
    public function run($data)
    {
        $this->compareArray($this->dataValidator, $data);

        if (count($this->emptyFields)) {
            throw new \Exception("Faltando dados: " . print_r($this->emptyFields,1));
        }
    }


    private function compareArray($dataValidator, $data, $emptyFields = null)
    {
        $emptyFields = $emptyFields ?? [];
        $primarykeys = array_keys($dataValidator);

        foreach ($primarykeys as $key => $value) {
            if (is_array($dataValidator[$value])) {
                $controller = ($value == "SEQ") ? key($data) : $value;
                $this->compareArray($dataValidator[$value], $data[$controller], $emptyFields);
                unset($data[$controller]);
                continue;
            }

            if (!isset($data[$value])) {
                $this->emptyFields[] = $value;
            }
        }
    }


}
