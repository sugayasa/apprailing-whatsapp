<?php
namespace App\Libraries;
use App\Libraries\OneMsgIO;
use App\Models\MainOperation;
use App\Models\CronModel;

class WhatsappMessage
{
    public function saveWebhookMessage($message, $dateTimeNow)
    {
        $mainOperation  =   new MainOperation();
        $cronModel      =   new CronModel();
        $logger         =   \Config\Services::logger();
        
        try {
            if(is_array($message)) $message   =   (object) $message;

            $author             =   $message->author ?? null;
            $chatId             =   $message->chatId ?? null;
            $messageId          =   $message->id ?? null;
            $messageType        =   $message->type ?? null;
            $messageBody        =   $message->body ?? null;
            $senderName         =   $message->senderName ?? null;
            $fromMe             =   $message->fromMe ?? null;
            $caption            =   $message->caption ?? null;
            $quotedMsgId        =   $message->quotedMsgId ?? null;
            $isForwarded        =   $message->isForwarded ?? null;
            $timeStamp          =   $message->time ?? null;
            $phoneNumber        =   getPhoneNumberFromWhatsappAuthor($chatId);
            $phoneNumber        =	preg_replace('/[^0-9]/', '', $phoneNumber);
            $phoneNumberBase    =   $this->getDataPhoneNumberBase($phoneNumber);
            $idCountry          =   $phoneNumberBase['idCountry'] ?? 0;
            $phoneNumberBase    =   $phoneNumberBase['phoneNumberBase'] ?? $phoneNumber;
            $isZeroPrefixNumber =   $phoneNumberBase['isZeroPrefixNumber'] ?? false;
            $detailChatList     =   $mainOperation->getDetailChatListByPhoneNumber($idCountry, $phoneNumberBase);
            $idContact          =   $detailChatList['IDCONTACT'] ?? null;
            $idChatThreadType   =   1;
            
            switch($messageType){
                case 'image'    :   $idChatThreadType   =   2; break;
                case 'document' :   $idChatThreadType   =   3; break;
                case 'audio'    :   $idChatThreadType   =   4; break;
                case 'video'    :   $idChatThreadType   =   5; break;
                case 'location' :   $idChatThreadType   =   6; break;
                default         :   break;
            }

            $arrAdditionalThread =   [
                'idChatThreadType'  =>  $idChatThreadType,
                'quotedMsgId'       =>  $quotedMsgId,
                'caption'           =>  $caption,
                'isForwarded'       =>  $isForwarded
            ];
            
            if(!$detailChatList || is_null($idContact)){
                $arrInsertContact   =   [
                    'IDCOUNTRY'             =>  $idCountry,
                    'IDNAMETITLE'           =>  0,
                    'NAMEFULL'              =>  $senderName,
                    'PHONENUMBER'           =>  $phoneNumber,
                    'PHONENUMBERBASE'       =>  $phoneNumberBase,
                    'PHONENUMBERZEROPREFIX' =>  $isZeroPrefixNumber,
                    'EMAILS'                =>  '',
                    'ISVALIDWHATSAPP'       =>  1,
                    'DATETIMEINSERT'        =>  $dateTimeNow
                ];
                $procInsertContact   =   $mainOperation->insertDataTable('t_contact', $arrInsertContact);
                if($procInsertContact['status']) $idContact = $procInsertContact['insertID'];
            }

            if(!$fromMe){
                if(!is_null($quotedMsgId) && $quotedMsgId != ''){
                    $detailChatThreadQuoted =   $cronModel->getDetailChatThreadQuoted($quotedMsgId);
                    $isQuotedTemplate       =   $detailChatThreadQuoted['ISTEMPLATE'];

                    if($isQuotedTemplate){
                        //Continue check quoted message is template
                    }
                }

                if(!is_null($idContact)) $mainOperation->insertUpdateChatTable($timeStamp, $idContact, $messageId, $messageBody, 0, $arrAdditionalThread);
                if(APP_AUTO_REPLY_STATUS) {
                    $detailLastMessageReply =   $cronModel->getDetailLastReplyMessage($idContact);
                    $isSendAutoReply        =   false;
                    
                    if($detailLastMessageReply){
                        $lastTimeReply  =   isset($detailLastMessageReply['DATETIMECHAT']) && !is_null($detailLastMessageReply['DATETIMECHAT']) ? $detailLastMessageReply['DATETIMECHAT'] : 0;
                        if($lastTimeReply == 0){
                            $isSendAutoReply    =   true;
                        } else {
                            $timeDiffHours  =   round(abs($timeStamp - $lastTimeReply) / 3600, 2);
                            if($timeDiffHours >= 2){
                                $isSendAutoReply    =   true;
                            }
                        }
                    } else {
                        $isSendAutoReply    =   true;
                    }
                    
                    if($isSendAutoReply){
                        $oneMsgIO               =   new OneMsgIO();
                        $detailTemplateAutoReply=   $mainOperation->getDataChatTemplate(0, 'Auto Reply');
                        
                        if($detailTemplateAutoReply){
                            $templateCode           =   $detailTemplateAutoReply['TEMPLATECODE'] ?? '';
                            $templateLanguageCode   =   $detailTemplateAutoReply['TEMPLATELANGUAGECODE'] ?? '';
                            $dataRegionalContact    =   $mainOperation->getDataRegionalContact();
                            
                            if($dataRegionalContact){
                                $arrDataTemplateParameters  =   [];

                                foreach($dataRegionalContact as $detailRegionalContact){
                                    $arrDataTemplateParameters[]    =   ['type' => 'text', 'text' => $detailRegionalContact->NAMAKOTA];
                                    $arrDataTemplateParameters[]    =   ['type' => 'text', 'text' => $detailRegionalContact->MARKETINGUTAMANAMA];
                                    $arrDataTemplateParameters[]    =   ['type' => 'text', 'text' => $detailRegionalContact->MARKETINGUTAMATELPON];
                                }

                                $arrTemplateParameters[]    =   [
                                    "type"      =>  "body",
                                    "parameters"=>  $arrDataTemplateParameters
                                ];
                                $oneMsgIO->sendMessageTemplate($templateCode, $templateLanguageCode, $phoneNumber, $arrTemplateParameters);
                            }
                        }
                    }
                }
            } else {
                $isMessageIdExist =   $cronModel->isMessageIdExist($messageId);
                if(!$isMessageIdExist) {
                    $arrAdditionalThread['isBOT']   =   true;
                    $mainOperation->insertUpdateChatTable($timeStamp, $idContact, $messageId, $messageBody, 1, $arrAdditionalThread);
                }
            }

            if(!is_null($idContact)) $mainOperation->updateDataTable('t_contact', ['PHONENUMBERZEROPREFIX' => $isZeroPrefixNumber], ['IDCONTACT' => $idContact]);

            return true;
        } catch (\Exception $e) {
            $logger->error('Error saving webhook message: ' . $e->getMessage());
            return false;
        }
    }
    
    public function getDataPhoneNumberBase($phoneNumber)
    {   
        $mainOperation          =   new MainOperation();
        $dataCountryPhoneNumber =   $mainOperation->getDataCountryCodeByPhoneNumber($phoneNumber);
        $idCountry              =   $dataCountryPhoneNumber['idCountry'] ?? 0;
        $countryPhoneCode	    =   $dataCountryPhoneNumber['countryPhoneCode'] ?? '';
		$phoneNumberBase	    =	substr($phoneNumber, strlen($countryPhoneCode)) * 1;
        $isZeroPrefixNumber     =   substr($phoneNumberBase, 0, 1) == '0' ? true : false;
		
		return [
            'idCountry'         =>  $idCountry,
            'phoneNumberBase'   =>  $phoneNumberBase,
            'isZeroPrefixNumber'=>  $isZeroPrefixNumber
        ];
	}
}