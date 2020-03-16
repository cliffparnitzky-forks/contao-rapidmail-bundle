<?php

namespace Alnv\ContaoRapidMailBundle\Hooks;

use Contao\System;
use Rapidmail\ApiClient\Client;
use Rapidmail\ApiClient\Exception\ApiException;
use Psr\Log\LogLevel;

class Form {


    public function processFormData( $arrPost, $arrForm ) {

        $strRecipientlistId = '';
        $blnActiveRapidMail = false;
        $objFormFields = \Database::getInstance()->prepare('SELECT * FROM tl_form_field WHERE pid=?')->execute( $arrForm['id'] );

        if ( !$objFormFields->numRows ) {

            return null;
        }

        while ( $objFormFields->next() ) {

            if ( $objFormFields->sendToRapidMail && $arrPost[ $objFormFields->name ] ) {

                $blnActiveRapidMail = true;
                $strRecipientlistId = $objFormFields->rapidMailRecipientlistId;
                break;
            }
        }

        if ( !$blnActiveRapidMail || !$strRecipientlistId ) {

            return null;
        }

        if ( !\Config::get('rapidmailUsername') || !\Config::get('rapidmailPassword') || !$arrPost['email'] ) {

            return null;
        }

        $arrRapidmailConfig = [];
        $arrData = ['firstname','lastname','email','gender','email'];
        foreach ( $arrData as $strField ) {
            if ( isset( $arrPost[$strField] ) ) {
                $arrRapidmailConfig[$strField] = $arrPost[$strField];
            }
        }
        $arrRapidmailConfig['recipientlist_id'] = $strRecipientlistId;
        $objClient = new Client( \Config::get('rapidmailUsername'), \Config::get('rapidmailPassword') );
        $objRecipientsService = $objClient->recipients();
        
        try
        {
          $objRecipientsService->create(
              $arrRapidmailConfig,
              [
                  'send_activationmail' => 'yes'
              ]
          );
        }
        catch (ApiException $e)
        {
          if ($e->getCode() == 409)
          {
            // do nothing, the recipient already existed in the mailing list, okay, who cares ;-)
          }
          else
          {
            System::getContainer()->get('monolog.logger.contao')->log(LogLevel::ERROR, 'Rapidmail API Exception occured: ' . $e->getMessage());
          }
        }
    }
}