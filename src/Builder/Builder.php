<?php

namespace Umbrella\Ya\RemessaBoleto\Builder;

use Locale;
use Symfony\Component\Yaml\Yaml;
use Umbrella\Ya\RemessaBoleto\Enum\BancoEnum;

class Builder
{
    /**
     * arquivo que contem os dados da geracao do boleto
     */
    const CONFIG_FILE = "/../config/params.yml";

    /**
     * @param int $bancoIdentificador
     */
    public function __construct(int $bancoIdentificador)
    {
        $fileconfig = dirname(__FILE__) . self::CONFIG_FILE;
        if (!file_exists($fileconfig)) {
            throw new \Exception('Arquivo de configuração nao localizado: {self::CONFIG_FILE}');
        }

        $this->carregarDadosBoletoBanco($bancoIdentificador);
    }

    /**
     * Carrega os dados de acordo com banco passado no parametro.
     * @param  int    $bancoIdentificador
     * @return void
     */
    private function carregarDadosBoletoBanco(int $bancoIdentificador)
    {
        $fileconfig = dirname(__FILE__) . self::CONFIG_FILE;
        switch ($bancoIdentificador) {
            case BancoEnum::BRADESCO:
                $this->dadosBoleto = Yaml::parseFile($fileconfig)['cnab400']['bradesco'];
                break;

            case BancoEnum::SICOOB:
                $this->dadosBoleto = Yaml::parseFile($fileconfig)['cnab400']['sicoob'];
                break;

            default:
                throw new \Exception("Dados de boleto do banco não localizado {self::CONFIG_FILE}");
                break;
        }
    }

    protected function concatenarDados()
    {
        $args   = func_get_args();
        $output = '';

        foreach ($args as $key => $dado) { $output .= "{$dado}"; }

        return isset($output) ? $output : "";
    }

    protected function parseInteger($string)
    {
        $retorno = preg_replace("/[^0-9]/", '', trim($string));
        return (int) $retorno;
    }

    protected function removerAcentos($string)
    {
        // pega a locale default como backup.
        $locale = Locale::getDefault();
        // muda pra locale que trabalha os acentos
        setlocale(LC_CTYPE, 'pt_BR.utf-8');
        // retira os acentos.
        $acentosRemovidos = preg_replace('#[`^~\'´"]#', null, iconv(mb_detect_encoding($string), 'ASCII//TRANSLIT', $string));
        // retorna a locale para a que era anterior a manipulação da string.
        setlocale(LC_CTYPE, $locale);
        return $acentosRemovidos;
    }


    protected function getSeqConvenio($arrConvenio)
    {
        foreach($arrConvenio as $key => $value) return $key;
    }


}
