<?php

namespace App\Models;
use CodeIgniter\Model;

class ContactModel extends Model
{
    protected $DBGroup          = 'default';
    protected $table            = 't_contact';
    protected $primaryKey       = 'IDCONTACT';
    protected $useAutoIncrement = true;
    protected $insertID         = 0;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = ['IDCONTACT', 'IDCOUNTRY', 'IDNAMETITLE', 'NAMEFULL', 'PHONENUMBER', 'EMAILS', 'DATETIMEINSERT'];

    // Dates
    protected $useTimestamps = false;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    // Validation
    protected $validationRules      = [];
    protected $validationMessages   = [];
    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    // Callbacks
    protected $allowCallbacks = true;
    protected $beforeInsert   = [];
    protected $afterInsert    = [];
    protected $beforeUpdate   = [];
    protected $afterUpdate    = [];
    protected $beforeFind     = [];
    protected $afterFind      = [];
    protected $beforeDelete   = [];
    protected $afterDelete    = [];

    public function getDataContactRecentlyAdd($page, $searchKeyword, $recentlyAdded = false)
    {	
        $dataPerPage=   100;
        $pageOffset =   ($page - 1) * $dataPerPage;
        $this->select("A.IDCONTACT, A.NAMEFULL, LEFT(A.NAMEFULL, 1) AS NAMEALPHASEPARATOR, A.PHONENUMBER, A.EMAILS,
                    IFNULL(B.DATETIMELASTREPLY, 0) AS DATETIMELASTREPLY, B.IDCHATLIST");
        $this->from('t_contact AS A', true);
        $this->join('t_chatlist AS B', 'A.IDCONTACT = B.IDCONTACT', 'LEFT');
        if(isset($searchKeyword) && !is_null($searchKeyword) && $searchKeyword != '') {
            $this->groupStart();
            $this->like('A.NAMEFULL', $searchKeyword, 'both')
            ->orLike('A.PHONENUMBER', $searchKeyword, 'both')
            ->orLike('A.EMAILS', $searchKeyword, 'both');
            $this->groupEnd();
        }
        $this->groupBy('A.IDCONTACT');
        if($recentlyAdded) $this->orderBy('A.DATETIMEINSERT DESC, A.IDCONTACT');
        $this->limit($dataPerPage, $pageOffset);

        $result =   $this->get()->getResultObject();

        if(is_null($result)) return false;
        return $result;
    }

    public function getDetailContact($regionalDatabaseName, $idContact)
    {	
        $this->select(
            "A.NAMEFULL, '' AS REGIONALNAME, D.NAMA AS NAMAMARKETING, A.PHONENUMBER, A.PHONENUMBERBASE, IFNULL(B.COUNTRYNAME, '-') AS COUNTRYNAME, IFNULL(C.CONTINENTNAME, '-') AS CONTINENTNAME,
            IF(A.EMAILS = '' OR A.EMAILS IS NULL, '-', A.EMAILS) AS EMAILS, IFNULL(G.DATETIMELASTREPLY, '') AS DATETIMELASTREPLY, '' AS DATETIMEINTERVALINFO,
            COUNT(DISTINCT(E.IDSALESORDERREKAP)) AS TOTALSALESORDER, COUNT(DISTINCT(F.IDPROYEK)) AS TOTALPROYEK, A.ISVALIDWHATSAPP, IFNULL(G.DATETIMELASTREPLY, 0) AS TIMESTAMPLASTREPLY,
            G.IDCHATLIST, A.IDCOUNTRY, A.IDNAMETITLE"
        );
        $this->from('t_contact A', true);
        $this->join('m_country AS B', 'A.IDCOUNTRY = B.IDCOUNTRY', 'LEFT');
        $this->join('m_continent AS C', 'B.IDCONTINENT = C.IDCONTINENT', 'LEFT');
        $this->join($regionalDatabaseName.'.m_marketing AS D', 'A.IDMARKETING = D.IDMARKETING', 'LEFT');
        $this->join($regionalDatabaseName.'.t_salesorderrekap AS E', 'A.IDCUSTOMER = E.IDCUSTOMER', 'LEFT');
        $this->join($regionalDatabaseName.'.t_proyek AS F', 'A.IDCUSTOMER = F.IDCUSTOMER', 'LEFT');
        $this->join('t_chatlist AS G', 'A.IDCONTACT = G.IDCONTACT', 'LEFT');
        $this->where('A.IDCONTACT', $idContact);
        $this->groupBy('A.IDCONTACT');
        $this->limit(1);

        $result =   $this->get()->getRowArray();

        if(!is_null($result)) return $result;
        return [
            'NAMEFULL'              =>  '-',
            'REGIONALNAME'          =>  '-',
            'NAMAMARKETING'         =>  '-',
            'PHONENUMBER'           =>  '-',
            'PHONENUMBERBASE'       =>  '',
            'COUNTRYNAME'           =>  '-',
            'CONTINENTNAME'         =>  '-',
            'EMAILS'                =>  '-',
            'DATETIMELASTREPLY'     =>  '',
            'DATETIMEINTERVALINFO'  =>  '',
            'TOTALSALESORDER'       =>  0,
            'TOTALPROYEK'           =>  0,
            'ISVALIDWHATSAPP'       =>  -1,
            'TIMESTAMPLASTREPLY'    =>  0,
            'IDCHATLIST'            =>  '',
            'IDCOUNTRY'             =>  '',
            'IDNAMETITLE'           =>  ''
        ];
    }

    public function getListDetailSalesOrder($regionalDatabaseName, $idCustomer)
    {	
        $this->select("DATE_FORMAT(A.TANGGALWAKTU, '%d %b %Y %H:%i:%s') AS TANGGALWAKTU, B.TIPESALESORDER, C.NAMA AS NAMAMARKETING, IFNULL(D.NAMAKELOMPOKHARGA, '-') AS NAMAKELOMPOKHARGA,
                    A.CARABAYAR, COUNT(DISTINCT(F.IDPAYMENTNONLANGSUNG)) AS JUMLAHTERMIN, DATE_FORMAT(A.TANGGALBAYARTEMPO, '%d %b %Y') AS TANGGALBAYARTEMPO, A.STATUSPENAWARAN,
                    A.STATUSBAYAR, IF(A.KETERANGAN IS NULL OR A.KETERANGAN = '', '-', A.KETERANGAN) AS KETERANGAN, IF(A.DISCAPPROVENOTE IS NULL OR A.DISCAPPROVENOTE = '', '-', A.DISCAPPROVENOTE) AS DISCAPPROVENOTE,
                    GROUP_CONCAT(DISTINCT(CONCAT('-', E.ALAMAT, '<br/>'))) AS ALAMATKIRIM, A.TOTALHARGABARANG, A.ONGKOSPASANGTOTAL, A.ONGKIRESTIMASI, A.DISCREQ, A.DISCAPPROVE,
                    A.TOTALPPN, A.GRANDTOTALHARGA, '[]' AS LISTTEMPLATEMESSAGE, A.IDSALESORDERREKAP");
        $this->from($regionalDatabaseName.'.t_salesorderrekap A', true);
        $this->join(APP_MAIN_DATABASE_NAME.'.m_salesordertipe AS B', 'A.IDTIPESALESORDER = B.IDTIPESALESORDER', 'LEFT');
        $this->join($regionalDatabaseName.'.m_marketing AS C', 'A.IDMARKETING = C.IDMARKETING', 'LEFT');
        $this->join(APP_MAIN_DATABASE_NAME.'.m_kelompokharga AS D', 'A.IDKELOMPOKHARGA = D.IDKELOMPOKHARGA', 'LEFT');
        $this->join($regionalDatabaseName.'.t_suratjalan AS E', 'A.IDSALESORDERREKAP = E.IDSALESORDERREKAP', 'LEFT');
        $this->join($regionalDatabaseName.'.t_paymentnonlangsung AS F', 'A.IDSALESORDERREKAP = F.IDSALESORDER', 'LEFT');
        $this->where("A.IDCUSTOMER", $idCustomer);
        $this->groupBy('A.IDSALESORDERREKAP');
        $this->orderBy('A.TANGGALWAKTU DESC');

        $result =   $this->get()->getResultObject();

        if(is_null($result)) return false;
        return $result;
    }

    public function getListTemplateMessage()
    {
        $this->select("IDCHATTEMPLATE, IDONEMSGIO, TEMPLATECODE, TEMPLATENAME, TEMPLATELANGUAGECODE, CONTENTHEADER, CONTENTBODY, CONTENTFOOTER, CONTENTBUTTONS,
                PARAMETERSHEADER, PARAMETERSBODY");
        $this->from("t_chattemplate", true);

        $result = $this->get()->getResultObject();

        if (is_null($result)) return [];
        return $result;
    }
}
