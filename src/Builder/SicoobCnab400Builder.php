<?php

namespace Umbrella\Ya\RemessaBoleto\Builder;

use Umbrella\Ya\RemessaBoleto\Cnab\Cnab400\SICOOB\Detalhe;
use Umbrella\Ya\RemessaBoleto\Cnab\Cnab400\SICOOB\File;
use Umbrella\Ya\RemessaBoleto\Cnab\Cnab400\SICOOB\Header;
use Umbrella\Ya\RemessaBoleto\Cnab\Cnab400\SICOOB\Trailler;
use Umbrella\Ya\RemessaBoleto\Enum\BancoEnum;

class SicoobCnab400Builder extends Builder
{
    /**
     * Dados configurados no arquivo "src/config/params.yml"
     * @var array
     */
    protected $dadosBoleto;

    /**
     * Dados recebidos por parametro contendo dados referente a geracao dinamica do boleto
     * @var array
     */
    protected $detalhesBoleto;


    /**
     * @param array $detalhesDoBoleto [description]
     */
    public function __construct(array $detalhesDoBoleto)
    {
        parent::__construct(BancoEnum::SICOOB);
        $this->detalhesBoleto = $detalhesDoBoleto;
    }

    /**
     * funcao para geracao do arquivo de remessa do cnab400 do boleto sicoob
     * @return string [caminho completo do arquivo de remessa]
     */
    public function build($path)
    {
        return $this
            ->detalhe()
            ->header()
            ->trailler()
            ;
    }

    /**
     * gerador da trasacao do arquivo de remessa cnab400 bradesco
     * @return Detalhe
     */
    protected function detalhe()
    {
        $seqConvenio            = $this->getSeqConvenio($this->detalhesBoleto['convenios']);
        $convenioBancario       = $this->detalhesBoleto['convenios'][$seqConvenio];
        $documentosArrecadacao  = $this->detalhesBoleto['transacoes'][$seqConvenio];

        $arrDetalhes = [];
        $sequencialRegistro = 2;

        foreach ($documentosArrecadacao['dam'] as $key => $documento) {
            $detalhe = new Detalhe();

            $detalhe->setSequencialRegistro($sequencialRegistro++);
            $detalhe->setDataVencimento((new \DateTime($documento['dataVencimento']))->format('dmy'));
            $detalhe->setDataEmissao((new \DateTime($documento['dataEmissao']))->format('dmy'));

            /** dados do convenio */
            $detalhe->setTipoInscricaoBeneficiario(strlen($convenioBancario['orgao']['pessoa']['cnpj']) > 11 ? "02" : "01");
            $detalhe->setCpfcnpjBeneficiario($convenioBancario['orgao']['pessoa']['cnpj']);

            $detalhe->setPrefixoCooperativa($convenioBancario['agencia']);
            $detalhe->setDigitoVerificadorPrefixoCooperativa($convenioBancario['digitoAgencia']);
            $detalhe->setContaCorrente($convenioBancario['conta']);
            $detalhe->setDigitoVerificadorConta($convenioBancario['digitoConta']);

            /** dados do boleto */
            $detalhe->setNossoNumero($documento['nossoNumero']);
            $detalhe->setNumeroBoleto($documento['numeroDocumento']);
            $detalhe->setValorTitulo($documento['valor']);

            /** dados do pagante */
            $detalhe->setTipoInscricaoPagador(strlen($documento['pessoa']['cpfCnpj']) > 11 ? "02" : "01");
            $detalhe->setClienteCpfCnpj($documento['pessoa']['cpfCnpj']);
            $detalhe->setClienteNome($documento['pessoa']['nome']);
            $detalhe->setClienteEndereco($documento['pessoa']['endereco']);
            $detalhe->setClienteBairro($documento['pessoa']['bairro']);
            $detalhe->setClienteCep($documento['pessoa']['cep']);
            $detalhe->setClienteCidade($documento['pessoa']['municipio']['nome']);
            $detalhe->setClienteUF($documento['pessoa']['municipio']['uf']['sigla']);

            $arrDetalhes[] = $detalhe;
        }

        $this->detalhes = $arrDetalhes;
        return $this;
    }

    /**
     * gerador do header do arquivo de remessa do bradesco cnab400
     * @return Header
     */
    protected function header()
    {
        $seqConvenio = $this->getSeqConvenio($this->detalhesBoleto['convenios']);
        $convenioBancario       = $this->detalhesBoleto['convenios'][$seqConvenio];

        $header = new Header();

        $header->setCodigoCliente(substr($convenioBancario['convenio'],0,-1));
        $header->setDigitoVerificadorCodigo(substr($convenioBancario['convenio'],-1));

        $header->setSequencialRegistro(1);
        $header->setPrefixoCooperativa($convenioBancario['agencia']);
        $header->setDigitoVerificadorCooperativa($convenioBancario['digitoAgencia']);

        $header->setConvenioLider($convenioBancario['convenio']);

        $header->setNomeBeneficiario(mb_strtoupper(mb_substr($this->detalhesBoleto['convenios'][$seqConvenio]['orgao']['descricao'], 0, 30)));
        $header->setSequencialRemessa($this->detalhesBoleto['totalRemessas']);
        $header->setDataGeracao((new \DateTime())->format('dmy'));

        $this->header = $header;

        return $this;
    }

    /**
     * Gerador do triller do bradesco cnab400
     * @return Trailler
     */
    protected function trailler()
    {
        $trailler = new Trailler();
        $trailler->setSequencialRegistro(1);

        $this->trailler = $trailler;
        return $trailler;
    }


    public function montarArquivo($path)
    {
        $fileName = (new File())->buildName();
        $fullpath = $path . '/' . $fileName;

        $file = fopen($fullpath, 'w');

        if (!file_exists($fullpath)) {
            throw new \Exception("Não foi possivel abrir o arquivo para criar a remessa {$fullpath}");
        }

        $header     = $this->header;
        $detalhes   = $this->detalhes;
        $trailler   = $this->trailler;

        $stringHeader = $header->getHeaderToString();
        fwrite($file, $stringHeader . "\n");

        foreach ($detalhes as $detalhe) {
            $stringDetalhes = $detalhe->getDetalheToString();
            fwrite($file, $stringDetalhes . "\n");
        }

        $ultimoSequencial = count($detalhes) + 1;

        $trailler->setSequencialRegistro(++$ultimoSequencial);
        $stringTrailler = $trailler->getTraillerToString();

        fwrite($file, $stringTrailler);
        fclose($file);

        return $fullpath;
    }

}
