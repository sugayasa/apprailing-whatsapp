<?php

namespace App\Models;
use CodeIgniter\Model;

class ChatModel extends Model
{
    protected $DBGroup          = 'default';
    protected $table            = 't_chatlist';
    protected $primaryKey       = 'IDCONTACT';
    protected $useAutoIncrement = true;
    protected $insertID         = 0;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = ['IDCHATLIST', 'IDCONTACT', 'TOTALUNREADMESSAGE', 'LASTMESSAGE', 'DATETIMELASTMESSAGE'];

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

    //FIX
    public function getDataChatList($page, $dataPerPage = 25, $searchKeyword, $chatType, $idContact = null)
    {	
        $pageOffset     =   ($page - 1) * $dataPerPage;
        $this->select("A.IDCHATLIST, LEFT(B.NAMEFULL, 1) AS NAMEALPHASEPARATOR, A.LASTSENDERFIRSTNAME, B.NAMEFULL, A.TOTALUNREADMESSAGE,
                A.LASTMESSAGE, A.DATETIMELASTMESSAGE, '' AS DATETIMELASTMESSAGESTR, IFNULL(A.DATETIMELASTREPLY, 0) AS DATETIMELASTREPLY,
                A.HANDLESTATUS, A.HANDLEFORCE");
        $this->from('t_chatlist A', true);
        $this->join('t_contact AS B', 'A.IDCONTACT = B.IDCONTACT', 'LEFT');

        if(isset($searchKeyword) && !is_null($searchKeyword) && $searchKeyword != '' && ($idContact == null || $idContact == '')) {
            $this->groupStart();
            $this->like('B.NAMEFULL', $searchKeyword, 'both')
            ->orLike('B.PHONENUMBER', $searchKeyword, 'both')
            ->orLike('B.EMAILS', $searchKeyword, 'both')
            ->orLike('A.LASTMESSAGE', $searchKeyword, 'both');
            $this->groupEnd();
        }

        switch($chatType) {
            case 2  :   $this->where('A.TOTALUNREADMESSAGE > ', 0); break;
            default :   break;
        }

        if(isset($idContact) && !is_null($idContact) && $idContact != '') {
            $this->where('A.IDCONTACT = ', $idContact);
        }

        $this->groupBy('A.IDCHATLIST');
        $this->orderBy('A.DATETIMELASTMESSAGE DESC');
        $this->limit($dataPerPage, $pageOffset);

        $result =   $this->get()->getResultObject();
        if(is_null($result)) return false;
        return $result;
    }

    public function getInactiveForceHandleChatList($timeStamp24HoursAgo) : array
    {
        $this->select("IDCHATLIST");
        $this->from('t_chatlist', true);
        $this->where('HANDLEFORCE', 1);
        $this->where('DATETIMELASTREPLY < ', $timeStamp24HoursAgo);

        $result     =   $this->get()->getResultObject();
        if(is_null($result)) return [];
        return $result;
    }

    public function getDetailContactChat($idChatList)
    {	
        $this->select("A.HANDLESTATUS, A.HANDLEFORCE, LEFT(B.NAMEFULL, 1) AS NAMEALPHASEPARATOR, B.NAMEFULL, B.PHONENUMBER, C.COUNTRYNAME,
                    D.CONTINENTNAME, IF(B.EMAILS = '' OR B.EMAILS IS NULL, '-', B.EMAILS) AS EMAILS, IFNULL(A.DATETIMELASTREPLY, 0) AS DATETIMELASTREPLY,
                    A.IDCONTACT, A.TOTALUNREADMESSAGE");
        $this->from('t_chatlist A', true);
        $this->join('t_contact AS B', 'A.IDCONTACT = B.IDCONTACT', 'LEFT');
        $this->join('m_country AS C', 'B.IDCOUNTRY = C.IDCOUNTRY', 'LEFT');
        $this->join('m_continent AS D', 'C.IDCONTINENT = D.IDCONTINENT', 'LEFT');
        $this->where('A.IDCHATLIST', $idChatList);
        $this->limit(1);

        $row    =   $this->get()->getRowArray();

        if(is_null($row)) return false;
        return $row;
    }

    public function getListChatThread($idChatList, $page, $dataPerPage = 20)
    {	
        $pageOffset =   ($page - 1) * $dataPerPage;
        $subQuery   =   $this->db->table('t_chatthread', true);
        $subQuery->select(
            'IDCHATTHREAD, IDMESSAGE, IDMESSAGEQUOTED, IDCHATTHREADTYPE, IDUSERADMIN, CHATCONTENTHEADER, CHATCONTENTBODY, CHATCONTENTFOOTER, DATETIMECHAT,
            STATUSREAD, DATETIMESENT, DATETIMEDELIVERED, DATETIMEREAD, CHATCAPTION, ISFORWARDED, ISTEMPLATE, ISBOT, IDCHATLIST'
        );
        $subQuery->where('IDCHATLIST', $idChatList);
        $subQuery->orderBy('DATETIMECHAT DESC, IDUSERADMIN ASC');
        $subQuery->limit($dataPerPage, $pageOffset);
        $subQueryString =   $subQuery->getCompiledSelect();
        $queryString    =   "SELECT A.IDCHATTHREAD, A.IDMESSAGE, A.IDMESSAGEQUOTED, A.IDCHATTHREADTYPE, IF(A.IDUSERADMIN = 0, LEFT(D.NAMEFULL, 1), LEFT(B.NAME, 1)) AS INITIALNAME,
                                A.CHATCONTENTHEADER, A.CHATCONTENTBODY, A.CHATCONTENTFOOTER, A.DATETIMECHAT, '' AS CHATTIME, '' AS DAYTITLE, '' AS MESSAGEQUOTED, 'Auto System' AS MESSAGEQUOTEDSENDER,
                                A.STATUSREAD, A.DATETIMESENT, A.DATETIMEDELIVERED, A.DATETIMEREAD, IF(A.IDUSERADMIN = 0, D.NAMEFULL, IF(A.ISBOT = 0, B.NAME, CONCAT(B.NAME, ' (Bot)'))) AS USERNAMECHAT,
                                IF(A.IDUSERADMIN = 0, 'L', 'R') AS CHATTHREADPOSITION, A.CHATCAPTION, A.ISFORWARDED, A.ISTEMPLATE, A.ISBOT, IFNULL(CONCAT('[', GROUP_CONCAT(E.IDUSERADMIN), ']'), '[]') AS ARRIDUSERADMINREAD
                            FROM ({$subQueryString}) AS A
                            LEFT JOIN m_useradmin B ON A.IDUSERADMIN = B.IDUSERADMIN
                            LEFT JOIN t_chatlist C ON A.IDCHATLIST = C.IDCHATLIST
                            LEFT JOIN t_contact D ON C.IDCONTACT = D.IDCONTACT
                            LEFT JOIN t_chatdetailread E ON A.IDCHATTHREAD = E.IDCHATTHREAD
                            GROUP BY A.IDCHATTHREAD";
        $result     =   $this->db->query($queryString)->getResultObject();

        if(is_null($result)) return false;
        return $result;
    }

    public function getListActiveSalesOrder($regionalDatabaseName, $idCustomer)
    {	
        $dateNow    =   date('Y-m-d');
        $this->select("DATE_FORMAT(A.TANGGALWAKTU, '%d %b %Y %H:%i') AS TANGGALWAKTU, B.TIPESALESORDER, C.NAMA AS NAMAMARKETING, IFNULL(D.NAMAKELOMPOKHARGA, '-') AS NAMAKELOMPOKHARGA,
                    A.CARABAYAR, COUNT(DISTINCT(F.IDPAYMENTNONLANGSUNG)) AS JUMLAHTERMIN, DATE_FORMAT(A.TANGGALBAYARTEMPO, '%d %b %Y') AS TANGGALBAYARTEMPO, A.STATUSPENAWARAN,
                    A.STATUSBAYAR, IF(A.KETERANGAN IS NULL OR A.KETERANGAN = '', '-', A.KETERANGAN) AS KETERANGAN, IF(A.DISCAPPROVENOTE IS NULL OR A.DISCAPPROVENOTE = '', '-', A.DISCAPPROVENOTE) AS DISCAPPROVENOTE,
                    A.TOTALHARGABARANG, A.ONGKOSPASANGTOTAL, A.ONGKIRESTIMASI, A.DISCREQ, A.DISCAPPROVE,
                    A.TOTALPPN, A.GRANDTOTALHARGA, A.IDSALESORDERREKAP");
        $this->from($regionalDatabaseName.'.t_salesorderrekap A', true);
        $this->join(APP_MAIN_DATABASE_NAME.'.m_salesordertipe AS B', 'A.IDTIPESALESORDER = B.IDTIPESALESORDER', 'LEFT');
        $this->join($regionalDatabaseName.'.m_marketing AS C', 'A.IDMARKETING = C.IDMARKETING', 'LEFT');
        $this->join(APP_MAIN_DATABASE_NAME.'.m_kelompokharga AS D', 'A.IDKELOMPOKHARGA = D.IDKELOMPOKHARGA', 'LEFT');
        $this->join($regionalDatabaseName.'.t_suratjalan AS E', 'A.IDSALESORDERREKAP = E.IDSALESORDERREKAP', 'LEFT');
        $this->join($regionalDatabaseName.'.t_paymentnonlangsung AS F', 'A.IDSALESORDERREKAP = F.IDSALESORDER', 'LEFT');
        $this->where("A.IDCUSTOMER", $idCustomer);
        $this->where("A.STATUSPENAWARAN >= ", 0);
        $this->groupBy('A.IDSALESORDERREKAP');
        $this->orderBy('A.STATUSPENAWARAN, A.STATUSBAYAR, A.TANGGALWAKTU DESC');

        $result =   $this->get()->getResultObject();

        if(is_null($result)) return false;
        return $result;
    }

    public function getDataThreadACK($idChatThread)
    {	
        $this->select("B.NAME, A.DATETIMEREAD");
        $this->from('t_chatdetailread A', true);
        $this->join('m_useradmin AS B', 'A.IDUSERADMIN = B.IDUSERADMIN', 'LEFT');
        $this->where('A.IDCHATTHREAD', $idChatThread);
        $this->orderBy('A.DATETIMEREAD');

        $result     =   $this->get()->getResultObject();

        if(is_null($result)) return false;
        return $result;
    }

    public function getDataUnreadChatThread($idChatList){
        $this->select("IDCHATTHREAD");
        $this->from('t_chatthread', true);
        $this->where('STATUSREAD', 0);
        $this->orderBy('IDCHATTHREAD');

        $result     =   $this->get()->getResultObject();

        if(is_null($result)) return false;
        return $result;
    }

    public function getDetailReservation($idReservation)
    {	
        $this->select("RESERVATIONTITLE, DURATIONOFDAY, DATE_FORMAT(RESERVATIONDATESTART, '%d-%m-%Y') AS RESERVATIONDATESTART, SUBSTRING(RESERVATIONTIMESTART, 1, 2) AS RESERVATIONHOUR,
            SUBSTRING(RESERVATIONTIMESTART, 4, 2) AS RESERVATIONMINUTE, IDAREA, HOTELNAME, PICKUPLOCATION, URLPICKUPLOCATION, DROPOFFLOCATION, NUMBEROFADULT, NUMBEROFCHILD, NUMBEROFINFANT,
            INCOMEAMOUNTCURRENCY, SUBSTRING_INDEX(SUBSTRING_INDEX(INCOMEAMOUNT, '.', 1), '.', -1) AS INCOMEAMOUNTINTEGER, SUBSTRING_INDEX(SUBSTRING_INDEX(INCOMEAMOUNT, '.', 2), '.', -1) AS INCOMEAMOUNTDECIMAL,
            INCOMEEXCHANGECURRENCY, INCOMEAMOUNTIDR, TOURPLAN, REMARK, SPECIALREQUEST");
        $this->from(APP_MAIN_DATABASE_NAME.'.t_reservation', true);
        $this->where('IDRESERVATION', $idReservation);
        $this->limit(1);

        $row    =   $this->get()->getRowArray();
        if(is_null($row)) return false;
        return $row;
    }

    public function getDataCurrencyExchange() : array
    {
        $this->select("CURRENCY, EXCHANGETOIDR");
        $this->from(APP_MAIN_DATABASE_NAME.'.helper_exchangecurrency', true);
        $this->orderBy('CURRENCY');

        $result     =   $this->get()->getResultObject();
        if(is_null($result)) return [];
        return $result;
    }

    public function getDataChatThreadByContactWithLimit($idChatList, $limit)
    {	
        $this->select("IDCHATTHREAD");
        $this->from('t_chatthread', true);
        $this->where('IDCHATLIST', $idChatList);
        $this->where('IDUSERADMIN', 0);
        $this->orderBy('DATETIMECHAT DESC');
        $this->limit($limit, 0);

        $result     =   $this->get()->getResultObject();

        if(is_null($result)) return false;
        return $result;
    }
}
